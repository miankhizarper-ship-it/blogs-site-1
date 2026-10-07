<?php
/**
 * Partial: reusable blog card.
 * Expected $blog keys: title, slug, excerpt|content, featured_image, category_name,
 * category_slug, author_name, published_at, views, reading_time (optional).
 */
$img       = !empty($blog['featured_image']) ? url($blog['featured_image']) : null;
$excerptTx = excerpt($blog['excerpt'] ?? strip_tags($blog['content'] ?? ''), 140);
$when      = !empty($blog['published_at']) ? time_ago((string)$blog['published_at']) : 'Draft';
$mins      = isset($blog['reading_time'])
              ? (int)$blog['reading_time']
              : reading_time(strip_tags($blog['content'] ?? ''));
?>
<article class="card blog-card">
  <a class="card-media" href="<?= url('/blog/' . e($blog['slug'])) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($img): ?>
      <img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async" width="640" height="360">
    <?php else: ?>
      <span class="flex-center" style="height:100%;font-size:2.5rem" aria-hidden="true">📝</span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <?php if (!empty($blog['category_name'])): ?>
      <span><a class="badge badge-primary" href="<?= url('/category/' . e($blog['category_slug'] ?? '')) ?>">
        <?= e($blog['category_name']) ?></a></span>
    <?php endif; ?>
    <h3 class="card-title">
      <a href="<?= url('/blog/' . e($blog['slug'])) ?>" rel="bookmark"><?= e($blog['title']) ?></a>
    </h3>
    <p class="card-excerpt"><?= e($excerptTx) ?></p>
    <div class="card-meta">
      <span title="Author">👤 <?= e($blog['author_name'] ?? 'Admin') ?></span>
      <time datetime="<?= e(date('Y-m-d', strtotime((string)($blog['published_at'] ?? 'now')))) ?>">🗓 <?= e($when) ?></time>
      <span>⏱ <?= (int)$mins ?> min</span>
      <span>👁 <?= e(format_number((int)($blog['views'] ?? 0))) ?></span>
    </div>
  </div>
</article>
