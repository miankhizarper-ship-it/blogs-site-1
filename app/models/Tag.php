<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Tags model — CRUD + usage counts. */
class Tag extends Model
{
    protected string $table = 'tags';

    public function withCounts(): array
    {
        return $this->run(
            "SELECT t.*, COUNT(bt.blog_id) AS posts_count
             FROM tags t
             LEFT JOIN blog_tags bt ON bt.tag_id = t.id
             LEFT JOIN blogs b ON b.id = bt.blog_id AND b.status = 'published' AND b.deleted_at IS NULL
             GROUP BY t.id
             ORDER BY t.name ASC"
        )->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /** Attach/detach tag pivots for a blog. */
    public function syncForBlog(int $blogId, array $tagIds): void
    {
        $this->run('DELETE FROM blog_tags WHERE blog_id = ?', [$blogId]);
        foreach (array_unique(array_map('intval', $tagIds)) as $tid) {
            if ($tid > 0 && $this->find($tid)) {
                $this->run('INSERT INTO blog_tags (blog_id, tag_id) VALUES (?, ?)', [$blogId, $tid]);
            }
        }
    }

    public function tagIdsFor(int $blogId): array
    {
        return array_map(
            fn ($r) => (int) $r['tag_id'],
            $this->run('SELECT tag_id FROM blog_tags WHERE blog_id = ?', [$blogId])->fetchAll()
        );
    }
}
