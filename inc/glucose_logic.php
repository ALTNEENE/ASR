<?php

declare(strict_types=1);

/**
 * inc/glucose_logic.php
 * 
 * Provides:
 *   - classify_glucose()         - Age/gender/context-aware classification
 *   - getDashboardStats()        - PDO stats query (last N days)
 *   - getPersonalizedTips()      - PDO health tips query
 */

/**
 * Classify a glucose reading into Arabic status.
 *
 * Rules (mg/dL):
 *   Low  < 70  (all ages/contexts)
 *   Post (after meal 2h): High >= 140; Normal 70-139
 *   Fasting by age:
 *     0-50:  Normal 70-110, High >= 111
 *     51-60: Normal 70-120, High >= 121
 *     61+:   Normal 70-130, High >= 131
 *   Special rules:
 *     Children (age < 18) + fasting: Normal 70-100
 *     GDM (pregnancy):
 *       Fasting: Normal 70-95, High >= 96
 *       Post 1h:  High >= 140 (we treat all post as 2h here with 140 limit)
 *       Post 2h:  High >= 120
 *
 * @param float  $valueMg    Reading in mg/dL (already converted if mmol)
 * @param string $context    'fasting' | 'post' | 'ramadan' (treated as fasting)
 * @param int    $age
 * @param string $gender     'male' | 'female'
 * @param string $diabetesType e.g. 'Type 1', 'Type 2', 'GDM', 'T1', 'T2'
 * @return string  'منخفض' | 'طبيعي' | 'مرتفع'
 */
function classify_glucose(float $valueMg, string $context, int $age, string $gender = 'male', string $diabetesType = 'Type 2'): string
{
    // Always low below 70
    if ($valueMg < 70.0) {
        return 'منخفض';
    }

    // Normalize context
    $isPostMeal = in_array(strtolower(trim($context)), ['post', 'after_meal', 'postmeal', 'بعد الأكل'], true);
    $isGdm = stripos($diabetesType, 'GDM') !== false || stripos($diabetesType, 'gestational') !== false || stripos($diabetesType, 'الحمل') !== false;
    $isChild = $age > 0 && $age < 18;

    // ---------- POST MEAL ----------
    if ($isPostMeal) {
        if ($isGdm) {
            $highCutoff = 120.0; // GDM post 2h
        } else {
            $highCutoff = 140.0; // Standard
        }
        return $valueMg >= $highCutoff ? 'مرتفع' : 'طبيعي';
    }

    // ---------- FASTING ----------
    if ($isGdm) {
        $normalMax = 95.0;
    } elseif ($isChild) {
        $normalMax = 100.0;
    } elseif ($age >= 61) {
        $normalMax = 130.0;
    } elseif ($age >= 51) {
        $normalMax = 120.0;
    } else {
        $normalMax = 110.0;
    }

    return $valueMg > $normalMax ? 'مرتفع' : 'طبيعي';
}

/**
 * Get dashboard stats for the last $days days using PDO.
 * Excludes invalid readings (<= 0).
 * Converts mmol readings to mg/dL for stats.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param int $days
 * @return array{total_readings:int, high_count:int, normal_count:int, low_count:int,
 *               fasting_count:int, post_count:int,
 *               avg_glucose:float, max_glucose:float, min_glucose:float}
 */
function getDashboardStats(PDO $pdo, int $userId, int $days = 30): array
{
    $sql = "
        SELECT
            reading_value,
            reading_unit,
            reading_context,
            classification
        FROM glucose_readings
        WHERE user_id = ?
          AND reading_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          AND reading_value > 0
        ORDER BY reading_date DESC, reading_time DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $days]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $high = 0;
    $normal = 0;
    $low = 0;
    $fasting = 0;
    $post = 0;
    $values = [];

    foreach ($rows as $r) {
        $total++;
        $mg = strtolower($r['reading_unit']) === 'mmol'
            ? (float)$r['reading_value'] * 18.0
            : (float)$r['reading_value'];

        $values[] = $mg;

        $cls = (string)($r['classification'] ?? '');
        if (strpos($cls, 'مرتفع') !== false) {
            $high++;
        } elseif (strpos($cls, 'منخفض') !== false) {
            $low++;
        } else {
            $normal++;
        }

        $ctx = strtolower((string)($r['reading_context'] ?? ''));
        if (in_array($ctx, ['post', 'after_meal'], true)) {
            $post++;
        } else {
            $fasting++;
        }
    }

    $avg = $total > 0 ? array_sum($values) / count($values) : 0.0;
    $max = $total > 0 ? max($values) : 0.0;
    $min = $total > 0 ? min($values) : 0.0;

    return [
        'total_readings' => $total,
        'high_count'     => $high,
        'normal_count'   => $normal,
        'low_count'      => $low,
        'fasting_count'  => $fasting,
        'post_count'     => $post,
        'avg_glucose'    => round($avg, 1),
        'max_glucose'    => round($max, 1),
        'min_glucose'    => round($min, 1),
    ];
}

/**
 * Get personalized health tips for a user using PDO.
 *
 * Filters by age (between min_age/max_age, NULL is open-ended),
 * gender_target ('any' or actual gender), diabetes_target ('any' or diabetes_type),
 * and is_active = 1. Ordered by priority DESC.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param int $limit
 * @return array<int, array{id:int, title_ar:string, body_ar:string, priority:int}>
 */
function getPersonalizedTips(PDO $pdo, int $userId, int $limit = 5): array
{
    // Fetch patient profile
    $profileStmt = $pdo->prepare(
        'SELECT age, gender, diabetes_type FROM patient_data WHERE user_id = ? LIMIT 1'
    );
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        // Return general tips if no profile
        $genStmt = $pdo->prepare(
            "SELECT id, title_ar, body_ar, priority FROM health_tips
             WHERE is_active = 1
               AND (gender_target = 'any' OR gender_target IS NULL)
               AND (diabetes_target = 'any' OR diabetes_target IS NULL)
             ORDER BY priority DESC LIMIT ?"
        );
        $genStmt->execute([$limit]);
        return $genStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $age = (int)($profile['age'] ?? 0);
    $gender = strtolower(trim((string)($profile['gender'] ?? 'any')));
    $diabetesType = trim((string)($profile['diabetes_type'] ?? 'any'));

    $sql = "
        SELECT t.id, t.title_ar, t.body_ar, t.priority
        FROM health_tips t
        WHERE t.is_active = 1
          AND (t.min_age IS NULL OR ? >= t.min_age)
          AND (t.max_age IS NULL OR ? <= t.max_age)
          AND (t.gender_target = 'any' OR t.gender_target = ?)
          AND (t.diabetes_target = 'any' OR t.diabetes_target = ?)
        ORDER BY t.priority DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$age, $age, $gender, $diabetesType, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
