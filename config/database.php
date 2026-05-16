<?php
declare(strict_types=1);

if (!function_exists('load_local_env_file')) {
    function load_local_env_file(?string $path = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $loaded = true;
        $path = $path ?: dirname(__DIR__) . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $equalsPos = strpos($line, '=');
            if ($equalsPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equalsPos));
            $value = trim(substr($line, $equalsPos + 1));
            if ($key === '' || !preg_match('/^[A-Z0-9_]+$/i', $key)) {
                continue;
            }

            $quote = $value[0] ?? '';
            if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}

load_local_env_file();

if (!function_exists('app_env')) {
    function app_env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('database_config')) {
    function database_config(): array
    {
        $url = app_env('DATABASE_URL') ?: app_env('MYSQL_URL');
        $config = [
            'host' => app_env('DB_HOST', app_env('MYSQLHOST', '127.0.0.1')),
            'port' => (int) app_env('DB_PORT', app_env('MYSQLPORT', 3306)),
            'database' => app_env('DB_DATABASE', app_env('DB_NAME', app_env('MYSQLDATABASE', 'project_db'))),
            'username' => app_env('DB_USERNAME', app_env('DB_USER', app_env('MYSQLUSER', 'root'))),
            'password' => app_env('DB_PASSWORD', app_env('DB_PASS', app_env('MYSQLPASSWORD', ''))),
            'charset' => app_env('DB_CHARSET', 'utf8mb4'),
        ];

        if (is_string($url) && trim($url) !== '') {
            $parts = parse_url($url);
            if ($parts !== false) {
                $config['host'] = $parts['host'] ?? $config['host'];
                $config['port'] = isset($parts['port']) ? (int) $parts['port'] : $config['port'];
                $config['username'] = isset($parts['user']) ? rawurldecode($parts['user']) : $config['username'];
                $config['password'] = isset($parts['pass']) ? rawurldecode($parts['pass']) : $config['password'];

                if (!empty($parts['path']) && $parts['path'] !== '/') {
                    $config['database'] = ltrim($parts['path'], '/');
                }

                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $query);
                    if (!empty($query['charset'])) {
                        $config['charset'] = (string) $query['charset'];
                    }
                }
            }
        }

        return $config;
    }
}

if (!function_exists('database_mysqli')) {
    function database_mysqli(): mysqli
    {
        $config = database_config();

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli(
            (string) $config['host'],
            (string) $config['username'],
            (string) $config['password'],
            (string) $config['database'],
            (int) $config['port']
        );

        if ($conn->connect_errno) {
            throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
        }

        if (!$conn->set_charset((string) $config['charset'])) {
            throw new RuntimeException('Failed to set database charset: ' . $conn->error);
        }

        return $conn;
    }
}

if (!function_exists('database_pdo')) {
    function database_pdo(): PDO
    {
        $config = database_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        return new PDO(
            $dsn,
            (string) $config['username'],
            (string) $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
