<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسجل دخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';
$groqConfig = require __DIR__ . '/../../config/groq_config.php';

$userId = (int)$_SESSION['user_id'];
$cacheKey = 'ai_diagnosis_v5_' . $userId;
$cacheTimeKey = $cacheKey . '_time';
$cacheTtl = 6 * 3600;

if (isset($_SESSION[$cacheKey], $_SESSION[$cacheTimeKey])) {
    if ((time() - (int)$_SESSION[$cacheTimeKey]) < $cacheTtl) {
        echo (string)$_SESSION[$cacheKey];
        exit;
    }
}

function toMgdl(float $value, string $unit): float
{
    return strtolower(trim($unit)) === 'mmol' ? ($value * 18.0) : $value;
}

function hasAnyText(string $text, array $needles): bool
{
    $toLower = static function (string $val): string {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($val, 'UTF-8');
        }
        return strtolower($val);
    };

    $txt = $toLower($text);
    foreach ($needles as $needle) {
        if (strpos($txt, $toLower((string)$needle)) !== false) {
            return true;
        }
    }
    return false;
}

function isConfirmedDiabetesType(string $diabetesType): bool
{
    $type = trim($diabetesType);
    if ($type === '') {
        return false;
    }

    $low = function_exists('mb_strtolower')
        ? mb_strtolower($type, 'UTF-8')
        : strtolower($type);

    if (
        strpos($low, 'غير محدد') !== false ||
        strpos($low, 'none') !== false ||
        strpos($low, 'غير مصاب') !== false ||
        strpos($low, 'لا يوجد') !== false ||
        strpos($low, 'prediab') !== false ||
        strpos($low, 'ما قبل') !== false
    ) {
        return false;
    }

    if (
        strpos($low, 'type1') !== false ||
        strpos($low, 'type2') !== false ||
        strpos($low, 'gestational') !== false ||
        strpos($low, 'lada') !== false ||
        strpos($low, 'mody') !== false ||
        strpos($low, 'سكري') !== false ||
        strpos($low, 'النوع') !== false ||
        strpos($low, 'سكري الحمل') !== false
    ) {
        return true;
    }

    return false;
}

function normalizeKnownDiabetesWording(string $text, bool $isConfirmedDiabetes): string
{
    $clean = trim($text);
    if ($clean === '' || !$isConfirmedDiabetes) {
        return $clean;
    }

    $search = [
        'خطر الإصابة بمرض السكري أو مشاكل صحية أخرى',
        'خطر الإصابة بمرض السكري',
        'خطر الإصابة بالسكري',
        'الإصابة بمرض السكري',
        'الإصابة بالسكري',
    ];
    $replace = [
        'خطر عدم استقرار السكر واحتمال ظهور مضاعفات صحية أخرى',
        'خطر عدم استقرار السكر',
        'خطر عدم استقرار السكر',
        'عدم استقرار السكر',
        'عدم استقرار السكر',
    ];

    return str_replace($search, $replace, $clean);
}

function normalizeGender(?string $raw): string
{
    $val = trim((string)$raw);
    $low = strtolower($val);

    if ($low === 'male' || $val === 'ذكر') {
        return 'ذكر';
    }
    if ($low === 'female' || $val === 'أنثى' || $val === 'انثى') {
        return 'أنثى';
    }
    return $val !== '' ? $val : 'غير محدد';
}

function ageGroupFromAge(?int $age): string
{
    if ($age === null || $age < 0) {
        return 'بالغ';
    }
    if ($age <= 11) {
        return 'طفل';
    }
    if ($age <= 17) {
        return 'مراهق';
    }
    if ($age <= 59) {
        return 'بالغ';
    }
    return 'كبير سن';
}

function genderFocusText(string $gender): string
{
    if ($gender === 'أنثى') {
        return 'مراعاة أثر التغيرات الهرمونية الشهرية، والحمل عند حدوثه، على تذبذب السكر.';
    }
    if ($gender === 'ذكر') {
        return 'التركيز على ضبط نمط الحياة وتقليل السمنة البطنية والضغط النفسي المرتبط بالعمل.';
    }
    return 'التركيز على العوامل اليومية المؤثرة مثل النوم، التوتر، والنشاط البدني.';
}

function tipsByAgeAndGender(string $ageGroup, string $gender): array
{
    if ($ageGroup === 'طفل') {
        return [
            'تثبيت مواعيد الوجبات والوجبات الخفيفة مع متابعة أحد الوالدين.',
            'الانتباه لأي علامات هبوط أثناء اللعب أو النشاط المدرسي.',
            $gender === 'أنثى'
                ? 'تعزيز المتابعة الأسرية والدعم النفسي لبناء عادات صحية مبكرة.'
                : 'تعزيز المتابعة الأسرية والالتزام بروتين يومي ثابت للنشاط والنوم.',
        ];
    }

    if ($ageGroup === 'مراهق') {
        return [
            'تنظيم النوم وتقليل السهر لتجنب تقلبات السكر اليومية.',
            'ربط القياس بالأوقات الثابتة (قبل/بعد الوجبات) خصوصًا أيام الدراسة.',
            $gender === 'أنثى'
                ? 'تتبع تغير القراءات حول الدورة الشهرية لتوقع التذبذب بشكل أفضل.'
                : 'تقليل المشروبات السكرية والوجبات السريعة خاصة خارج المنزل.',
        ];
    }

    if ($ageGroup === 'كبير سن') {
        return [
            'تجنب فترات الصيام الطويلة ومراقبة أعراض الهبوط بشكل مبكر.',
            'الحفاظ على الترطيب والحركة اليومية الخفيفة لتحسين الاستقرار.',
            $gender === 'أنثى'
                ? 'الانتباه لصحة العظام والتوازن أثناء النشاط اليومي.'
                : 'مراقبة ضغط الدم والنشاط القلبي الوعائي ضمن الروتين اليومي.',
        ];
    }

    return [
        'الالتزام بمواعيد نوم واستيقاظ ثابتة لتقليل التذبذب.',
        'تقسيم الكربوهيدرات على الوجبات وتجنب الإفراط في السكريات السريعة.',
        $gender === 'أنثى'
            ? 'تتبع أثر التغيرات الهرمونية والتوتر على القراءات الأسبوعية.'
            : 'التركيز على النشاط البدني المنتظم وتقليل الجلوس لفترات طويلة.',
    ];
}

function parseJsonObject(string $text): ?array
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $trimmed, $m)) {
        $decoded = json_decode($m[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function cleanStringList($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $out = [];
    foreach ($value as $item) {
        $item = trim((string)$item);
        if ($item !== '') {
            $out[] = $item;
        }
    }
    return array_values($out);
}

function containsDisallowedMedicationText(array $assessment): bool
{
    $blob = json_encode($assessment, JSON_UNESCAPED_UNICODE);
    if (!is_string($blob)) {
        return false;
    }

    $terms = ['دواء', 'أدوية', 'جرعة', 'أنسولين', 'حبوب', 'ميتفورمين', 'insulin', 'dose', 'medication'];
    return hasAnyText($blob, $terms);
}

function computeRiskLevel(int $total, int $high, int $low, ?float $avg, ?float $max, ?float $min): string
{
    if ($total <= 0) {
        return 'متوسط';
    }

    $highRatio = $high / max(1, $total);
    $lowRatio = $low / max(1, $total);
    $avgVal = (float)($avg ?? 0.0);
    $maxVal = (float)($max ?? 0.0);
    $minVal = (float)($min ?? 999.0);

    if ($maxVal >= 300.0 || $low >= 2 || $lowRatio >= 0.20 || $highRatio >= 0.55) {
        return 'مرتفع';
    }
    if ($highRatio >= 0.25 || $avgVal >= 160.0 || $minVal < 70.0) {
        return 'متوسط';
    }
    return 'منخفض';
}

function fallbackAssessment(
    int $age,
    string $gender,
    string $diabetesType,
    int $total,
    ?float $avg,
    ?float $max,
    ?float $min,
    int $high,
    int $normal,
    int $low,
    array $recentReadings
): array {
    $ageGroup = ageGroupFromAge($age >= 0 ? $age : null);
    $genderFocus = genderFocusText($gender);
    $tips = tipsByAgeAndGender($ageGroup, $gender);
    $riskLevel = computeRiskLevel($total, $high, $low, $avg, $max, $min);

    $avgText = $avg !== null ? number_format($avg, 1) : 'غير متاح';
    $maxText = $max !== null ? number_format($max, 1) : 'غير متاح';
    $minText = $min !== null ? number_format($min, 1) : 'غير متاح';

    $summary = 'التقييم المبدئي يشير إلى ';
    if ($total === 0) {
        $summary .= 'عدم كفاية بيانات القراءات حاليًا؛ يلزم الاستمرار بالتسجيل المنتظم للحصول على تقييم أدق.';
    } elseif ($riskLevel === 'مرتفع') {
        $summary .= 'مستوى خطورة مرتفع مع تذبذب واضح في القراءات ويستلزم متابعة لصيقة لتقليل الارتفاعات والهبوطات.';
    } elseif ($riskLevel === 'متوسط') {
        $summary .= 'مستوى خطورة متوسط مع حاجة لتحسين الاستقرار اليومي للسكر.';
    } else {
        $summary .= 'مستوى خطورة منخفض نسبيًا مع استمرار أهمية المتابعة الدورية.';
    }

    $riskSignals = [];
    if ($total === 0) {
        $riskSignals[] = 'غياب القراءات الكافية يمنع تقييم الاتجاهات بدقة.';
    } else {
        if ($high > 0) {
            $riskSignals[] = "وجود {$high} قراءة مرتفعة يحتاج تقليل العوامل اليومية المسببة للارتفاع.";
        }
        if ($low > 0) {
            $riskSignals[] = "وجود {$low} قراءة منخفضة يستلزم الحذر من الهبوط المفاجئ.";
        }
        if ($max !== null && $min !== null && ($max - $min) >= 120.0) {
            $riskSignals[] = 'اتساع الفارق بين أعلى وأدنى قراءة يدل على تذبذب ملحوظ.';
        }
        foreach ($recentReadings as $r) {
            $psych = (string)($r['psychological_status'] ?? '');
            if ($psych !== '' && hasAnyText($psych, ['قلق', 'توتر', 'stressed', 'anxious'])) {
                $riskSignals[] = 'الضغط النفسي المتكرر قد يكون عاملًا مساهمًا في عدم الاستقرار.';
                break;
            }
        }
    }
    if (empty($riskSignals)) {
        $riskSignals[] = 'لا توجد إشارات خطر حادة من الملخص الحالي مع ضرورة الاستمرار في المتابعة.';
    }

    return [
        'risk_level' => $riskLevel,
        'summary' => $summary,
        'key_indicators' => [
            "عدد القراءات: {$total}",
            "متوسط السكر: {$avgText} mg/dL",
            "أعلى/أدنى قراءة: {$maxText} / {$minText} mg/dL",
            "توزيع القراءات (مرتفع/طبيعي/منخفض): {$high} / {$normal} / {$low}",
        ],
        'age_gender_specific_tips' => [
            'age_group' => $ageGroup,
            'gender_focus' => $genderFocus,
            'tips' => $tips,
        ],
        'risk_signals' => $riskSignals,
        'general_advice' => [
            'المداومة على القياس في أوقات ثابتة يوميًا وتوثيق النتائج.',
            'تحسين جودة النوم وتقليل التوتر لدعم ثبات القراءات.',
            'اعتماد نشاط بدني منتظم وتغذية متوازنة قليلة السكريات السريعة.',
        ],
    ];
}

function normalizeAssessment(array $raw, array $fallback, bool $isConfirmedDiabetes): array
{
    $allowedLevels = ['منخفض', 'متوسط', 'مرتفع'];
    $allowedAgeGroups = ['طفل', 'مراهق', 'بالغ', 'كبير سن'];

    $riskLevel = trim((string)($raw['risk_level'] ?? ''));
    if (!in_array($riskLevel, $allowedLevels, true)) {
        $riskLevel = (string)$fallback['risk_level'];
    }

    $summary = trim((string)($raw['summary'] ?? ''));
    if ($summary === '') {
        $summary = (string)$fallback['summary'];
    }
    $summary = normalizeKnownDiabetesWording($summary, $isConfirmedDiabetes);

    $keyIndicators = cleanStringList($raw['key_indicators'] ?? null);
    if (empty($keyIndicators)) {
        $keyIndicators = (array)$fallback['key_indicators'];
    }

    $riskSignals = cleanStringList($raw['risk_signals'] ?? null);
    if (empty($riskSignals)) {
        $riskSignals = (array)$fallback['risk_signals'];
    }
    $riskSignals = array_values(array_map(
        static fn(string $item): string => normalizeKnownDiabetesWording($item, $isConfirmedDiabetes),
        $riskSignals
    ));

    $generalAdvice = cleanStringList($raw['general_advice'] ?? null);
    if (empty($generalAdvice)) {
        $generalAdvice = (array)$fallback['general_advice'];
    }
    $generalAdvice = array_values(array_map(
        static fn(string $item): string => normalizeKnownDiabetesWording($item, $isConfirmedDiabetes),
        $generalAdvice
    ));

    $ageTipsRaw = is_array($raw['age_gender_specific_tips'] ?? null)
        ? $raw['age_gender_specific_tips']
        : [];

    $ageGroup = trim((string)($ageTipsRaw['age_group'] ?? ''));
    if (!in_array($ageGroup, $allowedAgeGroups, true)) {
        $ageGroup = (string)$fallback['age_gender_specific_tips']['age_group'];
    }

    $genderFocus = trim((string)($ageTipsRaw['gender_focus'] ?? ''));
    if ($genderFocus === '') {
        $genderFocus = (string)$fallback['age_gender_specific_tips']['gender_focus'];
    }

    $tips = cleanStringList($ageTipsRaw['tips'] ?? null);
    if (count($tips) < 3) {
        $tips = (array)$fallback['age_gender_specific_tips']['tips'];
    } else {
        $tips = array_slice($tips, 0, 5);
    }

    return [
        'risk_level' => $riskLevel,
        'summary' => $summary,
        'key_indicators' => $keyIndicators,
        'age_gender_specific_tips' => [
            'age_group' => $ageGroup,
            'gender_focus' => $genderFocus,
            'tips' => $tips,
        ],
        'risk_signals' => $riskSignals,
        'general_advice' => $generalAdvice,
    ];
}

$profile = [
    'name' => (string)($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'المريض'),
    'age' => -1,
    'gender' => 'غير محدد',
    'diabetes_type' => 'غير محدد',
];

$stmt = $conn->prepare('SELECT full_name, age, gender, diabetes_type FROM user_profile WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (is_array($row)) {
        if (trim((string)($row['full_name'] ?? '')) !== '') {
            $profile['name'] = (string)$row['full_name'];
        }
        if (is_numeric($row['age'] ?? null)) {
            $profile['age'] = (int)$row['age'];
        }
        $profile['gender'] = normalizeGender((string)($row['gender'] ?? ''));
        if (trim((string)($row['diabetes_type'] ?? '')) !== '') {
            $profile['diabetes_type'] = (string)$row['diabetes_type'];
        }
    }
}

if ($profile['age'] < 0 || $profile['gender'] === 'غير محدد') {
    $stmt = $conn->prepare('SELECT name, age, gender FROM patient_data WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            if ($profile['name'] === 'المريض' && trim((string)($row['name'] ?? '')) !== '') {
                $profile['name'] = (string)$row['name'];
            }
            if ($profile['age'] < 0 && is_numeric($row['age'] ?? null)) {
                $profile['age'] = (int)$row['age'];
            }
            if ($profile['gender'] === 'غير محدد') {
                $profile['gender'] = normalizeGender((string)($row['gender'] ?? ''));
            }
        }
    }
}

$availableCols = [];
$colsRs = $conn->query('SHOW COLUMNS FROM glucose_readings');
if ($colsRs) {
    while ($c = $colsRs->fetch_assoc()) {
        $availableCols[$c['Field']] = true;
    }
    $colsRs->close();
}

$highConds = ["(CASE WHEN reading_unit='mmol' THEN reading_value*18 ELSE reading_value END) > 180"];
$lowConds = ["(CASE WHEN reading_unit='mmol' THEN reading_value*18 ELSE reading_value END) < 70"];

if (isset($availableCols['status_key'])) {
    $highConds[] = "LOWER(TRIM(status_key)) = 'high'";
    $lowConds[] = "LOWER(TRIM(status_key)) = 'low'";
}
if (isset($availableCols['status_ar'])) {
    $highConds[] = "status_ar LIKE '%مرتفع%'";
    $lowConds[] = "status_ar LIKE '%منخفض%'";
}
if (isset($availableCols['classification'])) {
    $highConds[] = "classification LIKE '%high%'";
    $highConds[] = "classification LIKE '%مرتفع%'";
    $lowConds[] = "classification LIKE '%low%'";
    $lowConds[] = "classification LIKE '%منخفض%'";
}

$sqlStats = "
    SELECT
        COUNT(*) AS total,
        ROUND(AVG(CASE WHEN reading_unit='mmol' THEN reading_value*18 ELSE reading_value END), 1) AS avg_mg,
        ROUND(MAX(CASE WHEN reading_unit='mmol' THEN reading_value*18 ELSE reading_value END), 1) AS max_mg,
        ROUND(MIN(CASE WHEN reading_unit='mmol' THEN reading_value*18 ELSE reading_value END), 1) AS min_mg,
        SUM(CASE WHEN (" . implode(' OR ', $highConds) . ") THEN 1 ELSE 0 END) AS high_c,
        SUM(CASE WHEN (" . implode(' OR ', $lowConds) . ") THEN 1 ELSE 0 END) AS low_c
    FROM (
        SELECT *
        FROM glucose_readings
        WHERE user_id = ? AND reading_value > 0
        ORDER BY COALESCE(created_at, TIMESTAMP(reading_date, COALESCE(reading_time, '00:00:00'))) DESC
        LIMIT 3
    ) AS recent_readings
";
$totalReadings = 0;
$avgMg = null;
$maxMg = null;
$minMg = null;
$highCount = 0;
$lowCount = 0;
$normalCount = 0;

$stmt = $conn->prepare($sqlStats);
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $totalReadings = (int)($stats['total'] ?? 0);
    $avgMg = isset($stats['avg_mg']) ? (float)$stats['avg_mg'] : null;
    $maxMg = isset($stats['max_mg']) ? (float)$stats['max_mg'] : null;
    $minMg = isset($stats['min_mg']) ? (float)$stats['min_mg'] : null;
    $highCount = (int)($stats['high_c'] ?? 0);
    $lowCount = (int)($stats['low_c'] ?? 0);
    $normalCount = max(0, $totalReadings - $highCount - $lowCount);
}

$selectCols = ['reading_date', 'reading_time', 'reading_value', 'reading_unit'];
foreach (['reading_context', 'classification', 'status_key', 'status_ar', 'psychological_status', 'note'] as $col) {
    if (isset($availableCols[$col])) {
        $selectCols[] = $col;
    }
}

$orderBy = isset($availableCols['created_at'])
    ? "COALESCE(created_at, TIMESTAMP(reading_date, COALESCE(reading_time, '00:00:00'))) DESC"
    : "reading_date DESC, COALESCE(reading_time, '00:00:00') DESC";

$recentRows = [];
$sqlRecent = 'SELECT ' . implode(', ', $selectCols) . " FROM (
    SELECT * FROM glucose_readings WHERE user_id = ? AND reading_value > 0 ORDER BY {$orderBy} LIMIT 3
) AS recent_subquery";
$stmt = $conn->prepare($sqlRecent);
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $recentRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();

$contextMap = [
    'fasting' => 'صائم',
    'post' => 'بعد الأكل',
    'ramadan' => 'صائم',
    'random' => 'عشوائي',
];

$readingsSummary = [];
foreach ($recentRows as $row) {
    $valueMg = round(toMgdl((float)($row['reading_value'] ?? 0), (string)($row['reading_unit'] ?? 'mg')), 1);
    $statusText = trim((string)($row['status_ar'] ?? ''));
    $classText = trim((string)($row['classification'] ?? ''));
    $statusKey = strtolower(trim((string)($row['status_key'] ?? '')));
    if ($statusText === '') {
        if ($statusKey === 'high' || hasAnyText($classText, ['high', 'مرتفع'])) {
            $statusText = 'مرتفع';
        } elseif ($statusKey === 'low' || hasAnyText($classText, ['low', 'منخفض'])) {
            $statusText = 'منخفض';
        } else {
            $statusText = 'طبيعي';
        }
    }

    $ctxRaw = (string)($row['reading_context'] ?? '');
    $readingsSummary[] = [
        'date' => (string)($row['reading_date'] ?? ''),
        'time' => (string)($row['reading_time'] ?? ''),
        'value_mgdl' => $valueMg,
        'status' => $statusText,
        'context' => $contextMap[$ctxRaw] ?? ($ctxRaw !== '' ? $ctxRaw : '-'),
        'psychological_status' => (string)($row['psychological_status'] ?? '-'),
    ];
}

$age = $profile['age'];
$gender = normalizeGender($profile['gender']);
$diabetesType = trim((string)$profile['diabetes_type']) !== '' ? (string)$profile['diabetes_type'] : 'غير محدد';

$fallback = fallbackAssessment(
    $age,
    $gender,
    $diabetesType,
    $totalReadings,
    $avgMg,
    $maxMg,
    $minMg,
    $highCount,
    $normalCount,
    $lowCount,
    $recentRows
);

if ($totalReadings === 0) {
    $payload = ['success' => true, 'data' => $fallback, 'cached' => false, 'source' => 'no_data'];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cacheKey] = $json;
    $_SESSION[$cacheTimeKey] = time();
    echo $json;
    exit;
}

$ageText = $age >= 0 ? (string)$age : 'غير محدد';
$avgText = $avgMg !== null ? number_format($avgMg, 1, '.', '') : 'غير متاح';
$maxText = $maxMg !== null ? number_format($maxMg, 1, '.', '') : 'غير متاح';
$minText = $minMg !== null ? number_format($minMg, 1, '.', '') : 'غير متاح';
$readingsJson = json_encode($readingsSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$prompt = "أنت مساعد صحي طبي.\n"
    . "قدّم تشخيصًا مبدئيًا فقط وليس تشخيصًا نهائيًا.\n\n"
    . "اعتمد على بيانات المريض التالية:\n\n"
    . "البيانات الشخصية:\n"
    . "- العمر: {$ageText}\n"
    . "- الجنس: {$gender}   (ذكر / أنثى)\n"
    . "- نوع السكري: {$diabetesType}\n\n"
    . "إحصائيات قراءات السكر:\n"
    . "- عدد القراءات: {$totalReadings}\n"
    . "- متوسط السكر: {$avgText} mg/dL\n"
    . "- أعلى قراءة: {$maxText} mg/dL\n"
    . "- أدنى قراءة: {$minText} mg/dL\n"
    . "- القراءات المرتفعة: {$highCount}\n"
    . "- القراءات الطبيعية: {$normalCount}\n"
    . "- القراءات المنخفضة: {$lowCount}\n\n"
    . "تفاصيل مختصرة لآخر القراءات:\n"
    . "{$readingsJson}\n\n"
    . "المطلوب:\n"
    . "أرجع النتيجة بصيغة JSON فقط بالشكل التالي:\n\n"
    . "{\n"
    . "  \"risk_level\": \"منخفض | متوسط | مرتفع\",\n"
    . "  \"summary\": \"تقييم عام للحالة الصحية\",\n"
    . "  \"key_indicators\": [\"...\"],\n"
    . "  \"age_gender_specific_tips\": {\n"
    . "    \"age_group\": \"طفل | مراهق | بالغ | كبير سن\",\n"
    . "    \"gender_focus\": \"نقاط متعلقة بالجنس\",\n"
    . "    \"tips\": [\"نصيحة 1\", \"نصيحة 2\", \"نصيحة 3\"]\n"
    . "  },\n"
    . "  \"risk_signals\": [\"...\"],\n"
    . "  \"general_advice\": [\"...\"]\n"
    . "}\n\n"
    . "قواعد:\n"
    . "- النصائح تكون مناسبة للعمر والجنس فقط\n"
    . "- لا تذكر أدوية\n"
    . "- المريض مصاب بالسكري بالفعل حسب نوع السكري المرسل، لذلك لا تكتب (خطر الإصابة بالسكري) وبدلها استخدم (خطر عدم الاستقرار أو المضاعفات)\n"
    . "- لا تطلب معلومات إضافية\n"
    . "- لا تذكر أنك ذكاء اصطناعي";

$apiKey = trim((string)($groqConfig['api_key'] ?? ''));
$model  = trim((string)($groqConfig['model']   ?? 'llama-3.3-70b-versatile'));
$chatUrl = trim((string)($groqConfig['chat_url'] ?? 'https://api.groq.com/openai/v1/chat/completions'));
if ($model === '') {
    $model = 'llama-3.3-70b-versatile';
}

if ($apiKey === '') {
    $payload = ['success' => true, 'data' => $fallback, 'cached' => false, 'source' => 'fallback_no_key'];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cacheKey] = $json;
    $_SESSION[$cacheTimeKey] = time();
    echo $json;
    exit;
}

$requestBody = json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'أنت مساعد صحي طبي توعوي. التزم بالرد بصيغة JSON فقط وبدون أي نص إضافي.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'temperature' => 0.2,
    'max_tokens' => 900,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($chatUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $requestBody,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($curlError !== '' || !$response || $httpCode < 200 || $httpCode >= 300) {
    $payload = ['success' => true, 'data' => $fallback, 'cached' => false, 'source' => 'fallback_http'];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cacheKey] = $json;
    $_SESSION[$cacheTimeKey] = time();
    echo $json;
    exit;
}

$decoded = json_decode($response, true);
$rawContent = (string)($decoded['choices'][0]['message']['content'] ?? '');
$parsed = parseJsonObject($rawContent);

if (!is_array($parsed)) {
    $assessment = $fallback;
    $source = 'fallback_parse';
} else {
    $assessment = normalizeAssessment($parsed, $fallback, isConfirmedDiabetesType($diabetesType));
    if (containsDisallowedMedicationText($assessment)) {
        $assessment = $fallback;
        $source = 'fallback_policy';
    } else {
        $source = 'groq';
    }
}

// Backward compatibility for current dashboard renderer.
$assessment['age_group_label'] = (string)$assessment['age_gender_specific_tips']['age_group'];
$assessment['age_group_tips'] = (array)$assessment['age_gender_specific_tips']['tips'];

$payload = ['success' => true, 'data' => $assessment, 'cached' => false, 'source' => $source];
$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
$_SESSION[$cacheKey] = $json;
$_SESSION[$cacheTimeKey] = time();
echo $json;
