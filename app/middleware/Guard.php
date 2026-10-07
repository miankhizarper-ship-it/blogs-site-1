<?php
declare(strict_types=1);

namespace Middleware;

use Core\Auth;
use Core\Csrf;
use Core\RateLimiter;

/**
 * Route middleware callbacks. Return false to halt dispatch
 * (they already sent the response/redirect).
 */
class Guard
{
    /** Require any logged-in staff user. */
    public static function auth(array $args = []): bool
    {
        if (!Auth::check()) {
            flash('error', 'Please sign in to continue.');
            redirect("/login");
        }
        return true;
    }

    /** Restrict to specific roles: Middleware\Guard::role('admin','editor') */
    public static function role(string ...$roles): callable
    {
        return function (array $args = []) use ($roles): bool {
            if (!Auth::check()) {
                redirect("/login");
            }
            if (!Auth::hasRole(...$roles)) {
                http_response_code(403);
                echo '403 — You do not have permission for this area.';
                exit;
            }
            return true;
        };
    }

    /** CSRF gate for POST routes. */
    public static function csrf(array $args = []): bool
    {
        Csrf::verify();
        return true;
    }

    /** Generic rate limiter factory, e.g. for comments/contact. */
    public static function throttle(string $action, int $max = 5, int $window = 60): callable
    {
        return function (array $args = []) use ($action, $max, $window): bool {
            if (!RateLimiter::allow($action, $max, $window)) {
                $wait = RateLimiter::retryAfter($action, $window);
                if (is_ajax()) {
                    json_response(['ok' => false, 'error' => "Too many requests. Wait {$wait}s."], 429);
                }
                flash('error', "You are doing that too fast. Please wait {$wait} seconds.");
                redirect($_SERVER['HTTP_REFERER'] ?? '/');
            }
            return true;
        };
    }
}
