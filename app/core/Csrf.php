<?php
declare(strict_types=1);

namespace Core;

/**
 * Synchronizer-token CSRF protection.
 * Token lives in the session, is embedded via csrf_field() or sent as an
 * X-CSRF-Token header (fetch/AJAX). Verification is constant-time.
 */
class Csrf
{
    private const KEY = '_csrf_token';

    /** No-op: tokens are generated lazily by token(). Kept for bootstrapping clarity. */
    public static function init(): void
    {
        if (!isset($_SESSION[self::KEY])) {
            self::token(); // generate on first page load
        }
    }

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(?string $submitted = null): bool
    {
        $stored = $_SESSION[self::KEY] ?? '';
        $sent   = $submitted ?? ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        return $stored !== '' && is_string($sent) && hash_equals($stored, $sent);
    }

    /** Abort with 419 on failure (AJAX gets JSON, pages get a flash + back redirect). */
    public static function verify(): void
    {
        if (self::check()) {
            return;
        }
        http_response_code(419);
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Session expired. Please refresh the page and try again.'], 419);
        }
        flash('error', 'Your session expired or the form was tampered with. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /** Rotate after privilege changes (login). */
    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
        self::token();
    }
}
