<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

$groqApiKey = trim((string)app_env('GROQ_API_KEY', ''));
$groqApiKeySource = 'GROQ_API_KEY';

if ($groqApiKey === '') {
    $groqApiKey = trim((string)app_env('GROK_API_KEY', ''));
    $groqApiKeySource = $groqApiKey === '' ? '' : 'GROK_API_KEY';
}

if (!function_exists('groq_api_key_status')) {
    function groq_api_key_status(string $apiKey): array
    {
        $apiKey = trim($apiKey);
        $lowerKey = strtolower($apiKey);
        $knownPlaceholders = [
            'gsk_your_groq_api_key_here',
            'gsk_your_real_key',
            'your_groq_api_key',
            'your_groq_api_key_here',
        ];

        if ($apiKey === '') {
            return [
                'ok' => false,
                'error' => 'Groq API key is missing. Add GROQ_API_KEY to .env locally and to Vercel/Railway environment variables.',
            ];
        }

        if (in_array($lowerKey, $knownPlaceholders, true) || str_contains($lowerKey, 'your_')) {
            return [
                'ok' => false,
                'error' => 'Groq API key is still a placeholder. Replace GROQ_API_KEY with a real key from Groq Cloud.',
            ];
        }

        return ['ok' => true, 'error' => ''];
    }
}

return [
    'api_key' => $groqApiKey,
    'api_key_source' => $groqApiKeySource,
    'api_key_status' => groq_api_key_status($groqApiKey),
    'model' => app_env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    'chat_url' => app_env('GROQ_CHAT_URL', 'https://api.groq.com/openai/v1/chat/completions'),
    'models_url' => app_env('GROQ_MODELS_URL', 'https://api.groq.com/openai/v1/models'),
];
