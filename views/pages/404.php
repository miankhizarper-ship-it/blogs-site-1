<?php
/**
 * Page: 404 Not Found — styled, helpful links, noindex.
 * Controller sets http_response_code(404) and Seo::noindex().
 */
use Core\View;
?>
<section class="error-page">
  <div>
    <p class="error-code" aria-hidden="true">404</p>
    <h1>Page not found</h1>
    <p>The page you're looking for doesn't exist, was moved, or the link is
       mistyped. Maybe one of these will help:</p>

    <div class="flex flex-center flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= url('/') ?>">← Back to Home</a>
      <a class="btn btn-outline" href="<?= url('/blogs') ?>">Browse All Blogs</a>
      <a class="btn btn-outline" href="<?= url('/contact') ?>">Report a Broken Link</a>
    </div>

    <form class="search-form" action="<?= url('/search') ?>" method="get"
          role="search" style="max-width:420px;margin:var(--sp-6) auto 0">
      <label class="visually-hidden" for="nf-search">Search the site</label>
      <input class="input" id="nf-search" type="search" name="q" placeholder="Try searching instead…" minlength="2" required>
      <button class="btn btn-primary" type="submit">Search</button>
    </form>

    <?= View::partial('breadcrumbs', ['crumbs' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => '404',  'url' => '/404'],
    ]]) ?>
  </div>
</section>
