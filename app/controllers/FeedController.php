<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Helpers\Seo;
use Models\Blog;
use Core\Settings;

/**
 * Phase 5 — machine-facing endpoints: robots.txt, sitemap.xml, RSS feed.
 * All output is generated live from published database content.
 */
class FeedController extends Controller
{
    /** Absolute base URL of the site (from APP_URL). */
    private function base(): string
    {
        return rtrim((string) config('app_url', 'http://localhost'), '/');
    }

    /* GET /robots.txt ---------------------------------------------------- */
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $base = $this->base();
        echo "User-agent: *\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /search?\n";
        echo "Allow: /\n\n";
        echo "Sitemap: {$base}/sitemap.xml\n";
        echo "Sitemap: {$base}/rss.xml\n";
    }

    /* GET /sitemap.xml --------------------------------------------------- */
    public function sitemap(): void
    {
        $blog   = new Blog();
        $base   = $this->base();
        $now    = gmdate('Y-m-d\TH:i:s+00:00');
        $urls   = [];

        // Home — highest priority, updated whenever newest post changes.
        $latest = $blog->latest(1);
        $homeMod = $latest ? gmdate('Y-m-d', strtotime((string) $latest[0]['published_at'])) : null;
        $urls[] = ['loc' => $base . '/', 'lastmod' => $homeMod, 'changefreq' => 'daily', 'priority' => '1.0'];

        // Index pages
        $urls[] = ['loc' => $base . '/blogs', 'lastmod' => $homeMod, 'changefreq' => 'daily', 'priority' => '0.9'];

        // Static/legal pages from DB
        foreach ($blog->run("SELECT slug, updated_at FROM pages")->fetchAll() as $p) {
            $urls[] = ['loc' => $base . '/' . $p['slug'], 'lastmod' => gmdate('Y-m-d', strtotime((string) $p['updated_at'])),
                       'changefreq' => 'monthly', 'priority' => '0.4'];
        }

        // Categories with at least one published post
        foreach ($blog->categoryCounts() as $c) {
            if ((int) $c['post_count'] > 0) {
                $urls[] = ['loc' => $base . '/category/' . $c['slug'], 'lastmod' => null,
                           'changefreq' => 'weekly', 'priority' => '0.6'];
            }
        }

        // Author pages
        foreach ($blog->run("SELECT DISTINCT u.slug, MAX(b.updated_at) AS m
                               FROM blogs b INNER JOIN users u ON u.id = b.author_id
                              WHERE b.status='published' AND b.deleted_at IS NULL
                              GROUP BY u.slug")->fetchAll() as $a) {
            $urls[] = ['loc' => $base . '/author/' . $a['slug'],
                       'lastmod' => $a['m'] ? gmdate('Y-m-d', strtotime((string) $a['m'])) : null,
                       'changefreq' => 'weekly', 'priority' => '0.5'];
        }

        // Every published post (respect per-post robots flag)
        foreach ($blog->allPublished(2000) as $b) {
            if (($b['robots'] ?? 'index') === 'noindex') {
                continue;
            }
            $mod = $b['updated_at'] ?: $b['published_at'];
            $urls[] = ['loc' => $base . '/blog/' . $b['slug'],
                       'lastmod' => gmdate('Y-m-d', strtotime((string) $mod)),
                       'changefreq' => 'monthly', 'priority' => '0.8'];
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo "  <url>\n";
            echo '    <loc>' . e($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                echo '    <lastmod>' . e($u['lastmod']) . "</lastmod>\n";
            }
            echo '    <changefreq>' . e($u['changefreq']) . "</changefreq>\n";
            echo '    <priority>' . e($u['priority']) . "</priority>\n";
            echo "  </url>\n";
        }
        echo "</urlset>\n";
    }

    /* GET /rss.xml -------------------------------------------------------- */
    public function rss(): void
    {
        $blog  = new Blog();
        $site  = Settings::get('site_name', 'Blog');
        $desc  = Settings::get('default_meta_description', 'Latest posts');
        $base  = $this->base();
        $items = $blog->allPublished(20);

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">\n';
        echo "<channel>\n";
        echo '  <title>' . e($site) . "</title>\n";
        echo '  <link>' . e($base . '/') . "</link>\n";
        echo '  <description>' . e($desc) . "</description>\n";
        echo '  <language>en</language>' . "\n";
        echo '  <lastBuildDate>' . gmdate('r') . "</lastBuildDate>\n";
        echo '  <atom:link href="' . e($base . '/rss.xml') . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($items as $b) {
            $link = $base . '/blog/' . $b['slug'];
            echo "  <item>\n";
            echo '    <title>' . e($b['title']) . "</title>\n";
            echo '    <link>' . e($link) . "</link>\n";
            echo '    <guid isPermaLink="true">' . e($link) . "</guid>\n";
            echo '    <pubDate>' . gmdate('r', strtotime((string) $b['published_at'])) . "</pubDate>\n";
            if (!empty($b['category_name'])) {
                echo '    <category>' . e($b['category_name']) . "</category>\n";
            }
            echo '    <description>' . e($b['excerpt'] ?: excerpt((string) $b['content'], 200)) . "</description>\n";
            echo "  </item>\n";
        }
        echo "</channel>\n</rss>\n";
    }
}
