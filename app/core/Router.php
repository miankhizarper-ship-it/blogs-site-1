<?php
declare(strict_types=1);

namespace Core;

/**
 * Front-controller router with clean URLs.
 * Supports named params ({slug}), optional trailing slash, method matching,
 * middleware callbacks and a 404 fallback.
 */
class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, params:string[], handler:mixed, middleware:array}>> */
    private array $routes = ['GET' => [], 'POST' => []];
    private mixed $notFound = null;

    /**
     * @param mixed $handler  'Controller@method' string, [Class::class,'method'] array, or callable.
     * @param array $options  ['middleware' => callable[], 'params' => array] (legacy list of callables also accepted).
     */
    public function get(string $uri, mixed $handler, array $options = []): void
    {
        $this->add('GET', $uri, $handler, $options);
    }

    public function post(string $uri, mixed $handler, array $options = []): void
    {
        $this->add('POST', $uri, $handler, $options);
    }

    public function any(string $uri, mixed $handler, array $options = []): void
    {
        $this->get($uri, $handler, $options);
        $this->post($uri, $handler, $options);
    }

    public function setNotFound(mixed $handler): void
    {
        $this->notFound = $handler;
    }

    private function add(string $method, string $uri, mixed $handler, array $options): void
    {
        // Accept both ['middleware' => [...], 'params' => [...]] and a plain list of callables.
        $middleware = $options['middleware'] ?? (array_is_list($options) ? $options : []);
        $extraParams = $options['params'] ?? [];

        $params = [];
        // /blog/{slug} -> #^/blog/(?P<slug>[^/]+)$#
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $uri);
        $regex = '#^' . rtrim($regex, '/') . '/?$#';

        $this->routes[$method][] = [
            'regex'      => $regex,
            'params'     => $params,
            'defaults'   => $extraParams,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
        if ($uri === '//') {
            $uri = '/';
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $uri, $m)) {
                $args = $route['defaults']; // route-level params (e.g. slug for /about)
                foreach ($route['params'] as $name) {
                    $args[$name] = rawurldecode((string) ($m[$name] ?? ''));
                }
                // run middleware — each may return false to halt
                foreach ($route['middleware'] as $mw) {
                    if (is_callable($mw) && $mw($args) === false) {
                        return;
                    }
                }
                $this->invoke($route['handler'], $args);
                return;
            }
        }

        // 404
        http_response_code(404);
        if ($this->notFound !== null) {
            $this->invoke($this->notFound, []);
        } else {
            echo '404 Not Found';
        }
    }

    /** Handler form: 'Controller@method', [Class::class, 'method'], or callable. */
    private function invoke(mixed $handler, array $args): void
    {
        Controller::$currentParams = $args; // expose {param}s to controllers

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $handler = $handler[0] . '@' . $handler[1];
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $action] = explode('@', $handler, 2);
            $fqcn = str_contains($class, '\\') ? $class : 'Controllers\\' . $class;
            if (!class_exists($fqcn)) {
                throw new \RuntimeException("Controller $fqcn not found");
            }
            $controller = new $fqcn();
            // Pass route params as positional args too (typed controller methods).
            call_user_func_array([$controller, $action], array_values($args));
            return;
        }
        if (is_callable($handler)) {
            call_user_func_array($handler, array_values($args));
            return;
        }
        throw new \RuntimeException('Invalid route handler');
    }
}
