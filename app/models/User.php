<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Users model — staff accounts with roles. */
class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function paginate(int $page, int $perPage, string $q = ''): array
    {
        $where = '';
        $bind = [];
        if ($q !== '') {
            $where = 'WHERE (name LIKE ? OR email LIKE ?)';
            $like = "%$q%";
            $bind = [$like, $like];
        }
        $total = (int) $this->run("SELECT COUNT(*) FROM users $where", $bind)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->run(
            "(SELECT u.*, (SELECT COUNT(*) FROM blogs b WHERE b.author_id = u.id) AS posts_count
              FROM users u $where ORDER BY u.created_at ASC LIMIT $perPage OFFSET $offset)"
            , $bind
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
                'pages' => (int) ceil($total / max(1, $perPage))];
    }

    public function adminsAndEditors(): array
    {
        return $this->run(
            "SELECT id, name, role FROM users WHERE is_active = 1 AND role IN ('admin','editor') ORDER BY name"
        )->fetchAll();
    }
}
