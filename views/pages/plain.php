<?php
/**
 * Page: generic static page rendered from the `pages` table (Phase 5).
 * Expects $heading and $body — $body is ALREADY sanitized HTML, printed raw.
 */
use Core\View;
?>
<?= View::partial('breadcrumbs', ['crumbs' => $crumbs ?? []]) ?>
<article class="page-static">
  <header class="page-static-head">
    <h1 class="page-static-title"><?= e($heading) ?></h1>
  </header>
  <div class="prose page-static-body">
    <?= $body /* Sanitizer::html() applied in the controller */ ?>
  </div>
</article>
