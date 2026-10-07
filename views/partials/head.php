<?php
/**
 * Partial: <head> contents — charset, viewport, dynamic SEO meta/OG/JSON-LD,
 * favicon, styles, and a no-flash inline theme bootstrap.
 * Requires: \Helpers\Seo (rendered), settings via Core\Settings.
 */
use Core\Settings;
use Helpers\Seo;

$siteName = Settings::get('site_name', 'My Blog');
$favicon  = Settings::get('favicon');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#ffffff">
<?= Seo::render() . "\n" ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> RSS" href="<?= url('/rss.xml') ?>">
<?php if ($favicon): ?>
<link rel="icon" href="<?= e(url($favicon)) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%234f46e5'/%3E%3Ctext x='50' y='68' font-size='55' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'%3E%25b%3C/text%3E%3C/svg%3E">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>" media="all">
<script>
/* No-flash theme bootstrap: runs before first paint.
   Order of truth: saved choice → OS preference → light. */
(function () {
  try {
    var t = localStorage.getItem('blogsite_theme');
    if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.dataset.theme = t;
  } catch (e) { document.documentElement.dataset.theme = 'light'; }
})();
</script>
