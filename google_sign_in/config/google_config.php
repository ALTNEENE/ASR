<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/bootstrap.php';

$googleClientClass = 'Google\\Client';
if (!class_exists($googleClientClass)) {
    throw new RuntimeException('Google API Client not found. Run composer install before deployment.');
}

$googleClientId = (string) app_env('GOOGLE_CLIENT_ID', '');
$googleClientSecret = (string) app_env('GOOGLE_CLIENT_SECRET', '');
$googleRedirectUri = (string) app_env('GOOGLE_REDIRECT_URI', '');

if ($googleRedirectUri === '') {
    $googleRedirectUri = app_url('google_sign_in/auth/google_callback.php');
}

$client = new $googleClientClass();

if (class_exists('GuzzleHttp\\Client')) {
    $verifySsl = filter_var(app_env('GOOGLE_HTTP_VERIFY', true), FILTER_VALIDATE_BOOL);
    $httpOptions = ['verify' => $verifySsl];
    if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
        $httpOptions['curl'] = [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
    }

    $client->setHttpClient(new \GuzzleHttp\Client($httpOptions));
}

$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);
$client->setRedirectUri($googleRedirectUri);
$client->setState(bin2hex(random_bytes(16)));

$client->addScope('openid');
$client->addScope('https://www.googleapis.com/auth/userinfo.email');
$client->addScope('https://www.googleapis.com/auth/userinfo.profile');
