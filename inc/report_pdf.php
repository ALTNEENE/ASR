<?php

declare(strict_types=1);

/**
 * inc/report_pdf.php
 * 
 * Generates a PDF report of selected glucose readings using Dompdf.
 * 
 * Usage (called from main/api/generate_report_pdf.php):
 *   require __DIR__ . '/report_pdf.php';
 *   generateReadingsPdf($pdo, $userId, $readingIds);
 */

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../config/bootstrap.php';

/**
 * Generate and stream a PDF to the browser.
 *
 * @param PDO   $pdo
 * @param int   $userId
 * @param int[] $readingIds  IDs to include; empty = all readings
 * @param array $filters     ['from'=>'Y-m-d','to'=>'Y-m-d','context'=>'fasting|post|all']
 */
function generateReadingsPdf(PDO $pdo, int $userId, array $readingIds = [], array $filters = []): void
{
    // ── 1. Fetch patient profile ──────────────────────────────────────
    $profileStmt = $pdo->prepare(
        'SELECT * FROM patient_data WHERE user_id = ? LIMIT 1'
    );
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Try user table for name
    $userStmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // ── 2. Fetch readings ─────────────────────────────────────────────
    $params = [$userId];
    $where = ['r.user_id = ?', 'r.reading_value > 0'];

    if (!empty($readingIds)) {
        $placeholders = implode(',', array_fill(0, count($readingIds), '?'));
        $where[] = "r.id IN ($placeholders)";
        $params = array_merge($params, $readingIds);
    }

    if (!empty($filters['from'])) {
        $where[] = 'r.reading_date >= ?';
        $params[] = $filters['from'];
    }

    if (!empty($filters['to'])) {
        $where[] = 'r.reading_date <= ?';
        $params[] = $filters['to'];
    }

    if (!empty($filters['context']) && $filters['context'] !== 'all') {
        $where[] = 'r.reading_context = ?';
        $params[] = $filters['context'];
    }

    $whereClause = implode(' AND ', $where);
    $readingsStmt = $pdo->prepare(
        "SELECT r.id, r.reading_date, r.reading_time, r.reading_value, r.reading_unit,
                r.reading_context, r.classification, r.note, r.medications
         FROM glucose_readings r
         WHERE $whereClause
         ORDER BY r.reading_date DESC, r.reading_time DESC
         LIMIT 500"
    );
    $readingsStmt->execute($params);
    $readings = $readingsStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── 3. Compute quick stats ────────────────────────────────────────
    $total = count($readings);
    $high = $low = $normal = 0;
    $values = [];
    foreach ($readings as $r) {
        $mg = strtolower($r['reading_unit']) === 'mmol'
            ? (float)$r['reading_value'] * 18.0
            : (float)$r['reading_value'];
        $values[] = $mg;
        $cls = (string)($r['classification'] ?? '');
        if (strpos($cls, 'مرتفع') !== false) $high++;
        elseif (strpos($cls, 'منخفض') !== false) $low++;
        else $normal++;
    }
    $avgGlucose = $total > 0 ? round(array_sum($values) / count($values), 1) : 0;
    $maxGlucose = $total > 0 ? max($values) : 0;
    $minGlucose = $total > 0 ? min($values) : 0;

    // ── 4. Build HTML ─────────────────────────────────────────────────
    $patientName = htmlspecialchars($profile['name'] ?? ($userRow['email'] ?? 'غير محدد'));
    $patientAge  = htmlspecialchars((string)($profile['age'] ?? '-'));
    $patientGend = htmlspecialchars($profile['gender'] ?? '-');
    $patientDiab = htmlspecialchars($profile['diabetes_type'] ?? '-');
    $reportDate  = date('Y-m-d H:i');

    $contextMap = ['fasting' => 'صائم', 'post' => 'بعد الأكل', 'ramadan' => 'صائم'];

    $rowsHtml = '';
    foreach ($readings as $row) {
        $cls = htmlspecialchars((string)($row['classification'] ?? '-'));
        $color = '';
        if (strpos((string)($row['classification'] ?? ''), 'مرتفع') !== false) {
            $color = 'color:#b91c1c; background:#fee2e2;';
        } elseif (strpos((string)($row['classification'] ?? ''), 'منخفض') !== false) {
            $color = 'color:#b45309; background:#fef3c7;';
        } else {
            $color = 'color:#047857; background:#d1fae5;';
        }

        $ctx = $contextMap[$row['reading_context']] ?? $row['reading_context'];
        $note = htmlspecialchars(($row['note'] ?: '-'));
        $rowsHtml .= "
        <tr>
            <td>{$row['reading_date']}</td>
            <td>{$row['reading_time']}</td>
            <td dir='ltr'>{$row['reading_value']} {$row['reading_unit']}</td>
            <td>$ctx</td>
            <td style='$color font-weight:bold;'>$cls</td>
            <td>$note</td>
        </tr>";
    }

    if ($rowsHtml === '') {
        $rowsHtml = "<tr><td colspan='6' style='text-align:center;'>لا توجد قراءات</td></tr>";
    }

    $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; direction: rtl; color: #1e293b; margin: 20px; }
  h1 { font-size: 20px; color: #2f7f7a; text-align: center; border-bottom: 2px solid #2f7f7a; padding-bottom: 8px; margin-bottom: 16px; }
  h2 { font-size: 15px; color: #334155; margin-bottom: 8px; }
  .info-grid { display: table; width: 100%; margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; }
  .info-row { display: table-row; }
  .info-cell { display: table-cell; padding: 4px 12px; width: 50%; }
  .label { font-weight: bold; color: #2f7f7a; }
  .stats-grid { display: table; width: 100%; margin-bottom: 16px; }
  .stats-cell { display: table-cell; text-align: center; width: 25%; border: 1px solid #e2e8f0; padding: 8px; }
  .stats-label { font-size: 11px; color: #64748b; }
  .stats-value { font-size: 18px; font-weight: bold; color: #2f7f7a; }
  table.readings { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  table.readings th { background: #2f7f7a; color: white; padding: 8px; text-align: right; font-size: 12px; }
  table.readings td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
  table.readings tr:nth-child(even) { background: #f8fafc; }
  .footer { text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 16px; }
  .disclaimer { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 4px; padding: 8px 12px; font-size: 11px; color: #92400e; margin-bottom: 12px; }
</style>
</head>
<body>
  <h1>📄 تقرير متابعة السكري — A.S.R</h1>

  <div class="info-grid">
    <div class="info-row">
      <div class="info-cell"><span class="label">اسم المريض:</span> $patientName</div>
      <div class="info-cell"><span class="label">تاريخ التقرير:</span> $reportDate</div>
    </div>
    <div class="info-row">
      <div class="info-cell"><span class="label">العمر:</span> $patientAge سنة</div>
      <div class="info-cell"><span class="label">الجنس:</span> $patientGend</div>
    </div>
    <div class="info-row">
      <div class="info-cell"><span class="label">نوع السكري:</span> $patientDiab</div>
      <div class="info-cell"><span class="label">عدد القراءات:</span> $total</div>
    </div>
  </div>

  <h2>ملخص الإحصائيات</h2>
  <div class="stats-grid">
    <div class="stats-cell"><div class="stats-label">متوسط السكر (mg/dL)</div><div class="stats-value">$avgGlucose</div></div>
    <div class="stats-cell"><div class="stats-label">أعلى قراءة</div><div class="stats-value">$maxGlucose</div></div>
    <div class="stats-cell"><div class="stats-label">أدنى قراءة</div><div class="stats-value">$minGlucose</div></div>
    <div class="stats-cell"><div class="stats-label">مرتفع / طبيعي / منخفض</div><div class="stats-value" style="font-size:14px;">↑$high / ✓$normal / ↓$low</div></div>
  </div>

  <h2>سجل القراءات</h2>
  <table class="readings">
    <thead>
      <tr>
        <th>التاريخ</th><th>الوقت</th><th>القراءة</th><th>التوقيت</th><th>التقييم</th><th>ملاحظة</th>
      </tr>
    </thead>
    <tbody>
      $rowsHtml
    </tbody>
  </table>

  <div class="disclaimer">
    ⚠️ هذا التقرير للتوعية والمتابعة الشخصية فقط ولا يُغني عن الاستشارة الطبية المتخصصة.
  </div>

  <div class="footer">
    نظام A.S.R — جامعة كرري — تم التوليد بتاريخ $reportDate
  </div>
</body>
</html>
HTML;

    // ── 5. Render with Dompdf ─────────────────────────────────────────
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'glucose_report_' . date('Ymd_His') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}
