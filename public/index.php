<?php
declare(strict_types=1);

/**
 * Front controller — the ONLY PHP entry point exposed to the web.
 * Apache/nginx route all non-file requests here (see .htaccess / nginx snippet).
 */

require_once dirname(__DIR__) . '/config/config.php';   // env, PDO, sessions, helpers
require_once dirname(__DIR__) . '/app/core/autoload.php';

use Core\Router;
use Middleware\Guard;

$router = new Router();

/* ---------- Public routes (expanded in phases 3–4) ---------- */
$router->get('/',                      'HomeController@index');
$router->get('/blogs',                 'HomeController@index');   // placeholder
$router->get('/blog/{slug}',           'HomeController@index');   // placeholder
$router->get('/health', function () {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'time' => date('c')]);
});

/* ---------- Admin routes (real controllers in phase 5) ---------- */
$router->get('/admin', function () {
    echo 'Admin panel shell — login & dashboard arrive in Phase 5.';
}, [[Guard::class, 'auth']]);

$router->setNotFound('HomeController@notFound');

$router->dispatch();
