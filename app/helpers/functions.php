<?php
declare(strict_types=1);

/**
 * Global helper functions used across public + admin views and controllers.
 * Loaded by config/config.php on every request.
 */

use Core\Csrf;
use Core\Settings;
use Helpers\Sanitizer;

/* ---------- Output escaping (XSS) ---------- */
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/** For rich HTML fields already sanitized on save. */
if (!function_exists('raw_html')) {
    function raw_html(?string $html): string
    {
        return Sanitizer::html((string) $html);
    }
}

/* ---------- URLs ---------- */

/**
 * Base URL of the site.
 * Uses APP_URL when configured; otherwise detects scheme + host from the
 * current request so the app works on any domain without config changes
 * (prevents hard-coded localhost redirects in production).
 */
if (!function_exists('base_url')) {
    function base_url(): string
    {
        $configured = trim((string) config('app.url', ''));
        if ($configured !== '' && stripos($configured, 'localhost') === false
            && !preg_match('#://(127\.0\.0\.1|0\.0\.0\.0)(:\d+)?$#i', $configured)) {
            return rtrim($configured, '/');
        }

        // Auto-detect from the live request
        if (php_sapi_name() === 'cli') {
            return 'http://localhost';
        }
        $https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $host    = preg_replace('/[^A-Za-z0-9.\-:_]/', '',
                    (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        // Strip a dev port (:8080 etc.) — never keep it on a real domain
        $hostClean = preg_replace('/:\d+$/', '', $host);
        return ($https ? 'https://' : 'http://') . $hostClean;
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $relativePath): ?string
    {
        return $relativePath ? url('uploads/' . ltrim($relativePath, '/')) : null;
    }
}

/**
 * Redirect helper. Relative paths (e.g. "/login") are resolved against the
 * detected base URL; absolute http(s) URLs are passed through untouched.
 */
if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('json_response')) {
    function json_response(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
}

/* ---------- CSRF shortcuts ---------- */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(): bool
    {
        return Csrf::check();
    }
}

/* ---------- Flash messages ---------- */
if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('get_flashes')) {
    function get_flashes(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}

/* ---------- Text utilities ---------- */
require_once __DIR__ . '/Slugger.php';   // slugify() depends on it
if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        return \Helpers\Slugger::make($text);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }
        $ts   = strtotime($datetime);
        $diff = time() - $ts;
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return floor($diff / 60) . ' min ago';
        if ($diff < 86400)  return floor($diff / 3600) . ' h ago';
        if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
        return date('M j, Y', $ts);
    }
}

/** Reading time in minutes (~225 wpm), rounded up, min 1. */
if (!function_exists('reading_time')) {
    function reading_time(?string $html): int
    {
        $words = str_word_count(strip_tags((string) $html));
        return max(1, (int) ceil($words / 225));
    }
}

if (!function_exists('excerpt')) {
    function excerpt(?string $html, int $len = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
    }
}

if (!function_exists('format_number')) {
    function format_number(int|float|null $n): string
    {
        $n = (int) $n;
        if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
        if ($n >= 1000)    return round($n / 1000, 1) . 'k';
        return (string) $n;
    }
}

/* ---------- Pagination ---------- */
/**
 * @return array{page:int, per:int, total:int, pages:int, offset:int, links:string[]}
 */
if (!function_exists('paginate')) {
    function paginate(int $totalItems, int $perPage, int $currentPage, string $baseUrl): array
    {
        $pages = max(1, (int) ceil($totalItems / max(1, $perPage)));
        $page  = min(max(1, $currentPage), $pages);
        // build simple windowed links with query string preserved
        $links = [];
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
                $sep = str_contains($baseUrl, '?') ? '&' : '?';
                $links[$i] = $baseUrl . ($i > 1 ? $sep . 'page=' . $i : '');
            }
        }
        return [
            'page'   => $page,
            'per'    => $perPage,
            'total'  => $totalItems,
            'pages'  => $pages,
            'offset' => ($page - 1) * $perPage,
            'links'  => $links,
        ];
    }
}

/* ---------- Settings shortcut ---------- */
if (!function_exists('setting')) {
    function setting(string $key, ?string $default = null): ?string
    {
        return Settings::get($key, $default);
    }
}

/* ---------- Current page canonical ---------- */
if (!function_exists('current_url')) {
    function current_url(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $qs  = $_GET ? '?' . http_build_query($_GET) : '';
        return url($uri . $qs);
    }
}

/**
 * Base URL for pagination links: current path + current query filters,
 * minus 'page' (paginate() appends it). Used by Blog::paginate().
 */
if (!function_exists('current_url_path_with_filters')) {
    function current_url_path_with_filters(): string
    {
        $uri   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $query = $_GET;
        unset($query['page']);
        $qs = $query ? '?' . http_build_query($query) : '';
        return url($uri . $qs);
    }
}

/* ---------- Client IP (single source of truth) ---------- */
if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        // Only trust proxy headers if you configure your server accordingly.
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}
