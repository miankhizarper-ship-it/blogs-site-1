<?php
declare(strict_types=1);

namespace Core;

/**
 * CSRF protection: per-session synchronizer token, rotated on login.
 * All state-changing forms must include csrf_field() and pass csrf_check().
 */
class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::KEY . '" value="' . e(self::token()) . '">';
    }

    /** Accepts POST body token or X-CSRF-Token header (for fetch/AJAX). */
    public static function check(?string $submitted = null): bool
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $submitted ??= ($_POST[self::KEY] ?? $header);
        $token = $_SESSION[self::KEY] ?? '';
        return is_string($submitted) && $token !== '' && hash_equals($token, $submitted);
    }

    /** Abort with 419 on failure — call at top of every POST action. */
    public static function verify(): void
    {
        if (!self::check()) {
            http_response_code(419);
            if (is_ajax()) {
                json_response(['ok' => false, 'error' => 'Session expired. Refresh and try again.'], 419);
            }
            flash('error', 'Security token expired. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
        self::token();
    }
}
