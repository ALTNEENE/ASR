<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/figma_assets.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

/**
 * GET /main/share_report.php?t=TOKEN
 *
 * Public read-only page — shows shared glucose readings.
 * No login required; validates token expiry/revocation only.
 */

// ── PDO connection ─────────────────────────────────────────────────────
try {
    $pdo = database_pdo();
} catch (PDOException $e) {
    die('<p style="font-family:sans-serif;padding:40px;color:red;">خطأ في الاتصال بقاعدة البيانات.</p>');
}

// ── Validate token ─────────────────────────────────────────────────────
$token = preg_replace('/[^a-f0-9]/i', '', trim((string)($_GET['t'] ?? '')));
if (strlen($token) !== 32) {
    http_response_code(404);
    die(renderError('رابط غير صحيح', 'الرابط الذي تستخدمه غير موجود أو تالف.'));
}

$shareStmt = $pdo->prepare('
    SELECT * FROM report_shares
    WHERE token = ? LIMIT 1
');
$shareStmt->execute([$token]);
$share = $shareStmt->fetch();

if (!$share) {
    http_response_code(404);
    die(renderError('رابط غير موجود', 'لم يتم العثور على هذا التقرير.'));
}
if ($share['revoked_at'] !== null) {
    http_response_code(410);
    die(renderError('رابط ملغي', 'صاحب التقرير قام بإلغاء هذا الرابط.'));
}
if (strtotime($share['expires_at']) < time()) {
    http_response_code(410);
    die(renderError('رابط منتهي الصلاحية', 'انتهت صلاحية هذا الرابط. يمكنك طلب رابط جديد من صاحب التقرير.'));
}

$userId = (int)$share['user_id'];

// ── Load patient profile ───────────────────────────────────────────────
$profStmt = $pdo->prepare('
    SELECT full_name, age, gender, diabetes_type
    FROM user_profile WHERE user_id = ? LIMIT 1
');
$profStmt->execute([$userId]);
$profile = $profStmt->fetch() ?: [];
$patientName = !empty($profile['full_name']) ? $profile['full_name'] : 'مريض';

// ── Load selected readings ─────────────────────────────────────────────
$ids = array_values(array_filter(array_map('intval', explode(',', $share['selected_ids_text']))));
if (empty($ids)) {
    die(renderError('لا توجد قراءات', 'هذا التقرير لا يحتوي على قراءات.'));
}

$ph = implode(',', array_fill(0, count($ids), '?'));
$readStmt = $pdo->prepare("
    SELECT reading_date, reading_time, reading_value, reading_unit,
           reading_context, classification, note, psychological_status
    FROM glucose_readings
    WHERE id IN ($ph) AND user_id = ?
    ORDER BY reading_date DESC, reading_time DESC
");
$readStmt->execute([...$ids, $userId]);
$readings = $readStmt->fetchAll();

// ── Compute stats ──────────────────────────────────────────────────────
$total = count($readings);
$high = $low = $normal = $fasting = $post = 0;
$vals = [];
foreach ($readings as $r) {
    $mg = strtolower((string)($r['reading_unit'] ?? '')) === 'mmol'
        ? (float)$r['reading_value'] * 18.0
        : (float)$r['reading_value'];
    $vals[]  = $mg;
    $cls     = (string)($r['classification'] ?? '');
    if (str_contains($cls, 'مرتفع'))       $high++;
    elseif (str_contains($cls, 'منخفض'))   $low++;
    else                                    $normal++;
    if ($r['reading_context'] === 'fasting') $fasting++;
    elseif ($r['reading_context'] === 'post') $post++;
}
$avg = $total > 0 ? round(array_sum($vals) / count($vals), 1) : 0;
$max = $total > 0 ? round(max($vals), 1) : 0;
$min = $total > 0 ? round(min($vals), 1) : 0;

$reportDate  = date('Y-m-d H:i');
$expiresDate = date('Y-m-d H:i', strtotime($share['expires_at']));

$contextMap = [
    'fasting' => '🌙 صائم', 'post' => '🍽️ بعد الأكل',
    'random'  => '🎲 عشوائي', 'ramadan' => '🌙 صائم',
];

// ── Share URLs ─────────────────────────────────────────────────────────
$shareUrl  = app_url('main/share_report.php?t=' . $token);
$waText    = "📊 *تقرير متابعة السكري*\n🔗 {$shareUrl}";
$waUrl     = 'https://wa.me/?text=' . rawurlencode($waText);
$emailUrl  = 'mailto:?subject=' . rawurlencode('تقرير متابعة السكري') . '&body=' . rawurlencode("رابط التقرير:\n{$shareUrl}");

// ── Render ─────────────────────────────────────────────────────────────
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function renderError(string $title, string $msg): string {
    return <<<HTML
    <!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8">
    <title>{$title}</title>
    <style>body{font-family:'Tajawal',Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
    .box{background:#1e293b;border-radius:12px;padding:40px;text-align:center;max-width:400px;}h2{color:#f87171;}p{color:#94a3b8;}</style></head>
    <body><div class="box"><h2>⚠️ {$title}</h2><p>{$msg}</p></div></body></html>
    HTML;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تقرير متابعة السكري — A.S.R</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Tajawal', Arial, sans-serif;
      direction: rtl;
      background: #0f172a;
      color: #e2e8f0;
      min-height: 100vh;
      padding: 24px 16px;
    }
    .card {
      max-width: 860px;
      margin: 0 auto;
      background: #1e293b;
      border-radius: 16px;
      padding: 36px;
      box-shadow: 0 8px 40px rgba(0,0,0,0.4);
    }
    .report-header {
      text-align: center;
      border-bottom: 2px solid #2f7f7a;
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    .report-header h1 { font-size: 22px; color: #34d399; }
    .report-header p  { color: #94a3b8; font-size: 13px; margin-top: 4px; }
    .figma-share {
      margin-bottom: 16px;
      display: grid;
      gap: 10px;
    }
    .figma-share .figma-slot {
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 10px;
      padding: 8px;
    }
    .figma-share .figma-slot-img {
      width: 100%;
      height: auto;
      border-radius: 8px;
      display: block;
    }
    .figma-share .figma-asset-error {
      background: #2a1111;
      color: #fecaca;
      border: 1px solid #ef4444;
      border-radius: 8px;
      padding: 10px;
      font-size: 12px;
      line-height: 1.6;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 10px 24px;
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    .info-row { display: flex; gap: 6px; }
    .info-label { color: #34d399; font-weight: 700; white-space: nowrap; }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 10px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 10px;
      padding: 14px 10px;
      text-align: center;
    }
    .stat-label { font-size: 11px; color: #94a3b8; margin-bottom: 6px; }
    .stat-value { font-size: 24px; font-weight: 800; color: #34d399; }
    .stat-value.danger { color: #f87171; }
    .stat-value.warn   { color: #fbbf24; }

    h2 { font-size: 15px; color: #94a3b8; margin-bottom: 12px; font-weight: 700; }

    .table-wrap { overflow-x: auto; margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    thead th {
      background: #2f7f7a;
      color: #fff;
      padding: 10px 12px;
      text-align: right;
      font-weight: 700;
      white-space: nowrap;
    }
    tbody td {
      padding: 9px 12px;
      border-bottom: 1px solid #1e293b;
    }
    tbody tr { background: #0f172a; }
    tbody tr:nth-child(even) { background: #131f32; }

    .badge {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 12px;
    }
    .badge-high   { background: #fef2f2; color: #b91c1c; }
    .badge-low    { background: #fffbeb; color: #b45309; }
    .badge-normal { background: #f0fdf4; color: #047857; }

    .share-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      background: #0f172a;
      border: 1px solid #334155;
      border-radius: 10px;
      padding: 14px 18px;
      margin-bottom: 20px;
    }
    .btn {
      padding: 10px 18px;
      border: none;
      border-radius: 8px;
      font-family: inherit;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: opacity 0.2s;
    }
    .btn:hover { opacity: 0.85; }
    .btn-wa    { background: #22c55e; color: #fff; }
    .btn-email { background: #3b82f6; color: #fff; }
    .btn-copy  { background: #475569; color: #fff; }
    .btn-print { background: #2f7f7a; color: #fff; }

    .expiry-note {
      font-size: 12px;
      color: #94a3b8;
      text-align: center;
      margin-top: 12px;
    }
    .disclaimer {
      background: #1c1f0f;
      border: 1px solid #713f12;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 12.5px;
      color: #fef08a;
      margin-top: 20px;
    }

    @media print {
      @page { margin: 1.5cm; size: A4 portrait; }
      body { background: white !important; color: #1e293b !important; padding: 0 !important; }
      .share-bar, .btn-print { display: none !important; }
      .card { box-shadow: none !important; background: white !important; color: #1e293b !important; max-width: 100% !important; padding: 0 !important; }
      table { color: #1e293b !important; }
      tbody tr { background: white !important; }
      .stat-card, .info-grid { background: #f8fafc !important; color: #1e293b !important; border-color: #e2e8f0 !important; }
    }
  </style>
</head>
<body>
<div class="card">

  <div class="report-header">
    <h1>📄 تقرير متابعة السكري</h1>
    <p>نظام A.S.R — جامعة كرري &nbsp;|&nbsp; تقرير مشارك — للقراءة فقط</p>
  </div>

  <div class="figma-share">
    <?= figma_render_page_slots('report') ?>
  </div>

  <!-- Share bar -->
  <div class="share-bar">
    <span style="font-weight:700; color:#34d399; font-size:14px;">↗️ مشاركة هذا التقرير:</span>
    <a class="btn btn-wa" href="<?= h($waUrl) ?>" target="_blank" rel="noopener">💬 واتساب</a>
    <a class="btn btn-email" href="<?= h($emailUrl) ?>">✉️ إيميل</a>
    <button class="btn btn-copy" onclick="copyLink()">📋 نسخ الرابط</button>
    <button class="btn btn-print" onclick="window.print()">🖨️ طباعة / PDF</button>
    <span id="copy-msg" style="font-size:13px; color:#34d399; display:none;">✅ تم النسخ!</span>
  </div>

  <!-- Patient info -->
  <div class="info-grid">
    <div class="info-row"><span class="info-label">اسم المريض:</span> <?= h($patientName) ?></div>
    <div class="info-row"><span class="info-label">تاريخ التقرير:</span> <?= h($reportDate) ?></div>
    <div class="info-row"><span class="info-label">العمر:</span> <?= h($profile['age'] ?? '-') ?> سنة</div>
    <div class="info-row"><span class="info-label">الجنس:</span> <?= h($profile['gender'] ?? '-') ?></div>
    <div class="info-row"><span class="info-label">نوع السكري:</span> <?= h($profile['diabetes_type'] ?? '-') ?></div>
    <div class="info-row"><span class="info-label">عدد القراءات:</span> <strong><?= $total ?></strong></div>
  </div>

  <!-- Stats -->
  <h2>📊 ملخص الإحصائيات</h2>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">متوسط السكر (mg/dL)</div>
      <div class="stat-value"><?= $avg ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">أعلى قراءة</div>
      <div class="stat-value danger"><?= $max ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">أدنى قراءة</div>
      <div class="stat-value warn"><?= $min ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">مرتفع / طبيعي / منخفض</div>
      <div class="stat-value" style="font-size:18px;"><?= $high ?> / <?= $normal ?> / <?= $low ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">صائم / بعد الأكل</div>
      <div class="stat-value" style="font-size:18px;"><?= $fasting ?> / <?= $post ?></div>
    </div>
  </div>

  <!-- Readings table -->
  <h2>📋 سجل القراءات</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>التاريخ</th><th>الوقت</th><th>القراءة</th>
          <th>التوقيت</th><th>التقييم</th><th>الحالة النفسية</th><th>الأعراض</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($readings as $r):
          $cls = (string)($r['classification'] ?? '');
          $badgeClass = str_contains($cls, 'مرتفع') ? 'badge-high'
                      : (str_contains($cls, 'منخفض') ? 'badge-low' : 'badge-normal');
          $ctx = $contextMap[$r['reading_context']] ?? h($r['reading_context']);
        ?>
        <tr>
          <td><?= h($r['reading_date']) ?></td>
          <td><?= h($r['reading_time']) ?></td>
          <td dir="ltr" style="text-align:center"><?= h($r['reading_value'] . ' ' . $r['reading_unit']) ?></td>
          <td><?= $ctx ?></td>
          <td><span class="badge <?= $badgeClass ?>"><?= h($cls ?: '-') ?></span></td>
          <td><?= h($r['psychological_status'] ?? '-') ?></td>
          <td style="color:#94a3b8"><?= h($r['note'] ?: '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($readings)): ?>
          <tr><td colspan="7" style="text-align:center; padding:20px; color:#64748b;">لا توجد قراءات</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="disclaimer">
    ⚠️ هذا التقرير للتوعية والمتابعة الشخصية فقط ولا يُغني عن الاستشارة الطبية المتخصصة.
  </div>

  <p class="expiry-note">⏳ هذا الرابط صالح حتى: <?= h($expiresDate) ?></p>
</div>

<script>
function copyLink() {
  const url = <?= json_encode($shareUrl) ?>;
  navigator.clipboard.writeText(url).then(() => {
    const msg = document.getElementById('copy-msg');
    msg.style.display = 'inline';
    setTimeout(() => { msg.style.display = 'none'; }, 2500);
  }).catch(() => {
    prompt('انسخ الرابط:', url);
  });
}
</script>
</body>
</html>
