<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/**
 * Contact form messages — stored for the admin inbox (Phase 7 adds the UI).
 */
class ContactMessage extends Model
{
    protected string $table = 'contact_messages';

    /** Insert a sanitized message. Returns new id. */
    public function log(array $data): int
    {
        return $this->create([
            'name'         => mb_substr($data['name'], 0, 100),
            'email'        => mb_substr($data['email'], 0, 190),
            'subject'      => mb_substr($data['subject'] ?? '', 0, 190),
            'message'      => mb_substr($data['message'], 0, 5000),
            'is_read'      => 0,
            'ip_address'   => $data['ip'] ?? null,
        ]);
    }

    public function unreadCount(): int
    {
        return $this->count(['is_read' => 0]);
    }
}
