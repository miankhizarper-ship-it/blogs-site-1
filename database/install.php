<?php
declare(strict_types=1);

/**
 * Standalone installer: applies schema.sql + seed.sql and re-hashes the demo
 * passwords with real bcrypt so default logins actually work.
 *
 * CLI :  php database/install.php
 * Web :  open database/install.php once in the browser, then DELETE this file.
 */

if (PHP_SAPI !== 'cli') {
    // simple guard for web usage
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once dirname(__DIR__) . '/config/config.php';

function install_out(string $msg): void
{
    echo $msg . (PHP_SAPI === 'cli' ? PHP_EOL : "\n");
}

try {
    $pdo = Database::conn();

    foreach (['schema.sql', 'seed.sql'] as $file) {
        $path = __DIR__ . '/' . $file;
        if (!is_readable($path)) {
            throw new RuntimeException("Missing $file");
        }
        $sql  = file_get_contents($path);
        // split on semicolons at end of line (statements here are simple)
        $stmts = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));
        foreach ($stmts as $stmtSql) {
            if ($stmtSql === '' || str_starts_with($stmtSql, '--')) {
                continue;
            }
            $pdo->exec($stmtSql);
        }
        install_out("[OK] Imported $file (" . count($stmts) . " statements)");
    }

    // Regenerate real bcrypt hashes for demo accounts
    $demo = [
        'admin@site.com'  => 'Admin@12345',
        'editor@site.com' => 'Editor@12345',
        'author@site.com' => 'Author@12345',
    ];
    $upd = $pdo->prepare('UPDATE users SET password = ?, must_change_pw = 1 WHERE email = ?');
    foreach ($demo as $email => $plain) {
        $upd->execute([password_hash($plain, PASSWORD_BCRYPT), $email]);
    }
    install_out('[OK] Demo passwords hashed (admin@site.com / Admin@12345). CHANGE ON FIRST LOGIN.');
    install_out('[DONE] Installation complete. Delete database/install.php now.');
} catch (Throwable $e) {
    http_response_code(500);
    install_out('[ERROR] ' . (config('app.debug') ? $e->getMessage() : 'Installation failed. Check config/.env and error logs.'));
}
