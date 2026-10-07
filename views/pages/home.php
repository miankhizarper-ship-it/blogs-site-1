<?php
/**
 * Page: Home (Phase 3 — fully database-driven).
 * Sections: featured slider, latest grid, trending list, categories, newsletter.
 * Expects: $featured, $latest, $trending, $categories (arrays of blog rows / category rows).
 */
use Core\View;

$fullHero = !empty($featured[0]);
$f        = $featured[0] ?? null;
?>

<!-- ============================ HERO + FEATURED SLIDER ============================ -->
<section class="hero" style="margin-inline:calc(50% - 50vw);padding-inline:max(1.25rem,calc(50vw - var(--container)/2))">
  <div class="container">
    <?php if ($fullHero): ?>
      <div class="slider" data-slider aria-roledescription="carousel" aria-label="Featured posts">
        <div class="slider-track" id="sliderTrack">
          <?php foreach ($featured as $i => $post): ?>
            <div class="slide<?= $i === 0 ? ' is-active' : '' ?>" role="group"
                 aria-roledescription="slide" aria-label="Slide <?= $i + 1 ?> of <?= count($featured) ?>"
                 <?= $i === 0 ? '' : 'hidden' ?>>
              <a class="slide-inner" href="<?= url('/blog/' . e($post['slug'])) ?>">
                <?php if (!empty($post['featured_image'])): ?>
                  <img class="slide-img" src="<?= e(url($post['featured_image'])) ?>"
                       alt="<?= e($post['title']) ?>" <?= $i > 0 ? 'loading="lazy"' : 'fetchpriority="high"' ?>
                       width="1200" height="640">
                <?php endif; ?>
                <div class="slide-caption">
                  <span class="badge badge-primary"><?= e($post['category_name'] ?? 'Featured') ?></span>
                  <h2><?= e($post['title']) ?></h2>
                  <p><?= e(excerpt($post['excerpt'] ?? strip_tags((string) $post['content']), 160)) ?></p>
                  <div class="card-meta">
                    <span>👤 <a href="<?= url('/author/' . e($post['author_slug'] ?? '')) ?>"><?= e($post['author_name']) ?></a></span>
                    <time datetime="<?= e(date('c', strtotime((string) $post['published_at']))) ?>">🗓 <?= e(time_ago((string) $post['published_at'])) ?></time>
                    <span>⏱ <?= (int) $post['reading_time'] ?> min read</span>
                  </div>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if (count($featured) > 1): ?>
          <button class="slider-btn slider-prev" type="button" data-slider-prev aria-label="Previous slide">‹</button>
          <button class="slider-btn slider-next" type="button" data-slider-next aria-label="Next slide">›</button>
          <div class="slider-dots" role="tablist" aria-label="Choose slide">
            <?php for ($i = 0; $i < count($featured); $i++): ?>
              <button class="dot<?= $i === 0 ? ' is-active' : '' ?>" type="button"
                      role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                      data-slider-dot="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"></button>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- No featured posts yet — static hero keeps the layout alive -->
      <h1>Ideas, code &amp; guides worth your time.</h1>
      <p class="lede"><?= e(\Core\Settings::get('site_tagline')) ?></p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= url('/blogs') ?>">Read the blogs</a>
        <a class="btn btn-outline btn-lg" href="<?= url('/about') ?>">About the site</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ============================ LATEST GRID ============================ -->
<section class="section container" aria-labelledby="latest-h">
  <div class="section-head">
    <h2 class="section-title" id="latest-h">Latest posts</h2>
    <a class="btn btn-sm btn-outline" href="<?= url('/blogs') ?>">View all →</a>
  </div>

  <?php if ($latest): ?>
    <div class="grid grid-cards" role="list" aria-label="Latest blog posts">
      <?php foreach ($latest as $blog): ?>
        <div role="listitem"><?= View::isolated('blog-card', ['blog' => $blog]) ?></div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon" aria-hidden="true">📝</div>
      <h3>No posts yet</h3>
      <p>The first articles will appear here once published from the admin panel.</p>
    </div>
  <?php endif; ?>
</section>

<!-- ============================ TRENDING ============================ -->
<?php if ($trending): ?>
<section class="section section--alt" style="margin-inline:calc(50% - 50vw);padding-inline:max(1.25rem,calc(50vw - var(--container)/2))" aria-labelledby="trend-h">
  <div class="container">
    <h2 class="section-title" id="trend-h">🔥 Trending now</h2>
    <ol class="trending-list">
      <?php foreach ($trending as $i => $post): ?>
        <li>
          <a href="<?= url('/blog/' . e($post['slug'])) ?>">
            <span class="rank" aria-hidden="true"><?= $i + 1 ?></span>
            <span class="trending-body">
              <strong><?= e($post['title']) ?></strong>
              <small><?= e($post['category_name'] ?? '') ?> · 👁 <?= e(format_number((int) $post['views'])) ?> views · ⏱ <?= (int) $post['reading_time'] ?> min</small>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<!-- ============================ CATEGORIES ============================ -->
<?php if ($categories): ?>
<section class="section container" aria-labelledby="cats-h">
  <h2 class="section-title" id="cats-h">Browse by topic</h2>
  <div class="grid grid-3">
    <?php foreach ($categories as $cat): ?>
      <a class="cat-tile" href="<?= url('/category/' . e($cat['slug'])) ?>">
        <?= e($cat['name']) ?>
        <span class="count"><?= (int) $cat['post_count'] ?> post<?= (int) $cat['post_count'] === 1 ? '' : 's' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ============================ NEWSLETTER ============================ -->
<section class="section container text-center" aria-labelledby="nl-h">
  <h2 class="section-title" id="nl-h" style="justify-content:center">Stay in the loop</h2>
  <p class="text-muted mb-4">One email a week. The best posts, nothing else.</p>
  <?= View::isolated('newsletter', ['idPrefix' => 'home-nl']) ?>
</section>
