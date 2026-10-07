<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Media library rows (note: avoids reserved word clash via class name Medium). */
class Medium extends Model
{
    protected string $table = 'media';

    public function paginate(int $page, int $perPage, string $q = ''): array
    {
        $where = '';
        $bind  = [];
        if ($q !== '') {
            $where = 'WHERE m.original_name LIKE ? OR m.alt_text LIKE ?';
            $like  = "%$q%";
            $bind  = [$like, $like];
        }
        $total = (int) $this->run("SELECT COUNT(*) FROM media m $where", $bind)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->run(
            "SELECT m.*, u.name AS uploader
             FROM media m LEFT JOIN users u ON u.id = m.uploaded_by
             $where ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset",
            $bind
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
                'pages' => (int) ceil($total / max(1, $perPage))];
    }

    public function record(array $data, ?int $userId): int
    {
        return $this->create($data + ['uploaded_by' => $userId]);
    }
}
