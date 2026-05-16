<?php
declare(strict_types=1);

/**
 * Legacy constants kept for older report scripts.
 * Values come from environment variables in production.
 */
require_once __DIR__ . '/config/database.php';

$dbConfig = database_config();

define('DB_HOST', (string) $dbConfig['host']);
define('DB_USER', (string) $dbConfig['username']);
define('DB_PASS', (string) $dbConfig['password']);
define('DB_NAME', (string) $dbConfig['database']);

define('SMTP_HOST', app_env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) app_env('SMTP_PORT', 587));
define('SMTP_SECURE', app_env('SMTP_SECURE', 'tls'));
define('SMTP_USERNAME', app_env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', app_env('SMTP_PASSWORD', ''));

define('FROM_EMAIL', app_env('FROM_EMAIL', app_env('SMTP_USERNAME', '')));
define('FROM_NAME', app_env('FROM_NAME', 'A.S.R Diabetes System'));
define('DOCTOR_EMAIL', app_env('DEFAULT_DOCTOR_EMAIL', ''));
define('DOCTOR_NAME', app_env('DEFAULT_DOCTOR_NAME', ''));

define('ENABLE_EMAIL', filter_var(app_env('ENABLE_EMAIL', true), FILTER_VALIDATE_BOOL));
define('DEBUG_MODE', filter_var(app_env('APP_DEBUG', false), FILTER_VALIDATE_BOOL));
