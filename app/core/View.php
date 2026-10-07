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
    private static ?string $rootOverride = null;

    /** Point rendering at another root folder (used by the admin panel). */
    public static function setRoot(string $absoluteDir): void
    {
        self::$rootOverride = rtrim($absoluteDir, '/');
    }

    public static function root(): string
    {
        return self::$rootOverride ?? config('paths.views');
    }

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

    /** Render a partial WITHOUT inheriting shared/global view vars.
     *  Prevents loop-local variables (e.g. $blog in card grids) from
     *  leaking into nested partials that expect controller-level data. */
    public static function isolated(string $template, array $data = []): string
    {
        return self::capture('partials/' . $template, $data);
    }

    private static function capture(string $template, array $vars): string
    {
        // Absolute template paths bypass the root (admin controllers use them).
        $file = str_starts_with($template, '/')
            ? $template . '.php'
            : self::root() . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
