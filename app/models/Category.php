<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Categories model — CRUD + counts used by nav, archives and admin. */
class Category extends Model
{
    protected string $table = 'categories';

    /** All categories with published-post counts (admin list + public nav). */
    public function withCounts(bool $includeDrafts = false): array
    {
        $statusCond = $includeDrafts ? '' : "AND b.status = 'published' AND b.deleted_at IS NULL";
        return $this->run(
            "SELECT c.*, COUNT(b.id) AS posts_count
             FROM categories c
             LEFT JOIN blogs b ON b.category_id = c.id $statusCond
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC"
        )->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function exists(int $id): bool
    {
        return $this->count(['id' => $id]) > 0;
    }
}
