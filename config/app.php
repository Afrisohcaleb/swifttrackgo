<?php
declare(strict_types=1);

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^([\'"]).*\1$/s', $value)) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '' && !array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

defined('APP_NAME') || define('APP_NAME', $_ENV['APP_NAME'] ?? 'SwiftTrackGo');
defined('APP_URL') || define('APP_URL', rtrim($_ENV['APP_URL'] ?? 'http://localhost/swifttrackgo', '/'));
defined('APP_ENV') || define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', max(300, (int)($_ENV['SESSION_LIFETIME'] ?? 3600)));
defined('UPLOAD_MAX_SIZE') || define('UPLOAD_MAX_SIZE', max(1, (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 2097152)));
defined('UPLOAD_PATH') || define('UPLOAD_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
defined('ASSETS_PATH') || define('ASSETS_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'assets');
defined('STORAGE_PATH') || define('STORAGE_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'storage');

defined('SHIPMENT_STATUSES') || define('SHIPMENT_STATUSES', [
    'Shipment Created', 'Processing', 'Picked Up', 'In Transit', 'Arrived at Facility',
    'Customs Clearance', 'Out for Delivery', 'Delivered', 'Delayed',
    'Delivery Attempted', 'Exception', 'Cancelled',
]);

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => parse_url(APP_URL, PHP_URL_PATH) ?: '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
