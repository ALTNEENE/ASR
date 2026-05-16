<?php
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    http_response_code(500);
    exit('Application root not found');
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode($uriPath ?: '/');
$path = str_replace('\\', '/', $path);

if ($path === '/' || $path === '') {
    $path = '/index.php';
}

$relative = trim($path, '/');
if ($relative === 'api/index.php') {
    $relative = 'index.php';
}

$segments = [];
foreach (explode('/', $relative) as $segment) {
    if ($segment === '' || $segment === '.') {
        continue;
    }

    if ($segment === '..') {
        http_response_code(404);
        exit('Not found');
    }

    $segments[] = $segment;
}

$relative = implode('/', $segments);
$protectedPrefixes = [
    '.',
    'config/',
    'db/',
    'inc/',
    'includes/',
    'scripts/',
    'src/',
    'vendor/',
    'google_sign_in/config/',
    'google_sign_in/vendor/',
];
$protectedFiles = [
    '.env',
    '.env.example',
    '.gitignore',
    '.vercelignore',
    'config.php',
    'composer.json',
    'composer.lock',
    'database_setup.sql',
    'DEPLOYMENT.md',
    'migration_add_reading_status_columns.sql',
    'migration_glucose_standards.sql',
    'vercel.json',
];

if (in_array($relative, $protectedFiles, true)) {
    http_response_code(404);
    exit('Not found');
}

$relativeForMatch = $relative . (str_ends_with($relative, '/') ? '' : '/');
foreach ($protectedPrefixes as $prefix) {
    if ($prefix === '.' && str_starts_with($relative, '.')) {
        http_response_code(404);
        exit('Not found');
    }

    if ($prefix !== '.' && str_starts_with($relativeForMatch, $prefix)) {
        http_response_code(404);
        exit('Not found');
    }
}

$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (is_dir($target)) {
    $target = rtrim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
}

if (!is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === '') {
    $phpTarget = $target . '.php';
    if (is_file($phpTarget)) {
        $target = $phpTarget;
        $relative .= '.php';
    }
}

$realTarget = realpath($target);
if ($realTarget === false || !str_starts_with($realTarget, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Not found');
}

$extension = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
if ($extension === 'php') {
    $_SERVER['SCRIPT_FILENAME'] = $realTarget;
    $_SERVER['SCRIPT_NAME'] = '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($realTarget, strlen($root) + 1));
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

    chdir(dirname($realTarget));
    require $realTarget;
    return;
}

$mimeTypes = [
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'pdf' => 'application/pdf',
    'html' => 'text/html; charset=UTF-8',
    'txt' => 'text/plain; charset=UTF-8',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
];

if (!isset($mimeTypes[$extension])) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($realTarget));
readfile($realTarget);
