<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

$groq_config = require __DIR__ . '/../../config/groq_config.php';
$apiKey = trim((string)($groq_config['api_key'] ?? ''));
$apiKeyStatus = $groq_config['api_key_status'] ?? ['ok' => $apiKey !== '', 'error' => ''];
$modelsUrl = (string)($groq_config['models_url'] ?? 'https://api.groq.com/openai/v1/models');

if (!($apiKeyStatus['ok'] ?? false)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => (string)($apiKeyStatus['error'] ?? 'Groq API key is not configured.')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PHP cURL extension is not enabled.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ch = curl_init($modelsUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($err) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$j = json_decode((string)$res, true);
$models = [];
foreach ((array)($j['data'] ?? []) as $modelRow) {
    if (is_array($modelRow) && isset($modelRow['id'])) {
        $models[] = $modelRow['id'];
    }
}

http_response_code($code);
echo json_encode(['ok' => ($code >= 200 && $code < 300), 'models' => $models], JSON_UNESCAPED_UNICODE);
