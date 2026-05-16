<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

$geminiModel = (string) app_env('GEMINI_MODEL', 'gemini-2.5-flash');

define('GEMINI_API_KEY', app_env('GEMINI_API_KEY', ''));
define('GEMINI_MODEL', $geminiModel);
define(
    'GEMINI_API_URL',
    app_env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1/models/' . $geminiModel . ':generateContent')
);

define('GEMINI_SYSTEM_CONTEXT', app_env('GEMINI_SYSTEM_CONTEXT', 'You are a health-awareness assistant specialized in diabetes education. Provide clear, simple information and do not diagnose, prescribe medication, or replace professional medical advice.'));
define('GEMINI_MAX_REQUESTS_PER_MINUTE', (int) app_env('GEMINI_MAX_REQUESTS_PER_MINUTE', 60));
