<?php
declare(strict_types=1);

/**
 * POST /main/api/generate_share.php
 *
 * Creates a secure share token for selected glucose readings.
 *
 * POST body (JSON or form-data):
 *   selected_ids[]   int[]   – reading IDs to share (required OR use date range)
 *   date_from        string  – Y-m-d start date (optional, used to auto-select if no IDs)
 *   date_to          string  – Y-m-d end date (optional)
 *   expires_hours    int     – hours until expiry (default: 48, max: 168)
 *
 * Returns JSON:
 *   { ok, share_url, whatsapp_url, email_url, expires_at }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// ── Auth guard ─────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── PDO connection ─────────────────────────────────────────────────────
try {
    $pdo = database_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Parse input ────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$dateFrom    = trim((string)($input['date_from'] ?? ''));
$dateTo      = trim((string)($input['date_to']   ?? ''));
$expiresHrs  = min(168, max(1, (int)($input['expires_hours'] ?? 48)));

// Collect + validate selected IDs (must belong to this user)
$rawIds = $input['selected_ids'] ?? $input['ids'] ?? [];
if (!is_array($rawIds)) {
    $rawIds = array_filter(array_map('intval', explode(',', (string)$rawIds)));
}
$selectedIds = array_values(array_filter(array_map('intval', $rawIds)));

// If no IDs given, auto-select by date range
if (empty($selectedIds)) {
    if ($dateFrom === '' && $dateTo === '') {
        echo json_encode(['ok' => false, 'error' => 'يجب اختيار قراءات أو تحديد نطاق زمني'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $whereParts = ['user_id = :uid', 'reading_value > 0'];
    $autoParams = ['uid' => $userId];
    if ($dateFrom !== '') { $whereParts[] = 'reading_date >= :df'; $autoParams['df'] = $dateFrom; }
    if ($dateTo   !== '') { $whereParts[] = 'reading_date <= :dt'; $autoParams['dt'] = $dateTo; }
    $autoStmt = $pdo->prepare('SELECT id FROM glucose_readings WHERE ' . implode(' AND ', $whereParts) . ' LIMIT 500');
    $autoStmt->execute($autoParams);
    $selectedIds = $autoStmt->fetchAll(PDO::FETCH_COLUMN);
}

if (empty($selectedIds)) {
    echo json_encode(['ok' => false, 'error' => 'لم يتم العثور على قراءات'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verify all IDs belong to this user (security check)
$ph = implode(',', array_fill(0, count($selectedIds), '?'));
$verifyStmt = $pdo->prepare("SELECT id FROM glucose_readings WHERE id IN ($ph) AND user_id = ?");
$verifyStmt->execute([...$selectedIds, $userId]);
$validIds = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);

if (count($validIds) === 0) {
    echo json_encode(['ok' => false, 'error' => 'لا توجد قراءات صحيحة مختارة'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Generate token & insert ────────────────────────────────────────────
$token     = bin2hex(random_bytes(16));   // 32 hex chars, 128-bit entropy
$idsText   = implode(',', $validIds);
$expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresHrs} hours"));

$insert = $pdo->prepare('
    INSERT INTO report_shares
        (user_id, token, selected_ids_text, date_from, date_to, expires_at)
    VALUES
        (:uid, :token, :ids, :df, :dt, :exp)
');
$insert->execute([
    'uid'   => $userId,
    'token' => $token,
    'ids'   => $idsText,
    'df'    => $dateFrom !== '' ? $dateFrom : null,
    'dt'    => $dateTo   !== '' ? $dateTo   : null,
    'exp'   => $expiresAt,
]);

// ── Build share URLs ───────────────────────────────────────────────────
$shareUrl  = app_url('main/share_report.php?t=' . $token);

$waText = "📊 *تقرير متابعة السكري*\n\n"
        . "مشاركة " . count($validIds) . " قراءة من سجلات السكر الخاصة بي.\n\n"
        . "🔗 رابط التقرير:\n{$shareUrl}\n\n"
        . "⏳ الرابط صالح لـ {$expiresHrs} ساعة.";
$waUrl = 'https://wa.me/?text=' . rawurlencode($waText);

$emailSubject = 'تقرير متابعة السكري — A.S.R';
$emailBody    = "السلام عليكم،\n\n"
              . "أشارككم تقرير قراءات السكر الخاص بي:\n\n"
              . "عدد القراءات: " . count($validIds) . "\n"
              . "رابط التقرير: {$shareUrl}\n\n"
              . "⏳ الرابط صالح لـ {$expiresHrs} ساعة.\n\n"
              . "تم الإرسال عبر نظام A.S.R — جامعة كرري";
$emailUrl = 'mailto:?subject=' . rawurlencode($emailSubject)
          . '&body='    . rawurlencode($emailBody);

echo json_encode([
    'ok'          => true,
    'share_url'   => $shareUrl,
    'whatsapp_url'=> $waUrl,
    'email_url'   => $emailUrl,
    'expires_at'  => $expiresAt,
    'reading_count' => count($validIds),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
