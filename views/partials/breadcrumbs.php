<?php
/**
 * Partial: breadcrumbs nav (visible) — mirrors Seo::breadcrumbs() JSON-LD.
 * Usage: <?= View::partial('breadcrumbs', ['crumbs' => [['label'=>'Home','url'=>'/'], …]]) ?>
 */
$crumbs = $crumbs ?? [];
if (!$crumbs) { return; }
$last = count($crumbs) - 1;
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol role="list">
    <?php foreach ($crumbs as $i => $c): ?>
      <li>
        <?php if ($i === $last): ?>
          <span aria-current="page"><?= e($c['label']) ?></span>
        <?php else: ?>
          <a href="<?= e(url($c['url'])) ?>"><?= e($c['label']) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
