<?php
/**
 * Partial: GDPR-style cookie consent banner (Phase 5).
 * Shown until the user accepts/disables; choice stored in localStorage
 * (`cookie_consent` = "granted" | "denied") and announced via a
 * `consent:changed` CustomEvent so analytics snippets can gate on it.
 */
?>
<div class="cookie-banner" id="cookieBanner" role="dialog" aria-modal="false"
     aria-labelledby="cookieTitle" hidden>
  <div class="cookie-banner-inner">
    <p class="cookie-text" id="cookieTitle">
      🍪 We use essential cookies for the site to work and — with your consent —
      analytics cookies to understand what content resonates. Read our
      <a href="<?= url('/cookie-policy') ?>">Cookie Policy</a>.
    </p>
    <div class="cookie-actions">
      <button type="button" class="btn btn-ghost btn-sm" data-cookie-choice="denied">Reject non-essential</button>
      <button type="button" class="btn btn-primary btn-sm" data-cookie-choice="granted">Accept all</button>
    </div>
  </div>
</div>
