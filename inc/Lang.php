<?php

/**
 * Lang — simple JSON-based i18n.
 *
 * Usage:
 *   Lang::load('en');          // load locale (called from auth.php)
 *   __('key')                  // translate
 *   __('key', 'Arg')           // with sprintf substitution
 *   Lang::available()          // ['en' => 'English', 'ru' => 'Русский', ...]
 */
class Lang
{
    private static array  $strings  = [];
    private static array  $fallback = [];
    private static string $locale   = 'en';
    private static string $langDir  = '';

    // Language names shown in the UI selector
    private const NAMES = [
        'en' => 'English',
        'ru' => 'Русский',
        'bg' => 'Български',
        'uk' => 'Українська',

        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'pt' => 'Português',

        'zh' => '中文',
        'ja' => '日本語',
        'hi' => 'हिन्दी',
    ];

    public static function load(string $locale): void
    {
        self::$langDir = dirname(__DIR__) . '/lang/';

        // sanitize locale
        $locale = preg_replace('/[^a-z]/', '', strtolower($locale));

        $file = self::$langDir . $locale . '.json';
        if (!file_exists($file)) {
            $locale = 'en';
            $file   = self::$langDir . 'en.json';
        }

        self::$locale = $locale;

        // load selected locale
        $contents = @file_get_contents($file);
        self::$strings = $contents ? (json_decode($contents, true) ?? []) : [];

        // load fallback (en)
        if ($locale !== 'en') {
            $fallbackFile = self::$langDir . 'en.json';
            $fallbackContents = @file_get_contents($fallbackFile);

            self::$fallback = $fallbackContents
                ? (json_decode($fallbackContents, true) ?? [])
                : [];
        } else {
            self::$fallback = self::$strings;
        }
    }

    public static function get(string $key, ...$args): string
    {
        $str = self::$strings[$key]
            ?? self::$fallback[$key]
            ?? $key;

        if (!$args) {
            return $str;
        }

        try {
            return vsprintf($str, $args);
        } catch (\Throwable $e) {
            return $str;
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * Returns locales that have a lang/*.json file,
     * merged with known display names.
     *
     * @return array<string, string>
     */
    public static function available(): array
    {
        if (!self::$langDir) {
            self::$langDir = dirname(__DIR__) . '/lang/';
        }

        $out = [];

        foreach (glob(self::$langDir . '*.json') as $file) {
            $code = basename($file, '.json');

            // allow only 2-letter locale codes
            if (!preg_match('/^[a-z]{2}$/', $code)) {
                continue;
            }

            $out[$code] = self::NAMES[$code] ?? strtoupper($code);
        }

        ksort($out);
        return $out;
    }
}

// Global shorthand — safe for multiple includes
if (!function_exists('__')) {
    function __(string $key, ...$args): string
    {
        return Lang::get($key, ...$args);
    }
}