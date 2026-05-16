<?php

declare(strict_types=1);

/**
 * POST /main/api/send_report_email.php
 *
 * Builds the HTML report for selected readings and sends it directly
 * via PHPMailer — no download required.
 *
 * Body (JSON or FormData):
 *   to_email  string  – recipient email
 *   to_name   string  – recipient name (optional)
 *   ids[]     int[]   – reading IDs to include (optional)
 *   from      string  – filter start date Y-m-d (optional)
 *   to        string  – filter end date Y-m-d (optional)
 *   context   string  – fasting|post|all (optional)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'غير مسجّل دخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!class_exists(PHPMailer::class)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Email dependencies are not installed. Run composer install.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

function normalize_filter_date(string $raw, string $today): string
{
    $value = trim($raw);
    if ($value === '') return '';

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) return '';

    return ($value > $today) ? $today : $value;
}

// ── Parse input ────────────────────────────────────────────────────────
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$toEmail  = trim((string)($input['to_email'] ?? ''));
$toName   = trim((string)($input['to_name']  ?? 'دكتور'));

if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'الرجاء إدخال إيميل صحيح'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawIds = $input['ids'] ?? [];
$readingIds = [];
if (is_array($rawIds)) {
    foreach ($rawIds as $id) { $int = (int)$id; if ($int > 0) $readingIds[] = $int; }
}
$todayDate     = date('Y-m-d');
$filterFrom    = normalize_filter_date((string)($input['from'] ?? ''), $todayDate);
$filterTo      = normalize_filter_date((string)($input['to'] ?? ''), $todayDate);
$filterContext = trim((string)($input['context'] ?? 'all'));

if ($filterFrom !== '' && $filterTo !== '' && $filterFrom > $filterTo) {
    $tmp = $filterFrom;
    $filterFrom = $filterTo;
    $filterTo = $tmp;
}

// ── Fetch patient profile from questionnaire (user_profile) ──────────────
$profile = [];
$profileStmt = $conn->prepare('SELECT full_name, age, gender, diabetes_type FROM user_profile WHERE user_id = ? LIMIT 1');
if ($profileStmt) {
    $profileStmt->bind_param('i', $userId);
    $profileStmt->execute();
    $profile = $profileStmt->get_result()->fetch_assoc() ?: [];
    $profileStmt->close();
}
$patientName = !empty($profile['full_name'])
    ? $profile['full_name']
    : ($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'المريض');

// ── Build readings query ───────────────────────────────────────────────
$whereParts = ['user_id = ?', 'reading_value > 0'];
$params     = [$userId];
$types      = 'i';

if (!empty($readingIds)) {
    $ph = implode(',', array_fill(0, count($readingIds), '?'));
    $whereParts[] = "id IN ($ph)";
    foreach ($readingIds as $id) { $params[] = $id; $types .= 'i'; }
}
if ($filterFrom !== '') { $whereParts[] = 'reading_date >= ?'; $params[] = $filterFrom; $types .= 's'; }
if ($filterTo   !== '') { $whereParts[] = 'reading_date <= ?'; $params[] = $filterTo;   $types .= 's'; }
if ($filterContext !== 'all' && $filterContext !== '') {
    $whereParts[] = 'reading_context = ?'; $params[] = $filterContext; $types .= 's';
}

$whereStr = implode(' AND ', $whereParts);
$readings = [];
$stmt = $conn->prepare(
    "SELECT id, reading_date, reading_time, reading_value, reading_unit,
            reading_context, classification, note, psychological_status
     FROM glucose_readings
     WHERE $whereStr
     ORDER BY reading_date DESC, reading_time DESC LIMIT 500"
);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $readings[] = $row;
    $stmt->close();
}

// ── Build stats ────────────────────────────────────────────────────────
$total = count($readings);
$high = $low = $normal = 0;
$values = [];
foreach ($readings as $r) {
    $mg = strtolower($r['reading_unit']) === 'mmol'
        ? (float)$r['reading_value'] * 18.0 : (float)$r['reading_value'];
    $values[] = $mg;
    $cls = (string)($r['classification'] ?? '');
    if (str_contains($cls, 'مرتفع')) $high++;
    elseif (str_contains($cls, 'منخفض')) $low++;
    else $normal++;
}
$avg = $total > 0 ? round(array_sum($values) / count($values), 1) : 0;
$max = $total > 0 ? round(max($values), 1) : 0;
$min = $total > 0 ? round(min($values), 1) : 0;

$contextMap = ['fasting' => 'صائم', 'post' => 'بعد الأكل', 'ramadan' => 'صائم', 'random' => 'عشوائي'];
$reportDate = date('Y-m-d H:i');

// ── Build HTML for email ───────────────────────────────────────────────
$rowsHtml = '';
foreach ($readings as $r) {
    $cls = htmlspecialchars((string)($r['classification'] ?? '-'));
    $bg = '#d1fae5'; $fc = '#047857';
    if (str_contains((string)($r['classification'] ?? ''), 'مرتفع')) { $bg='#fee2e2'; $fc='#b91c1c'; }
    elseif (str_contains((string)($r['classification'] ?? ''), 'منخفض')) { $bg='#fef3c7'; $fc='#b45309'; }
    $ctx  = htmlspecialchars($contextMap[$r['reading_context']] ?? $r['reading_context']);
    $note = htmlspecialchars($r['note'] ?: '-');
    $psych = htmlspecialchars($r['psychological_status'] ?? '-');
    $rowsHtml .= "
    <tr>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0;'>".htmlspecialchars($r['reading_date'])."</td>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0;'>".htmlspecialchars($r['reading_time'])."</td>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0; direction:ltr; text-align:center;'>".htmlspecialchars($r['reading_value'].' '.$r['reading_unit'])."</td>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0;'>$ctx</td>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0;'><span style='background:$bg;color:$fc;padding:2px 10px;border-radius:20px;font-weight:bold;display:inline-block;'>$cls</span></td>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0;'>$psych</td>
      <td style='padding:8px 12px; border-bottom:1px solid #e2e8f0; color:#64748b;'>$note</td>
    </tr>";
}
if ($rowsHtml === '') {
    $rowsHtml = "<tr><td colspan='7' style='padding:20px; text-align:center; color:#64748b;'>لا توجد قراءات</td></tr>";
}

$patientAge    = htmlspecialchars((string)($profile['age'] ?? '-'));
$patientGender = htmlspecialchars((string)($profile['gender'] ?? '-'));
$patientDiab   = htmlspecialchars((string)($profile['diabetes_type'] ?? '-'));
$pName         = htmlspecialchars($patientName);

$htmlBody = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="UTF-8">
  <title>تقرير السكري</title>
</head>
<body style="font-family:Arial,sans-serif; direction:rtl; color:#1e293b; background:#f8fafc; margin:0; padding:24px;">
  <div style="max-width:700px; margin:0 auto; background:#fff; border-radius:12px; padding:32px; box-shadow:0 2px 16px rgba(0,0,0,0.08);">
    
    <div style="text-align:center; border-bottom:2px solid #2f7f7a; padding-bottom:16px; margin-bottom:24px;">
      <h1 style="color:#2f7f7a; font-size:22px; margin:0;">📄 تقرير متابعة السكري</h1>
      <p style="color:#64748b; margin:6px 0 0;">نظام A.S.R — جامعة كرري</p>
    </div>

    <table style="width:100%; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:20px; border-collapse:collapse;">
      <tr>
        <td style="padding:6px 12px; width:50%;"><strong style="color:#2f7f7a;">اسم المريض:</strong> $pName</td>
        <td style="padding:6px 12px;"><strong style="color:#2f7f7a;">تاريخ التقرير:</strong> $reportDate</td>
      </tr>
      <tr>
        <td style="padding:6px 12px;"><strong style="color:#2f7f7a;">العمر:</strong> $patientAge سنة</td>
        <td style="padding:6px 12px;"><strong style="color:#2f7f7a;">الجنس:</strong> $patientGender</td>
      </tr>
      <tr>
        <td style="padding:6px 12px;"><strong style="color:#2f7f7a;">نوع السكري:</strong> $patientDiab</td>
        <td style="padding:6px 12px;"><strong style="color:#2f7f7a;">عدد القراءات:</strong> <strong>$total</strong></td>
      </tr>
    </table>

    <h2 style="color:#334155; font-size:15px; margin-bottom:12px;">📊 ملخص الإحصائيات</h2>
    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
      <tr>
        <td style="text-align:center; border:1px solid #e2e8f0; border-radius:8px; padding:12px; width:25%;">
          <div style="font-size:11px; color:#64748b;">متوسط السكر (mg/dL)</div>
          <div style="font-size:24px; font-weight:800; color:#2f7f7a;">$avg</div>
        </td>
        <td style="text-align:center; border:1px solid #e2e8f0; padding:12px; width:25%;">
          <div style="font-size:11px; color:#64748b;">أعلى قراءة</div>
          <div style="font-size:24px; font-weight:800; color:#b91c1c;">$max</div>
        </td>
        <td style="text-align:center; border:1px solid #e2e8f0; padding:12px; width:25%;">
          <div style="font-size:11px; color:#64748b;">أدنى قراءة</div>
          <div style="font-size:24px; font-weight:800; color:#047857;">$min</div>
        </td>
        <td style="text-align:center; border:1px solid #e2e8f0; padding:12px; width:25%;">
          <div style="font-size:11px; color:#64748b;">↑ مرتفع / ✓ طبيعي / ↓ منخفض</div>
          <div style="font-size:18px; font-weight:800; color:#334155;">$high / $normal / $low</div>
        </td>
      </tr>
    </table>

    <h2 style="color:#334155; font-size:15px; margin-bottom:12px;">📋 سجل القراءات</h2>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
      <thead>
        <tr style="background:#2f7f7a; color:#fff;">
          <th style="padding:10px 12px; text-align:right;">التاريخ</th>
          <th style="padding:10px 12px; text-align:right;">الوقت</th>
          <th style="padding:10px 12px; text-align:center;">القراءة</th>
          <th style="padding:10px 12px; text-align:right;">التوقيت</th>
          <th style="padding:10px 12px; text-align:right;">التقييم</th>
          <th style="padding:10px 12px; text-align:right;">الحالة النفسية</th>
          <th style="padding:10px 12px; text-align:right;">الأعراض</th>
        </tr>
      </thead>
      <tbody>
        $rowsHtml
      </tbody>
    </table>

    <div style="background:#fef9c3; border:1px solid #fde68a; border-radius:8px; padding:10px 14px; font-size:12px; color:#92400e; margin-top:20px;">
      ⚠️ هذا التقرير للتوعية والمتابعة الشخصية فقط ولا يُغني عن الاستشارة الطبية المتخصصة.
    </div>

    <p style="text-align:center; font-size:12px; color:#94a3b8; margin-top:16px;">
      تم الإرسال بواسطة نظام A.S.R — $reportDate
    </p>
  </div>
</body>
</html>
HTML;

// ── Load SMTP config & send ────────────────────────────────────────────
$smtpConfig = [];
try {
    $smtpConfig = require __DIR__ . '/../../config/smtp_config.php';
} catch (Throwable $e) {
    // Fall through with defaults
}

$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $smtpConfig['smtp_host']     ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig['smtp_username'] ?? '';
    $mail->Password   = $smtpConfig['smtp_password'] ?? '';
    $mail->SMTPSecure = $smtpConfig['smtp_secure']   ?? PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)($smtpConfig['smtp_port'] ?? 587);
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPDebug  = 0;

    // Addresses
    $mail->setFrom(
        $smtpConfig['from_email'] ?? 'noreply@asr-diabetes.com',
        $smtpConfig['from_name']  ?? 'نظام A.S.R'
    );
    $mail->addAddress($toEmail, $toName);

    // Content
    $mail->isHTML(true);
    $mail->Subject = "=?UTF-8?B?" . base64_encode("تقرير متابعة السكري - {$patientName}") . "?=";
    $mail->Body    = $htmlBody;
    $mail->AltBody = "تقرير متابعة السكري لـ {$patientName} — {$reportDate}\nعدد القراءات: {$total}\nمتوسط: {$avg} mg/dL";

    $mail->send();

    echo json_encode([
        'ok'      => true,
        'message' => "تم إرسال التقرير إلى {$toEmail} بنجاح ✅",
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'ok'    => false,
        'error' => 'فشل الإرسال: ' . $mail->ErrorInfo,
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn)) $conn->close();
}
