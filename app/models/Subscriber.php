<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Newsletter subscribers (public subscribe form + admin export in Phase 7). */
class Subscriber extends Model
{
    protected string $table = 'subscribers';

    /** Idempotent subscribe — re-subscribing after unsubscribing reactivates. */
    public function subscribe(string $email): string
    {
        $this->run(
            'INSERT INTO subscribers (email, ip_address) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE is_active = 1',
            [mb_strtolower($email), client_ip()]
        );
        return 'You are subscribed — welcome aboard!';
    }

    public function isActive(string $email): bool
    {
        $row = $this->run(
            'SELECT is_active FROM subscribers WHERE email = ? LIMIT 1',
            [mb_strtolower($email)]
        )->fetch();
        return $row !== false && (int) $row['is_active'] === 1;
    }

    /** Count of active subscribers (dashboard stat card). */
    public function activeCount(): int
    {
        return (int) $this->run('SELECT COUNT(*) FROM subscribers WHERE is_active = 1')->fetchColumn();
    }
}
