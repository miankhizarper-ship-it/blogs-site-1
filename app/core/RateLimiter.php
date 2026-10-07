<?php
declare(strict_types=1);

namespace Core;

use Database;

/**
 * DB-backed rate limiter + login throttling.
 * Limits are (action, ip) scoped with a rolling window.
 */
class RateLimiter
{
    /** In-memory fallback store for actions that don't need persistence. */
    private const SESSION_PREFIX = '_rl_';

    /**
     * Allow an action if the caller is under the limit.
     * @param string $action  e.g. 'comment' | 'contact' | 'subscribe'
     * @param int    $maxHits allowed within $windowSeconds
     */
    public static function allow(string $action, int $maxHits = 5, int $windowSeconds = 60): bool
    {
        $key = self::SESSION_PREFIX . $action;
        $now = time();
        $hits = $_SESSION[$key] ?? [];
        // keep only hits inside window
        $hits = array_values(array_filter($hits, fn($t) => $t > $now - $windowSeconds));
        if (count($hits) >= $maxHits) {
            return false;
        }
        $hits[] = $now;
        $_SESSION[$key] = $hits;
        return true;
    }

    /** Seconds until the window frees up (for user-friendly messages). */
    public static function retryAfter(string $action, int $windowSeconds = 60): int
    {
        $hits = $_SESSION[self::SESSION_PREFIX . $action] ?? [];
        if (!$hits) {
            return 0;
        }
        return max(0, (int) ($windowSeconds - (time() - min($hits))));
    }

    /* ---------------- Login throttling (persistent, per IP+email) ---------------- */

    public static function loginAttempts(string $email, string $ip, int $minutes = 15): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) AS c FROM login_attempts
             WHERE (ip_address = ? OR email = ?) AND success = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ip, mb_strtolower($email), $minutes]
        )->fetch()['c'];
    }

    public static function recordLoginAttempt(string $email, string $ip, bool $success): void
    {
        Database::run(
            'INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)',
            [mb_strtolower($email), $ip, $success ? 1 : 0]
        );
        if ($success) {
            // clear history for this pair on success
            Database::run(
                'DELETE FROM login_attempts WHERE email = ? AND ip_address = ?',
                [mb_strtolower($email), $ip]
            );
        }
    }

    /** Lock out after N failed attempts in window. */
    public static function loginLocked(string $email, string $ip, int $maxAttempts = 5, int $minutes = 15): bool
    {
        return self::loginAttempts($email, $ip, $minutes) >= $maxAttempts;
    }
}
