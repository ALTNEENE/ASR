<?php
declare(strict_types=1);

/**
 * POST /main/api/generate_report_pdf.php
 * Generates an Arabic HTML report that the browser prints/saves as PDF.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<p>غير مسجل دخول</p>';
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';
require_once __DIR__ . '/../../includes/figma_assets.php';

$userId = (int)$_SESSION['user_id'];

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function contains_text(string $haystack, string $needle): bool
{
    return strpos($haystack, $needle) !== false;
}

function map_status_key_to_ar(string $statusKey): string
{
    switch (strtolower(trim($statusKey))) {
        case 'low':    return 'منخفض';
        case 'normal': return 'طبيعي';
        case 'high':   return 'مرتفع';
        default:       return '';
    }
}

function map_status_ar_to_key(string $statusAr): string
{
    switch (trim($statusAr)) {
        case 'منخفض': return 'low';
        case 'طبيعي': return 'normal';
        case 'مرتفع': return 'high';
        default:       return '';
    }
}

function normalize_status_text(?string $raw): string
{
    $text = trim((string)$raw);
    if ($text === '') return '';

    $lower = strtolower($text);
    if (strpos($lower, 'low')    !== false) return 'منخفض';
    if (strpos($lower, 'high')   !== false) return 'مرتفع';
    if (strpos($lower, 'normal') !== false || strpos($lower, 'prediab') !== false) return 'طبيعي';

    if (contains_text($text, 'منخفض')) return 'منخفض';
    if (contains_text($text, 'مرتفع')) return 'مرتفع';
    if (contains_text($text, 'طبيعي') || contains_text($text, 'ما قبل السكري')) return 'طبيعي';

    return '';
}

function translate_psych_status(string $val): string
{
    switch (strtolower(trim($val))) {
        case 'normal':    case 'طبيعي':   return 'طبيعي';
        case 'anxious':   case 'قلق':     return 'قلق';
        case 'stressed':  case 'متوتر':   return 'متوتر';
        case 'sad':       case 'حزين':    return 'حزين';
        case 'happy':     case 'سعيد':    return 'سعيد';
        case 'depressed': case 'مكتئب':   return 'مكتئب';
        case '':                          return '-';
        default:                          return $val;
    }
}

function to_mgdl(float $value, string $unit): float
{
    return strtolower(trim($unit)) === 'mmol' ? ($value * 18.0) : $value;
}

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    $refs   = [&$types];
    foreach ($params as $idx => &$p) { $refs[] = &$params[$idx]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function normalize_filter_date(string $raw, string $today): string
{
    $value = trim($raw);
    if ($value === '') return '';

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) return '';

    return ($value > $today) ? $today : $value;
}

function normalize_reading_status(array $row): array
{
    $val = (float)($row['reading_value'] ?? 0);
    $unit = strtolower(trim((string)($row['reading_unit'] ?? 'mg')));
    $val_mg = ($unit === 'mmol') ? $val * 18.0 : $val;
    $ctx = strtolower(trim((string)($row['reading_context'] ?? '')));

    $key = '';
    $ar = '';

    // Re-evaluate strictly based on values to fix the 300/600 being normal issue
    if ($ctx === 'fasting' || $ctx === 'ramadan') {
        if ($val_mg < 70) { $key = 'low'; $ar = 'منخفض'; }
        elseif ($val_mg <= 130) { $key = 'normal'; $ar = 'طبيعي'; }
        else { $key = 'high'; $ar = 'مرتفع'; }
    } else { // post or random
        if ($val_mg < 70) { $key = 'low'; $ar = 'منخفض'; }
        elseif ($val_mg <= 180) { $key = 'normal'; $ar = 'طبيعي'; }
        else { $key = 'high'; $ar = 'مرتفع'; }
    }

    $row['status_key'] = $key;
    $row['status_ar'] = $ar;
    $row['classification'] = $ar;
    return $row;
}

function infer_age_group(?int $age): string
{
    if ($age === null || $age < 0) return 'بالغ';
    if ($age <= 11) return 'طفل';
    if ($age <= 17) return 'مراهق';
    if ($age <= 59) return 'بالغ';
    return 'كبير سن';
}

function normalize_gender_label(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === 'male' || trim($raw) === 'ذكر') return 'ذكر';
    if ($v === 'female' || trim($raw) === 'أنثى') return 'أنثى';
    return trim($raw) !== '' ? trim($raw) : 'غير محدد';
}

function infer_pattern_label(array $values, string $trendHint, int $high, int $low, int $total): string
{
    $hint = trim($trendHint);
    if ($hint === 'تصاعدي' || $hint === 'صاعد') return 'تصاعدية';
    if ($hint === 'تحسن' || $hint === 'هابط') return 'تحسن';

    if (!empty($values)) {
        $range = max($values) - min($values);
        $highRatio = $high / max(1, $total);
        $lowRatio = $low / max(1, $total);
        if ($range >= 120 || ($highRatio >= 0.25 && $lowRatio >= 0.10) || ($high > 0 && $low > 0)) {
            return 'متذبذبة';
        }
    }

    return 'مستقرة';
}

function build_personalized_tips(string $ageGroup, string $gender, string $diabetesType, string $pattern, string $riskLevel): array
{
    $tips = [];

    if ($ageGroup === 'طفل') {
        $tips[] = 'ثبّت مواعيد الوجبات والنوم مع متابعة أسرية مستمرة للقياسات.';
    } elseif ($ageGroup === 'مراهق') {
        $tips[] = 'حافظ على روتين يومي ثابت للنوم والوجبات خصوصًا أيام الدراسة والاختبارات.';
    } elseif ($ageGroup === 'كبير سن') {
        $tips[] = 'تجنب فترات الصيام الطويلة وراقب أي أعراض هبوط مبكرًا خلال اليوم.';
    } else {
        $tips[] = 'حافظ على انتظام الوجبات والنشاط البدني لتقليل تقلبات السكر اليومية.';
    }

    if ($gender === 'أنثى') {
        $tips[] = 'تابعي تأثير التغيرات الهرمونية الشهرية على القراءات وسجليها بشكل منتظم.';
    } elseif ($gender === 'ذكر') {
        $tips[] = 'ركز على تقليل الجلوس الطويل والوزن الزائد لدعم استقرار القراءات.';
    } else {
        $tips[] = 'تابع العوامل اليومية (النوم، التوتر، النشاط) لأنها تؤثر على تذبذب السكر.';
    }

    $typeLower = strtolower(trim($diabetesType));
    if (strpos($typeLower, '1') !== false || strpos($diabetesType, 'الأول') !== false) {
        $tips[] = 'في السكري من النوع الأول، الالتزام الشديد بتوقيت القياس اليومي مهم لتقليل التذبذب.';
    } elseif (strpos($typeLower, '2') !== false || strpos($diabetesType, 'الثاني') !== false) {
        $tips[] = 'في السكري من النوع الثاني، تحسين نمط الأكل والحركة اليومية ينعكس بسرعة على المتوسط.';
    } else {
        $tips[] = 'استمر في المتابعة المنتظمة لأن نمط القراءات هو الأساس في تحسين التحكم.';
    }

    if ($pattern === 'تصاعدية') {
        $tips[] = 'لاحظ الوجبات أو الضغوط التي تسبق الارتفاعات المتكررة وحاول تعديلها.';
    } elseif ($pattern === 'متذبذبة') {
        $tips[] = 'وحّد أوقات القياس اليومية لتحديد أسباب التذبذب بدقة أكبر.';
    } elseif ($pattern === 'تحسن') {
        $tips[] = 'استمر على نفس العادات الحالية التي أدت لتحسن الاتجاه العام.';
    } else {
        $tips[] = 'حافظ على العادات الحالية مع متابعة دورية لضمان استمرار الاستقرار.';
    }

    if ($riskLevel === 'مرتفع') {
        $tips[] = 'المتابعة الطبية القريبة مهمة لتقييم أسباب الارتفاعات أو الهبوطات المتكررة.';
    }

    return array_values(array_unique($tips));
}

function build_preliminary_diagnosis(
    array $rows,
    ?string $trendHint = null,
    array $userData = [],
    int $detailedCount = 0
): array
{
    $age = isset($userData['age']) && is_numeric($userData['age']) ? (int)$userData['age'] : null;
    $gender = normalize_gender_label((string)($userData['gender'] ?? ''));
    $diabetesType = trim((string)($userData['diabetes_type'] ?? ''));
    if ($diabetesType === '') $diabetesType = 'غير محدد';
    $ageGroup = infer_age_group($age);

    $total = count($rows);
    if ($total === 0) {
        $trendLabel = infer_pattern_label([], (string)$trendHint, 0, 0, 0);
        $tips = build_personalized_tips($ageGroup, $gender, $diabetesType, $trendLabel, 'متوسط');
        return [
            'risk_level' => 'متوسط',
            'age_group' => $ageGroup,
            'trend_analysis' => "نمط القراءات: {$trendLabel}",
            'summary' => 'لا توجد قراءات حديثة كافية لبناء تقييم مبدئي لمراقبة السكر.',
            'risk_signals' => ['غياب القراءات الحديثة يمنع معرفة الاتجاه الحالي للسكر.'],
            'personalized_tips' => $tips,
            'key_indicators' => ['إجمالي القراءات المفحوصة: 0'],
            'evaluation_basis' => [
                'تم الاعتماد على آخر القراءات المتاحة فقط.',
                "الفئة العمرية المستنتجة: {$ageGroup}.",
                "تحليل الاتجاه الحالي: {$trendLabel}.",
            ],
        ];
    }

    $high = $normal = $low = 0;
    $values = [];
    foreach ($rows as $row) {
        $row = normalize_reading_status($row);
        $values[] = to_mgdl((float)$row['reading_value'], (string)$row['reading_unit']);
        $key = strtolower(trim((string)$row['status_key']));
        if ($key === 'high') $high++;
        elseif ($key === 'low') $low++;
        else $normal++;
    }

    $avg = round(array_sum($values) / $total, 1);
    $max = round(max($values), 1);
    $min = round(min($values), 1);
    $highRatio = $high / max(1, $total);
    $lowRatio = $low / max(1, $total);

    $pattern = infer_pattern_label($values, (string)$trendHint, $high, $low, $total);

    $riskLevel = 'منخفض';
    if ($max >= 300 || $low >= 3 || $lowRatio >= 0.20 || $highRatio >= 0.55) {
        $riskLevel = 'مرتفع';
    } elseif ($highRatio >= 0.25 || $avg >= 160 || $min < 70) {
        $riskLevel = 'متوسط';
    }
    if ($riskLevel === 'منخفض' && ($pattern === 'تصاعدية' || $pattern === 'متذبذبة')) {
        $riskLevel = 'متوسط';
    }

    $summary = "تم التقييم المبدئي بالاعتماد على آخر {$total} قراءة للمريض.";
    if ($riskLevel === 'مرتفع') {
        $summary .= ' النتائج تشير إلى خطورة مرتفعة وتذبذب واضح في سكر الدم.';
    } elseif ($riskLevel === 'متوسط') {
        $summary .= ' النتائج تشير إلى خطورة متوسطة مع حاجة إلى تحسين الثبات اليومي.';
    } else {
        $summary .= ' النتائج تشير إلى خطورة منخفضة نسبيًا مع الاستمرار في المتابعة.';
    }

    $riskSignals = [];
    if ($high > 0) $riskSignals[] = "تكرار القراءات المرتفعة: {$high} قراءة.";
    if ($low > 0) $riskSignals[] = "وجود قراءات منخفضة: {$low} قراءة.";
    if ($max >= 300) $riskSignals[] = 'تم رصد قراءة مرتفعة جدًا (300 mg/dL أو أكثر).';
    if (($max - $min) >= 120) $riskSignals[] = 'فارق كبير بين أعلى وأدنى قراءة (تذبذب ملحوظ).';
    if ($pattern === 'تصاعدية') {
        $riskSignals[] = 'الاتجاه العام للقراءات صاعد مقارنة بالفترة السابقة.';
    }
    if (empty($riskSignals)) $riskSignals[] = 'لا توجد إشارات خطر حادة من سجل القراءات الحالي.';
    $tips = build_personalized_tips($ageGroup, $gender, $diabetesType, $pattern, $riskLevel);

    $evaluationBasis = [
        'التحليل يعتمد على أحدث القراءات فقط لقياس جودة المراقبة الحالية للسكر.',
        'تم توحيد كل القيم إلى mg/dL قبل الحساب لضمان مقارنة عادلة.',
        'التقييم يعتمد على النسب، المتوسط، القيم القصوى، واتجاه القراءات الحديثة.',
    ];

    if ($detailedCount > 0) {
        $evaluationBasis[] = "تم تحليل آخر {$detailedCount} قراءة بالتفصيل لحساب الاتجاه قصير المدى.";
    }

    if (!empty($userData)) {
        $ageTxt = isset($userData['age']) ? (string)$userData['age'] : 'غير محدد';
        $genderTxt = trim((string)($userData['gender'] ?? '')) !== '' ? (string)$userData['gender'] : 'غير محدد';
        $typeTxt = trim((string)($userData['diabetes_type'] ?? '')) !== '' ? (string)$userData['diabetes_type'] : 'غير محدد';
        $evaluationBasis[] = "تم تضمين بيانات المستخدم: العمر {$ageTxt}، الجنس {$genderTxt}، نوع السكري {$typeTxt}.";
    }

    return [
        'risk_level' => $riskLevel,
        'age_group' => $ageGroup,
        'trend_analysis' => "نمط القراءات: {$pattern}",
        'summary' => $summary,
        'personalized_tips' => $tips,
        'key_indicators' => [
            "إجمالي القراءات المفحوصة: {$total}",
            "متوسط/أعلى/أدنى: {$avg} / {$max} / {$min} mg/dL",
            "توزيع القراءات (مرتفع/طبيعي/منخفض): {$high} / {$normal} / {$low}",
            "الفئة العمرية المستنتجة: {$ageGroup}",
            "نمط القراءات: {$pattern}",
        ],
        'risk_signals' => $riskSignals,
        'evaluation_basis' => $evaluationBasis,
    ];
}

// ── Parse input ────────────────────────────────────────────────────────
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$readingIds = [];
$rawIds = $input['ids'] ?? [];
if (is_array($rawIds)) {
    foreach ($rawIds as $id) { $i = (int)$id; if ($i > 0) $readingIds[] = $i; }
}
$todayDate     = date('Y-m-d');
$filterFrom    = normalize_filter_date((string)($input['from'] ?? ''), $todayDate);
$filterTo      = normalize_filter_date((string)($input['to'] ?? ''), $todayDate);
$filterContext = trim((string)($input['context'] ?? 'all'));
$reportPatientName  = trim((string)($input['patient_name'] ?? ''));
$reportPatientPhone = trim((string)($input['patient_phone'] ?? ''));
$summaryReportDate  = trim((string)($input['summary_report_date'] ?? ''));
$summaryReportTime  = trim((string)($input['summary_report_time'] ?? ''));
$summaryTotalReadings = trim((string)($input['summary_total_readings'] ?? ''));
$summaryAvgReading  = trim((string)($input['summary_avg_reading'] ?? ''));
$summaryHighRatio   = trim((string)($input['summary_high_ratio'] ?? ''));
$summaryLowRatio    = trim((string)($input['summary_low_ratio'] ?? ''));
$summaryControlLevel = trim((string)($input['summary_control_level'] ?? ''));
$summaryPeriod      = trim((string)($input['summary_period'] ?? ''));
$summaryControlNote = trim((string)($input['summary_control_note'] ?? ''));
$summaryLatestReading = trim((string)($input['summary_latest_reading'] ?? ''));

if ($filterFrom !== '' && $filterTo !== '' && $filterFrom > $filterTo) {
    $tmp = $filterFrom;
    $filterFrom = $filterTo;
    $filterTo = $tmp;
}

// Keep latest report identity info in session (helps when reopening PDF URL directly)
if ($reportPatientName !== '') {
    $_SESSION['report_patient_name'] = $reportPatientName;
} elseif (!empty($_SESSION['report_patient_name'])) {
    $reportPatientName = (string)$_SESSION['report_patient_name'];
}
if ($reportPatientPhone !== '') {
    $_SESSION['report_patient_phone'] = $reportPatientPhone;
} elseif (!empty($_SESSION['report_patient_phone'])) {
    $reportPatientPhone = (string)$_SESSION['report_patient_phone'];
}

// ── Patient profile ────────────────────────────────────────────────────
$profile = [];
$ps = $conn->prepare('SELECT full_name, age, gender, diabetes_type FROM user_profile WHERE user_id = ? LIMIT 1');
if ($ps) { $ps->bind_param('i', $userId); $ps->execute(); $profile = $ps->get_result()->fetch_assoc() ?: []; $ps->close(); }

$patientName = $reportPatientName !== ''
    ? $reportPatientName
    : (!empty($profile['full_name'])
        ? (string)$profile['full_name']
        : (string)($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'المريض'));
$patientPhone = $reportPatientPhone !== '' ? $reportPatientPhone : '-';

// Translate gender — saved as 'male'/'female' by questionnaire
$genderRaw     = strtolower(trim((string)($profile['gender'] ?? '')));
if ($genderRaw === 'male')        { $patientGender = 'ذكر'; }
elseif ($genderRaw === 'female')  { $patientGender = 'أنثى'; }
elseif ($genderRaw !== '')        { $patientGender = $profile['gender']; }
else                              { $patientGender = '-'; }

// 1) جلب كل بيانات المستخدم (تُستخدم كمدخلات إضافية للتقييم)
$userData = [
    'age' => isset($profile['age']) && is_numeric($profile['age']) ? (int)$profile['age'] : null,
    'gender' => (string)($profile['gender'] ?? ''),
    'diabetes_type' => (string)($profile['diabetes_type'] ?? ''),
];

// ── Fetch user medications ─────────────────────────────────────────────
$medications = [];
$ms = $conn->prepare("SELECT m.name_ar, m.name_en, um.dose FROM user_medications um JOIN medications m ON m.id = um.medication_id WHERE um.user_id = ? ORDER BY um.created_at DESC");
if ($ms) {
    $ms->bind_param('i', $userId); $ms->execute();
    $mr = $ms->get_result();
    while ($row = $mr->fetch_assoc()) $medications[] = $row;
    $ms->close();
}
$medsHtml = '';
if (!empty($medications)) {
    foreach ($medications as $m) {
        $n = h($m['name_ar'] ?: $m['name_en']);
        $d = $m['dose'] ? ' — ' . h($m['dose']) : '';
        $medsHtml .= "<li><strong>$n</strong>$d</li>";
    }
} else {
    $medsHtml = '<li style="color:#94a3b8;">لا توجد أدوية مسجّلة</li>';
}

// ── Build WHERE ────────────────────────────────────────────────────────
$whereParts = ['user_id = ?', 'reading_value > 0'];
$types = 'i'; $params = [$userId];

if (!empty($readingIds)) {
    $ph = implode(',', array_fill(0, count($readingIds), '?'));
    $whereParts[] = "id IN ($ph)";
    foreach ($readingIds as $id) { $types .= 'i'; $params[] = $id; }
}
if ($filterFrom !== '') { $whereParts[] = 'reading_date >= ?'; $types .= 's'; $params[] = $filterFrom; }
if ($filterTo   !== '') { $whereParts[] = 'reading_date <= ?'; $types .= 's'; $params[] = $filterTo; }
if ($filterContext !== '' && $filterContext !== 'all') { $whereParts[] = 'reading_context = ?'; $types .= 's'; $params[] = $filterContext; }

// Detect available columns
$availableCols = [];
$sr = $conn->query('SHOW COLUMNS FROM glucose_readings');
if ($sr) { while ($r = $sr->fetch_assoc()) $availableCols[$r['Field']] = true; $sr->close(); }

$selectCols = [];
foreach (['id','reading_date','reading_time','reading_value','reading_unit','reading_context',
          'classification','note','medications','psychological_status','status_key','status_ar'] as $col) {
    if (isset($availableCols[$col])) $selectCols[] = $col;
}

$whereStr = implode(' AND ', $whereParts);
$sql = 'SELECT ' . implode(', ', $selectCols) . " FROM glucose_readings WHERE $whereStr ORDER BY reading_date DESC, COALESCE(reading_time,'00:00:00') DESC LIMIT 500";

$readings = [];
$st = $conn->prepare($sql);
if ($st) {
    bind_dynamic_params($st, $types, $params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $readings[] = normalize_reading_status($row);
    }
    $st->close();
}

// 2) جلب آخر 3 قراءات كاملة + 3) حساب الاتجاه (Trend) + 4) التشخيص المبدئي منها فقط
$readingsDetailed = [];
$trend = 'مستقر';
$diagnosisReadingsLimit = 3;
$detailedSql = "SELECT reading_date, reading_time, reading_value, reading_unit, reading_context, classification, status_key, status_ar
                FROM glucose_readings
                WHERE user_id = ? AND reading_value > 0
                ORDER BY reading_date DESC, COALESCE(reading_time,'00:00:00') DESC
                LIMIT {$diagnosisReadingsLimit}";
$detailedStmt = $conn->prepare($detailedSql);
if ($detailedStmt) {
    $detailedStmt->bind_param('i', $userId);
    $detailedStmt->execute();
    $detailedRes = $detailedStmt->get_result();
    while ($row = $detailedRes->fetch_assoc()) {
        $readingsDetailed[] = normalize_reading_status($row);
    }
    $detailedStmt->close();
}

$trendValues = [];
foreach ($readingsDetailed as $row) {
    $trendValues[] = to_mgdl((float)$row['reading_value'], (string)$row['reading_unit']);
}
if (count($trendValues) >= 3) {
    // Using latest 3 points only: compare newest against oldest in this short window.
    $recent = $trendValues[0];
    $older = $trendValues[count($trendValues) - 1];
    if ($recent >= ($older + 20)) {
        $trend = 'تصاعدي';
    } elseif ($recent <= ($older - 20)) {
        $trend = 'تحسن';
    }
}

$diagnosisRows = $readingsDetailed;
$diagnosis = build_preliminary_diagnosis($diagnosisRows, $trend, $userData, count($diagnosisRows));

// ── Stats ──────────────────────────────────────────────────────────────
$total = count($readings);
$high = $normal = $low = 0;
$values = [];
foreach ($readings as $r) {
    $values[] = to_mgdl((float)$r['reading_value'], (string)$r['reading_unit']);
    $k = strtolower(trim((string)($r['status_key'] ?? '')));
    if ($k === 'high') $high++; elseif ($k === 'low') $low++; else $normal++;
}
$avgGlucose = $total > 0 ? round(array_sum($values) / $total, 1) : 0;
$maxGlucose = $total > 0 ? round(max($values), 1) : 0;
$minGlucose = $total > 0 ? round(min($values), 1) : 0;

$contextMap = ['fasting' => 'صائم', 'post' => 'بعد الأكل', 'ramadan' => 'صائم', 'random' => 'عشوائي'];
$reportDate = date('Y-m-d H:i');
if ($summaryReportDate !== '' || $summaryReportTime !== '') {
    $reportDate = trim($summaryReportDate . ' ' . $summaryReportTime);
}

// ── Build rows ─────────────────────────────────────────────────────────
$rowsHtml = '';
foreach ($readings as $r) {
    $statusText = (string)($r['status_ar'] ?? 'طبيعي');
    $key = strtolower(trim((string)($r['status_key'] ?? 'normal')));
    $bg  = '#d1fae5'; $fg = '#047857';
    if ($key === 'high') { $bg = '#fee2e2'; $fg = '#b91c1c'; }
    elseif ($key === 'low') { $bg = '#fef3c7'; $fg = '#b45309'; }

    $ctx   = h($contextMap[(string)$r['reading_context']] ?? (string)$r['reading_context']);
    $note  = h((string)($r['note'] ?: '-'));
    $meds  = h((string)($r['medications'] ?: '-'));
    $psych = h(translate_psych_status((string)($r['psychological_status'] ?? '')));

    $rowsHtml .= '<tr>'
        . '<td>' . h($r['reading_date']) . '</td>'
        . '<td>' . h($r['reading_time']) . '</td>'
        . "<td dir='ltr' style='text-align:center'>" . h($r['reading_value'] . ' ' . $r['reading_unit']) . '</td>'
        . '<td>' . $ctx . '</td>'
        . "<td><span style='background:{$bg};color:{$fg};padding:2px 10px;border-radius:20px;font-weight:bold;'>" . h($statusText) . '</span></td>'
        . '<td>' . $psych . '</td>'
        . '<td>' . $note . '</td>'
        . '<td>' . $meds . '</td>'
        . '</tr>';
}
if ($rowsHtml === '') {
    $rowsHtml = "<tr><td colspan='8' style='text-align:center;padding:20px;color:#64748b;'>لا توجد قراءات مطابقة</td></tr>";
}

$patientAge      = h((string)($profile['age'] ?? '-'));
$patientGenderH  = h($patientGender);
$patientDiab     = h((string)($profile['diabetes_type'] ?? '-'));
$patientNameHtml = h($patientName);
$patientPhoneHtml = h($patientPhone);
$displayTotalReadings = $summaryTotalReadings !== '' ? $summaryTotalReadings : (string)(int)$total;
$displayAvgReading = $summaryAvgReading !== '' ? $summaryAvgReading : (string)$avgGlucose;
$ratioCardLabel = '↑ مرتفع / ✓ طبيعي / ↓ منخفض';
$ratioCardValue = (string)((int)$high . ' / ' . (int)$normal . ' / ' . (int)$low);
if ($summaryHighRatio !== '' || $summaryLowRatio !== '') {
    $ratioCardLabel = 'نسبة مرتفع% / نسبة منخفض%';
    $ratioCardValue = ($summaryHighRatio !== '' ? $summaryHighRatio : '-') . ' / ' . ($summaryLowRatio !== '' ? $summaryLowRatio : '-');
}

$summaryMetaHtml = '';
if ($summaryControlLevel !== '' || $summaryPeriod !== '' || $summaryControlNote !== '' || $summaryLatestReading !== '') {
    $summaryMetaHtml .= '<div class="summary-meta"><h3>ملخص التقرير (من الصفحة)</h3><ul>';
    if ($summaryControlLevel !== '') {
        $summaryMetaHtml .= '<li><strong>تقييم التحكم:</strong> ' . h($summaryControlLevel) . '</li>';
    }
    if ($summaryPeriod !== '') {
        $summaryMetaHtml .= '<li><strong>فترة التقرير:</strong> ' . h($summaryPeriod) . '</li>';
    }
    if ($summaryControlNote !== '') {
        $summaryMetaHtml .= '<li><strong>توضيح سريع:</strong> ' . h($summaryControlNote) . '</li>';
    }
    if ($summaryLatestReading !== '') {
        $summaryMetaHtml .= '<li><strong>آخر قراءة:</strong> ' . h($summaryLatestReading) . '</li>';
    }
    $summaryMetaHtml .= '</ul></div>';
}

$diagRisk = (string)($diagnosis['risk_level'] ?? 'متوسط');
$diagBg = '#fef3c7'; $diagFg = '#92400e';
if ($diagRisk === 'مرتفع') { $diagBg = '#fee2e2'; $diagFg = '#b91c1c'; }
elseif ($diagRisk === 'منخفض') { $diagBg = '#d1fae5'; $diagFg = '#047857'; }

$diagSummary = h((string)($diagnosis['summary'] ?? '-'));

$diagIndicatorsHtml = '';
foreach ((array)($diagnosis['key_indicators'] ?? []) as $item) {
    $diagIndicatorsHtml .= '<li>' . h((string)$item) . '</li>';
}
if ($diagIndicatorsHtml === '') $diagIndicatorsHtml = '<li>-</li>';

$diagSignalsHtml = '';
foreach ((array)($diagnosis['risk_signals'] ?? []) as $item) {
    $diagSignalsHtml .= '<li>' . h((string)$item) . '</li>';
}
if ($diagSignalsHtml === '') $diagSignalsHtml = '<li>-</li>';

$diagBasisHtml = '';
foreach ((array)($diagnosis['evaluation_basis'] ?? []) as $item) {
    $diagBasisHtml .= '<li>' . h((string)$item) . '</li>';
}
if ($diagBasisHtml === '') $diagBasisHtml = '<li>-</li>';

$hasFigmaAssets = false;
try {
    figma_asset_assert('report', 'pdf-header-hero');
    figma_asset_assert('report', 'pdf-stats-illustration');
    figma_asset_assert('report', 'pdf-disclaimer-icon');
    $hasFigmaAssets = true;
} catch (Throwable $e) {
    error_log("Figma assets for report missing: " . $e->getMessage());
}

$figmaHeaderSvg = $hasFigmaAssets ? figma_asset_url('report', 'pdf-header-hero', 'svg') : '';
$figmaHeaderPng = $hasFigmaAssets ? figma_asset_url('report', 'pdf-header-hero', 'png') : '';
$figmaStatsSvg = $hasFigmaAssets ? figma_asset_url('report', 'pdf-stats-illustration', 'svg') : '';
$figmaStatsPng = $hasFigmaAssets ? figma_asset_url('report', 'pdf-stats-illustration', 'png') : '';
$figmaDisclaimerSvg = $hasFigmaAssets ? figma_asset_url('report', 'pdf-disclaimer-icon', 'svg') : '';
$figmaDisclaimerPng = $hasFigmaAssets ? figma_asset_url('report', 'pdf-disclaimer-icon', 'png') : '';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تقرير متابعة السكري</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Tajawal','Arial',sans-serif;direction:rtl;color:#1e293b;background:#f1f5f9;padding:24px;}
    .report-card{max-width:920px;margin:0 auto;background:#fff;border-radius:14px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,0.08);}
    h1{font-size:22px;color:#2f7f7a;text-align:center;border-bottom:2px solid #2f7f7a;padding-bottom:12px;margin-bottom:24px;}
    h2{font-size:15px;color:#334155;margin:20px 0 10px;font-weight:700;}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;margin-bottom:20px;}
    .info-row{display:flex;gap:6px;font-size:14px;}
    .info-label{font-weight:700;color:#2f7f7a;white-space:nowrap;}
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;}
    .stat-card{text-align:center;border:1px solid #e2e8f0;border-radius:10px;padding:12px 8px;background:#f8fafc;}
    .stat-label{font-size:12px;color:#64748b;margin-bottom:4px;}
    .stat-value{font-size:22px;font-weight:800;color:#2f7f7a;}
    .figma-block{margin-bottom:16px;}
    .figma-block picture{display:block;}
    .figma-block img{display:block;width:100%;height:auto;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc;}
    .summary-meta{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:14px;}
    .summary-meta h3{font-size:14px;color:#2f7f7a;margin-bottom:8px;}
    .summary-meta ul{padding-right:18px;line-height:1.9;font-size:13px;color:#334155;}
    .diag-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:20px;}
    .diag-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap;}
    .diag-risk{display:inline-block;padding:5px 12px;border-radius:999px;font-size:13px;font-weight:800;}
    .diag-summary{font-size:14px;line-height:1.9;color:#334155;margin-bottom:10px;}
    .diag-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
    .diag-col{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;}
    .diag-col h3{font-size:13px;color:#2f7f7a;margin-bottom:6px;}
    .diag-col ul{padding-right:18px;line-height:1.8;font-size:13px;}
    .meds-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 20px;margin-bottom:20px;}
    .meds-box ul{padding-right:20px;list-style:disc;}
    .meds-box li{font-size:14px;line-height:2;}
    table{width:100%;border-collapse:collapse;font-size:13.5px;margin-bottom:20px;}
    thead th{background:#2f7f7a;color:#fff;padding:10px 12px;text-align:right;font-weight:700;}
    tbody td{padding:9px 12px;border-bottom:1px solid #e2e8f0;}
    tbody tr:nth-child(even){background:#f8fafc;}
    .disclaimer{background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12.5px;color:#92400e;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;}
    .disclaimer-icon{width:28px;min-width:28px;}
    .disclaimer-icon img{display:block;width:100%;height:auto;}
    .disclaimer-text{line-height:1.8;}
    .footer{text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:12px;}
    .print-btn{display:block;margin:0 auto 24px;padding:12px 36px;background:#2f7f7a;color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;}
    .print-btn:hover{background:#1d5c57;}
    @media (max-width:860px){.stats-grid{grid-template-columns:repeat(2,1fr)} .diag-grid{grid-template-columns:1fr;}}
    @media print{
      @page{margin:1.5cm;size:A4 portrait;}
      body{background:white!important;padding:0!important;}
      .print-btn{display:none!important;}
      .report-card{box-shadow:none!important;border-radius:0!important;padding:0!important;max-width:100%!important;}
      .figma-block{break-inside:avoid;page-break-inside:avoid;}
      .figma-block img{border-color:#dbe2ea;}
    }
  </style>
</head>
<body>
<div class="report-card">

  <button class="print-btn" onclick="window.print()">🖨️ طباعة / حفظ PDF</button>

  <h1>📄 تقرير متابعة السكري — A.S.R</h1>
  <?php if ($hasFigmaAssets): ?>
  <div class="figma-block figma-header">
    <picture>
      <source media="print" srcset="<?= h($figmaHeaderPng) ?>" type="image/png">
      <img
        src="<?= h($figmaHeaderSvg) ?>"
        alt="صورة توضيحية لرأس التقرير"
        data-figma-slot="pdf-header-hero"
        width="920"
        height="280"
      >
    </picture>
  </div>
  <?php endif; ?>

  <div class="info-grid">
    <div class="info-row"><span class="info-label">اسم المريض:</span> <?= $patientNameHtml ?></div>
    <div class="info-row"><span class="info-label">رقم الهاتف:</span> <?= $patientPhoneHtml ?></div>
    <div class="info-row"><span class="info-label">تاريخ التقرير:</span> <?= h($reportDate) ?></div>
    <div class="info-row"><span class="info-label">العمر:</span> <?= $patientAge ?> سنة</div>
    <div class="info-row"><span class="info-label">الجنس:</span> <?= $patientGenderH ?></div>
    <div class="info-row"><span class="info-label">نوع السكري:</span> <?= $patientDiab ?></div>
    <div class="info-row"><span class="info-label">عدد القراءات:</span> <strong><?= h($displayTotalReadings) ?></strong></div>
  </div>
  <?= $summaryMetaHtml ?>

  <h2>📊 ملخص الإحصائيات</h2>
  <?php if ($hasFigmaAssets): ?>
  <div class="figma-block figma-stats">
    <picture>
      <source media="print" srcset="<?= h($figmaStatsPng) ?>" type="image/png">
      <img
        src="<?= h($figmaStatsSvg) ?>"
        alt="رسم توضيحي لقسم الإحصائيات"
        data-figma-slot="pdf-stats-illustration"
        width="860"
        height="220"
      >
    </picture>
  </div>
  <?php endif; ?>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">متوسط السكر (mg/dL)</div>
      <div class="stat-value"><?= h($displayAvgReading) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">أعلى قراءة</div>
      <div class="stat-value" style="color:#b91c1c"><?= h($maxGlucose) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">أدنى قراءة</div>
      <div class="stat-value" style="color:#047857"><?= h($minGlucose) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label"><?= h($ratioCardLabel) ?></div>
      <div class="stat-value" style="font-size:16px;"><?= h($ratioCardValue) ?></div>
    </div>
  </div>

  <h2>📋 سجل القراءات</h2>
  <table>
    <thead>
      <tr>
        <th>التاريخ</th>
        <th>الوقت</th>
        <th>القراءة</th>
        <th>التوقيت</th>
        <th>التقييم</th>
        <th>الحالة النفسية</th>
        <th>الأعراض</th>
        <th>الأدوية</th>
      </tr>
    </thead>
    <tbody>
      <?= $rowsHtml ?>
    </tbody>
  </table>

  <div class="disclaimer">
    <?php if ($hasFigmaAssets): ?>
    <picture class="disclaimer-icon">
      <source media="print" srcset="<?= h($figmaDisclaimerPng) ?>" type="image/png">
      <img
        src="<?= h($figmaDisclaimerSvg) ?>"
        alt="أيقونة تنبيه طبي"
        data-figma-slot="pdf-disclaimer-icon"
        width="28"
        height="28"
      >
    </picture>
    <?php else: ?>
    <div class="disclaimer-icon" style="font-size: 24px;">⚠️</div>
    <?php endif; ?>
    <span class="disclaimer-text">هذا التقرير للتوعية والمتابعة الشخصية فقط ولا يغني عن الاستشارة الطبية المتخصصة.</span>
  </div>
  <div class="footer">
    نظام A.S.R — جامعة كرري — تم التوليد بتاريخ <?= h($reportDate) ?>
  </div>

</div>
<script>
  window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 800);
  });
</script>
</body>
</html>
<?php $conn->close(); ?>
