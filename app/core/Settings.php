<?php
declare(strict_types=1);

namespace Core;

use Database;

/**
 * Settings store with in-request cache. Falls back to defaults so the site
 * works even before seed data is imported.
 */
class Settings
{
    private static ?array $cache = null;

    private const DEFAULTS = [
        'site_name'              => 'My Blog',
        'site_tagline'           => 'Stories, guides and code.',
        'logo'                   => '',
        'favicon'                => '',
        'posts_per_page'         => '9',
        'comment_moderation'     => '1',
        'email_notify_new_comment' => '0',
        'default_meta_description' => 'A modern blog about web engineering.',
        'contact_email'          => 'admin@site.com',
        'analytics_code'         => '',
        'adsense_code'           => '',
        'footer_about'           => 'An independent blog about pragmatic web engineering.',
        'copyright_start'        => '2026',
        'social_facebook'        => '',
        'social_twitter'         => '',
        'social_linkedin'        => '',
        'social_instagram'       => '',
        'social_youtube'         => '',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (Database::run('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
                    self::$cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Throwable $e) {
                // DB not installed yet — defaults keep the app alive.
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        $val = $all[$key] ?? $default;
        return ($val === null || $val === '') ? ($default ?? $val) : $val;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
        self::$cache = null; // invalidate
    }

    public static function bool(string $key): bool
    {
        return self::get($key, '0') === '1';
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }
}
