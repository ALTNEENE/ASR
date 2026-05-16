<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/gemini_config.php';

/**
 * @return never
 */
function send_json(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = '{"success":false,"error":"JSON encoding failure"}';
    }

    echo $json;
    exit;
}

/**
 * @return array{success: bool, response?: string, error?: string}
 */
function tryGeminiAPI(string $prompt): array
{
    $apiKey = defined('GEMINI_API_KEY') ? trim((string) GEMINI_API_KEY) : '';
    if ($apiKey === '' || $apiKey === 'YOUR_API_KEY_HERE') {
        return ['success' => false, 'error' => 'Gemini API key is not configured'];
    }

    $configuredEndpoint = defined('GEMINI_API_URL') ? trim((string) GEMINI_API_URL) : '';
    $model = defined('GEMINI_MODEL') ? trim((string) GEMINI_MODEL) : 'gemini-2.5-flash';
    if (str_starts_with($model, 'models/')) {
        $model = substr($model, 7);
    }
    if ($model === '') {
        $model = 'gemini-2.5-flash';
    }

    $modelEncoded = rawurlencode($model);
    $endpoints = array_values(array_unique(array_filter([
        $configuredEndpoint,
        "https://generativelanguage.googleapis.com/v1/models/{$modelEncoded}:generateContent",
        "https://generativelanguage.googleapis.com/v1beta/models/{$modelEncoded}:generateContent",
        'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent',
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
    ])));

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 500,
        ],
    ];

    $postBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($postBody === false) {
        return ['success' => false, 'error' => 'Failed to encode Gemini request body'];
    }

    $lastError = 'All Gemini endpoints failed';

    foreach ($endpoints as $url) {
        $ch = curl_init($url . '?key=' . rawurlencode($apiKey));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $postBody,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            $lastError = 'cURL error: ' . $curlError;
            continue;
        }

        if (!is_string($response) || $response === '') {
            $lastError = 'Gemini returned an empty response';
            continue;
        }

        if ($httpCode !== 200) {
            $parsedError = json_decode($response, true);
            $apiMessage = (string) ($parsedError['error']['message'] ?? '');
            $lastError = $apiMessage !== '' ? $apiMessage : ('Gemini HTTP ' . $httpCode);
            continue;
        }

        $result = json_decode($response, true);
        $text = trim((string) ($result['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($text !== '') {
            return ['success' => true, 'response' => $text];
        }

        $lastError = 'Gemini returned no text candidate';
    }

    return ['success' => false, 'error' => $lastError];
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
if ($requestMethod !== 'POST') {
    send_json(['success' => false, 'error' => 'Invalid request method'], 405);
}

$userMessage = trim((string) ($_POST['message'] ?? ''));
if ($userMessage === '') {
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody) && $rawBody !== '') {
        $jsonInput = json_decode($rawBody, true);
        if (is_array($jsonInput)) {
            $userMessage = trim((string) ($jsonInput['message'] ?? $jsonInput['prompt'] ?? ''));
        }
    }
}

if ($userMessage === '') {
    send_json(['success' => false, 'error' => 'Please enter your question'], 422);
}

$systemContext = defined('GEMINI_SYSTEM_CONTEXT')
    ? (string) GEMINI_SYSTEM_CONTEXT
    : 'You are a diabetes awareness assistant.';

$fullPrompt = $systemContext . "\n\nUser question: " . $userMessage;
$geminiResponse = tryGeminiAPI($fullPrompt);

if (!empty($geminiResponse['success'])) {
    send_json([
        'success' => true,
        'response' => (string) ($geminiResponse['response'] ?? ''),
        'source' => 'gemini_ai',
    ]);
}

send_json([
    'success' => false,
    'error' => 'Gemini service is currently unavailable',
    'details' => (string) ($geminiResponse['error'] ?? ''),
], 502);
