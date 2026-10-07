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
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** For rich HTML fields already sanitized on save. */
function raw_html(?string $html): string
{
    return Sanitizer::html((string) $html);
}

/* ---------- URLs ---------- */
function url(string $path = '/'): string
{
    return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function upload_url(?string $relativePath): ?string
{
    return $relativePath ? url('uploads/' . ltrim($relativePath, '/')) : null;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/* ---------- CSRF shortcuts ---------- */
function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_check(): bool
{
    return Csrf::check();
}

/* ---------- Flash messages ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------- Text utilities ---------- */
function slugify(string $text): string
{
    return \Helpers\Slugger::make($text);
}

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

/** Reading time in minutes (~225 wpm), rounded up, min 1. */
function reading_time(?string $html): int
{
    $words = str_word_count(strip_tags((string) $html));
    return max(1, (int) ceil($words / 225));
}

function excerpt(?string $html, int $len = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

function format_number(int|float|null $n): string
{
    $n = (int) $n;
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000)    return round($n / 1000, 1) . 'k';
    return (string) $n;
}

/* ---------- Pagination ---------- */
/**
 * @return array{page:int, per:int, total:int, pages:int, offset:int, links:string[]}
 */
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

/* ---------- Settings shortcut ---------- */
function setting(string $key, ?string $default = null): ?string
{
    return Settings::get($key, $default);
}

/* ---------- Current page canonical ---------- */
function current_url(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $qs  = $_GET ? '?' . http_build_query($_GET) : '';
    return url($uri . $qs);
}

/* ---------- Client IP (single source of truth) ---------- */
function client_ip(): string
{
    // Only trust proxy headers if you configure your server accordingly.
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}
