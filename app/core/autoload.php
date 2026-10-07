<?php
declare(strict_types=1);

namespace Core;

/**
 * Minimal PSR-0-style autoloader mapping namespaces to /app directories:
 *   Core\Router          -> app/core/Router.php
 *   Controllers\Home     -> app/controllers/Home.php
 *   Models\Blog          -> app/models/Blog.php
 *   Helpers\Html         -> app/helpers/Html.php
 */
spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\'        => BASE_PATH . '/app/core/',
        'Controllers\\' => BASE_PATH . '/app/controllers/',
        'Models\\'      => BASE_PATH . '/app/models/',
        'Helpers\\'     => BASE_PATH . '/app/helpers/',
        'Middleware\\'  => BASE_PATH . '/app/middleware/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});
