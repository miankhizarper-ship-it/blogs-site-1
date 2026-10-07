<?php
declare(strict_types=1);

namespace Controllers;

use Helpers\Seo;
use Models\Blog;
use Core\Settings;

/**
 * Shared plumbing for every public listing page (blogs / search / category /
 * tag / author): parse query filters, run Blog::paginate, set SEO meta.
 */
trait ListTrait
{
    /** Read + sanitize listing query params from the request. */
    protected function listFilters(): array
    {
        $int   = fn ($v) => is_numeric($v) ? (int) $v : null;
        $sorts = ['latest', 'oldest', 'popular', 'title'];
        $sort  = (string) ($_GET['sort'] ?? 'latest');
        return [
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'category' => (string) ($_GET['category'] ?? ''),
            'tag'      => (string) ($_GET['tag'] ?? ''),
            'author'   => (string) ($_GET['author'] ?? ''),
            'sort'     => in_array($sort, $sorts, true) ? $sort : 'latest',
        ];
    }

    protected function currentPage(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    protected function perPage(): int
    {
        return max(1, min(50, Settings::int('posts_per_page', 9)));
    }

    /** Run the paginated query through the Blog model. */
    protected function listing(array $filters): array
    {
        $blog = new Blog();
        // strip empty filters so WHERE stays lean
        $clean = array_filter($filters, fn ($v) => $v !== '' && $v !== null);
        return $blog->paginate($clean, $this->currentPage(), $this->perPage());
    }

    /** Apply canonical + description for a listing page. */
    protected function listingSeo(string $title, string $description, string $canonicalPath): void
    {
        Seo::title($title);
        Seo::description($description);
        Seo::canonical(url($canonicalPath . ($this->currentPage() > 1 ? '?page=' . $this->currentPage() : '')));
        if ($this->currentPage() > 1) {
            Seo::noindex(false); // keep paginated pages indexable but canonical to self
        }
    }
}
