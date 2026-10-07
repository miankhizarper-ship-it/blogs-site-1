<?php
/**
 * Partial: site header — logo, primary nav (Home/Blogs/Categories▾/About/Contact),
 * header search, dark-mode toggle, mobile drawer menu.
 * Shared data expected: $navCategories (array of ['name','slug'] | null).
 */
use Core\Settings;

$siteName   = Settings::get('site_name', 'My Blog');
$logo       = Settings::get('logo');
$categories = $navCategories ?? [];
$path       = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/** Mark the active nav item by simple path prefix matching. */
$isActive = function (string $prefix) use ($path): string {
    if ($prefix === '/') {
        return $path === '/' ? ' is-active' : '';
    }
    return str_starts_with($path, $prefix) ? ' is-active' : '';
};
?>
<header class="site-header">
  <div class="container header-inner">

    <a class="logo" href="<?= url('/') ?>" aria-label="<?= e($siteName) ?> — home">
      <?php if ($logo): ?>
        <img src="<?= e(url($logo)) ?>" alt="<?= e($siteName) ?> logo" width="34" height="34">
      <?php else: ?>
        <span class="logo-mark" aria-hidden="true"><?= e(mb_substr($siteName, 0, 1)) ?></span>
      <?php endif; ?>
      <span><?= e($siteName) ?></span>
    </a>

    <nav class="nav" aria-label="Primary">
      <ul class="nav-list" role="list">
        <li><a class="nav-link<?= $isActive('/') ?>" href="<?= url('/') ?>">Home</a></li>
        <li><a class="nav-link<?= $isActive('/blogs') ?>" href="<?= url('/blogs') ?>">Blogs</a></li>
        <?php if ($categories): ?>
        <li class="nav-dropdown" style="position:relative">
          <button class="nav-link" aria-haspopup="true" aria-expanded="false"
                  onclick="this.setAttribute('aria-expanded', this.getAttribute('aria-expanded')==='true'?'false':'true'); this.nextElementSibling.hidden=!this.nextElementSibling.hidden;">
            Categories ▾
          </button>
          <ul class="dropdown-menu card" role="list" hidden
              style="position:absolute;top:110%;left:0;min-width:200px;padding:.5rem;z-index:60">
            <?php foreach ($categories as $cat): ?>
              <li><a class="nav-link" style="display:block" href="<?= url('/category/' . e($cat['slug'])) ?>">
                <?= e($cat['name']) ?>
              </a></li>
            <?php endforeach; ?>
          </ul>
        </li>
        <?php endif; ?>
        <li><a class="nav-link<?= $isActive('/about') ?>" href="<?= url('/about') ?>">About</a></li>
        <li><a class="nav-link<?= $isActive('/contact') ?>" href="<?= url('/contact') ?>">Contact</a></li>
      </ul>
    </nav>

    <div class="header-actions">
      <form class="search-form header-search" action="<?= url('/search') ?>" method="get" role="search">
        <label class="visually-hidden" for="header-search">Search blogs</label>
        <input class="input" id="header-search" type="search" name="q" placeholder="Search…"
               minlength="2" maxlength="100" value="<?= e($_GET['q'] ?? '') ?>" required>
        <button class="icon-btn" type="submit" aria-label="Submit search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
          </svg>
        </button>
      </form>

      <button class="icon-btn theme-toggle" type="button" data-theme-toggle
              aria-label="Toggle dark mode" title="Toggle dark mode">
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="12" r="4"/>
          <path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
        </svg>
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
        </svg>
      </button>

      <button class="icon-btn menu-toggle" type="button" id="menuToggle"
              aria-label="Open menu" aria-controls="mobileNav" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
    </div>
  </div>
</header>

<!-- Mobile drawer -->
<div class="mobile-nav" id="mobileNav" hidden>
  <div class="mobile-nav-backdrop"></div>
  <div class="mobile-nav-panel" role="dialog" aria-modal="true" aria-label="Site menu">
    <div class="flex-between mb-4">
      <strong>Menu</strong>
      <button class="icon-btn" type="button" data-close-menu aria-label="Close menu">✕</button>
    </div>
    <form class="search-form" action="<?= url('/search') ?>" method="get" role="search" style="margin-bottom:1rem">
      <label class="visually-hidden" for="mobile-search">Search blogs</label>
      <input class="input" id="mobile-search" type="search" name="q" placeholder="Search…" minlength="2" required>
      <button class="btn btn-primary btn-sm" type="submit">Go</button>
    </form>
    <a href="<?= url('/') ?>">Home</a>
    <a href="<?= url('/blogs') ?>">All Blogs</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= url('/category/' . e($cat['slug'])) ?>"><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('/about') ?>">About</a>
    <a href="<?= url('/contact') ?>">Contact</a>
    <hr class="divider">
    <a href="<?= url('/rss.xml') ?>">RSS Feed</a>
    <a href="<?= url('/sitemap.xml') ?>">Sitemap</a>
  </div>
</div>
