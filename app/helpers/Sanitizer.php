<?php
declare(strict_types=1);

namespace Helpers;

/**
 * Whitelist HTML sanitizer for WYSIWYG content and comments.
 * Dependency-free: strips tags outside the whitelist, kills event handlers,
 * javascript: URIs and dangerous elements. (For richer needs swap in HTMLPurifier.)
 */
class Sanitizer
{
    private const ALLOWED_TAGS = [
        'p','br','hr','h1','h2','h3','h4','h5','h6','strong','b','em','i','u','s','a','ul','ol','li',
        'blockquote','pre','code','img','figure','figcaption','table','thead','tbody','tr','th','td',
        'span','div','sup','sub','del','ins','small','mark','iframe',
    ];

    private const ALLOWED_ATTRS = ['href','src','alt','title','class','id','width','height','target',
        'rel','data-*','style'];

    /** Only iframes from these hosts (YouTube embeds). */
    private const IFRAME_HOSTS = ['www.youtube.com','youtube.com','www.youtube-nocookie.com','player.vimeo.com'];

    public static function html(string $dirty): string
    {
        // 1) Remove script/style/objects entirely with their contents
        $clean = preg_replace('#<(script|style|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $dirty);
        $clean = preg_replace('#<(script|style|object|embed|form|link|meta)\b[^>]*/?>#is', '', $clean ?? $dirty);

        // 2) Drop disallowed tags but keep inner text
        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $clean = strip_tags((string) $clean, $allowed);

        // 3) Filter attributes on every remaining tag
        $clean = preg_replace_callback('/<([a-z0-9]+)((?:\s+[^<>]*)?)>/is', function ($m) {
            $tag   = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            $out   = [];
            if (preg_match_all('/([a-z0-9_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/is', $attrs, $am, PREG_SET_ORDER)) {
                foreach ($am as $a) {
                    $name  = strtolower($a[1]);
                    $value = $a[3] !== '' ? $a[3] : ($a[4] ?? ($a[5] ?? ''));
                    if (!self::attrAllowed($name)) {
                        continue;
                    }
                    if (in_array($name, ['href','src'], true) && !self::uriSafe($value, $tag)) {
                        continue;
                    }
                    if ($name === 'style' && preg_match('/(expression|javascript|url\s*\(\s*[\'"]?\s*javascript)/i', $value)) {
                        continue;
                    }
                    $out[] = sprintf('%s="%s"', $name, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
                }
            }
            // iframe host allowlist
            if ($tag === 'iframe') {
                $src = '';
                foreach ($out as $o) {
                    if (str_starts_with($o, 'src=')) {
                        $src = trim($o, '"');
                        $src = substr($src, 5, -1);
                    }
                }
                $host = parse_url(html_entity_decode($src, ENT_QUOTES), PHP_URL_HOST) ?: '';
                if (!in_array(strtolower($host), self::IFRAME_HOSTS, true)) {
                    return '';
                }
                $out[] = 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" loading="lazy"';
            }
            // external links get rel hardening
            if ($tag === 'a' && str_contains($attrs, 'target="_blank"') && !self::hasRelNoopener($out)) {
                $out[] = 'rel="noopener noreferrer"';
            }
            return '<' . $tag . ($out ? ' ' . implode(' ', $out) : '') . '>';
        }, $clean ?? '');

        return trim((string) $clean);
    }

    /** Plain-text fields (names, excerpts shown escaped anyway). */
    public static function text(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? '');
    }

    private static function attrAllowed(string $name): bool
    {
        if (str_starts_with($name, 'data-')) {
            return true;
        }
        if (str_starts_with($name, 'on')) {   // onclick etc — never
            return false;
        }
        return in_array($name, self::ALLOWED_ATTRS, true);
    }

    private static function uriSafe(string $uri, string $tag): bool
    {
        $uri = trim(html_entity_decode($uri, ENT_QUOTES, 'UTF-8'));
        if ($uri === '') {
            return false;
        }
        if (preg_match('/^(javascript|vbscript|file|gopher|ftp):/i', $uri)) {
            return false;
        }
        if (str_starts_with($uri, 'data:')) {
            return $tag === 'img' && (bool) preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $uri);
        }
        return true; // http(s), relative, mailto, anchors
    }

    private static function hasRelNoopener(array $attrs): bool
    {
        foreach ($attrs as $a) {
            if (str_starts_with($a, 'rel=') && str_contains($a, 'noopener')) {
                return true;
            }
        }
        return false;
    }
}
