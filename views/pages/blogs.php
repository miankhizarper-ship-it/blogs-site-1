<?php
/**
 * Page: Blog listing (used by /blogs, /search, /category/{slug}, /tag/{slug}).
 * Expects: $result ['items','meta'], $filters, $categories, $tags,
 *          $heading, $subheading, $emptyTitle, $emptyText.
 */
use Core\View;

$meta    = $result['meta'];
$items   = $result['items'];
$baseQs  = fn (array $override): string => '?' . http_build_query(array_filter(
            array_merge($_GET, $override, ['page' => null]),
            fn ($v) => $v !== '' && $v !== null
        ));
?>
<?= View::isolated('breadcrumbs', ['crumbs' => [
    ['label' => 'Home', 'url' => '/'],
    ['label' => $heading, 'url' => current_url_path_with_filters()],
]]) ?>

<header class="page-head">
  <h1><?= e($heading) ?></h1>
  <p class="text-muted"><?= e($subheading) ?></p>
</header>

<!-- ============================ FILTER BAR ============================ -->
<form class="filter-bar card" method="get" action="<?= url(parse_url($_SERVER['REQUEST_URI'] ?? '/blogs', PHP_URL_PATH) ?: '/blogs') ?>" role="search" aria-label="Filter blogs">
  <div class="filter-fields">
    <label class="visually-hidden" for="f-q">Search</label>
    <input class="input" id="f-q" type="search" name="q" placeholder="Search posts…"
           maxlength="100" value="<?= e($filters['q'] ?? '') ?>">

    <label class="visually-hidden" for="f-cat">Category</label>
    <select class="input" id="f-cat" name="category" <?= !empty($lockedCategory) ? 'disabled' : '' ?>>
      <option value="">All categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat['slug']) ?>" <?= (($filters['category'] ?? '') === $cat['slug']) ? 'selected' : '' ?>>
          <?= e($cat['name']) ?> (<?= (int) $cat['post_count'] ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <label class="visually-hidden" for="f-sort">Sort</label>
    <select class="input" id="f-sort" name="sort">
      <?php foreach (['latest' => 'Newest first', 'oldest' => 'Oldest first', 'popular' => 'Most popular', 'title' => 'Title A–Z'] as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= ($filters['sort'] ?? 'latest') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>

    <button class="btn btn-primary" type="submit">Apply</button>
    <?php if (array_filter([$filters['q'] ?? '', $filters['category'] ?? '', $filters['tag'] ?? '', $filters['author'] ?? ''])): ?>
      <a class="btn btn-ghost" href="<?= url(parse_url($_SERVER['REQUEST_URI'] ?? '/blogs', PHP_URL_PATH) ?: '/blogs') ?>">Clear</a>
    <?php endif; ?>
  </div>
</form>

<!-- Active filter chips -->
<?php $chips = [];
if (!empty($filters['q']))        $chips[] = ['🔍 ' . $filters['q'], $baseQs(['q' => null])];
if (!empty($filters['tag']))      $chips[] = ['#' . ltrim((string)$filters['tag'], '#'), $baseQs(['tag' => null])];
if (!empty($filters['author']))   $chips[] = ['👤 ' . $filters['author'], $baseQs(['author' => null])];
if ($chips): ?>
  <div class="chip-row mb-4" aria-label="Active filters">
    <?php foreach ($chips as [$label, $removeUrl]): ?>
      <a class="chip" href="<?= e($removeUrl) ?>" aria-label="Remove filter <?= e($label) ?>"><?= e($label) ?> ✕</a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ============================ RESULTS ============================ -->
<?php if ($items): ?>
  <div class="grid grid-cards" id="postsGrid" role="list" aria-label="Blog posts" data-live-region>
    <?php foreach ($items as $blog): ?>
      <div role="listitem"><?= View::isolated('blog-card', ['blog' => $blog]) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination (progressive enhancement: JS turns links into "load more") -->
  <?php if ($meta['pages'] > 1): ?>
    <nav class="pagination" aria-label="Pagination" data-pagination>
      <?php if ($meta['page'] > 1): ?><a class="page-link" rel="prev" href="<?= e($baseQs(['page' => $meta['page'] - 1])) ?>">← Prev</a><?php endif; ?>
      <?php foreach ($meta['links'] as $p => $link): ?>
        <?php if ($p === $meta['page']): ?>
          <span class="page-link is-active" aria-current="page"><?= $p ?></span>
        <?php else: ?>
          <a class="page-link" href="<?= e($link) ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($meta['page'] < $meta['pages']): ?>
        <a class="page-link" rel="next" href="<?= e($baseQs(['page' => $meta['page'] + 1])) ?>">Next →</a>
      <?php endif; ?>
    </nav>
    <p class="pagination-info">
      Showing page <?= $meta['page'] ?> of <?= $meta['pages'] ?> — <?= e(format_number($meta['total'])) ?> post(s).
    </p>
    <div class="text-center mt-4">
      <button class="btn btn-outline" type="button" data-load-more
              data-next-url="<?= e($meta['page'] < $meta['pages'] ? $baseQs(['page' => $meta['page'] + 1]) : '') ?>"
              data-total-pages="<?= (int) $meta['pages'] ?>" data-current-page="<?= (int) $meta['page'] ?>">
        Load more posts
      </button>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="empty-state">
    <div class="empty-icon" aria-hidden="true">🔍</div>
    <h2><?= e($emptyTitle) ?></h2>
    <p><?= e($emptyText) ?></p>
    <a class="btn btn-primary mt-4" href="<?= url('/blogs') ?>">Browse all blogs</a>
  </div>
<?php endif; ?>

<!-- Tag cloud sidebar-style strip -->
<?php if (!empty($tags)): ?>
  <section class="section" aria-labelledby="tagcloud-h">
    <h2 class="section-title" id="tagcloud-h">Popular tags</h2>
    <div class="chip-row">
      <?php foreach ($tags as $t): ?>
        <a class="chip" href="<?= url('/tag/' . e($t['slug'])) ?>">#<?= e($t['name']) ?> <small>(<?= (int) $t['post_count'] ?>)</small></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
