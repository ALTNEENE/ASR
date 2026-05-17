<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'غير مسجل دخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$user_id = (int)$_SESSION['user_id'];
$cache_key = 'measurement_assessment_' . $user_id;
$cache_time_key = $cache_key . '_time';
$cache_ttl = 6 * 3600;
$groq_config = require __DIR__ . '/../../config/groq_config.php';
$api_key = trim((string)($groq_config['api_key'] ?? ''));
$chat_url = (string)($groq_config['chat_url'] ?? 'https://api.groq.com/openai/v1/chat/completions');
$model_name = (string)($groq_config['model'] ?? 'llama-3.3-70b-versatile');

if (isset($_SESSION[$cache_key], $_SESSION[$cache_time_key]) && (time() - (int)$_SESSION[$cache_time_key] < $cache_ttl)) {
    $cached_raw = (string)$_SESSION[$cache_key];
    $cached = json_decode($cached_raw, true);
    $cached_source = is_array($cached) ? ($cached['source'] ?? '') : '';
    $has_mojibake = preg_match('/[طظ][^\\s]{1,6}/u', $cached_raw) === 1;
    // If cached result is fallback and Groq key exists, recompute instead of serving stale fallback.
    if (!($cached_source === 'fallback' && !empty($api_key)) && !$has_mojibake) {
        echo $cached_raw;
        exit;
    }
}

function normalizeToMgdl(float $value, string $unit): float {
    return $unit === 'mmol' ? ($value * 18.0) : $value;
}

function containsAny(string $text, array $terms): bool {
    $text = mb_strtolower($text, 'UTF-8');
    foreach ($terms as $term) {
        if (mb_strpos($text, mb_strtolower($term, 'UTF-8')) !== false) {
            return true;
        }
    }
    return false;
}

function classifyFlags(array $row): array {
    $value = (float)($row['reading_value'] ?? 0);
    $unit = ($row['reading_unit'] ?? 'mg') === 'mmol' ? 'mmol' : 'mg';
    $classification = trim((string)($row['classification'] ?? ''));

    $is_high = false;
    $is_low = false;

    if ($classification !== '') {
        $is_high = containsAny($classification, ['high', 'مرتفع', 'خطر']);
        $is_low = containsAny($classification, ['low', 'منخفض', 'هبوط']);
    }

    if (!$is_high && !$is_low) {
        if ($unit === 'mmol') {
            $is_low = $value < 3.9;
            $is_high = $value > 10.0;
        } else {
            $is_low = $value < 70;
            $is_high = $value > 180;
        }
    }

    return [$is_high, $is_low];
}

function computeSummary(array $rows): array {
    $count_total = count($rows);
    $values_mg = [];
    $count_high = 0;
    $count_low = 0;
    $stress_flag = false;

    foreach ($rows as $row) {
        $value = (float)($row['reading_value'] ?? 0);
        $unit = ($row['reading_unit'] ?? 'mg') === 'mmol' ? 'mmol' : 'mg';
        $value_mg = normalizeToMgdl($value, $unit);
        $values_mg[] = $value_mg;

        [$is_high, $is_low] = classifyFlags($row);
        if ($is_high) {
            $count_high++;
        }
        if ($is_low) {
            $count_low++;
        }

        $psych = (string)($row['psychological_status'] ?? '');
        if ($psych !== '' && containsAny($psych, ['stress', 'stressed', 'anxiety', 'توتر', 'قلق', 'ضغط'])) {
            $stress_flag = true;
        }
    }

    $avg = $count_total > 0 ? array_sum($values_mg) / $count_total : 0;
    $min = $count_total > 0 ? min($values_mg) : 0;
    $max = $count_total > 0 ? max($values_mg) : 0;

    $last = $rows[0] ?? null;
    $last_value = null;
    $last_time = null;
    if ($last) {
        $last_unit = ($last['reading_unit'] ?? 'mg') === 'mmol' ? 'mmol' : 'mg';
        $last_value = round(normalizeToMgdl((float)$last['reading_value'], $last_unit), 1);
        $last_time = $last['created_at'] ?: (($last['reading_date'] ?? '') . ' ' . ($last['reading_time'] ?? '00:00:00'));
    }

    $trend = 'stable';
    $recent = array_slice($values_mg, 0, 5);
    $previous = array_slice($values_mg, 5, 5);
    if (count($recent) >= 3 && count($previous) >= 3) {
        $avg_recent = array_sum($recent) / count($recent);
        $avg_prev = array_sum($previous) / count($previous);
        $delta = $avg_recent - $avg_prev;
        if ($delta > 10) {
            $trend = 'up';
        } elseif ($delta < -10) {
            $trend = 'down';
        }
    }

    return [
        'count_total' => $count_total,
        'avg_value' => round($avg, 1),
        'min_value' => round($min, 1),
        'max_value' => round($max, 1),
        'count_high' => $count_high,
        'count_low' => $count_low,
        'trend' => $trend,
        'last_value' => $last_value,
        'last_time' => $last_time,
        'stress_flag' => $stress_flag ? 'yes' : 'no'
    ];
}

function fallbackAssessment(array $summary, string $age_group): array {
    $total = max(1, (int)$summary['count_total']);
    $high_ratio = (int)$summary['count_high'] / $total;
    $low_ratio = (int)$summary['count_low'] / $total;

    $status = 'مستقر';
    if ((int)$summary['count_low'] >= 3 || $low_ratio >= 0.25) {
        $status = 'خطر مرتفع';
    } elseif ((int)$summary['count_high'] >= 4 || $high_ratio >= 0.35 || $summary['trend'] === 'up') {
        $status = 'يحتاج متابعة';
    }

    $insights = [
        'متوسط القراءات خلال الفترة: ' . $summary['avg_value'] . ' mg/dL',
        'أعلى/أدنى قراءة: ' . $summary['max_value'] . ' / ' . $summary['min_value'] . ' mg/dL'
    ];

    $next = [
        'الاستمرار على القياس المنتظم وتسجيل القراءات في أدوات القياس.',
        'الالتزام بنمط غذائي متوازن ونشاط بدني مناسب للحالة.'
    ];

    $red = [];
    if ((int)$summary['count_low'] >= 3) {
        $red[] = 'تكرار قراءات منخفضة خلال فترة قصيرة يتطلب متابعة طبية.';
    }
    if ((int)$summary['count_high'] >= 4) {
        $red[] = 'تكرار قراءات مرتفعة قد يشير إلى ضعف التحكم بالسكر.';
    }
    if ($summary['trend'] === 'up') {
        $red[] = 'الاتجاه العام للقراءات صاعد مقارنة بالفترة السابقة.';
    }
    if ($summary['stress_flag'] === 'yes') {
        $red[] = 'تم رصد مؤشرات ضغط/توتر نفسي قد تؤثر على القراءات.';
    }
    if (empty($red)) {
        $red[] = 'لا توجد إشارات خطر حادة من الملخص الحالي، مع ضرورة الاستمرار بالمتابعة.';
    }

    return [
        'status_level' => $status,
        'general_assessment' => 'هذا تقييم مبدئي تعليمي مبني على آخر القياسات المسجلة فقط، وليس تشخيصًا نهائيًا.',
        'measurement_insights' => $insights,
        'age_group_notes' => ['الفئة العمرية: ' . $age_group, 'يختلف تفسير القراءات حسب العمر والحالة العامة.'],
        'next_steps' => $next,
        'red_flags' => $red,
        'disclaimer' => 'هذا المحتوى للتوعية ولا يغني عن التقييم الطبي المباشر.'
    ];
}

function buildPrompts(array $profile, array $summary): array {
    $age = $profile['age'] !== null ? (string)$profile['age'] : 'غير محدد';
    $age_group = $profile['age_group'];
    $diabetes_type = $profile['diabetes_type'] ?: 'غير محدد';
    $treatment_type = $profile['treatment_type'] ?: 'غير محدد';

    $system_prompt = 'أنت مساعد توعوي متخصص في السكري فقط.
ممنوع طرح أي أسئلة على المستخدم.
ممنوع طلب معلومات إضافية.
ممنوع إعطاء تشخيص نهائي أو وصف جرعات أو تغيير علاج.
استخدم ملخص القياسات فقط لإنتاج تقييم مبدئي توعوي.
اكتب جميع النصوص بالعربية فقط (بدون أي جمل إنجليزية).';

    $user_prompt = "بيانات الحالة:\n" .
        "- الفئة العمرية: {$age_group}\n" .
        "- العمر: {$age}\n" .
        "- نوع السكري: {$diabetes_type}\n" .
        "- نوع العلاج: {$treatment_type}\n" .
        "- إجمالي القراءات: {$summary['count_total']}\n" .
        "- المتوسط: {$summary['avg_value']} mg/dL\n" .
        "- أدنى قراءة: {$summary['min_value']} mg/dL\n" .
        "- أعلى قراءة: {$summary['max_value']} mg/dL\n" .
        "- عدد القراءات المرتفعة: {$summary['count_high']}\n" .
        "- عدد القراءات المنخفضة: {$summary['count_low']}\n" .
        "- الاتجاه: {$summary['trend']}\n" .
        "- آخر قراءة: " . ($summary['last_value'] === null ? 'غير متاح' : $summary['last_value'] . ' mg/dL') . "\n" .
        "- وقت آخر قراءة: " . ($summary['last_time'] ?: 'غير متاح') . "\n" .
        "- مؤشر التوتر: {$summary['stress_flag']}\n\n" .
        "أعطِ تقييمًا مبدئيًا تلقائيًا اعتمادًا على القياسات فقط، وبالعربية فقط.\n" .
        "الرد يجب أن يكون JSON فقط وبهذه البنية حرفيًا:\n" .
        "{\n" .
        " \"status_level\": \"مستقر/يحتاج متابعة/خطر مرتفع\",\n" .
        " \"general_assessment\": \"فقرة قصيرة بالعربية\",\n" .
        " \"measurement_insights\": [\"نقطة\",\"نقطة\"],\n" .
        " \"age_group_notes\": [\"نقطة\",\"نقطة\"],\n" .
        " \"next_steps\": [\"خطوة عامة آمنة\",\"خطوة عامة آمنة\"],\n" .
        " \"red_flags\": [\"تنبيه مهم\",\"تنبيه مهم\"],\n" .
        " \"disclaimer\": \"هذا تقييم مبدئي للتوعية ولا يغني عن مراجعة الطبيب.\"\n" .
        "}";

    return [$system_prompt, $user_prompt];
}

function parseJsonPayload(string $text): ?array {
    $trimmed = trim($text);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $trimmed, $m)) {
        $decoded = json_decode($m[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function textHasEnglishWords(string $text): bool {
    // Allow short unit-like tokens (e.g. mg/dL), but block normal English words.
    return preg_match('/\b[A-Za-z]{3,}\b/u', $text) === 1;
}

function valueHasEnglishWords($value): bool {
    if (is_string($value)) {
        return textHasEnglishWords($value);
    }
    if (is_array($value)) {
        foreach ($value as $item) {
            if (valueHasEnglishWords($item)) {
                return true;
            }
        }
    }
    return false;
}

// Fetch the latest 3 readings
$sql = "SELECT reading_value, reading_unit, classification, reading_date, reading_time, created_at, psychological_status, note
        FROM glucose_readings
        WHERE user_id = ?
        ORDER BY COALESCE(created_at, TIMESTAMP(reading_date, COALESCE(reading_time, '00:00:00'))) DESC
        LIMIT 3";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$profile = [
    'age' => null,
    'age_group' => 'غير محدد',
    'diabetes_type' => null,
    'treatment_type' => null
];

$pstmt = $conn->prepare('SELECT age, diabetes_type, treatment_type FROM user_profile WHERE user_id = ? LIMIT 1');
$pstmt->bind_param('i', $user_id);
$pstmt->execute();
$profileRow = $pstmt->get_result()->fetch_assoc();
$pstmt->close();
$conn->close();

if ($profileRow) {
    $profile['age'] = is_numeric($profileRow['age']) ? (int)$profileRow['age'] : null;
    $profile['diabetes_type'] = $profileRow['diabetes_type'] ?? null;
    $profile['treatment_type'] = $profileRow['treatment_type'] ?? null;
}

if ($profile['age'] !== null) {
    if ($profile['age'] < 18) {
        $profile['age_group'] = 'الأطفال/المراهقون';
    } elseif ($profile['age'] <= 60) {
        $profile['age_group'] = 'البالغون';
    } else {
        $profile['age_group'] = 'كبار السن';
    }
}

if (count($rows) === 0) {
    $payload = [
        'ok' => true,
        'source' => 'no_data',
        'age_group' => $profile['age_group'],
        'assessment' => [
            'status_level' => 'يحتاج متابعة',
            'general_assessment' => 'لا توجد قياسات كافية بعد لإجراء تشخيص مبدئي. الرجاء إضافة أول قياس من صفحة أدوات القياس.',
            'measurement_insights' => ['لا توجد قراءات مسجلة خلال الفترة المطلوبة.'],
            'age_group_notes' => ['الفئة العمرية: ' . $profile['age_group']],
            'next_steps' => ['أدخل أول قياس سكر من صفحة أدوات القياس للبدء بالتحليل التلقائي.'],
            'red_flags' => ['غياب البيانات يمنع تقييم الاتجاهات أو مؤشرات الخطر.'],
            'disclaimer' => 'هذا المحتوى للتوعية ولا يغني عن التقييم الطبي المباشر.'
        ]
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cache_key] = $json;
    $_SESSION[$cache_time_key] = time();
    echo $json;
    exit;
}

$summary = computeSummary($rows);
$fallback = fallbackAssessment($summary, $profile['age_group']);
[$system_prompt, $user_prompt] = buildPrompts($profile, $summary);

if (!$api_key) {
    $payload = [
        'ok' => true,
        'source' => 'fallback',
        'age_group' => $profile['age_group'],
        'assessment' => $fallback
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cache_key] = $json;
    $_SESSION[$cache_time_key] = time();
    echo $json;
    exit;
}

$body = [
    'model' => $model_name,
    'messages' => [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt]
    ],
    'temperature' => 0.2,
    'max_tokens' => 700
];

$ch = curl_init($chat_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ],
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 30
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($err || $code < 200 || $code >= 300 || !$res) {
    $payload = [
        'ok' => true,
        'source' => 'fallback',
        'age_group' => $profile['age_group'],
        'assessment' => $fallback
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cache_key] = $json;
    $_SESSION[$cache_time_key] = time();
    echo $json;
    exit;
}

$llm_data = json_decode($res, true);
$content = $llm_data['choices'][0]['message']['content'] ?? '';
$parsed = parseJsonPayload((string)$content);

if (!$parsed || !isset($parsed['status_level'], $parsed['general_assessment'])) {
    $payload = [
        'ok' => true,
        'source' => 'fallback',
        'age_group' => $profile['age_group'],
        'assessment' => $fallback
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cache_key] = $json;
    $_SESSION[$cache_time_key] = time();
    echo $json;
    exit;
}

$assessment = [
    'status_level' => (string)$parsed['status_level'],
    'general_assessment' => (string)$parsed['general_assessment'],
    'measurement_insights' => array_values(array_map('strval', (array)($parsed['measurement_insights'] ?? []))),
    'age_group_notes' => array_values(array_map('strval', (array)($parsed['age_group_notes'] ?? []))),
    'next_steps' => array_values(array_map('strval', (array)($parsed['next_steps'] ?? []))),
    'red_flags' => array_values(array_map('strval', (array)($parsed['red_flags'] ?? []))),
    'disclaimer' => (string)($parsed['disclaimer'] ?? 'هذا المحتوى للتوعية ولا يغني عن التقييم الطبي المباشر.')
];

if (valueHasEnglishWords($assessment)) {
    $payload = [
        'ok' => true,
        'source' => 'fallback',
        'age_group' => $profile['age_group'],
        'assessment' => $fallback
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $_SESSION[$cache_key] = $json;
    $_SESSION[$cache_time_key] = time();
    echo $json;
    exit;
}

$payload = [
    'ok' => true,
    'source' => 'groq',
    'age_group' => $profile['age_group'],
    'assessment' => $assessment
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
$_SESSION[$cache_key] = $json;
$_SESSION[$cache_time_key] = time();
echo $json;
?>
