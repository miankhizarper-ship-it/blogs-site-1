<?php
/**
 * Main public layout.
 * Expected variables (from Controller::view / View::share):
 *   $content       — rendered page body HTML
 *   $navCategories — optional list for header dropdown
 *   $pageClass     — optional extra body class
 */
use Core\View;
?>
<!DOCTYPE html>
<html lang="<?= e(\Core\Settings::get('language', 'en')) ?>">
<head>
  <?= View::partial('head') ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
  <a class="skip-link" href="#main">Skip to content</a>

  <?= View::partial('header', ['navCategories' => $navCategories ?? []]) ?>

  <main id="main" class="main" tabindex="-1">
    <?php foreach (get_flashes() as $f): ?>
      <span class="visually-hidden" data-flash="<?= e($f['type']) ?>" data-flash-message="<?= e($f['message']) ?>"></span>
    <?php endforeach; ?>
    <div class="container">
      <?= $content ?>
    </div>
  </main>

  <?= View::partial('cookie-banner') ?>

  <?= View::partial('footer') ?>

  <script src="<?= asset('assets/js/main.js') ?>" defer></script>
  <?php $analytics = \Core\Settings::get('analytics_code'); if ($analytics): ?>
    <!-- Admin-managed analytics snippet -->
    <?= $analytics ?>
  <?php endif; ?>
</body>
</html>
