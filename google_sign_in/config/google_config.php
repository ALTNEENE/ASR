<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';

$googleClientId = (string) app_env('GOOGLE_CLIENT_ID', '');
$googleClientSecret = (string) app_env('GOOGLE_CLIENT_SECRET', '');
$googleRedirectUri = (string) app_env('GOOGLE_REDIRECT_URI', '');

if ($googleRedirectUri === '') {
    $googleRedirectUri = app_url('google_sign_in/auth/google_callback.php');
}

if (!function_exists('google_oauth_state')) {
    function google_oauth_state(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        return $state;
    }
}

if (!function_exists('google_auth_url')) {
    function google_auth_url(): string
    {
        global $googleClientId, $googleRedirectUri;

        if ($googleClientId === '' || $googleRedirectUri === '') {
            return '';
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $googleClientId,
            'redirect_uri' => $googleRedirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => google_oauth_state(),
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('google_http_json')) {
    function google_http_json(string $url, array $options = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ] + $options);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException('Google OAuth request failed: ' . $error);
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            throw new RuntimeException('Google OAuth returned an invalid response.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $json['error_description'] ?? $json['error'] ?? 'Google OAuth error';
            throw new RuntimeException((string) $message);
        }

        return $json;
    }
}

if (!function_exists('google_fetch_token')) {
    function google_fetch_token(string $code): array
    {
        global $googleClientId, $googleClientSecret, $googleRedirectUri;

        if ($googleClientId === '' || $googleClientSecret === '') {
            throw new RuntimeException('Google OAuth credentials are missing.');
        }

        return google_http_json('https://oauth2.googleapis.com/token', [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code,
                'client_id' => $googleClientId,
                'client_secret' => $googleClientSecret,
                'redirect_uri' => $googleRedirectUri,
                'grant_type' => 'authorization_code',
            ], '', '&', PHP_QUERY_RFC3986),
        ]);
    }
}

if (!function_exists('google_fetch_userinfo')) {
    function google_fetch_userinfo(string $accessToken): array
    {
        return google_http_json('https://www.googleapis.com/oauth2/v3/userinfo', [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
    }
}

if (!class_exists('DmProjectGoogleClient')) {
    final class DmProjectGoogleClient
    {
        public function createAuthUrl(): string
        {
            return google_auth_url();
        }
    }
}

$client = new DmProjectGoogleClient();
