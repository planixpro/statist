<?php
/**
 * Flag helper — safe for multiple includes.
 *
 * Flags: /storage/flags/{iso2}.webp  (lowercase ISO 3166-1 alpha-2)
 * Usage: flag_img('DE'), flag_img('en', 'English', '18px')
 */

if (!function_exists('flag_iso')) {

    // Language code → country flag (only codes that differ from country ISO)
    function _flag_map(): array {
        return [
            'en' => 'gb', 'zh' => 'cn', 'ja' => 'jp',
            'ko' => 'kr', 'ar' => 'sa', 'he' => 'il',
            'uk' => 'ua', 'be' => 'by',
        ];
    }

    function flag_iso(string $code): string {
        $code = strtolower(trim($code));
        $map  = _flag_map();
        return $map[$code] ?? $code;
    }

    function flag_exists(string $code): bool {
        $file = dirname(__DIR__) . '/storage/flags/' . flag_iso($code) . '.webp';
        return $file !== '' && file_exists($file);
    }

    function flag_url(string $code): string {
        return '/storage/flags/' . flag_iso($code) . '.webp';
    }

    /**
     * Returns <img> if flag file exists, otherwise <span>XX</span> fallback.
     */
    function flag_img(string $code, string $label = '', string $size = '20px'): string {
        if ($code === '' || $code === null) return '';

        $iso   = flag_iso($code);
        $alt   = htmlspecialchars($label ?: strtoupper($iso));

        if (flag_exists($code)) {
            $url = htmlspecialchars(flag_url($code));
            return sprintf(
                '<img src="%s" alt="%s" title="%s" '
                . 'style="width:%s;height:auto;border-radius:2px;'
                . 'vertical-align:middle;flex-shrink:0;display:inline-block" '
                . 'loading="lazy">',
                $url, $alt, $alt, $size
            );
        }

        return sprintf(
            '<span title="%s" style="font-family:\'JetBrains Mono\',monospace;'
            . 'font-size:10px;color:var(--muted);background:var(--bg);'
            . 'border:1px solid var(--border);border-radius:3px;'
            . 'padding:1px 4px;vertical-align:middle;flex-shrink:0;'
            . 'display:inline-block">%s</span>',
            $alt, htmlspecialchars(strtoupper($iso))
        );
    }

}
