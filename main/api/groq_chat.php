<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$groq_config = require __DIR__ . '/../../config/groq_config.php';
$GROQ_API_KEY = trim((string)($groq_config['api_key'] ?? ''));
$GROQ_CHAT_URL = (string)($groq_config['chat_url'] ?? 'https://api.groq.com/openai/v1/chat/completions');
$GROQ_MODEL = (string)($groq_config['model'] ?? 'llama-3.3-70b-versatile');

// اقرأ JSON أو FORM
$input  = json_decode(file_get_contents("php://input"), true) ?: [];
$prompt = $input["prompt"] ?? $input["message"] ?? $_POST["prompt"] ?? $_POST["message"] ?? "";
$prompt = trim($prompt);

if (!$prompt) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Missing prompt"], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($GROQ_API_KEY === '') {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "Groq API key is missing. Add GROQ_API_KEY to .env locally or to Vercel environment variables."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!function_exists('curl_init')) {
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "PHP cURL extension is not enabled."
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* السياق النظامي SYSTEM_PROMPT */
$SYSTEM_PROMPT = <<<SYS
أنت مساعد ذكي متخصص في التوعية بمرض السكري. دورك هو تقديم المعرفه، الدعم، والطمأنينة للمستخدمين.

ما يمكنك الإجابة عليه:
- أسئلة عن مرض السكري (أنواعه، أسبابه، أعراضه، مضاعفاته)
- التغذية وتأثير الأطعمة والمشروبات على مستوى السكر في الدم
- الأدوية المستخدمة في علاج السكري (معلومات عامة فقط، بدون جرعات)
- نمط الحياة: الرياضة، الوزن، النوم، الضغط النفسي وعلاقتها بالسكري
- قراءات الجلوكوز وتفسيرها
- الوقاية من السكري ومضاعفاته

ما لا يمكنك الإجابة عليه (ارفض بحزم):
- أسئلة السياسة، الأخبار، الرياضة (كرة القدم وغيرها)، الترفيه، الموسيقى
- أسئلة البرمجة، التقنية، الرياضيات
- أسئلة التاريخ، الجغرافيا، الثقافة العامة غير الطبية
- أي سؤال لا علاقة له بالسكري أو الصحة المرتبطة به

قواعد الرد:
1. إذا سُئلت عن طعام أو شراب: أجب من منظور تأثيره على السكر في الدم فقط.
2. إذا طلب المستخدم جرعة أو تشخيص: قل "لا أستطيع تحديد الجرعات أو التشخيص، يرجى مراجعة الطبيب."
3. إذا كان السؤال خارج نطاق السكري تماماً: قل فقط "أنا متخصص فقط في أسئلة مرض السكري، لا أستطيع الإجابة على هذا السؤال."
4. لا تستخدم رموز Markdown مثل (*, **, #, -). اكتب نصاً عربياً عادياً ومباشراً.
5. ردودك باللغة العربية دائماً ما لم يكتب المستخدم بالإنجليزية.

تذكر: أنت لست طبيباً، بل مرشد توعوي متخصص في السكري فقط. كن دائماً داعماً ومطمئناً للمستخدم، خاصة عندما يظهر عليه القلق أو عدم التأكد من حالته الصحية.
SYS;

/* ✅ (2) هنا تستخدمه داخل messages */
$payload = [
  "model" => $GROQ_MODEL,
  "messages" => [
    ["role" => "system", "content" => $SYSTEM_PROMPT],
    ["role" => "user", "content" => $prompt]
  ],
  "temperature" => 0.3,
  "max_tokens" => 500
];

$ch = curl_init($GROQ_CHAT_URL);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "Authorization: Bearer " . $GROQ_API_KEY
  ],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_TIMEOUT => 30
]);

$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"cURL: $err"], JSON_UNESCAPED_UNICODE);
  exit;
}

$j = json_decode($res, true);
$text = $j["choices"][0]["message"]["content"] ?? null;

if ($code < 200 || $code >= 300) {
  http_response_code($code);
  echo json_encode([
    "ok" => false,
    "error" => $j["error"]["message"] ?? "Groq API error",
    "raw" => $j
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

echo json_encode(["ok"=>true, "text"=>$text], JSON_UNESCAPED_UNICODE);
