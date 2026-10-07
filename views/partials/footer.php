<?php
/**
 * Partial: site footer — about blurb, quick links, legal links, social icons,
 * newsletter subscribe box (AJAX), bottom bar.
 */
use Core\Settings;

$siteName = Settings::get('site_name', 'My Blog');
$start    = Settings::get('copyright_start', '2026');
$year     = date('Y');
$copyFrom = (int)$start >= (int)$year ? $start : "$start–$year";

$socials = array_filter([
    'facebook'  => Settings::get('social_facebook'),
    'twitter'   => Settings::get('social_twitter'),
    'linkedin'  => Settings::get('social_linkedin'),
    'instagram' => Settings::get('social_instagram'),
    'youtube'   => Settings::get('social_youtube'),
]);

$icons = [
    'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
    'twitter'   => '<path d="M4 4l7.6 10L4.5 20h2.2l6-6.6L17.5 20H20L11.9 9.4 19.3 4h-2.2l-5.5 6L7.2 4z"/>',
    'linkedin'  => '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM9 9h4v1.7c.6-1 1.8-1.9 3.5-1.9 3 0 3.5 2 3.5 4.6V21h-4v-6.6c0-1.6-.6-2.5-1.9-2.5-1.2 0-1.9.8-1.9 2.5V21H9z"/>',
    'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2"/>',
    'youtube'   => '<path d="M22 12s0-3.3-.4-4.8a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.7 2 12 2 12s0 3.3.4 4.8a2.5 2.5 0 0 0 1.8 1.8c1.5.4 7.8.4 7.8.4s6.3 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8C22 15.3 22 12 22 12zM10 15V9l5 3z"/>',
];
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">

      <!-- About -->
      <section aria-labelledby="foot-about-h">
        <h2 class="footer-title" id="foot-about-h"><?= e($siteName) ?></h2>
        <p class="text-sm text-muted"><?= e(Settings::get('footer_about')) ?></p>
        <?php if ($socials): ?>
        <div class="social-links" aria-label="Social media">
          <?php foreach ($socials as $net => $link): ?>
            <a href="<?= e($link) ?>" target="_blank" rel="noopener me" aria-label="<?= e(ucfirst($net)) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= $icons[$net] ?? '' ?></svg>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

      <!-- Explore -->
      <nav aria-labelledby="foot-explore-h">
        <h2 class="footer-title" id="foot-explore-h">Explore</h2>
        <ul class="footer-links" role="list">
          <li><a href="<?= url('/') ?>">Home</a></li>
          <li><a href="<?= url('/blogs') ?>">All Blogs</a></li>
          <li><a href="<?= url('/about') ?>">About</a></li>
          <li><a href="<?= url('/contact') ?>">Contact</a></li>
          <li><a href="<?= url('/rss.xml') ?>">RSS Feed</a></li>
          <li><a href="<?= url('/sitemap.xml') ?>">Sitemap</a></li>
        </ul>
      </nav>

      <!-- Legal -->
      <nav aria-labelledby="foot-legal-h">
        <h2 class="footer-title" id="foot-legal-h">Legal</h2>
        <ul class="footer-links" role="list">
          <li><a href="<?= url('/privacy-policy') ?>">Privacy Policy</a></li>
          <li><a href="<?= url('/terms') ?>">Terms &amp; Conditions</a></li>
          <li><a href="<?= url('/disclaimer') ?>">Disclaimer</a></li>
          <li><a href="<?= url('/cookie-policy') ?>">Cookie Policy</a></li>
        </ul>
      </nav>

      <!-- Newsletter -->
      <section aria-labelledby="foot-news-h">
        <h2 class="footer-title" id="foot-news-h">Newsletter</h2>
        <p class="text-sm text-muted mb-4">Get the best posts in your inbox. No spam, unsubscribe anytime.</p>
        <?= \Core\View::isolated('newsletter', ['idPrefix' => 'foot-nl']) ?>
      </section>

    </div>

    <div class="footer-bottom">
      <span>© <?= e($copyFrom) ?> <?= e($siteName) ?>. All rights reserved.</span>
      <span>Built with PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?> · <a href="<?= url('/admin') ?>">Admin</a></span>
    </div>
  </div>
</footer>
