<?php
declare(strict_types=1);

namespace Helpers;

/**
 * Slug generation + uniqueness within a table.
 */
class Slugger
{
    public static function make(string $text): string
    {
        // transliterate (iconv) then clean
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');
        $text = trim($text, '-');
        return $text !== '' ? substr($text, 0, 190) : 'post-' . bin2hex(random_bytes(3));
    }

    /** Ensure unique slug in $table (excludes row $ignoreId). */
    public static function unique(string $table, string $base, int $ignoreId = 0): string
    {
        $table = preg_replace('/[^a-z_]/', '', $table); // defensive
        $slug  = self::make($base);
        $try   = $slug;
        $i     = 2;
        while (true) {
            $exists = \Database::run(
                "SELECT id FROM $table WHERE slug = ? AND id <> ? LIMIT 1",
                [$try, $ignoreId]
            )->fetch();
            if (!$exists) {
                return $try;
            }
            $try = $slug . '-' . $i++;
        }
    }
}
