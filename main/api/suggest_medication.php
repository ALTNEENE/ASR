<?php

declare(strict_types=1);

/**
 * POST /main/api/suggest_medication.php
 * Uses Groq AI to match user text to medications in DB.
 * Input: JSON { "q": "..." }
 * Output: { ok, best_id, best_name_ar, best_name_en, confidence, reason_ar, alternatives, alternative_details }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'غير مسجل دخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';
$groq_config = require __DIR__ . '/../../config/groq_config.php';

$apiKey = trim((string)($groq_config['api_key'] ?? ''));
$apiKeyStatus = $groq_config['api_key_status'] ?? ['ok' => $apiKey !== '', 'error' => ''];
$chatUrl = (string)($groq_config['chat_url'] ?? 'https://api.groq.com/openai/v1/chat/completions');
$model = (string)($groq_config['model'] ?? 'llama-3.3-70b-versatile');

$input = json_decode((string)file_get_contents('php://input'), true) ?: [];
$q = trim((string)($input['q'] ?? ''));

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => false, 'error' => 'الرجاء كتابة حرفين على الأقل'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!($apiKeyStatus['ok'] ?? false)) {
    echo json_encode(['ok' => false, 'error' => (string)($apiKeyStatus['error'] ?? 'Groq API key is not configured.')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    echo json_encode(['ok' => false, 'error' => 'PHP cURL extension is not enabled.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$searchTerm = '%' . $q . '%';
$stmt = $conn->prepare('SELECT id, name_ar, name_en, type FROM medications WHERE name_ar LIKE ? OR name_en LIKE ? LIMIT 30');
$stmt->bind_param('ss', $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$candidates = [];
$candidateIds = [];
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
    $candidateIds[] = (int)$row['id'];
}
$stmt->close();

if (empty($candidates)) {
    $result2 = $conn->query('SELECT id, name_ar, name_en, type FROM medications ORDER BY name_ar LIMIT 30');
    if ($result2) {
        while ($row = $result2->fetch_assoc()) {
            $candidates[] = $row;
            $candidateIds[] = (int)$row['id'];
        }
    }
}

if (empty($candidates)) {
    echo json_encode(['ok' => false, 'error' => 'لا توجد أدوية في القاموس'], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$candidateText = '';
foreach ($candidates as $c) {
    $candidateText .= "{$c['id']} | {$c['name_ar']} | {$c['name_en']} | {$c['type']}\n";
}

$systemPrompt = <<<SYS
أنت مساعد متخصص في مطابقة أسماء الأدوية فقط.
اختر أفضل دواء مطابق من القائمة المعطاة فقط.
ممنوع اختراع دواء غير موجود بالقائمة.
يجب أن يكون best_id من القائمة أو null.
إذا كانت المطابقة ضعيفة اجعل best_id = null و confidence = 0.
أعد JSON فقط بدون أي نص إضافي.
SYS;

$userPrompt = <<<USR
النص الذي كتبه المستخدم: "{$q}"

الأدوية المتاحة (id | الاسم العربي | الاسم الإنجليزي | النوع):
{$candidateText}

أعد JSON فقط بالشكل:
{"best_id": <id أو null>, "confidence": <رقم من 0 إلى 1>, "reason_ar": "<سبب قصير>", "alternatives": [<id>, <id>]}
USR;

$payload = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt],
    ],
    'temperature' => 0.1,
    'max_tokens' => 200,
];

$ch = curl_init($chatUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 15,
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($err) {
    echo json_encode(['ok' => false, 'error' => 'خطأ اتصال: ' . $err], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

if ($code < 200 || $code >= 300 || !$res) {
    $errBody = json_decode((string)$res, true);
    echo json_encode([
        'ok' => false,
        'error' => 'خطأ Groq API: ' . ($errBody['error']['message'] ?? ('HTTP ' . $code)),
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$j = json_decode((string)$res, true);
$aiText = trim((string)($j['choices'][0]['message']['content'] ?? ''));
if (preg_match('/\{.*\}/s', $aiText, $matches)) {
    $aiText = $matches[0];
}

$aiResult = json_decode($aiText, true);
if (!$aiResult || !is_array($aiResult)) {
    echo json_encode([
        'ok' => false,
        'message' => 'ما لقيت مطابقة، اختار من القائمة',
        'candidates' => $candidates,
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$bestId = isset($aiResult['best_id']) && $aiResult['best_id'] !== null ? (int)$aiResult['best_id'] : null;
$confidence = isset($aiResult['confidence']) ? (float)$aiResult['confidence'] : 0.0;
$reasonAr = (string)($aiResult['reason_ar'] ?? '');
$altIds = is_array($aiResult['alternatives'] ?? null) ? $aiResult['alternatives'] : [];

if ($bestId !== null && !in_array($bestId, $candidateIds, true)) {
    $bestId = null;
    $confidence = 0.0;
}

$validAlts = [];
foreach ($altIds as $altId) {
    $alt = (int)$altId;
    if ($alt > 0 && $alt !== $bestId && in_array($alt, $candidateIds, true)) {
        $validAlts[] = $alt;
    }
}

$response = [
    'ok' => true,
    'best_id' => $bestId,
    'confidence' => $confidence,
    'reason_ar' => $reasonAr,
    'alternatives' => $validAlts,
];

if ($bestId !== null) {
    foreach ($candidates as $c) {
        if ((int)$c['id'] === $bestId) {
            $response['best_name_ar'] = $c['name_ar'];
            $response['best_name_en'] = $c['name_en'];
            break;
        }
    }
}

$altDetails = [];
foreach ($validAlts as $aid) {
    foreach ($candidates as $c) {
        if ((int)$c['id'] === $aid) {
            $altDetails[] = [
                'id' => $aid,
                'name_ar' => $c['name_ar'],
                'name_en' => $c['name_en'],
            ];
            break;
        }
    }
}
$response['alternative_details'] = $altDetails;

if ($bestId === null || $confidence < 0.3) {
    $response['message'] = 'ما لقيت مطابقة قوية، اختار من القائمة';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
