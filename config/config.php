<?php
declare(strict_types=1);

/**
 * Application configuration loader.
 * Reads config/.env (if present), falls back to sane defaults, and exposes
 * a global config() helper. Also bootstraps session hardening + error handling.
 */

define('BASE_PATH', dirname(__DIR__));

/* ---------- Tiny .env parser (no external deps) ---------- */
function parse_env(string $file): array
{
    $env = [];
    if (!is_readable($file)) {
        return $env;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // strip inline comment (only when not quoted)
        if (!str_starts_with($value, '"') && ($pos = strpos($value, ' #')) !== false) {
            $value = rtrim(substr($value, 0, $pos));
        }
        $value = trim($value, "\"'");
        $env[$key] = $value;
    }
    return $env;
}

$__env = parse_env(BASE_PATH . '/config/.env');

function env(string $key, mixed $default = null): mixed
{
    global $__env;
    if (array_key_exists($key, $__env)) {
        $v = $__env[$key];
        // normalize booleans
        if (in_array(strtolower($v), ['true', 'false'], true)) {
            return strtolower($v) === 'true';
        }
        return $v;
    }
    return getenv($key) !== false ? getenv($key) : $default;
}

$config = [
    'app' => [
        'name'     => env('APP_NAME', 'My Blog'),
        'env'      => env('APP_ENV', 'production'),
        'debug'    => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
        // Empty APP_URL = auto-detect from the request (recommended for production).
        'url'      => rtrim((string) env('APP_URL', ''), '/'),
        'timezone' => env('APP_TIMEZONE', 'UTC'),
    ],
    'db' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => (int) env('DB_PORT', 3306),
        'name'    => env('DB_NAME', 'blog'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'session' => [
        'name'     => env('SESSION_NAME', 'blogsid'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
    ],
    'mail' => [
        'from' => env('MAIL_FROM', 'noreply@example.com'),
    ],
    'paths' => [
        'base'    => BASE_PATH,
        'app'     => BASE_PATH . '/app',
        'views'   => BASE_PATH . '/views',
        'admin'   => BASE_PATH . '/admin',
        'public'  => BASE_PATH . '/public',
        'uploads' => BASE_PATH . '/public/uploads',
    ],
];

/** Global config accessor: config('db.host') */
function config(string $key = null, mixed $default = null): mixed
{
    static $c = null;
    if ($c === null) {
        global $config;
        $c = $config;
    }
    if ($key === null) {
        return $c;
    }
    $value = $c;
    foreach (explode('.', $key) as $seg) {
        if (!is_array($value) || !array_key_exists($seg, $value)) {
            return $default;
        }
        $value = $value[$seg];
    }
    return $value;
}

date_default_timezone_set((string) config('app.timezone'));

/* ---------- Error handling: never leak stack traces in production ---------- */
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (config('app.debug')) {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        http_response_code(500);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            include BASE_PATH . '/views/pages/500.php';
        } else {
            echo "Internal Server Error";
        }
    }
});

/* ---------- Session hardening ---------- */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $secure = str_starts_with((string) config('app.url'), 'https://')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
           || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name((string) config('session.name'));
    session_set_cookie_params([
        'lifetime' => (int) config('session.lifetime'),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

/* ---------- mbstring polyfills (shared hosts often lack mbstring) ---------- */
// Loaded FIRST so every mb_* fallback exists before any file uses them.
// Guarded with require_once semantics via the function_exists checks inside.
require_once BASE_PATH . '/app/helpers/mb_compat.php';

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/helpers/functions.php';

return $config;
