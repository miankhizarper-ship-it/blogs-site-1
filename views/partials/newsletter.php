<?php
/**
 * Partial: newsletter subscribe form (footer + home band).
 * AJAX via data-ajax (main.js AjaxForms) with honeypot anti-spam.
 * Optional $idPrefix to avoid duplicate element ids on one page.
 */
$idp = $idPrefix ?? 'nl';
?>
<form class="newsletter-form" data-ajax action="<?= url('/subscribe') ?>" method="post">
  <?= csrf_field() ?>
  <div class="hp-field" aria-hidden="true">
    <label for="<?= e($idp) ?>-website">Website</label>
    <input type="text" id="<?= e($idp) ?>-website" name="website" tabindex="-1" autocomplete="off">
  </div>
  <label class="visually-hidden" for="<?= e($idp) ?>-email">Email address</label>
  <input class="input" id="<?= e($idp) ?>-email" type="email" name="email"
         placeholder="you@example.com" maxlength="190" required>
  <button class="btn btn-primary" type="submit">Subscribe</button>
</form>
