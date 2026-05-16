<?php

declare(strict_types=1);

namespace App;

final class GlucoseEvaluator
{
    private int $lowCutoff;
    private int $postMealHighMin;

    /** @var array<int, array{min:int,max:int,normal_max:int,key:string,label_ar:string}> */
    private array $fastingBands;

    /**
     * @param array{
     *   low_cutoff?: int,
     *   post_meal_high_min?: int,
     *   fasting_bands?: array<int, array{min:int,max:int,normal_max:int,key:string,label_ar:string}>
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->lowCutoff = (int)($config['low_cutoff'] ?? 70);
        $this->postMealHighMin = (int)($config['post_meal_high_min'] ?? 140);
        $this->fastingBands = $config['fasting_bands'] ?? [
            ['min' => 0, 'max' => 19, 'normal_max' => 110, 'key' => 'under_20', 'label_ar' => 'أقل من 20 سنة'],
            ['min' => 20, 'max' => 30, 'normal_max' => 110, 'key' => '20_30', 'label_ar' => '20-30 سنة'],
            ['min' => 31, 'max' => 40, 'normal_max' => 110, 'key' => '31_40', 'label_ar' => '31-40 سنة'],
            ['min' => 41, 'max' => 50, 'normal_max' => 110, 'key' => '41_50', 'label_ar' => '41-50 سنة'],
            ['min' => 51, 'max' => 60, 'normal_max' => 120, 'key' => '51_60', 'label_ar' => '51-60 سنة'],
            ['min' => 61, 'max' => 200, 'normal_max' => 130, 'key' => 'over_60', 'label_ar' => 'فوق 60 سنة'],
        ];
    }

    /**
     * Deterministic glucose evaluation in mg/dL according to age table.
     *
     * @return array{
     *   status_key:'low'|'normal'|'high',
     *   status_ar:'منخفض'|'طبيعي'|'مرتفع',
     *   reason_ar:string,
     *   thresholds:array{low_max:float, high_min:float},
     *   context:'fasting'|'after_meal',
     *   age_group:string,
     *   age_band_label:string
     * }
     */
    public function evaluate(int $age, float $value, string $context): array
    {
        $normalizedContext = $this->normalizeContext($context);
        $band = $this->resolveFastingBand($age);

        $lowCutoff = $this->lowCutoff;
        if ($normalizedContext === 'fasting') {
            $normalMax = (int)$band['normal_max'];
            $highMin = (float)($normalMax + 1);
        } else {
            $normalMax = $this->postMealHighMin - 1;
            $highMin = (float)$this->postMealHighMin;
        }

        if ($value < $lowCutoff) {
            $statusKey = 'low';
            $statusAr = 'منخفض';
        } elseif ($value >= $highMin) {
            $statusKey = 'high';
            $statusAr = 'مرتفع';
        } else {
            $statusKey = 'normal';
            $statusAr = 'طبيعي';
        }

        return [
            'status_key' => $statusKey,
            'status_ar' => $statusAr,
            'reason_ar' => $this->buildReasonAr(
                $statusKey,
                $normalizedContext,
                $lowCutoff,
                $normalMax,
                $highMin,
                (string)$band['label_ar']
            ),
            'thresholds' => [
                'low_max' => round($lowCutoff - 0.01, 2),
                'high_min' => $highMin,
            ],
            'context' => $normalizedContext,
            'age_group' => (string)$band['key'],
            'age_band_label' => (string)$band['label_ar'],
        ];
    }

    /**
     * Normalize accepted context aliases to fasting|after_meal.
     */
    public function normalizeContext(string $context): string
    {
        $raw = trim($context);
        $token = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
        $token = str_replace(['-', ' '], '_', $token);

        $fastingAliases = [
            'fasting',
            'صائم',
            'before_meal',
            'beforemeal',
            'pre_meal',
            'premeal',
            'ramadan',
        ];

        $afterMealAliases = [
            'after_meal',
            'بعد_الأكل',
            'بعد_الاكل',
            'بعدالأكل',
            'بعدالاكل',
            'postprandial',
            'post_prandial',
            'post',
            'post_meal',
            'postmeal',
        ];

        if (in_array($token, $afterMealAliases, true)) {
            return 'after_meal';
        }

        if (in_array($token, $fastingAliases, true)) {
            return 'fasting';
        }

        return 'fasting';
    }

    /**
     * @return array{min:int,max:int,normal_max:int,key:string,label_ar:string}
     */
    private function resolveFastingBand(int $age): array
    {
        foreach ($this->fastingBands as $band) {
            if ($age >= $band['min'] && $age <= $band['max']) {
                return $band;
            }
        }

        return $this->fastingBands[count($this->fastingBands) - 1];
    }

    private function buildReasonAr(
        string $statusKey,
        string $context,
        int $lowCutoff,
        int $normalMax,
        float $highMin,
        string $ageLabel
    ): string {
        $contextAr = $context === 'after_meal' ? 'بعد الأكل بساعتين' : 'قبل الأكل (صائم)';

        if ($statusKey === 'low') {
            return "القراءة {$contextAr} أقل من {$lowCutoff} mg/dL وتُصنّف منخفضة.";
        }

        if ($statusKey === 'high') {
            return "القراءة {$contextAr} تساوي أو تتجاوز {$highMin} mg/dL وتُصنّف مرتفعة.";
        }

        if ($context === 'fasting') {
            return "القراءة ضمن المجال الطبيعي لعمر {$ageLabel}: {$lowCutoff}-{$normalMax} mg/dL.";
        }

        return "القراءة ضمن المجال الطبيعي بعد الأكل: {$lowCutoff}-{$normalMax} mg/dL.";
    }
}

