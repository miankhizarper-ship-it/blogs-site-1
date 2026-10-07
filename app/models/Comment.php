<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Comments model — threaded, moderated, admin replies flagged. */
class Comment extends Model
{
    protected string $table = 'comments';

    /** Approved top-level comments + their approved replies for a blog. */
    public function approvedTree(int $blogId): array
    {
        $rows = $this->run(
            "SELECT c.*, u.name AS admin_name, u.avatar AS admin_avatar
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.blog_id = ? AND c.status = 'approved'
             ORDER BY c.created_at ASC",
            [$blogId]
        )->fetchAll();

        // Build two-level tree (top + children).
        $tree = [];
        $byId = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            $byId[$r['id']] = $r;
        }
        foreach ($byId as $id => $r) {
            if ($r['parent_id'] && isset($byId[$r['parent_id']])) {
                $byId[$r['parent_id']]['children'][] = &$byId[$id];
            } elseif (!$r['parent_id']) {
                $tree[] = &$byId[$id];
            } else {
                // orphan reply of pending parent: show at root level
                $tree[] = &$byId[$id];
            }
        }
        return $tree;
    }

    /** Admin listing with filters: status, search, pagination. */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $bind  = [];
        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $bind[]  = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(c.author_name LIKE ? OR c.content LIKE ? OR c.author_email LIKE ?)';
            $like    = '%' . $filters['q'] . '%';
            array_push($bind, $like, $like, $like);
        }
        if (!empty($filters['blog_id'])) {
            $where[] = 'c.blog_id = ?';
            $bind[]  = (int) $filters['blog_id'];
        }
        $w = implode(' AND ', $where);

        $total = (int) $this->run("SELECT COUNT(*) FROM comments c WHERE $w", $bind)->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->run(
            "SELECT c.*, b.title AS blog_title, b.slug AS blog_slug
             FROM comments c
             LEFT JOIN blogs b ON b.id = c.blog_id
             WHERE $w
             ORDER BY c.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $bind
        )->fetchAll();

        return [
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => (int) ceil($total / max(1, $perPage)),
        ];
    }

    public function countByStatus(?string $status = null): int
    {
        if ($status !== null) {
            return (int) $this->run('SELECT COUNT(*) FROM comments WHERE status = ?', [$status])->fetchColumn();
        }
        return (int) $this->run('SELECT COUNT(*) FROM comments')->fetchColumn();
    }

    public function recent(int $limit = 5, ?string $status = null): array
    {
        $sql = 'SELECT c.*, b.title AS blog_title, b.slug AS blog_slug
                FROM comments c LEFT JOIN blogs b ON b.id = c.blog_id';
        $bind = [];
        if ($status) {
            $sql .= ' WHERE c.status = ?';
            $bind[] = $status;
        }
        $sql .= ' ORDER BY c.created_at DESC LIMIT ' . (int) $limit;
        return $this->run($sql, $bind)->fetchAll();
    }

    public function setStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    /** Delete comment + all its replies (FK cascade handles children too). */
    public function removeWithReplies(int $id): bool
    {
        return $this->delete($id);
    }
}
