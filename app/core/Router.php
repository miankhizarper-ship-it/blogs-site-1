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

    public function get(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $uri, $handler, $middleware);
    }

    public function post(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $uri, $handler, $middleware);
    }

    public function any(string $uri, mixed $handler, array $middleware = []): void
    {
        $this->get($uri, $handler, $middleware);
        $this->post($uri, $handler, $middleware);
    }

    public function setNotFound(mixed $handler): void
    {
        $this->notFound = $handler;
    }

    private function add(string $method, string $uri, mixed $handler, array $middleware): void
    {
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
                $args = [];
                foreach ($route['params'] as $name) {
                    $args[$name] = $m[$name] ?? null;
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

    /** Handler form: 'Controller@method' or callable. */
    private function invoke(mixed $handler, array $args): void
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $action] = explode('@', $handler, 2);
            $fqcn = 'Controllers\\' . $class;
            if (!class_exists($fqcn)) {
                throw new \RuntimeException("Controller $fqcn not found");
            }
            $controller = new $fqcn();
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
