<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/**
 * Blog model — public queries + admin management (soft delete, rank, featured).
 */
class Blog extends Model
{
    protected string $table = 'blogs';

    private const PUBLIC_SELECT = "SELECT b.*, c.name AS category_name, c.slug AS category_slug,
            u.name AS author_name, u.slug AS author_slug, u.bio AS author_bio, u.avatar AS author_avatar
        FROM blogs b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN users u ON u.id = b.author_id";

    /* ============================ PUBLIC ============================ */

    public function findPublishedBySlug(string $slug): ?array
    {
        $row = $this->run(
            self::PUBLIC_SELECT . " WHERE b.slug = ? AND b.status = 'published'
              AND b.deleted_at IS NULL AND (b.published_at IS NULL OR b.published_at <= NOW()) LIMIT 1",
            [$slug]
        )->fetch();
        return $row === false ? null : $row;
    }

    /** Previous/next navigation among published posts. */
    public function siblings(int $id, string $publishedAt): array
    {
        $prev = $this->run(
            "SELECT title, slug FROM blogs
             WHERE status='published' AND deleted_at IS NULL AND published_at < ? AND id <> ?
             ORDER BY published_at DESC LIMIT 1",
            [$publishedAt, $id]
        )->fetch() ?: null;
        $next = $this->run(
            "SELECT title, slug FROM blogs
             WHERE status='published' AND deleted_at IS NULL AND published_at > ? AND id <> ?
             ORDER BY published_at ASC LIMIT 1",
            [$publishedAt, $id]
        )->fetch() ?: null;
        return ['prev' => $prev ?: null, 'next' => $next ?: null];
    }

    /** Related: same tags first, then same category. */
    public function related(int $blogId, ?int $categoryId, array $tagIds, int $limit = 3): array
    {
        $sql = self::PUBLIC_SELECT . ', COUNT(bt.tag_id) AS rel_score
                FROM blogs b
                LEFT JOIN categories c ON c.id = b.category_id
                LEFT JOIN users u ON u.id = b.author_id
                LEFT JOIN blog_tags bt ON bt.blog_id = b.id';
        $bind = [];
        if ($tagIds) {
            $in = implode(',', array_fill(0, count($tagIds), '?'));
            $sql .= " WHERE b.id <> ? AND b.status='published' AND b.deleted_at IS NULL AND bt.tag_id IN ($in)";
            $bind[] = $blogId;
            foreach ($tagIds as $t) { $bind[] = $t; }
        } else {
            $sql .= " WHERE b.id <> ? AND b.category_id = ? AND b.status='published' AND b.deleted_at IS NULL";
            $bind[] = $blogId;
            $bind[] = $categoryId ?? 0;
        }
        $sql .= ' GROUP BY b.id ORDER BY rel_score DESC, b.views DESC LIMIT ' . (int) $limit;
        return $this->run($sql, $bind)->fetchAll();
    }

    /** Paginated filtered listing. filters: q, category, tag, author, status, sort, includeAll */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = ['b.deleted_at IS NULL'];
        $bind  = [];

        if (empty($filters['includeAll'])) {
            $where[] = "b.status = 'published'";
            $where[] = '(b.published_at IS NULL OR b.published_at <= NOW())';
        } elseif (!empty($filters['status'])) {
            if ($filters['status'] === 'trashed') {
                $where   = ['b.deleted_at IS NOT NULL'];
            } else {
                $where[] = 'b.status = ?';
                $bind[]  = $filters['status'];
            }
        }

        if (!empty($filters['q'])) {
            $where[] = '(b.title LIKE ? OR b.excerpt LIKE ? OR b.content LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($bind, $like, $like, $like);
        }
        if (!empty($filters['category'])) {
            $where[] = 'c.slug = ?';
            $bind[]  = $filters['category'];
        } elseif (!empty($filters['category_id'])) {
            $where[] = 'b.category_id = ?';
            $bind[]  = (int) $filters['category_id'];
        }
        if (!empty($filters['author'])) {
            $where[] = 'u.slug = ?';
            $bind[]  = $filters['author'];
        } elseif (!empty($filters['author_id'])) {
            $where[] = 'b.author_id = ?';
            $bind[]  = (int) $filters['author_id'];
        }
        if (!empty($filters['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM blog_tags bt2 JOIN tags t2 ON t2.id = bt2.tag_id
                        WHERE bt2.blog_id = b.id AND t2.slug = ?)';
            $bind[]  = $filters['tag'];
        }
        if (!empty($filters['featured'])) {
            $where[] = 'b.is_featured = 1';
        }

        $sort = match ($filters['sort'] ?? 'latest') {
            'popular'  => 'b.views DESC',
            'oldest'   => 'b.published_at ASC',
            'title'    => 'b.title ASC',
            'rank'     => 'b.rank ASC, b.views DESC',
            'updated'  => 'b.updated_at DESC',
            default    => 'COALESCE(b.published_at, b.created_at) DESC',
        };

        $w = implode(' AND ', $where);
        $total = (int) $this->run(
            "SELECT COUNT(*) FROM blogs b LEFT JOIN categories c ON c.id=b.category_id LEFT JOIN users u ON u.id=b.author_id WHERE $w",
            $bind
        )->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->run(
            self::PUBLIC_SELECT . " WHERE $w ORDER BY $sort LIMIT $perPage OFFSET $offset",
            $bind
        )->fetchAll();

        return [
            'items' => $rows,
            'meta'  => [
                'total'   => $total,
                'page'    => $page,
                'perPage' => $perPage,
                'pages'   => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function featured(int $limit = 5): array
    {
        return $this->run(
            self::PUBLIC_SELECT . " WHERE b.is_featured = 1 AND b.status='published' AND b.deleted_at IS NULL
              AND (b.published_at IS NULL OR b.published_at <= NOW())
             ORDER BY b.featured_order ASC, b.published_at DESC LIMIT " . (int) $limit
        )->fetchAll();
    }

    public function latest(int $limit = 6): array
    {
        return $this->run(
            self::PUBLIC_SELECT . " WHERE b.status='published' AND b.deleted_at IS NULL
              AND (b.published_at IS NULL OR b.published_at <= NOW())
             ORDER BY b.published_at DESC LIMIT " . (int) $limit
        )->fetchAll();
    }

    /** Trending = manual rank first, then view count fallback. */
    public function trending(int $limit = 4): array
    {
        return $this->run(
            self::PUBLIC_SELECT . " WHERE b.status='published' AND b.deleted_at IS NULL
             ORDER BY (b.rank > 0) DESC, b.rank ASC, b.views DESC LIMIT " . (int) $limit
        )->fetchAll();
    }

    public function categoryCounts(): array
    {
        return $this->run(
            "SELECT c.*, COUNT(b.id) AS posts_count
             FROM categories c
             LEFT JOIN blogs b ON b.category_id = c.id AND b.status='published' AND b.deleted_at IS NULL
             GROUP BY c.id ORDER BY posts_count DESC, c.name ASC"
        )->fetchAll();
    }

    public function popularTags(int $limit = 20): array
    {
        return $this->run(
            "SELECT t.*, COUNT(bt.blog_id) AS posts_count
             FROM tags t
             JOIN blog_tags bt ON bt.tag_id = t.id
             JOIN blogs b ON b.id = bt.blog_id AND b.status='published' AND b.deleted_at IS NULL
             GROUP BY t.id ORDER BY posts_count DESC, t.name ASC LIMIT " . (int) $limit
        )->fetchAll();
    }

    public function tagsFor(int $blogId): array
    {
        return $this->run(
            'SELECT t.* FROM tags t JOIN blog_tags bt ON bt.tag_id = t.id WHERE bt.blog_id = ? ORDER BY t.name',
            [$blogId]
        )->fetchAll();
    }

    public function authorWithPosts(string $slug, int $page, int $perPage): array
    {
        $author = $this->run('SELECT * FROM users WHERE slug = ? LIMIT 1', [$slug])->fetch() ?: null;
        if (!$author) {
            return ['author' => null, 'list' => null];
        }
        $list = $this->paginate(['author_id' => (int) $author['id']], $page, $perPage);
        $author['post_total'] = $list['meta']['total'];
        return ['author' => $author, 'result' => $list];
    }

    /** For sitemap/RSS. */
    public function allPublished(int $limit = 500): array
    {
        return $this->run(
            "SELECT b.id, b.slug, b.title, b.excerpt, b.meta_description, b.published_at, b.updated_at, b.featured_image
             FROM blogs b WHERE b.status='published' AND b.deleted_at IS NULL
               AND (b.published_at IS NULL OR b.published_at <= NOW())
             ORDER BY b.published_at DESC LIMIT " . (int) $limit
        )->fetchAll();
    }

    /** Publish any due scheduled posts (called from bootstrap cron-lite). */
    public function publishDueScheduled(): int
    {
        return $this->run(
            "UPDATE blogs SET status='published' WHERE status='scheduled' AND published_at IS NOT NULL AND published_at <= NOW()"
        )->rowCount();
    }

    /* ============================ ADMIN ============================ */

    public function findAny(int $id): ?array
    {
        $row = $this->run(self::PUBLIC_SELECT . ' WHERE b.id = ? LIMIT 1', [$id])->fetch();
        return $row === false ? null : $row;
    }

    public function toggleFeatured(int $id, bool $featured, ?int $order = null): bool
    {
        $data = ['is_featured' => $featured ? 1 : 0];
        if ($order !== null) {
            $data['featured_order'] = $order;
        }
        return $this->update($id, $data);
    }

    public function setRank(int $id, int $rank): bool
    {
        return $this->update($id, ['rank' => $rank]);
    }

    /** Re-assign sequential ranks in one transaction-safe call order. */
    public function reorderRanks(array $orderedIds): void
    {
        foreach ($orderedIds as $i => $id) {
            $this->setRank((int) $id, $i + 1);
        }
    }

    public function softDelete(int $id): bool
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'trashed']);
    }

    public function restore(int $id): bool
    {
        return $this->update($id, ['deleted_at' => null, 'status' => 'draft']);
    }

    public function bulkStatus(array $ids, string $status): int
    {
        $n = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            if ($status === 'trashed') {
                $n += (int) $this->softDelete($id);
            } elseif ($status === 'restore') {
                $n += (int) $this->restore($id);
            } else {
                $data = ['status' => $status];
                if ($status === 'published') {
                    $row = $this->find($id);
                    if ($row && $row['published_at'] === null) {
                        $data['published_at'] = date('Y-m-d H:i:s');
                    }
                    $data['deleted_at'] = null;
                }
                $n += (int) $this->update($id, $data);
            }
        }
        return $n;
    }

    /** Copy content into a new draft with unique slug. */
    public function duplicate(int $id): ?int
    {
        $src = $this->find($id);
        if (!$src) return null;
        unset($src['id'], $src['created_at'], $src['updated_at']);
        $src['title']  = $src['title'] . ' (Copy)';
        $src['slug']   = \Helpers\Slugger::unique('blogs', slugify($src['title']));
        $src['status'] = 'draft';
        $src['views']  = 0;
        $src['likes']  = 0;
        $src['is_featured'] = 0;
        $src['rank']   = 0;
        $src['published_at'] = null;
        $src['deleted_at']   = null;
        $newId = $this->create($src);
        (new Tag())->syncForBlog($newId, (new Tag())->tagIdsFor($id));
        return $newId;
    }

    public function incrementViews(int $id): void
    {
        $this->run('UPDATE blogs SET views = views + 1 WHERE id = ?', [$id]);
    }

    public function incrementLikes(int $id, int $delta = 1): void
    {
        $this->run('UPDATE blogs SET likes = GREATEST(CAST(likes AS SIGNED) + ?, 0) WHERE id = ?', [$delta, $id]);
    }

    /** Dashboard aggregates + chart data. */
    public function stats(): array
    {
        $counts = $this->run(
            "SELECT
               SUM(status='published' AND deleted_at IS NULL) AS published,
               SUM(status='draft' AND deleted_at IS NULL) AS drafts,
               SUM(status='scheduled' AND deleted_at IS NULL) AS scheduled,
               SUM(deleted_at IS NOT NULL) AS trashed,
               COALESCE(SUM(views),0) AS total_views,
               COALESCE(SUM(likes),0) AS total_likes
             FROM blogs"
        )->fetch();
        return array_map('intval', $counts);
    }

    /** Views per day for the last N days (from blog_views log). */
    public function viewsSeries(int $days = 30): array
    {
        $rows = $this->run(
            'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM blog_views
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY d ORDER BY d',
            [$days]
        )->fetchAll();
        $map = array_column($rows, 'n', 'd');
        $labels = [];
        $data   = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($day));
            $data[]   = (int) ($map[$day] ?? 0);
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function topBlogs(int $limit = 5, string $by = 'views'): array
    {
        $col = $by === 'likes' ? 'likes' : 'views';
        return $this->run(
            'SELECT id, title, slug, views, likes, rank FROM blogs
             WHERE status="published" AND deleted_at IS NULL
             ORDER BY ' . $col . ' DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    /** Featured ordering list for admin manager. */
    public function featuredList(): array
    {
        return $this->run(
            self::PUBLIC_SELECT . ' WHERE b.is_featured = 1 AND b.deleted_at IS NULL
             ORDER BY b.featured_order ASC, b.published_at DESC'
        )->fetchAll();
    }

    public function rankedList(): array
    {
        return $this->run(
            self::PUBLIC_SELECT . " WHERE b.status='published' AND b.deleted_at IS NULL
             ORDER BY (b.rank > 0) DESC, b.rank ASC, b.views DESC"
        )->fetchAll();
    }
}
