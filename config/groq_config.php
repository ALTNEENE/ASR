<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

return [
    'api_key' => app_env('GROQ_API_KEY', ''),
    'model' => app_env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    'chat_url' => app_env('GROQ_CHAT_URL', 'https://api.groq.com/openai/v1/chat/completions'),
    'models_url' => app_env('GROQ_MODELS_URL', 'https://api.groq.com/openai/v1/models'),
];
