<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config/gemini_config.php';
require_once __DIR__ . '/../../google_sign_in/config/db.php';

function json_response(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json === false ? '{"ok":false,"error":"JSON encode failed"}' : $json;
    exit;
}

function gather_context(mysqli $conn): array
{
    $ctx = [
        'age' => null,
        'gender' => null,
        'weight' => null,
        'activity_level' => null,
    ];

    // Optional activity level from client
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody) && $rawBody !== '') {
        $body = json_decode($rawBody, true);
        if (is_array($body) && isset($body['activity_level'])) {
            $ctx['activity_level'] = trim((string)$body['activity_level']);
        }
    }
    if (!$ctx['activity_level'] && isset($_POST['activity_level'])) {
        $ctx['activity_level'] = trim((string)$_POST['activity_level']);
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($userId > 0) {
        $stmt = $conn->prepare('SELECT age, gender, weight FROM patient_data WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $ctx['age'] = isset($row['age']) ? (int)$row['age'] : null;
                $ctx['gender'] = $row['gender'] ?? null;
                $ctx['weight'] = isset($row['weight']) ? (float)$row['weight'] : null;
            }
            $stmt->close();
        }
    } elseif (isset($_SESSION['temp_patient_data']) && is_array($_SESSION['temp_patient_data'])) {
        $tmp = $_SESSION['temp_patient_data'];
        $ctx['age'] = isset($tmp['age']) ? (int)$tmp['age'] : null;
        $ctx['gender'] = $tmp['gender'] ?? null;
        $ctx['weight'] = isset($tmp['weight']) ? (float)$tmp['weight'] : null;
    }

    return $ctx;
}

function cache_key(array $ctx): string
{
    return md5(json_encode([
        date('Y-m-d'),
        $ctx['age'],
        $ctx['gender'],
        $ctx['weight'],
        $ctx['activity_level'],
    ], JSON_UNESCAPED_UNICODE));
}

function call_gemini(string $prompt): array
{
    $apiKey = defined('GEMINI_API_KEY') ? trim((string)GEMINI_API_KEY) : '';
    if ($apiKey === '' || $apiKey === 'YOUR_API_KEY_HERE') {
        return ['ok' => false, 'error' => 'Gemini API key is missing'];
    }

    $model = defined('GEMINI_MODEL') ? trim((string)GEMINI_MODEL) : 'gemini-2.5-flash';
    if (str_starts_with($model, 'models/')) {
        $model = substr($model, 7);
    }
    if ($model === '') {
        $model = 'gemini-2.5-flash';
    }

    $modelEncoded = rawurlencode($model);
    $url = "https://generativelanguage.googleapis.com/v1/models/{$modelEncoded}:generateContent?key=" . rawurlencode($apiKey);

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.6,
            'maxOutputTokens' => 220,
        ],
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($body === false) {
        return ['ok' => false, 'error' => 'encode_failed'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($err !== '') {
        return ['ok' => false, 'error' => $err];
    }
    if (!is_string($response) || $response === '') {
        return ['ok' => false, 'error' => 'empty_response'];
    }

    $json = json_decode($response, true);
    $text = trim((string)($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    if ($text === '') {
        return ['ok' => false, 'error' => 'no_text'];
    }

    return ['ok' => true, 'text' => $text];
}

function build_prompt(array $ctx): string
{
    $gender = $ctx['gender'] ?: 'غير محدد';
    $age = $ctx['age'] ?: 'غير محدد';
    $weight = $ctx['weight'] ? ($ctx['weight'] . ' كجم') : 'غير محدد';
    $activity = $ctx['activity_level'] ?: 'متوسط';

    return <<<PROMPT
أنت خبير توعية لمرض السكري. اصنع مخرجات قصيرة جداً بالعربية الفصحى وبصياغة تشجيعية وآمنة.
المعطيات: العمر: {$age}، الجنس: {$gender}، الوزن: {$weight}، مستوى النشاط: {$activity}.

أرجع فقط JSON صالح بدون أي نص آخر، بالصيغة التالية:
{
  "tips": [
    {"title": "عنوان مختصر", "body": "سطر أو سطران عمليان"},
    {"title": "...", "body": "..."},
    {"title": "...", "body": "..."}
  ],
  "quiz": {
    "question": "سؤال اختيار واحد لتثبيت المعلومة",
    "options": ["خيار A", "خيار B", "خيار C"],
    "answer_index": 0
  },
  "disclaimer": "تذكير قصير باستشارة الطبيب"
}

القيود:
- اجعل النصائح وقائية عامة (لا تشخيص، لا جرعات دواء).
- اضبط المحتوى حسب الجنس والعمر والنشاط.
- الإجابات قصيرة وواضحة.
PROMPT;
}

// ------------ Main flow ------------
$ctx = gather_context($conn);
$cacheKey = cache_key($ctx);

if (!empty($_SESSION['daily_ai_tips']['key']) && $_SESSION['daily_ai_tips']['key'] === $cacheKey) {
    json_response([
        'ok' => true,
        'cached' => true,
        'data' => $_SESSION['daily_ai_tips']['data'],
    ]);
}

$prompt = build_prompt($ctx);
$ai = call_gemini($prompt);

$fallback = [
    'tips' => [
        ['title' => 'شرب الماء بانتظام', 'body' => 'حافظ على كوب ماء كل ساعة خلال النهار، وقلل المشروبات المحلاة.'],
        ['title' => '10 دقائق حركة', 'body' => 'بعد كل وجبة امشِ 10 دقائق لتحسين حساسية الإنسولين.'],
        ['title' => 'وجبة خفيفة ذكية', 'body' => 'اختر حفنة مكسرات أو زبادي قليل الدسم بدل الحلوى.'],
    ],
    'quiz' => [
        'question' => 'بعد وجبة متوسطة الحجم، ما المدة المقترحة للمشي؟',
        'options' => ['10 دقائق', '60 دقيقة', 'لا حاجة للحركة'],
        'answer_index' => 0,
    ],
    'disclaimer' => 'المعلومات للتوعية ولا تغني عن الاستشارة الطبية.',
];

if (!$ai['ok']) {
    json_response(['ok' => true, 'cached' => false, 'data' => $fallback, 'note' => $ai['error'] ?? '']);
}

$parsed = json_decode((string)$ai['text'], true);
if (!is_array($parsed) || empty($parsed['tips']) || empty($parsed['quiz']['options'])) {
    $parsed = $fallback;
}

$_SESSION['daily_ai_tips'] = [
    'key' => $cacheKey,
    'data' => $parsed,
    'ts' => time(),
];

json_response(['ok' => true, 'cached' => false, 'data' => $parsed]);
