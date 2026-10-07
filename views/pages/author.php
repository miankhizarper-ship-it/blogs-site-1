<?php
/**
 * Page: Author profile (/author/{slug}).
 * Expects: $author (user row + post_total), $result ['items','meta'].
 */
use Core\View;

$meta  = $result['meta'];
$name  = $author['name'];
?>
<?= View::isolated('breadcrumbs', ['crumbs' => [
    ['label' => 'Home', 'url' => '/'],
    ['label' => $name, 'url' => '/author/' . $author['slug']],
]]) ?>

<header class="author-hero card">
  <div class="author-avatar" aria-hidden="true">
    <?php if (!empty($author['avatar'])): ?>
      <img src="<?= e(url($author['avatar'])) ?>" alt="" width="96" height="96" loading="lazy">
    <?php else: ?>
      <span><?= e(mb_substr($name, 0, 1)) ?></span>
    <?php endif; ?>
  </div>
  <div class="author-info">
    <h1><?= e($name) ?></h1>
    <p class="text-muted"><?= e(ucfirst((string) $author['role'])) ?> · <?= (int) $author['post_total'] ?> published post(s)</p>
    <?php if (!empty($author['bio'])): ?><p class="author-bio"><?= e($author['bio']) ?></p><?php endif; ?>
  </div>
</header>

<section class="section" aria-label="Posts by <?= e($name) ?>">
  <?php if ($result['items']): ?>
    <div class="grid grid-cards" role="list">
      <?php foreach ($result['items'] as $blog): ?>
        <div role="listitem"><?= View::isolated('blog-card', ['blog' => $blog]) ?></div>
      <?php endforeach; ?>
    </div>

    <?php if ($meta['pages'] > 1): ?>
      <nav class="pagination" aria-label="Pagination">
        <?php foreach ($meta['links'] as $p => $link): ?>
          <?php if ($p === $meta['page']): ?>
            <span class="page-link is-active" aria-current="page"><?= $p ?></span>
          <?php else: ?>
            <a class="page-link" href="<?= e($link) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon" aria-hidden="true">✍️</div>
      <h2>No posts yet</h2>
      <p><?= e($name) ?> hasn’t published anything so far.</p>
    </div>
  <?php endif; ?>
</section>
