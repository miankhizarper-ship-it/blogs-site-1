<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/**
 * Static pages model (About, Privacy, Terms …) — editable from the admin
 * Pages module. Only published-visible content is ever exposed publicly.
 */
class Page extends Model
{
    protected string $table = 'pages';

    /** One page by slug. */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->run('SELECT * FROM pages WHERE slug = ? LIMIT 1', [$slug])->fetch();
        return $row === false ? null : $row;
    }

    /** All pages (admin listing). */
    public function allPages(): array
    {
        return $this->run(
            'SELECT id, title, slug, updated_at FROM pages ORDER BY title ASC'
        )->fetchAll();
    }
}
