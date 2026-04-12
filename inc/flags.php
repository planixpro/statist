<?php
/**
 * Flag helper — safe for multiple includes.
 *
 * Flags: /storage/flags/{iso2}.webp
 */

if (!function_exists('flag_iso')) {

    function _flag_map(): array {
        return [
            'en' => 'gb',
            'zh' => 'cn',
            'ja' => 'jp',
            'ko' => 'kr',
            'ar' => 'sa',
            'he' => 'il',
            'uk' => 'ua',
            'be' => 'by',
        ];
    }

    function flag_iso(string $code): string {
        $code = strtolower(trim($code));
        $map  = _flag_map();
        return $map[$code] ?? $code;
    }

    function _flag_base_path(): string {
        static $path = null;
        if ($path === null) {
            $path = dirname(__DIR__) . '/storage/flags/';
        }
        return $path;
    }

    function flag_exists(string $iso): bool {
        static $cache = [];

        if (isset($cache[$iso])) {
            return $cache[$iso];
        }

        $file = _flag_base_path() . $iso . '.webp';
        return $cache[$iso] = file_exists($file);
    }

    function flag_url(string $iso): string {
        return '/storage/flags/' . $iso . '.webp';
    }

    /**
     * Returns <img> if flag file exists, otherwise <span>XX</span>
     */
    function flag_img(string $code, string $label = '', string $size = '20px'): string {
        if ($code === '' || $code === null) {
            return '';
        }

        $iso = flag_iso($code);
        $alt = htmlspecialchars($label ?: strtoupper($iso), ENT_QUOTES, 'UTF-8');

        if (flag_exists($iso)) {
            $url = htmlspecialchars(flag_url($iso), ENT_QUOTES, 'UTF-8');

            return sprintf(
                '<img src="%s" alt="%s" title="%s" '
                . 'style="width:%s;height:auto;border-radius:2px;'
                . 'vertical-align:middle;flex-shrink:0;display:inline-block" '
                . 'loading="lazy">',
                $url,
                $alt,
                $alt,
                $size
            );
        }

        return sprintf(
            '<span title="%s" style="font-family:\'JetBrains Mono\',monospace;'
            . 'font-size:10px;color:var(--muted);background:var(--bg);'
            . 'border:1px solid var(--border);border-radius:3px;'
            . 'padding:1px 4px;vertical-align:middle;flex-shrink:0;'
            . 'display:inline-block">%s</span>',
            $alt,
            htmlspecialchars(strtoupper($iso), ENT_QUOTES, 'UTF-8')
        );
    }

}