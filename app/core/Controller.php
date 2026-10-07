<?php
declare(strict_types=1);

namespace Core;

/**
 * Base controller: view rendering, JSON responses, redirects.
 */
abstract class Controller
{
    /** Route params for the current request (set by Router before invoke). */
    public static array $currentParams = [];

    protected function view(string $template, array $data = [], ?string $layout = 'main'): void
    {
        echo View::render($template, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $path, int $code = 302): never
    {
        header('Location: ' . url($path), true, $code);
        exit;
    }

    /** Sanitized request input (never trust raw $_GET/$_POST). */
    protected function input(string $key, mixed $default = null): mixed
    {
        $raw = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_string($raw)) {
            return trim(strip_tags($raw));
        }
        return $raw;
    }

    /** Route parameter captured by the Router ({slug}, {id}, …). */
    protected function param(string $key, mixed $default = null): mixed
    {
        return self::$currentParams[$key] ?? $default;
    }
}
