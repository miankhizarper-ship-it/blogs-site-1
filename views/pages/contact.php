<?php
/**
 * Page: /contact — editorial content from the pages table + AJAX form.
 * Expects $page (row), $body (sanitized HTML), $email (support address).
 * Form posts to /contact via data-ajax (JSON response → toast).
 */
use Core\View;
?>
<?= View::partial('breadcrumbs', ['crumbs' => [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Contact', 'url' => '/contact'],
]]) ?>

<div class="contact-layout">
  <div class="contact-info">
    <h1 class="page-static-title"><?= e($page['title'] ?? 'Contact') ?></h1>
    <div class="prose"><?= $body ?></div>

    <?php if (!empty($email)): ?>
      <p class="contact-email">
        Prefer email? <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
      </p>
    <?php endif; ?>
  </div>

  <form class="card contact-form" data-ajax action="<?= url('/contact') ?>" method="post" novalidate>
    <h2 class="contact-form-title">Send us a message</h2>

    <?= csrf_field() ?>

    <!-- Honeypot: hidden from humans, bots fill it -->
    <div class="hp-field" aria-hidden="true">
      <label for="cf-website">Website</label>
      <input type="text" id="cf-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="field">
      <label class="label" for="cf-name">Name <span class="req" aria-hidden="true">*</span></label>
      <input class="input" id="cf-name" name="name" type="text" minlength="2" maxlength="100"
             required autocomplete="name" placeholder="Your full name">
    </div>

    <div class="field">
      <label class="label" for="cf-email">Email <span class="req" aria-hidden="true">*</span></label>
      <input class="input" id="cf-email" name="email" type="email" maxlength="190"
             required autocomplete="email" placeholder="you@example.com">
    </div>

    <div class="field">
      <label class="label" for="cf-subject">Subject</label>
      <input class="input" id="cf-subject" name="subject" type="text" maxlength="190"
             placeholder="What is this about? (optional)">
    </div>

    <div class="field">
      <label class="label" for="cf-message">Message <span class="req" aria-hidden="true">*</span></label>
      <textarea class="input textarea" id="cf-message" name="message" rows="6"
                minlength="10" maxlength="5000" required placeholder="Write your message…"></textarea>
      <small class="form-hint"><span data-char-count-for="cf-message">0</span>/5000 characters</small>
    </div>

    <button class="btn btn-primary btn-block" type="submit">
      <span class="btn-label">Send message</span>
      <span class="btn-spinner" aria-hidden="true"></span>
    </button>
    <p class="form-footnote">We usually reply within 48 hours. Your data is handled per our
      <a href="<?= url('/privacy-policy') ?>">Privacy Policy</a>.</p>
  </form>
</div>
