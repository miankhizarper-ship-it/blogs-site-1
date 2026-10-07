<?php
declare(strict_types=1);

namespace Helpers;

use Core\Settings;

/**
 * SEO meta builder — collects page title/description/canonical/OG/Twitter/
 * JSON-LD/breadcrumbs and renders them into <head>.
 *
 * Usage from a controller:
 *   Seo::title('My Page');
 *   Seo::description('...');
 *   Seo::jsonLd($articleSchemaArray);
 *   Seo::breadcrumbs([['Home','/'], ['Blog','/blogs']]);
 * Then in the head partial: <?= Seo::render() ?>
 */
final class Seo
{
    private static ?string $title = null;
    private static ?string $description = null;
    private static ?string $canonical = null;
    private static ?string $image = null;
    private static string $type = 'website';
    private static array $jsonLd = [];
    private static bool $noindex = false;

    public static function title(?string $t): void        { self::$title = $t; }
    public static function description(?string $d): void  { self::$description = $d ? trim(preg_replace('/\s+/', ' ', strip_tags($d))) : null; }
    public static function canonical(?string $url): void  { self::$canonical = $url; }
    public static function image(?string $url): void      { self::$image = $url; }
    public static function type(string $t): void          { self::$type = $t; }
    public static function noindex(bool $v = true): void  { self::$noindex = $v; }

    /** Append a schema.org graph node (Article, BreadcrumbList, WebSite…). */
    public static function jsonLd(array $data): void
    {
        self::$jsonLd[] = $data;
    }

    /** Full effective title: "Page — Site Name" (site name only on home). */
    public static function fullTitle(): string
    {
        $site = Settings::get('site_name', 'My Blog');
        if (self::$title === null || trim(self::$title) === trim($site)) {
            return $site . ' — ' . Settings::get('site_tagline', '');
        }
        return self::$title . ' — ' . $site;
    }

    public static function effectiveDescription(): string
    {
        return self::$description ?: Settings::get('default_meta_description', 'A modern blog.');
    }

    public static function effectiveCanonical(): string
    {
        if (self::$canonical) {
            return self::$canonical;
        }
        $scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return $scheme . '://' . $host . ($uri === '/' ? '/' : rtrim($uri, '/'));
    }

    public static function effectiveImage(): ?string
    {
        if (self::$image) {
            return self::$image;
        }
        // Fall back to configured logo, else absolute placeholder path.
        $logo = Settings::get('logo');
        return $logo ? url($logo) : null;
    }

    /** BreadcrumbList JSON-LD + markup helper. $crumbs = [['label'=>'Home','url'=>'/'], …] */
    public static function breadcrumbs(array $crumbs): void
    {
        if (!$crumbs) {
            return;
        }
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $c['label'],
                'item'     => url($c['url']),
            ];
        }
        self::jsonLd([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    /** Standard Article node for single-blog pages (phase 3 fills real data). */
    public static function articleSchema(array $blog, string $authorName, string $imageUrl): void
    {
        self::jsonLd([
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $blog['meta_title'] ?: $blog['title'],
            'description'      => $blog['meta_description'] ?: excerpt((string)$blog['content'], 160),
            'image'            => $imageUrl,
            'datePublished'    => date('c', strtotime((string)($blog['published_at'] ?: $blog['created_at']))),
            'dateModified'     => date('c', strtotime((string)$blog['updated_at'])),
            'author'           => ['@type' => 'Person', 'name' => $authorName],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => url('/blog/' . $blog['slug'])],
            'keywords'         => $blog['focus_keyword'] ?? '',
            'url'              => url('/blog/' . $blog['slug']),
        ]);
    }

    /** Render everything into <head> HTML. Escaped for safe embedding. */
    public static function render(): string
    {
        $out   = [];
        $title = self::fullTitle();
        $desc  = self::effectiveDescription();
        $canon = self::effectiveCanonical();
        $img   = self::effectiveImage();

        $out[] = '<title>' . e($title) . '</title>';
        $out[] = '<meta name="description" content="' . e(mb_substr($desc, 0, 160)) . '">';
        $out[] = '<link rel="canonical" href="' . e($canon) . '">';
        $out[] = '<meta name="robots" content="' . (self::$noindex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large') . '">';

        // Open Graph
        $out[] = '<meta property="og:site_name" content="' . e(Settings::get('site_name')) . '">';
        $out[] = '<meta property="og:type" content="' . e(self::$type) . '">';
        $out[] = '<meta property="og:title" content="' . e($title) . '">';
        $out[] = '<meta property="og:description" content="' . e($desc) . '">';
        $out[] = '<meta property="og:url" content="' . e($canon) . '">';
        if ($img) {
            $out[] = '<meta property="og:image" content="' . e($img) . '">';
        }

        // Twitter cards
        $out[] = '<meta name="twitter:card" content="' . ($img ? 'summary_large_image' : 'summary') . '">';
        $out[] = '<meta name="twitter:title" content="' . e($title) . '">';
        $out[] = '<meta name="twitter:description" content="' . e($desc) . '">';
        if ($img) {
            $out[] = '<meta name="twitter:image" content="' . e($img) . '">';
        }

        // JSON-LD graph
        foreach (self::$jsonLd as $node) {
            $out[] = '<script type="application/ld+json">'
                   . json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
                   . '</script>';
        }

        return implode("\n      ", $out);
    }
}
