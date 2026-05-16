<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../google_sign_in/auth/auth_check.php';
require_once __DIR__ . '/../google_sign_in/config/db.php';
require_once __DIR__ . '/../config/bootstrap.php';

$debug_msg = ""; 
$user_id = $_SESSION['user_id'];
$readings = [];
$stats = null;
$latest_reading = null;
$period_start = null;
$period_end = null;
$control_level = 'مستقر';
$control_note = 'القراءات ضمن نطاق مقبول نسبيًا مع الاستمرار على المتابعة.';
$high_ratio = 0;
$low_ratio = 0;

function map_status_key_to_ar(string $statusKey): string
{
    switch (strtolower(trim($statusKey))) {
        case 'low':
            return 'منخفض';
        case 'normal':
            return 'طبيعي';
        case 'high':
            return 'مرتفع';
        default:
            return '';
    }
}

function map_status_ar_to_key(string $statusAr): string
{
    switch ($statusAr) {
        case 'منخفض':
            return 'low';
        case 'طبيعي':
            return 'normal';
        case 'مرتفع':
            return 'high';
        default:
            return '';
    }
}

function contains_text(string $haystack, string $needle): bool
{
    return strpos($haystack, $needle) !== false;
}

function normalize_status_text(?string $raw): string
{
    $text = trim((string)$raw);
    if ($text === '') {
        return '';
    }

    if (
        contains_text($text, 'منخفض')
    ) {
        return 'منخفض';
    }

    if (
        contains_text($text, 'مرتفع')
    ) {
        return 'مرتفع';
    }

    if (
        contains_text($text, 'طبيعي') ||
        contains_text($text, 'ما قبل السكري') ||
        stripos($text, 'prediab') !== false
    ) {
        return 'طبيعي';
    }

    return '';
}

function value_to_mgdl(float $value, string $unit): float
{
    return strtolower(trim($unit)) === 'mmol' ? ($value * 18.0) : $value;
}

function resolve_age(mysqli $conn, int $user_id): int
{
    $stmt = $conn->prepare('SELECT age FROM user_profile WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['age']) && is_numeric($row['age'])) {
            return (int)$row['age'];
        }
    }

    $stmt = $conn->prepare('SELECT age FROM patient_data WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['age']) && is_numeric($row['age'])) {
            return (int)$row['age'];
        }
    }

    return 30;
}

function normalize_filter_date(string $raw, string $today): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return '';
    }

    return ($value > $today) ? $today : $value;
}

// Fetch readings based on filters
$todayDate  = date('Y-m-d');
$filterFrom = normalize_filter_date((string)($_GET['from'] ?? ''), $todayDate);
$filterTo   = normalize_filter_date((string)($_GET['to']   ?? ''), $todayDate);
$filterCtx  = trim((string)($_GET['context'] ?? 'all'));

if ($filterFrom !== '' && $filterTo !== '' && $filterFrom > $filterTo) {
    $tmp = $filterFrom;
    $filterFrom = $filterTo;
    $filterTo = $tmp;
}

$age = resolve_age($conn, (int)$user_id);
$evaluator = new \App\GlucoseEvaluator();

$params = [$user_id];
$whereClauses = ['user_id = ?', 'reading_value > 0'];

if ($filterFrom !== '') {
    $whereClauses[] = 'reading_date >= ?';
    $params[] = $filterFrom;
}
if ($filterTo !== '') {
    $whereClauses[] = 'reading_date <= ?';
    $params[] = $filterTo;
}
if ($filterCtx !== '' && $filterCtx !== 'all') {
    $whereClauses[] = 'reading_context = ?';
    $params[] = $filterCtx;
}

$whereStr = implode(' AND ', $whereClauses);
$availableCols = [];
$schemaRes = $conn->query("SHOW COLUMNS FROM glucose_readings");
if ($schemaRes) {
    while ($schemaRow = $schemaRes->fetch_assoc()) {
        $availableCols[(string)$schemaRow['Field']] = true;
    }
    $schemaRes->close();
}

$baseCols = [
    'id',
    'reading_value',
    'reading_unit',
    'reading_context',
    'reading_date',
    'reading_time',
    'note',
    'medications',
    'classification',
];
$optionalCols = ['status_key', 'status_ar', 'reason_ar', 'psychological_status'];
$selectCols = [];
foreach (array_merge($baseCols, $optionalCols) as $col) {
    if (isset($availableCols[$col])) {
        $selectCols[] = $col;
    }
}

$query = "SELECT " . implode(', ', $selectCols) . "
          FROM glucose_readings
          WHERE $whereStr
          ORDER BY reading_date DESC, COALESCE(reading_time, '00:00:00') DESC
          LIMIT 1000";

if ($stmt = $conn->prepare($query)) {
    $types = str_repeat('s', count($params));
    $types[0] = 'i'; // user_id is integer
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $statsTotal = 0;
    $statsHigh = 0;
    $statsNormal = 0;
    $statsLow = 0;
    $sum = 0.0;
    $max = null;
    $min = null;

    while ($row = $result->fetch_assoc()) {
        $value = (float)($row['reading_value'] ?? 0);
        $unit = (string)($row['reading_unit'] ?? 'mg');
        $context = (string)($row['reading_context'] ?? 'fasting');
        $valueMg = value_to_mgdl($value, $unit);

        $statusKey = strtolower(trim((string)($row['status_key'] ?? '')));
        $statusAr = map_status_key_to_ar($statusKey);

        if ($statusAr === '') {
            $statusAr = normalize_status_text((string)($row['status_ar'] ?? ''));
        }
        if ($statusAr === '') {
            $statusAr = normalize_status_text((string)($row['classification'] ?? ''));
        }

        if ($statusAr === '') {
            $eval = $evaluator->evaluate($age, $valueMg, $context === 'post' ? 'after_meal' : $context);
            $statusKey = (string)$eval['status_key'];
            $statusAr = (string)$eval['status_ar'];
            if (empty($row['reason_ar'])) {
                $row['reason_ar'] = (string)$eval['reason_ar'];
            }
        }

        if ($statusKey === '') {
            $statusKey = map_status_ar_to_key($statusAr);
        }
        if ($statusKey === '') {
            $statusKey = 'normal';
        }
        if ($statusAr === '') {
            $statusAr = 'طبيعي';
        }

        $row['status_key'] = $statusKey;
        $row['status_ar'] = $statusAr;
        $row['classification'] = $statusAr;
        $readings[] = $row;

        if ($value > 0) {
            $statsTotal++;
            $sum += $valueMg;
            $max = $max === null ? $valueMg : max($max, $valueMg);
            $min = $min === null ? $valueMg : min($min, $valueMg);
            if ($statusKey === 'high') {
                $statsHigh++;
            } elseif ($statusKey === 'low') {
                $statsLow++;
            } else {
                $statsNormal++;
            }
        }
    }

    $stats = [
        'total_readings' => $statsTotal,
        'avg_reading' => $statsTotal > 0 ? round($sum / $statsTotal, 1) : null,
        'max_reading' => $max !== null ? round($max, 1) : null,
        'min_reading' => $min !== null ? round($min, 1) : null,
        'high_count' => $statsHigh,
        'normal_count' => $statsNormal,
        'low_count' => $statsLow,
    ];

    $stmt->close();
} else {
    $debug_msg = "Database Error: " . $conn->error;
}

if (!empty($readings)) {
    $latest_reading = $readings[0];
    $period_end = $readings[0]['reading_date'] ?? null;
    $period_start = $readings[count($readings) - 1]['reading_date'] ?? null;
}

if ($stats) {
    $total = (int)($stats['total_readings'] ?? 0);
    $high = (int)($stats['high_count'] ?? 0);
    $low = (int)($stats['low_count'] ?? 0);
    $avg = isset($stats['avg_reading']) ? (float)$stats['avg_reading'] : 0.0;

    if ($total > 0) {
        $high_ratio = round(($high / $total) * 100, 1);
        $low_ratio = round(($low / $total) * 100, 1);
    }

    if ($high_ratio >= 50 || $avg >= 220) {
        $control_level = 'خطر مرتفع';
        $control_note = 'نسبة القراءات المرتفعة عالية، ويُنصح بمراجعة الطبيب في أقرب وقت.';
    } elseif ($high_ratio >= 30 || $low_ratio >= 15 || $avg >= 180) {
        $control_level = 'يحتاج متابعة';
        $control_note = 'هناك تذبذب ملحوظ في القراءات، والمتابعة الدقيقة مطلوبة.';
    }
}

$figma_page = 'report';
include __DIR__ . '/../includes/header.php';
?>

<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Tajawal', 'Arial', sans-serif;
    background: var(--bg);
    color: var(--ink);
    padding: 20px;
    direction: rtl;
  }

  .report-container {
    max-width: 900px;
    margin: 0 auto;
    background: var(--surface);
    padding: 40px;
    border-radius: 10px;
    box-shadow: var(--shadow-soft);
  }

  .report-header {
    text-align: center;
    border-bottom: 3px solid var(--primary);
    padding-bottom: 20px;
    margin-bottom: 30px;
  }

  .report-header h1 {
    color: var(--primary);
    font-size: 2rem;
    margin-bottom: 10px;
  }

  .report-header p {
    color: var(--muted);
    font-size: 1rem;
  }

  .report-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 30px;
    padding: 20px;
    background: var(--surface-2);
    border-radius: 8px;
  }

  .info-item {
    display: flex;
    justify-content: space-between;
  }

  .info-label {
    font-weight: bold;
    color: var(--primary);
  }

  /* Scrollable table wrapper */
  .table-wrap {
    max-height: 70vh;
    overflow-y: auto;
    border-radius: 10px;
    border: 1px solid var(--border);
    margin-bottom: 30px;
  }

  @media (min-width: 1024px) {
    .table-wrap {
      max-height: 520px;
    }
  }

  .table-wrap::-webkit-scrollbar { width: 6px; }
  .table-wrap::-webkit-scrollbar-track { background: transparent; }
  .table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

  .report-table {
    width: 100%;
    border-collapse: collapse;
  }

  .report-table thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: var(--primary);
    color: white;
    padding: 12px;
    text-align: right;
    font-weight: bold;
    box-shadow: 0 1px 0 var(--primary);
  }

  .report-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    text-align: right;
  }

  .report-table tr:hover {
    background: var(--surface-2);
  }

  .status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: bold;
    display: inline-block;
  }

  .status-normal { background: #d1fae5; color: #047857; }
  .status-warning { background: #fef3c7; color: #b45309; }
  .status-danger { background: #fee2e2; color: #b91c1c; }

  .report-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 2px solid var(--border);
    color: var(--muted);
    font-size: 0.9rem;
  }

  .print-button {
    background: var(--primary);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    margin-bottom: 20px;
    display: block;
    margin-left: auto;
    margin-right: auto;
  }

  .print-button:hover {
    background: var(--primary-dark);
  }

  .info-input {
    border: 1px solid var(--border);
    padding: 6px 12px;
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.95rem;
    width: 100%;
    max-width: 300px;
    direction: rtl;
    background: var(--surface);
    color: var(--ink);
  }

  .info-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(47, 127, 122, 0.2);
  }

  /* Action Buttons Styling */
  .actions-toolbar {
    background: var(--surface-2);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
    border: 1px dashed var(--border);
  }

  .action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
  }

  .action-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s, box-shadow 0.2s;
    font-size: 1rem;
  }

  .action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  .pdf-btn { background: #2f7f7a; color: white; }
  .whatsapp-btn { background: #25d366; color: white; }
  .email-btn { background: #ea4335; color: white; }

  .report-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 20px;
  }

  .report-tab {
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--ink);
    padding: 10px 16px;
    border-radius: 999px;
    font-family: inherit;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .report-tab:hover {
    border-color: var(--primary);
    color: var(--primary);
  }

  .report-tab.is-active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
  }

  .tab-panel[hidden] {
    display: none !important;
  }

  .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
  }

  .summary-card {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
  }

  .summary-title {
    color: var(--muted);
    font-size: 0.9rem;
    margin-bottom: 6px;
  }

  .summary-value {
    color: var(--primary);
    font-weight: 800;
    font-size: 1.25rem;
  }

  .clarity-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-right: 4px solid var(--primary);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 16px;
    line-height: 1.9;
    color: var(--ink);
  }

  .section-help {
    color: var(--muted);
    font-size: 0.92rem;
    margin-bottom: 12px;
  }

  @media (max-width: 768px) {
    .report-container {
      padding: 24px 16px;
    }
    .report-info {
      grid-template-columns: 1fr;
    }
    .action-buttons {
      flex-direction: column;
    }
    .action-btn {
      width: 100%;
      justify-content: center;
    }
  }

  @media print {
    @page {
      margin: 0;
      size: auto;
    }
    body {
      background: white;
      padding: 0;
      margin: 1.6cm; /* Re-add margin for content since @page is 0 */
    }
    .actions-toolbar, .print-button, #doctor-contact-info, .report-tabs {
      display: none !important;
    }
    .tab-panel {
      display: block !important;
    }
    /* Remove scroll cap so full table prints */
    .table-wrap {
      max-height: none !important;
      overflow: visible !important;
      border: none !important;
    }
    .report-container {
      box-shadow: none;
      padding: 0;
      width: 100%;
      max-width: 100%;
    }
    .info-input {
      border: none;
      padding: 0;
      font-weight: normal;
    }
    /* Hide URL/Title headers/footers in modern browsers */
    html {
        margin: 0;
    }
  }
</style>

<div class="report-container">
  <div class="report-header">
    <h1>📄 تقرير متابعة السكري</h1>
    <p>نظام المتابعة الذكية لمرضى السكري - A.S.R</p>
  </div>

  <div class="report-tabs" role="tablist" aria-label="تبويبات التقرير">
    <button type="button" class="report-tab is-active" data-target="tab-summary">ملخص التقرير</button>
    <button type="button" class="report-tab" data-target="tab-readings">سجل القراءات</button>
  </div>

  <section id="tab-summary" class="tab-panel">
    <div class="report-info">
      <div class="info-item">
        <span class="info-label">اسم المريض:</span>
        <input type="text" id="patient-name" class="info-input" placeholder="أدخل اسمك هنا" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? ''); ?>">
      </div>
      <div class="info-item">
        <span class="info-label">تاريخ التقرير:</span>
        <span id="report-date"><?php echo date('Y-m-d'); ?></span>
      </div>
      <div class="info-item">
        <span class="info-label">رقم الهاتف:</span>
        <input type="tel" id="patient-phone" class="info-input" placeholder="أدخل رقم هاتفك" dir="ltr">
      </div>
      <div class="info-item">
        <span class="info-label">الوقت:</span>
        <span id="report-time"><?php echo date('H:i'); ?></span>
      </div>
    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-title">إجمالي القراءات</div>
        <div class="summary-value" id="summary-total-readings"><?php echo (int)($stats['total_readings'] ?? 0); ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-title">متوسط القراءة (mg/dL)</div>
        <div class="summary-value" id="summary-avg-reading"><?php echo isset($stats['avg_reading']) ? number_format((float)$stats['avg_reading'], 1) : '-'; ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-title">نسبة القراءات المرتفعة</div>
        <div class="summary-value" id="summary-high-ratio"><?php echo $high_ratio; ?>%</div>
      </div>
      <div class="summary-card">
        <div class="summary-title">نسبة القراءات المنخفضة</div>
        <div class="summary-value" id="summary-low-ratio"><?php echo $low_ratio; ?>%</div>
      </div>
      <div class="summary-card">
        <div class="summary-title">تقييم التحكم</div>
        <div class="summary-value" id="summary-control-level"><?php echo htmlspecialchars($control_level); ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-title">فترة التقرير</div>
        <div class="summary-value" id="summary-period" style="font-size: 0.98rem;">
          <?php if ($period_start && $period_end): ?>
            <?php echo htmlspecialchars($period_start . ' ← ' . $period_end); ?>
          <?php else: ?>
            لا توجد بيانات
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="clarity-box">
      <strong style="color: var(--primary);">توضيح سريع للتقرير:</strong>
      <div id="summary-control-note"><?php echo htmlspecialchars($control_note); ?></div>
      <div id="summary-latest-reading">
        <?php if ($latest_reading): ?>
          آخر قراءة مسجلة: 
          <?php echo htmlspecialchars($latest_reading['reading_value'] . ' ' . $latest_reading['reading_unit']); ?>
          بتاريخ <?php echo htmlspecialchars($latest_reading['reading_date']); ?>
          <?php if (!empty($latest_reading['reading_time'])): ?>
            الساعة <?php echo htmlspecialchars($latest_reading['reading_time']); ?>
          <?php endif; ?>
        <?php else: ?>
          لا توجد آخر قراءة مسجلة
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section id="tab-readings" class="tab-panel" hidden>
    <h2 style="color: var(--primary); margin-bottom: 8px;">📊 سجل القراءات</h2>
    <p class="section-help">يمكنك فلترة القراءات، اختيار محدد منها، وتوليد تقرير PDF أو حذفها.</p>

    <!-- Filter Form -->
    <form id="readings-filter-form" method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; background:var(--surface-2); padding:16px; border-radius:10px; margin-bottom:16px; border:1px solid var(--border);">
      <div style="display:flex; flex-direction:column; gap:4px;">
        <label style="font-size:0.85rem; font-weight:600; color:var(--muted);">من تاريخ</label>
        <input id="filter-from" type="date" name="from" value="<?php echo htmlspecialchars($filterFrom); ?>" max="<?php echo htmlspecialchars($todayDate); ?>" class="form-control" style="padding:8px 12px; border-radius:8px;">
      </div>
      <div style="display:flex; flex-direction:column; gap:4px;">
        <label style="font-size:0.85rem; font-weight:600; color:var(--muted);">إلى تاريخ</label>
        <input id="filter-to" type="date" name="to" value="<?php echo htmlspecialchars($filterTo); ?>" max="<?php echo htmlspecialchars($todayDate); ?>" class="form-control" style="padding:8px 12px; border-radius:8px;">
      </div>
      <div style="display:flex; flex-direction:column; gap:4px;">
        <label style="font-size:0.85rem; font-weight:600; color:var(--muted);">نوع القراءة</label>
        <select name="context" class="form-control" style="padding:8px 12px; border-radius:8px;">
          <option value="all" <?php echo $filterCtx==='all'?'selected':''; ?>>الكل</option>
          <option value="fasting" <?php echo $filterCtx==='fasting'?'selected':''; ?>>🌙 صائم</option>
          <option value="post" <?php echo $filterCtx==='post'?'selected':''; ?>>🍽️ بعد الأكل</option>
        </select>
      </div>
      <button type="submit" style="padding:10px 20px; background:var(--primary); color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">🔍 تطبيق</button>
      <a href="report.php" style="padding:10px 20px; background:var(--surface); color:var(--ink); border:1px solid var(--border); border-radius:8px; font-weight:bold; text-decoration:none;">↩ إعادة</a>
    </form>

    <!-- Bulk Actions Toolbar -->
    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:8px; align-items:center;">
      <label style="display:flex; align-items:center; gap:6px; font-weight:600; cursor:pointer;">
        <input type="checkbox" id="select-all-readings"> تحديد الكل
      </label>
      <span style="color:var(--muted); font-size:0.85rem;">(<span id="selected-count">0</span> محدد)</span>
      <button id="btn-generate-pdf" disabled style="padding:10px 20px; background:#2f7f7a; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer; opacity:0.5;">📄 عرض التقرير</button>
      <button id="btn-delete-selected" disabled style="padding:10px 20px; background:#b91c1c; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer; opacity:0.5;">🗑️ مسح المحدد</button>
    </div>

    <div class="table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th style="width:36px;"></th>
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
        <tbody id="report-data">
            <?php if ($debug_msg): ?>
                <tr><td colspan="9" style="color:red; text-align:center;"><?php echo $debug_msg; ?></td></tr>
            <?php endif; ?>

            <?php if (empty($readings)): ?>
                <tr>
                  <td colspan="9" style="text-align: center; padding: 30px; color: #94a3b8;">
                    لا توجد قراءات محفوظة حاليًا.<br>
                    <small>يمكنك إضافة قراءات من صفحة الأدوات</small>
                  </td>
                </tr>
            <?php else: ?>
                <?php foreach ($readings as $r): 
                    $contextMap = [
                        'fasting' => '🌙 صائم',
                        'post' => '🍽️ بعد الأكل',
                        'random' => '🎲 عشوائي'
                    ];
                    $context = $contextMap[$r['reading_context']] ?? $r['reading_context'];
                    
                    $statusText = (string)($r['status_ar'] ?? $r['classification'] ?? 'طبيعي');
                    $statusKey = strtolower(trim((string)($r['status_key'] ?? '')));
                    if ($statusKey === '' && strpos($statusText, 'مرتفع') !== false) $statusKey = 'high';
                    if ($statusKey === '' && strpos($statusText, 'منخفض') !== false) $statusKey = 'low';
                    if ($statusKey === '') $statusKey = 'normal';

                    $statusClass = 'status-normal';
                    if ($statusKey === 'high') $statusClass = 'status-danger';
                    if ($statusKey === 'low') $statusClass = 'status-warning';
                ?>
                <tr data-id="<?php echo (int)$r['id']; ?>">
                    <td><input type="checkbox" class="reading-checkbox" value="<?php echo (int)$r['id']; ?>"></td>
                    <td><?php echo htmlspecialchars($r['reading_date']); ?></td>
                    <td><?php echo htmlspecialchars($r['reading_time']); ?></td>
                    <td dir="ltr" style="text-align:right"><?php echo htmlspecialchars($r['reading_value'] . ' ' . $r['reading_unit']); ?></td>
                    <td><?php echo htmlspecialchars($context); ?></td>
                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span></td>
                    <td><?php echo htmlspecialchars($r['psychological_status'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['note'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['medications'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if ($stats): ?>
                <tr style="background-color: var(--surface-2); font-weight: bold;">
                  <td></td>
                  <td colspan="2">📊 ملخص سريع</td>
                  <td dir="ltr" style="text-align:right">المتوسط: <?php echo number_format((float)$stats['avg_reading'], 1); ?></td>
                  <td>العدد: <?php echo (int)$stats['total_readings']; ?></td>
                  <td colspan="3">مرتفع: <?php echo (int)$stats['high_count']; ?> | منخفض: <?php echo (int)$stats['low_count']; ?></td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>


  <div class="report-footer">
    <p><strong>ملاحظة:</strong> هذا التقرير للتوعية فقط ولا يغني عن الاستشارة الطبية المتخصصة.</p>
    <p style="margin-top: 10px;">تم إنشاء هذا التقرير بواسطة نظام A.S.R - جامعة كرري</p>
  </div>
</div>

<script>
  // Client-side interactions only (No data fetching)
  document.addEventListener('DOMContentLoaded', function() {

    // --- Tabs Logic ---
    const tabButtons = document.querySelectorAll('.report-tab');
    const tabPanels  = document.querySelectorAll('.tab-panel');

    tabButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        tabButtons.forEach(b => b.classList.remove('is-active'));
        this.classList.add('is-active');
        tabPanels.forEach(panel => { panel.hidden = panel.id !== targetId; });
      });
    });

    // Check URL for tab parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'readings') {
      const readingsTabBtn = document.querySelector('.report-tab[data-target="tab-readings"]');
      if (readingsTabBtn) readingsTabBtn.click();
    }

    // منع إدخال تواريخ مستقبلية في فلاتر السجل
    const readingsFilterForm = document.getElementById('readings-filter-form');
    const filterFromInput = document.getElementById('filter-from');
    const filterToInput = document.getElementById('filter-to');
    const todayDate = new Date().toISOString().split('T')[0];

    [filterFromInput, filterToInput].forEach(input => {
      if (input) input.max = todayDate;
    });

    if (readingsFilterForm) {
      readingsFilterForm.addEventListener('submit', function (e) {
        const fromVal = filterFromInput?.value || '';
        const toVal = filterToInput?.value || '';

        if ((fromVal && fromVal > todayDate) || (toVal && toVal > todayDate)) {
          e.preventDefault();
          alert('لا يمكن اختيار تاريخ مستقبلي في البحث.');
          return;
        }

        if (fromVal && toVal && fromVal > toVal) {
          e.preventDefault();
          alert('تاريخ "من" يجب أن يكون قبل أو يساوي "إلى".');
        }
      });
    }

    // ── Checkbox & selection logic ─────────────────────────────────────
    const selectAll           = document.getElementById('select-all-readings');
    const countSpan           = document.getElementById('selected-count');
    const btnPdf              = document.getElementById('btn-generate-pdf');
    const btnDelSel           = document.getElementById('btn-delete-selected');

    function getChecked() {
      return Array.from(document.querySelectorAll('.reading-checkbox:checked'));
    }

    function updateToolbar() {
      const checked  = getChecked();
      const count    = checked.length;
      const hasItems = count > 0;
      if (countSpan)           countSpan.textContent                  = count;
      if (btnPdf)            { btnPdf.disabled            = !hasItems; btnPdf.style.opacity            = hasItems ? '1' : '0.5'; }
      if (btnDelSel)         { btnDelSel.disabled         = !hasItems; btnDelSel.style.opacity         = hasItems ? '1' : '0.5'; }
    }

    document.querySelectorAll('.reading-checkbox').forEach(cb => {
      cb.addEventListener('change', updateToolbar);
    });

    if (selectAll) {
      selectAll.addEventListener('change', function() {
        document.querySelectorAll('.reading-checkbox').forEach(cb => { cb.checked = this.checked; });
        updateToolbar();
      });
    }

    // ── Generate PDF for selected readings ─────────────────────────────
    if (btnPdf) {
      btnPdf.addEventListener('click', function() {
        const ids = getChecked().map(cb => cb.value);
        if (ids.length === 0) return;
        const patientNameInput  = (document.getElementById('patient-name')?.value || '').trim();
        const patientPhoneInput = (document.getElementById('patient-phone')?.value || '').trim();
        const patientName  = patientNameInput  || (localStorage.getItem('patient_name')  || '').trim();
        const patientPhone = patientPhoneInput || (localStorage.getItem('patient_phone') || '').trim();
        if (patientName)  localStorage.setItem('patient_name', patientName);
        if (patientPhone) localStorage.setItem('patient_phone', patientPhone);

        // Build a form that POSTs to the report page in a new tab
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/generate_report_pdf.php';
        form.target = '_blank';
        form.style.display = 'none';

        const addHidden = (name, value) => {
          const inp = document.createElement('input');
          inp.type = 'hidden';
          inp.name = name;
          inp.value = value;
          form.appendChild(inp);
        };

        ids.forEach(id => {
          const inp = document.createElement('input');
          inp.type  = 'hidden';
          inp.name  = 'ids[]';
          inp.value = id;
          form.appendChild(inp);
        });

        addHidden('patient_name', patientName);
        addHidden('patient_phone', patientPhone);
        const getText = (id) => (document.getElementById(id)?.textContent || '').trim();
        addHidden('summary_report_date', getText('report-date'));
        addHidden('summary_report_time', getText('report-time'));
        addHidden('summary_total_readings', getText('summary-total-readings'));
        addHidden('summary_avg_reading', getText('summary-avg-reading'));
        addHidden('summary_high_ratio', getText('summary-high-ratio'));
        addHidden('summary_low_ratio', getText('summary-low-ratio'));
        addHidden('summary_control_level', getText('summary-control-level'));
        addHidden('summary_period', getText('summary-period'));
        addHidden('summary_control_note', getText('summary-control-note'));
        addHidden('summary_latest_reading', getText('summary-latest-reading'));

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
      });
    }

    // ── Delete selected readings ────────────────────────────────────────

    if (btnDelSel) {
      btnDelSel.addEventListener('click', async function() {
        const ids = getChecked().map(cb => cb.value);
        if (ids.length === 0) return;
        if (!confirm(`هل أنت متأكد من حذف ${ids.length} قراءة محددة؟ لا يمكن التراجع.`)) return;

        try {
          const resp = await fetch('api/delete_readings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'by_ids', ids: ids })
          });
          const data = await resp.json();
          if (data.ok) {
            alert(data.message || 'تم الحذف بنجاح');
            window.location.reload();
          } else {
            alert('فشل الحذف: ' + (data.error || ''));
          }
        } catch (err) {
          alert('حدث خطأ: ' + err.message);
        }
      });
    }
    
    // --- Manage Input Persistence (Patient & Doctor Info) ---

    const inputsToSave = [
      { id: 'patient-name', key: 'patient_name' },
      { id: 'patient-phone', key: 'patient_phone' },
      { id: 'doctor-phone', key: 'doctor_phone' },
      { id: 'doctor-email', key: 'doctor_email' }
    ];

    inputsToSave.forEach(item => {
      const inputEl = document.getElementById(item.id);
      if (inputEl) {
        const savedVal = localStorage.getItem(item.key);
        if (savedVal && !inputEl.value) {
          inputEl.value = savedVal;
        } else if (savedVal && (item.id === 'doctor-phone' || item.id === 'doctor-email')) {
          inputEl.value = savedVal;
        }

        inputEl.addEventListener('input', function() {
          localStorage.setItem(item.key, this.value);
        });
      }
    });

  });
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
