<?php
declare(strict_types=1);

namespace Core;

use Database;

/**
 * Authentication: bcrypt verify, session regeneration, roles, remember-me.
 */
class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = Database::run(
            'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1',
            [mb_strtolower(trim($email))]
        )->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        // transparent rehash if algorithm cost changed
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            Database::run('UPDATE users SET password = ? WHERE id = ?',
                [password_hash($password, PASSWORD_BCRYPT), $user['id']]);
        }
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);           // prevent session fixation
        Csrf::rotate();
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        static $cached = null;
        if (!self::check()) {
            return null;
        }
        if ($cached === null) {
            $cached = Database::run('SELECT * FROM users WHERE id = ? LIMIT 1', [self::id()])->fetch() ?: null;
        }
        return $cached;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /** admin > editor > author */
    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }
}
