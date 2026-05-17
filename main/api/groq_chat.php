<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function respond_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    echo $json === false ? '{"ok":false,"error":"JSON encode failed"}' : $json;
    exit;
}

function response_snippet(string $body): string
{
    $body = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body) ?? $body;
    $body = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $body) ?? $body;
    $body = strip_tags($body);
    $body = preg_replace('/\s+/', ' ', $body) ?? $body;

    return substr(trim($body), 0, 350);
}

try {
    $groqConfig = require __DIR__ . '/../../config/groq_config.php';
    $apiKey = trim((string)($groqConfig['api_key'] ?? ''));
    $apiKeyStatus = $groqConfig['api_key_status'] ?? ['ok' => $apiKey !== '', 'error' => ''];
    $apiKeySource = (string)($groqConfig['api_key_source'] ?? '');
    $chatUrl = (string)($groqConfig['chat_url'] ?? 'https://api.groq.com/openai/v1/chat/completions');
    $model = (string)($groqConfig['model'] ?? 'llama-3.3-70b-versatile');

    $rawInput = (string)file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = [];
    }

    $prompt = $input['prompt'] ?? $input['message'] ?? $_POST['prompt'] ?? $_POST['message'] ?? '';
    $prompt = trim((string)$prompt);

    if ($prompt === '') {
        respond_json(['ok' => false, 'error' => 'Missing prompt'], 400);
    }

    if (!($apiKeyStatus['ok'] ?? false)) {
        respond_json([
            'ok' => false,
            'error' => (string)($apiKeyStatus['error'] ?? 'Groq API key is not configured.'),
            'key_source' => $apiKeySource,
        ], 500);
    }

    if (!function_exists('curl_init')) {
        respond_json(['ok' => false, 'error' => 'PHP cURL extension is not enabled.'], 500);
    }

    $systemPrompt = <<<'SYS'
أنت مساعد ذكي متخصص في التوعية بمرض السكري. دورك تقديم معلومات عامة وواضحة وداعمة عن السكري فقط.

يمكنك الإجابة عن أعراض السكري، التغذية، الرياضة، قراءات الجلوكوز، الوقاية، المضاعفات، ومعلومات عامة عن الأدوية بدون تحديد جرعات.

لا تقدم تشخيصا طبيا ولا تحدد جرعات. إذا طلب المستخدم تشخيصا أو جرعة، قل له إنك لا تستطيع تحديد ذلك وأن عليه مراجعة الطبيب.

إذا كان السؤال خارج نطاق السكري أو الصحة المرتبطة به، قل فقط: أنا متخصص فقط في أسئلة مرض السكري، لا أستطيع الإجابة على هذا السؤال.

اكتب بالعربية الواضحة بدون Markdown. كن مختصرا ومطمئنا، واذكر دائما أن المعلومات للتوعية ولا تغني عن استشارة الطبيب عند وجود أعراض مقلقة.
SYS;

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.3,
        'max_tokens' => 500,
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        respond_json(['ok' => false, 'error' => 'Could not encode Groq request payload.'], 500);
    }

    $ch = curl_init($chatUrl);
    if ($ch === false) {
        respond_json(['ok' => false, 'error' => 'Could not initialize cURL.'], 500);
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($responseBody === false || $curlError !== '') {
        respond_json(['ok' => false, 'error' => 'Groq connection failed: ' . $curlError], 502);
    }

    $decoded = json_decode((string)$responseBody, true);
    if (!is_array($decoded)) {
        respond_json([
            'ok' => false,
            'error' => 'Groq returned a non-JSON response.',
            'http_code' => $httpCode,
            'body' => response_snippet((string)$responseBody),
        ], 502);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $status = ($httpCode >= 400 && $httpCode <= 599) ? $httpCode : 502;
        respond_json([
            'ok' => false,
            'error' => (string)($decoded['error']['message'] ?? ('Groq API error HTTP ' . $httpCode)),
            'http_code' => $httpCode,
        ], $status);
    }

    $text = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    if ($text === '') {
        respond_json(['ok' => false, 'error' => 'Groq response did not contain assistant text.'], 502);
    }

    respond_json(['ok' => true, 'text' => $text]);
} catch (Throwable $e) {
    respond_json(['ok' => false, 'error' => 'Assistant server error: ' . $e->getMessage()], 500);
}
