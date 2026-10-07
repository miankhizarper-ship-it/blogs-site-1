<?php
declare(strict_types=1);

/**
 * Front controller — every public request is rewritten here by .htaccess/nginx.
 * Boots config, session, shared view data, registers all routes and dispatches.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/config.php';  // env, error handling, paths (idempotent)
require_once BASE_PATH . '/app/core/autoload.php'; // PSR-4-ish autoloader

use Core\Csrf;
use Core\Router;
use Core\Settings;
use Core\View;
use Middleware\Guard;

/* --------------------------------------------------------------------------
   Maintenance mode — visitors get a friendly page, logged-in admins don't.
   -------------------------------------------------------------------------- */
if (Settings::bool('maintenance_mode') && !\Core\Auth::check()) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . htmlspecialchars(Settings::get('site_name', 'My Blog')) . ' — Maintenance</title>'
       . '<style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;'
       . 'min-height:100vh;margin:0;background:#0f172a;color:#e2e8f0;text-align:center}'
       . '.box{max-width:480px;padding:2rem}h1{font-size:1.6rem}p{color:#94a3b8}</style></head>'
       . '<body><div class="box"><h1>🛠 We&rsquo;ll be right back</h1>'
       . '<p>' . htmlspecialchars((string) Settings::get('maintenance_message',
            'The site is undergoing scheduled maintenance. Please check back soon.')) . '</p>'
       . '</div></body></html>';
    exit;
}

/* --------------------------------------------------------------------------
   Session + CSRF bootstrap (session already started & hardened in config)
   -------------------------------------------------------------------------- */
Csrf::init();

/* --------------------------------------------------------------------------
   Data shared with every view (header nav, footer socials)
   -------------------------------------------------------------------------- */
View::share('settings', Settings::all());
try {
    View::share('navCategories', array_map(
        static fn (array $c): array => ['name' => $c['name'], 'slug' => $c['slug']],
        (new Models\Category())->withCounts()
    ));
} catch (\Throwable $e) {
    View::share('navCategories', []); // DB not installed yet — degrade gracefully
}

/* --------------------------------------------------------------------------
   Routes
   -------------------------------------------------------------------------- */
$router = new Router();

// ---- Public pages --------------------------------------------------------
$router->get('/',                        [Controllers\HomeController::class, 'index']);
$router->get('/blogs',                   [Controllers\HomeController::class, 'blogs']);
$router->get('/search',                  [Controllers\HomeController::class, 'search']);
$router->get('/category/{slug}',         [Controllers\HomeController::class, 'category']);
$router->get('/tag/{slug}',              [Controllers\HomeController::class, 'tag']);
$router->get('/author/{slug}',           [Controllers\HomeController::class, 'author']);

// Static/legal pages served from the `pages` table (editable in admin)
foreach (['about', 'privacy-policy', 'terms', 'disclaimer', 'cookie-policy'] as $legalSlug) {
    $router->get('/' . $legalSlug, [Controllers\PageController::class, 'show'],
        ['params' => ['slug' => $legalSlug]]);
}
$router->get('/contact', [Controllers\PageController::class, 'show'], ['params' => ['slug' => 'contact']]);

// Single post + engagement endpoints
$router->get('/blog/{slug}',             [Controllers\BlogController::class, 'show']);
$router->post('/comment',                [Controllers\BlogController::class, 'comment']);
$router->post('/blog/{id}/like',         [Controllers\BlogController::class, 'like']);

// Newsletter + contact form (AJAX)
$router->post('/subscribe',              [Controllers\SubscribeController::class, 'store']);
$router->post('/contact',                [Controllers\PageController::class, 'store']);

// Feeds / SEO endpoints
$router->get('/sitemap.xml',             [Controllers\FeedController::class, 'sitemap']);
$router->get('/robots.txt',              [Controllers\FeedController::class, 'robots']);
$router->get('/rss.xml',                 [Controllers\FeedController::class, 'rss']);

// Health probe (used by uptime monitors; harmless to leave in)
$router->get('/health', static function (): void {
    json_response(['status' => 'ok', 'time' => date('c')]);
});

// ---- Auth ----------------------------------------------------------------
$router->any('/login',    [Controllers\AuthController::class, 'login']);
$router->any('/logout',   [Controllers\AuthController::class, 'logout']);
$router->any('/forgot-password', [Controllers\AuthController::class, 'forgot']);
$router->any('/reset-password',  [Controllers\AuthController::class, 'reset']);
$router->any('/change-password', [Controllers\AuthController::class, 'changePassword'], [Guard::auth()]);

// ---- Admin panel ---------------------------------------------------------
$adminMw = [Guard::auth(), Guard::role('admin', 'editor', 'author'), Guard::csrf()];

$router->get('/admin',                    [Controllers\AdminController::class, 'index'],    $adminMw);
$router->get('/admin/dashboard',          [Controllers\AdminController::class, 'index'],    $adminMw);

// Blogs module
$router->get('/admin/blogs',              [Controllers\BlogAdminController::class, 'index'],     $adminMw);
$router->get('/admin/blogs/create',       [Controllers\BlogAdminController::class, 'form'],      $adminMw);
$router->get('/admin/blogs/{id}/edit',    [Controllers\BlogAdminController::class, 'form'],      $adminMw);
$router->post('/admin/blogs/save',        [Controllers\BlogAdminController::class, 'save'],      $adminMw);
$router->post('/admin/blogs/{id}/delete',   [Controllers\BlogAdminController::class, 'delete'],  $adminMw);
$router->post('/admin/blogs/{id}/restore',  [Controllers\BlogAdminController::class, 'restore'], $adminMw);
$router->post('/admin/blogs/{id}/duplicate',[Controllers\BlogAdminController::class, 'duplicate'],$adminMw);
$router->post('/admin/blogs/bulk',        [Controllers\BlogAdminController::class, 'bulk'],      $adminMw);
$router->get('/admin/ranking',            [Controllers\BlogAdminController::class, 'ranking'],   $adminMw);
$router->post('/admin/ranking/save',      [Controllers\BlogAdminController::class, 'saveRanking'],$adminMw);
$router->post('/admin/ranking/set',       [Controllers\BlogAdminController::class, 'setRank'],   $adminMw);
$router->get('/admin/featured',           [Controllers\BlogAdminController::class, 'featured'],  $adminMw);
$router->post('/admin/featured/toggle',   [Controllers\BlogAdminController::class, 'toggleFeatured'], $adminMw);
$router->post('/admin/featured/order',    [Controllers\BlogAdminController::class, 'saveFeaturedOrder'], $adminMw);

// Comments module
$router->get('/admin/comments',                 [Controllers\CommentAdminController::class, 'index'],    $adminMw);
$router->post('/admin/comments/{id}/status',    [Controllers\CommentAdminController::class, 'setStatus'],$adminMw);
$router->post('/admin/comments/{id}/delete',    [Controllers\CommentAdminController::class, 'delete'],   $adminMw);
$router->post('/admin/comments/{id}/reply',     [Controllers\CommentAdminController::class, 'reply'],    $adminMw);
$router->post('/admin/comments/bulk',           [Controllers\CommentAdminController::class, 'bulk'],     $adminMw);

// Inbox + subscribers
$router->get('/admin/inbox',              [Controllers\InboxAdminController::class, 'messages'],    $adminMw);
$router->get('/admin/inbox/{id}',         [Controllers\InboxAdminController::class, 'read'],        $adminMw);
$router->post('/admin/inbox/{id}/delete', [Controllers\InboxAdminController::class, 'deleteMessage'],$adminMw);
$router->get('/admin/subscribers',        [Controllers\InboxAdminController::class, 'subscribers'], $adminMw);
$router->get('/admin/subscribers/export', [Controllers\InboxAdminController::class, 'exportSubscribers'], $adminMw);
$router->post('/admin/subscribers/{id}/toggle', [Controllers\InboxAdminController::class, 'toggleSubscriber'], $adminMw);

// Taxonomies
$router->get('/admin/categories',            [Controllers\TaxonomyAdminController::class, 'categories'], $adminMw);
$router->post('/admin/categories/save',      [Controllers\TaxonomyAdminController::class, 'saveCategory'],$adminMw);
$router->post('/admin/categories/{id}/delete',[Controllers\TaxonomyAdminController::class, 'deleteCategory'], $adminMw);
$router->get('/admin/tags',                  [Controllers\TaxonomyAdminController::class, 'tags'],       $adminMw);
$router->post('/admin/tags/save',            [Controllers\TaxonomyAdminController::class, 'saveTag'],    $adminMw);
$router->post('/admin/tags/{id}/delete',     [Controllers\TaxonomyAdminController::class, 'deleteTag'],  $adminMw);

// Pages manager
$router->get('/admin/pages',              [Controllers\PageAdminController::class, 'index'],  $adminMw);
$router->get('/admin/pages/create',       [Controllers\PageAdminController::class, 'form'],   $adminMw);
$router->get('/admin/pages/{id}/edit',    [Controllers\PageAdminController::class, 'form'],   $adminMw);
$router->post('/admin/pages/save',        [Controllers\PageAdminController::class, 'save'],   $adminMw);
$router->post('/admin/pages/{id}/delete', [Controllers\PageAdminController::class, 'delete'], $adminMw);

// Media library (authors may upload; only admins/editors delete others)
$router->get('/admin/media',              [Controllers\MediaAdminController::class, 'index'],   $adminMw);
$router->post('/admin/media/upload',      [Controllers\MediaAdminController::class, 'upload'],  $adminMw);
$router->post('/admin/media/{id}/delete', [Controllers\MediaAdminController::class, 'delete'],  $adminMw);
$router->post('/admin/media/{id}/alt',    [Controllers\MediaAdminController::class, 'updateAlt'],$adminMw);
$router->get('/admin/media/picker',       [Controllers\MediaAdminController::class, 'picker'],  $adminMw);

// Users & settings — admin role only
$usersMw   = [Guard::auth(), Guard::role('admin'), Guard::csrf()];
$router->get('/admin/users',              [Controllers\UserAdminController::class, 'index'],  $usersMw);
$router->get('/admin/users/create',       [Controllers\UserAdminController::class, 'form'],   $usersMw);
$router->get('/admin/users/{id}/edit',    [Controllers\UserAdminController::class, 'form'],   $usersMw);
$router->post('/admin/users/save',        [Controllers\UserAdminController::class, 'save'],   $usersMw);
$router->post('/admin/users/{id}/delete', [Controllers\UserAdminController::class, 'delete'], $usersMw);

$router->get('/admin/settings',           [Controllers\SettingAdminController::class, 'index'], $usersMw);
$router->post('/admin/settings/save',     [Controllers\SettingAdminController::class, 'save'],  $usersMw);
$router->post('/admin/settings/test-mail',[Controllers\SettingAdminController::class, 'testMail'], $usersMw);

// Tools, backups, activity log — admin only
$router->get('/admin/tools',                    [Controllers\ToolsAdminController::class, 'index'],    $usersMw);
$router->get('/admin/activity',                 [Controllers\ToolsAdminController::class, 'activity'], $usersMw);
$router->post('/admin/tools/backup',            [Controllers\ToolsAdminController::class, 'backup'],   $usersMw);
$router->get('/admin/tools/download/{name}',    [Controllers\ToolsAdminController::class, 'download'], $usersMw);
$router->post('/admin/tools/backup/delete',     [Controllers\ToolsAdminController::class, 'deleteBackup'], $usersMw);
$router->post('/admin/tools/clear-cache',       [Controllers\ToolsAdminController::class, 'clearCache'],   $usersMw);

// Scheduled-post publishing endpoint (hit by cron; token-guarded)
$router->post('/cron/publish', [Controllers\CronController::class, 'publish']);

// ---- Fallbacks -----------------------------------------------------------
$router->get('/404', [Controllers\HomeController::class, 'notFound']);
$router->setNotFound([Controllers\HomeController::class, 'notFound']);

$router->dispatch();
