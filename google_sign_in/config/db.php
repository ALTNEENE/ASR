<?php
// Shared mysqli connection used by the legacy PHP pages.
require_once __DIR__ . '/../../config/database.php';

try {
    $conn = database_mysqli();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    $message = 'Database connection failed. Check the deployment environment variables.';
    if (strpos($e->getMessage(), 'railway.internal') !== false || strpos($e->getMessage(), 'getaddrinfo failed') !== false) {
        $message = 'Database connection failed. Railway internal MySQL hosts only work inside Railway. Use the Railway public TCP proxy host and port for localhost or Vercel.';
    } elseif (function_exists('app_env') && filter_var(app_env('APP_DEBUG', false), FILTER_VALIDATE_BOOL)) {
        $message .= ' ' . $e->getMessage();
    }
    die($message);
}
