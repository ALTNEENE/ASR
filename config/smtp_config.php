<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

return [
    'smtp_host' => app_env('SMTP_HOST', 'smtp.gmail.com'),
    'smtp_port' => (int) app_env('SMTP_PORT', 587),
    'smtp_secure' => app_env('SMTP_SECURE', 'tls'),
    'smtp_username' => app_env('SMTP_USERNAME', ''),
    'smtp_password' => app_env('SMTP_PASSWORD', ''),
    'from_email' => app_env('FROM_EMAIL', app_env('SMTP_USERNAME', '')),
    'from_name' => app_env('FROM_NAME', 'A.S.R Diabetes Monitoring'),
    'default_doctor_email' => app_env('DEFAULT_DOCTOR_EMAIL', ''),
    'default_doctor_name' => app_env('DEFAULT_DOCTOR_NAME', ''),
    'enable_debug' => filter_var(app_env('SMTP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'charset' => app_env('SMTP_CHARSET', 'UTF-8'),
];
