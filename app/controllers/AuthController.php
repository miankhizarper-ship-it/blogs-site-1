<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\RateLimiter;
use Core\Settings;
use Database;
use Helpers\Validator;
use Models\ActivityLog;

/**
 * Admin authentication: login/logout, throttling, remember-me,
 * forgot/reset password, forced first-login password change.
 */
class AuthController extends Controller
{
    private const REMEMBER_DAYS = 30;

    /* ------------------------------------------------------------------
       GET|POST /login
       ------------------------------------------------------------------ */
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('/admin');
        }

        // Remember-me auto-login (secure cookie only — no credentials in URL)
        if ($this->tryRememberLogin()) {
            $this->redirect('/admin');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            Csrf::verify();

            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $ip    = client_ip();

            if (!RateLimiter::allow('login', 10, 900)) {
                flash('error', 'Too many attempts. Please wait a few minutes.');
                $this->redirect('/login');
            }
            if (RateLimiter::loginLocked($email, $ip)) {
                flash('error', 'Account temporarily locked after too many failed attempts. Try again in 15 minutes.');
                $this->redirect('/login');
            }

            $v = new Validator($_POST);
            $v->required('email', 'Email')->email('email', 'Email')
              ->required('password', 'Password');
            if ($v->fails()) {
                flash('error', (string) $v->firstError());
                $this->redirect('/login');
            }

            if (Auth::attempt($email, (string) $_POST['password'])) {
                RateLimiter::recordLoginAttempt($email, $ip, true);
                ActivityLog::record('login', 'Signed in');

                if (!empty($_POST['remember'])) {
                    $this->issueRememberToken($email);
                }
                if ((int) (Auth::user()['must_change_pw'] ?? 0) === 1) {
                    flash('info', 'This is a default account — please choose a new password now.');
                    $this->redirect('/change-password');
                }
                $this->redirect('/admin');
            }

            RateLimiter::recordLoginAttempt($email, $ip, false);
            flash('error', 'Invalid email or password.');
            $this->redirect('/login');
        }

        // GET — render the standalone login page
        \Helpers\Seo::noindex();
        $this->view('pages/login', ['bodyClass' => 'page-auth'], 'plain');
    }

    /* ------------------------------------------------------------------
       POST /logout
       ------------------------------------------------------------------ */
    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            Csrf::verify();
            $u = Auth::user();
            if ($u) {
                Database::run('UPDATE users SET remember_token = NULL WHERE id = ?', [$u['id']]);
                $this->clearRememberCookie();
                ActivityLog::record('logout', 'Signed out');
            }
            Auth::logout();
        }
        flash('success', 'You have been signed out.');
        $this->redirect('/login');
    }

    /* ------------------------------------------------------------------
       GET|POST /forgot-password
       ------------------------------------------------------------------ */
    public function forgot(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            Csrf::verify();
            if (!RateLimiter::allow('forgot', 3, 3600)) {
                flash('error', 'Too many reset requests. Please try again later.');
                $this->redirect('/forgot-password');
            }

            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $user  = Database::run(
                'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1', [$email]
            )->fetch();

            // Always show the same message (no account enumeration).
            flash('success', 'If that email exists, a password-reset link has been sent. Check your inbox.');

            if ($user) {
                $token = bin2hex(random_bytes(32));
                Database::run(
                    'UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?',
                    [hash('sha256', $token), $user['id']]
                );
                $link = url('/reset-password?email=' . rawurlencode($email) . '&token=' . $token);
                $sent = @mail(
                    $email,
                    Settings::get('site_name', 'My Blog') . ' — password reset',
                    "Hello {$user['name']},\n\nUse this link within 1 hour to reset your password:\n{$link}\n\n"
                  . "If you did not request this, you can safely ignore this email.\n",
                    'From: ' . Settings::get('contact_email', 'noreply@localhost')
                );
                if (!$sent && Settings::bool('debug_show_reset_link')) {
                    flash('info', 'Mail is not configured on this server. Reset link: ' . $link);
                }
            }
            $this->redirect('/login');
        }

        \Helpers\Seo::noindex();
        $this->view('pages/forgot', ['bodyClass' => 'page-auth'], 'plain');
    }

    /* ------------------------------------------------------------------
       GET|POST /reset-password?email=&token=
       ------------------------------------------------------------------ */
    public function reset(): void
    {
        $email = mb_strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

        $user = $email !== '' && $token !== '' ? Database::run(
            'SELECT * FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW() LIMIT 1',
            [$email, hash('sha256', $token)]
        )->fetch() : null;

        if (!$user) {
            flash('error', 'That reset link is invalid or has expired. Request a new one.');
            $this->redirect('/forgot-password');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            Csrf::verify();
            $v = new Validator($_POST);
            $v->required('password', 'Password')->strongPassword('password')
              ->matches('password_confirm', 'password', 'Password confirmation');
            if ($v->fails()) {
                flash('error', (string) $v->firstError());
                $this->redirect("/reset-password?email=" . rawurlencode($email) . "&token=" . rawurlencode($token));
            }

            Database::run(
                'UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, must_change_pw = 0 WHERE id = ?',
                [password_hash((string) $_POST['password'], PASSWORD_BCRYPT), $user['id']]
            );
            ActivityLog::record('password_reset', "Password reset for {$email}");
            flash('success', 'Password updated. You can sign in now.');
            $this->redirect('/login');
        }

        \Helpers\Seo::noindex();
        $this->view('pages/reset', [
            'bodyClass' => 'page-auth',
            'email'     => $email,
            'token'     => $token,
        ], 'plain');
    }

    /* ------------------------------------------------------------------
       GET|POST /change-password (any logged-in user)
       ------------------------------------------------------------------ */
    public function changePassword(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect('/login');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            Csrf::verify();
            $v = new Validator($_POST);
            $v->required('current', 'Current password')
              ->required('password', 'New password')->strongPassword('password')
              ->matches('password_confirm', 'password', 'Password confirmation');
            if ($v->fails()) {
                flash('error', (string) $v->firstError());
                $this->redirect('/change-password');
            }
            if (!password_verify((string) $_POST['current'], $user['password'])) {
                flash('error', 'Your current password is incorrect.');
                $this->redirect('/change-password');
            }

            Database::run(
                'UPDATE users SET password = ?, must_change_pw = 0 WHERE id = ?',
                [password_hash((string) $_POST['password'], PASSWORD_BCRYPT), $user['id']]
            );
            Auth::logout(); // fresh session with the new credentials
            flash('success', 'Password changed. Please sign in again.');
            $this->redirect('/login');
        }

        \Helpers\Seo::noindex();
        $this->view('pages/change-password', [
            'bodyClass' => 'page-auth',
            'force'     => (int) $user['must_change_pw'] === 1,
        ], 'plain');
    }

    /* ---------------- Remember-me internals ---------------- */

    private function issueRememberLoginCookie(string $value): void
    {
        $secure = str_starts_with((string) config('app.url'), 'https://');
        setcookie('blog_remember', $value, [
            'expires'  => time() + self::REMEMBER_DAYS * 86400,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function issueRememberToken(string $email): void
    {
        $selector = bin2hex(random_bytes(6));
        $validator = bin2hex(random_bytes(32));
        Database::run(
            'UPDATE users SET remember_token = ? WHERE email = ?',
            [$selector . ':' . hash('sha256', $validator), mb_strtolower($email)]
        );
        $this->issueRememberLoginCookie($selector . ':' . $validator);
    }

    private function clearRememberCookie(): void
    {
        setcookie('blog_remember', '', ['expires' => time() - 3600, 'path' => '/']);
    }

    private function tryRememberLogin(): bool
    {
        $raw = $_COOKIE['blog_remember'] ?? '';
        if (!is_string($raw) || substr_count($raw, ':') !== 1) {
            return false;
        }
        [$selector, $validator] = explode(':', $raw, 2);
        if (!preg_match('/^[a-f0-9]{12}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            return false;
        }

        $row = Database::run(
            'SELECT * FROM users WHERE remember_token LIKE ? AND is_active = 1 LIMIT 1',
            [$selector . ':%']
        )->fetch();

        if (!$row || !isset($row['remember_token'])) {
            $this->clearRememberCookie();
            return false;
        }
        [, $storedHash] = explode(':', $row['remember_token'], 2);
        if (!hash_equals($storedHash, hash('sha256', $validator))) {
            // possible theft — invalidate and force normal login
            Database::run('UPDATE users SET remember_token = NULL WHERE id = ?', [$row['id']]);
            $this->clearRememberCookie();
            return false;
        }

        Auth::login($row);
        $this->issueRememberToken($row['email']); // rotate validator each use
        return true;
    }
}
