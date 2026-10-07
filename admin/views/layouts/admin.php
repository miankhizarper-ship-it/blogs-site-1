<?php
/** Admin layout. Expects: $content, $title, $activeNav, $crumbs */
use Core\Auth;
$u = Auth::user();
$sLogo = \Core\Settings::get('logo', '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Admin') ?> — <?= e(site_name()) ?> Admin</title>
<link rel="icon" href="<?= e($sLogo ? url($sLogo) : asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<script>window.CSRF = '<?= \Core\Csrf::token() ?>';</script>
</head>
<body class="admin-body">
<aside class="sidebar" id="adminSidebar">
  <a class="side-brand" href="<?= url('/admin') ?>">
    <?php if ($sLogo): ?><img src="<?= e(url($sLogo)) ?>" alt="" height="26"><?php else: ?><span class="brand-badge">K</span><?php endif; ?>
    <span>Khizar · Admin</span>
  </a>
  <nav class="side-nav">
    <?php
    $items = [
      ['dashboard', 'Dashboard', '/admin', '📊'],
      ['blogs', 'Blogs', '/admin/blogs', '✍️'],
      ['pages', 'Pages', '/admin/pages', '📄'],
      ['media', 'Media', '/admin/media', '🖼️'],
      ['categories', 'Categories', '/admin/categories', '🗂️'],
      ['tags', 'Tags', '/admin/tags', '🏷️'],
      ['ranking', 'Ranking', '/admin/ranking', '🔥'],
      ['featured', 'Featured', '/admin/featured', '⭐'],
      ['comments', 'Comments', '/admin/comments', '💬'],
      ['inbox', 'Inbox', '/admin/inbox', '📬'],
      ['subscribers', 'Subscribers', '/admin/subscribers', '👥'],
      ['users', 'Users', '/admin/users', '🧑‍💼'],
      ['settings', 'Settings', '/admin/settings', '⚙️'],
      ['tools', 'Tools', '/admin/tools', '🛠️'],
      ['activity', 'Activity Log', '/admin/activity', '📜'],
    ];
    foreach ($items as [$key, $label, $href, $ico]):
      if ($key === 'users' && !Auth::isAdmin()) continue;
      if ($key === 'settings' && !Auth::isAdmin()) continue;
      if ($key === 'tools' && !Auth::isAdmin()) continue;
      if ($key === 'activity' && !Auth::isAdmin()) continue;
    ?>
      <a href="<?= url($href) ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>">
        <span class="ico"><?= $ico ?></span><span><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= url('/') ?>" target="_blank" rel="noopener">↗ View site</a>
    <form method="post" action="<?= url('/logout') ?>">
      <?= csrf_field() ?>
      <button class="linklike" type="submit">Sign out (<?= e($u['name'] ?? '') ?>)</button>
    </form>
  </div>
</aside>

<div class="admin-main">
  <header class="topbar">
    <button class="hamburger" onclick="document.getElementById('adminSidebar').classList.toggle('open')" aria-label="Menu">☰</button>
    <h1 class="page-title"><a href="<?= url('/admin') ?>">Admin</a>
      <?php foreach (($crumbs ?? []) as $c): ?>
        <span class="sep">/</span>
        <?php if (!empty($c['url'])): ?><a href="<?= url($c['url']) ?>"><?= e($c['label']) ?></a>
        <?php else: ?><span><?= e($c['label']) ?></span><?php endif; ?>
      <?php endforeach; ?>
    </h1>
    <div class="top-actions">
      <select onchange="if(this.value){location.href=this.value}" class="quick-jump" aria-label="Quick jump">
        <option value="">Quick actions…</option>
        <option value="<?= url('/admin/blogs/create') ?>">＋ New blog post</option>
        <option value="<?= url('/admin/pages/create') ?>">＋ New page</option>
        <option value="<?= url('/admin/media') ?>">Open media library</option>
        <option value="<?= url('/admin/comments?status=pending') ?>">Moderate comments</option>
        <option value="<?= url('/admin/settings') ?>">Site settings</option>
      </select>
    </div>
  </header>

  <?php if (\Core\Settings::bool('maintenance_mode')): ?>
    <div class="notice warn">🔧 <b>Maintenance mode is ON.</b> Visitors see a maintenance page. <a href="<?= url('/admin/settings?tab=general') ?>">Turn it off</a></div>
  <?php endif; ?>

  <?php foreach (get_flashes() as $f): ?>
    <div class="notice <?= in_array($f['type'], ['error', 'warning'], true) ? 'err' : 'ok' ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>

  <main class="content"><?= $content ?></main>

  <footer class="admin-foot">
    Khizar Blog CMS · PHP <?= PHP_VERSION ?> · logged in as <b><?= e($u['role'] ?? '') ?></b>
  </footer>
</div>
<div id="mediaModal" class="modal" hidden>
  <div class="modal-box">
    <div class="modal-head"><b>Select image</b><button type="button" class="x" onclick="closeMediaPicker()">✕</button></div>
    <iframe id="mediaFrame" title="Media picker"></iframe>
  </div>
</div>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
