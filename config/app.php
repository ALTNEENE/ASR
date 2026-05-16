<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        $configured = app_env('APP_BASE_PATH', null);
        if (is_string($configured) && $configured !== '') {
            return '/' . trim($configured, '/');
        }

        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '' && preg_match('#^(/dmproject)(?:/|$)#i', $script, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $base = rtrim(app_base_path(), '/');
        $path = '/' . ltrim($path, '/');

        return ($base === '' ? '' : $base) . $path;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $configured = rtrim((string) app_env('APP_URL', ''), '/');
        if ($configured !== '') {
            return $configured . app_path($path);
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . app_path($path);
    }
}

if (!function_exists('app_redirect')) {
    function app_redirect(string $path): void
    {
        header('Location: ' . app_path($path));
        exit;
    }
}
