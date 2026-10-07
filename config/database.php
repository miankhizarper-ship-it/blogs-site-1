<?php
declare(strict_types=1);

/**
 * PDO Singleton — every query in the app goes through Database::conn()
 * with prepared statements only.
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function conn(): PDO
    {
        if (self::$instance === null) {
            $db  = config('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                $db['port'],
                $db['name'],
                $db['charset'] ?? 'utf8mb4'
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], $options);
                self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$instance->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
            } catch (PDOException $e) {
                // Never expose credentials/DSN to the browser.
                error_log('DB connection failed: ' . $e->getMessage());
                if (config('app.debug')) {
                    throw $e;
                }
                if (PHP_SAPI === 'cli') {
                    fwrite(STDERR, 'DB connection failed. Check config/.env credentials and host access.' . PHP_EOL);
                    exit(1);
                }
                http_response_code(503);
                exit('Service temporarily unavailable.');
            }
        }
        return self::$instance;
    }

    /** Convenience: run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::conn();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
