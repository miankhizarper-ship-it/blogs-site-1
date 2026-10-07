<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Helpers\Seo;
use Models\Blog;
use Core\Settings;

/**
 * Public site controller — home, listings, archives, search.
 * Phase 3: everything is database-driven through the Blog model.
 */
class HomeController extends Controller
{
    use ListTrait;

    /** Shared data for header nav + footer (categories). */
    private function sharedData(Blog $blog): array
    {
        return ['navCategories' => array_map(
            fn ($c) => ['name' => $c['name'], 'slug' => $c['slug']],
            $blog->categoryCounts()
        )];
    }

    /* ------------------------------------------------------------------
       GET / — featured slider, latest grid, trending, categories, newsletter
       ------------------------------------------------------------------ */
    public function index(): void
    {
        $blog = new Blog();

        Seo::description(Settings::get('default_meta_description'));
        Seo::canonical(url('/'));
        Seo::jsonLd([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => Settings::get('site_name'),
            'url'      => url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => url('/search') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ]);

        $this->view('pages/home', $this->sharedData($blog) + [
            'featured'   => $blog->featured(5),
            'latest'     => $blog->latest(6),
            'trending'   => $blog->trending(4),
            'categories' => $blog->categoryCounts(),
        ]);
    }

    /* ------------------------------------------------------------------
       GET /blogs — all posts with filters/sort/pagination
       ------------------------------------------------------------------ */
    public function blogs(): void
    {
        $blog     = new Blog();
        $filters  = $this->listFilters();
        $result   = $this->listing($filters);

        Seo::title('All Blogs');
        Seo::description('Browse every blog post'
            . ($filters['sort'] !== 'latest' ? ", sorted by {$filters['sort']}" : '')
            . '. Guides, tutorials and opinions on web engineering.');
        Seo::canonical(url('/blogs') . ($this->currentPage() > 1 ? '?page=' . $this->currentPage() : ''));
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blogs', 'url' => '/blogs'],
        ]);

        $this->view('pages/blogs', $this->sharedData($blog) + [
            'result'     => $result,
            'filters'    => $filters,
            'categories' => $blog->categoryCounts(),
            'tags'       => $blog->popularTags(12),
            'heading'    => 'All Blogs',
            'subheading' => 'Every post on the site — filter, search and sort to find your next read.',
            'emptyTitle' => 'No posts found',
            'emptyText'  => 'Try clearing the filters or searching for something else.',
        ]);
    }

    /* ------------------------------------------------------------------
       GET /search?q=
       ------------------------------------------------------------------ */
    public function search(): void
    {
        $blog    = new Blog();
        $filters = $this->listFilters();
        $q       = $filters['q'];

        if ($q === '') {
            $this->redirect('/blogs');
        }

        $result = $this->listing($filters);

        Seo::title('Search: ' . $q);
        Seo::description("Search results for \u201c{$q}\u201d — " . $result['meta']['total'] . ' post(s) found.');
        Seo::canonical(url('/search') . '?q=' . rawurlencode($q));
        Seo::noindex(); // thin/duplicative pages shouldn't compete in the index
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Search', 'url' => '/search'],
        ]);

        $this->view('pages/blogs', $this->sharedData($blog) + [
            'result'     => $result,
            'filters'    => $filters,
            'categories' => $blog->categoryCounts(),
            'tags'       => $blog->popularTags(12),
            'heading'    => 'Results for “' . $q . '”',
            'subheading' => $result['meta']['total'] . ' post(s) matched your search.',
            'emptyTitle' => 'Nothing matched “' . $q . '”',
            'emptyText'  => 'Check the spelling, use fewer words, or browse all blogs instead.',
        ]);
    }

    /* ------------------------------------------------------------------
       GET /category/{slug}
       ------------------------------------------------------------------ */
    public function category(string $slug): void
    {
        $blog  = new Blog();
        $cat   = $blog->run(
            'SELECT id, name, slug, description, image FROM categories WHERE slug = ? LIMIT 1',
            [$slug]
        )->fetch();

        if (!$cat) {
            $this->notFound();
            return;
        }

        $filters = array_merge($this->listFilters(), ['category' => $cat['slug'], 'q' => '', 'tag' => '', 'author' => '']);
        $result  = $this->listing($filters);

        Seo::title('Category: ' . $cat['name']);
        Seo::description($cat['description'] ?: "All articles in the {$cat['name']} category.");
        Seo::image($cat['image'] ? url($cat['image']) : null);
        Seo::canonical(url('/category/' . $cat['slug']));
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blogs', 'url' => '/blogs'],
            ['label' => $cat['name'], 'url' => '/category/' . $cat['slug']],
        ]);

        $this->view('pages/blogs', $this->sharedData($blog) + [
            'result'     => $result,
            'filters'    => $this->listFilters(),
            'categories' => $blog->categoryCounts(),
            'tags'       => $blog->popularTags(12),
            'heading'    => $cat['name'],
            'subheading' => $cat['description'] ?: sprintf('%d article(s) in this category.', $result['meta']['total']),
            'emptyTitle' => 'No posts in this category yet',
            'emptyText'  => 'New articles are on the way — check back soon.',
        ]);
    }

    /* ------------------------------------------------------------------
       GET /tag/{slug}
       ------------------------------------------------------------------ */
    public function tag(string $slug): void
    {
        $blog = new Blog();
        $tag  = $blog->run('SELECT id, name, slug FROM tags WHERE slug = ? LIMIT 1', [$slug])->fetch();

        if (!$tag) {
            $this->notFound();
            return;
        }

        $filters = array_merge($this->listFilters(), ['tag' => $tag['slug'], 'category' => '', 'author' => '']);
        $result  = $this->listing($filters);

        Seo::title('Tag: ' . $tag['name']);
        Seo::description("Posts tagged #{$tag['name']}.");
        Seo::canonical(url('/tag/' . $tag['slug']));
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blogs', 'url' => '/blogs'],
            ['label' => '#' . $tag['name'], 'url' => '/tag/' . $tag['slug']],
        ]);

        $this->view('pages/blogs', $this->sharedData($blog) + [
            'result'     => $result,
            'filters'    => $this->listFilters(),
            'categories' => $blog->categoryCounts(),
            'tags'       => $blog->popularTags(12),
            'heading'    => '#' . $tag['name'],
            'subheading' => sprintf('%d post(s) tagged #%s.', $result['meta']['total'], $tag['name']),
            'emptyTitle' => 'No posts with this tag',
            'emptyText'  => 'This tag has no published posts right now.',
        ]);
    }

    /* ------------------------------------------------------------------
       GET /author/{slug}
       ------------------------------------------------------------------ */
    public function author(string $slug): void
    {
        $blog   = new Blog();
        $data   = $blog->authorWithPosts($slug, $this->currentPage(), $this->perPage());

        if (!$data['author']) {
            $this->notFound();
            return;
        }
        $author = $data['author'];

        Seo::title('Author: ' . $author['name']);
        Seo::description($author['bio'] ?: "Posts written by {$author['name']}.");
        Seo::image($author['avatar'] ? url($author['avatar']) : null);
        Seo::canonical(url('/author/' . $author['slug']));
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => $author['name'], 'url' => '/author/' . $author['slug']],
        ]);

        $this->view('pages/author', $this->sharedData($blog) + [
            'author' => $author,
            'result' => $data['result'],
        ]);
    }

    /* ------------------------------------------------------------------
       GET /about|/contact|/privacy-policy… (Pages module lands in Phase 6)
       ------------------------------------------------------------------ */
    public function page(string $slug = 'about'): void
    {
        (new PageController())->show($slug);
    }

    /** Catch-all 404 — proper status + noindex meta. */
    public function notFound(): void
    {
        http_response_code(404);
        Seo::title('Page Not Found');
        Seo::noindex();
        $this->view('pages/404');
    }
}
