<?php
declare(strict_types=1);

namespace Core;

/**
 * View renderer with layouts + partials.
 * Usage: $this->view('pages/home', ['blogs' => $blogs], 'main');
 */
class View
{
    private static array $shared = [];

    /** Data available to every view (nav categories, settings, etc.) */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'main'): string
    {
        $vars = array_merge(self::$shared, $data);
        $content = self::capture($template, $vars);

        if ($layout !== null) {
            $vars['content'] = $content;
            return self::capture('layouts/' . $layout, $vars);
        }
        return $content;
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, array_merge(self::$shared, $data));
    }

    private static function capture(string $template, array $vars): string
    {
        $file = config('paths.views') . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
