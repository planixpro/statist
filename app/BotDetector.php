<?php

class BotDetector
{
    private const BOT_MARKERS = [
        'bot', 'crawl', 'spider', 'slurp',
        'googlebot', 'bingbot', 'yandex', 'duckduckbot',
        'baiduspider', 'sogou', 'exabot', 'semrushbot',
        'ahrefsbot', 'mj12bot', 'dotbot', 'petalbot',

        'facebookexternalhit', 'twitterbot', 'linkedinbot',
        'whatsapp', 'telegrambot', 'vkshare',

        'lighthouse', 'pingdom', 'uptimerobot', 'statuscake',
        'wget', 'curl', 'python-requests', 'go-http-client',
        'java/', 'okhttp', 'httpclient',
    ];

    private const HEADLESS_MARKERS = [
        'headlesschrome',
        'puppeteer',
        'playwright',
        'phantomjs',
        'selenium',
    ];

    // Паттерны путей, характерных для сканеров/спамеров
    private const SCAN_PATH_PATTERNS = [
        '~^/(profile|user|member)/\d+$~i',
        '~^/(profile|user|member)/[a-z0-9\-_]{8,}$~i',
        // Длинный числовой ID в конце пути (/item/99999999)
        '~/\d{7,}~',
    ];

    private const SUSPICIOUS_PROVIDERS = [
        'amazon',
        'aws',
        'amazon technologies',
        'digitalocean',
        'ovh',
        'google cloud',
        'microsoft azure',
        'linode',
        'hetzner',
        'contabo',
        'vultr',
        'oracle cloud',
        'alibaba cloud',
        'tencent cloud',
    ];

    // ----------------------------------------------------------------
    // Базовые детекторы
    // ----------------------------------------------------------------

    public static function isBot(string $ua): bool
    {
        if ($ua === '') {
            return true;
        }

        $ua = mb_strtolower($ua);

        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function isHeadless(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        $ua = mb_strtolower($ua);

        foreach (self::HEADLESS_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * UA не похож на браузер.
     * Firefox: Mozilla/5.0 ... Gecko/... Firefox/...  (нет 'safari', нет 'applewebkit')
     * Chrome:  Mozilla/5.0 ... AppleWebKit/... Chrome/... Safari/...
     * Safari:  Mozilla/5.0 ... AppleWebKit/... Safari/...
     */
    public static function isWeirdUA(string $ua): bool
    {
        if ($ua === '') {
            return true;
        }

        $ual = mb_strtolower($ua);

        if (!str_contains($ual, 'mozilla/5.0')) {
            return true;
        }

        $hasWebkit = str_contains($ual, 'applewebkit');
        $hasGecko  = str_contains($ual, 'gecko/');
        $hasChrome = str_contains($ual, 'chrome/');
        $hasSafari = str_contains($ual, 'safari/');

        // Chrome/Edge/Opera: должны иметь applewebkit И safari
        if ($hasChrome && (!$hasWebkit || !$hasSafari)) {
            return true;
        }

        // Если нет ни webkit, ни gecko — подозрительно
        if (!$hasWebkit && !$hasGecko) {
            return true;
        }

        return false;
    }

    public static function isFakeChrome(string $ua): bool
    {
        return (bool)preg_match('~Chrome/\d+\.0\.0\.0~i', $ua);
    }

    public static function isStaleChrome(string $ua): bool
    {
        if (preg_match('~Chrome/(\d+)~i', $ua, $m)) {
            $version = (int)$m[1];
            return $version > 0 && $version < 110;
        }

        return false;
    }

    public static function isScanLikePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        foreach (self::SCAN_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    public static function isBadFingerprint(?string $fp): bool
    {
        $fp = trim((string)$fp);
        return $fp === '' || mb_strlen($fp) < 8;
    }

    public static function isBadScreen(?string $screen, string $ua = ''): bool
    {
        $screen = trim((string)$screen);
        $ual    = mb_strtolower($ua);

        if ($screen === '' || !preg_match('~^\d{2,5}x\d{2,5}$~', $screen)) {
            return true;
        }

        [$w, $h] = array_map('intval', explode('x', $screen, 2));

        // Совсем маленькие экраны (менее 240 — нереально для браузера)
        if ($w < 240 || $h < 240) {
            return true;
        }

        // На Windows экран уже 700 — явно не браузер
        if (str_contains($ual, 'windows') && $w < 700) {
            return true;
        }

        return false;
    }

    public static function isSuspiciousProvider(?string $provider): bool
    {
        $provider = mb_strtolower(trim((string)$provider));
        if ($provider === '') {
            return false;
        }

        foreach (self::SUSPICIOUS_PROVIDERS as $needle) {
            if (str_contains($provider, $needle)) {
                return true;
            }
        }

        return false;
    }

    // ----------------------------------------------------------------
    // Scoring
    // ----------------------------------------------------------------

    /**
     * Считает «бот-очки» сессии.
     *
     * Пороги classify():
     *   >= 15 → bot
     *   >= 8  → suspicious
     *   < 8   → good
     *
     * Логика подбора весов:
     * - Реальный новый пользователь (1 page_view, duration=0, js=1, нормальный UA,
     *   нормальный экран, нет fingerprint ещё) должен набирать < 8.
     * - Типичный скрапер без JS, с кривым UA, без экрана — должен набирать >= 15.
     */
    public static function score(array $ctx): int
    {
        $ua        = (string)($ctx['ua'] ?? '');
        $path      = (string)($ctx['path'] ?? '');
        $referrer  = (string)($ctx['referrer'] ?? '');
        $fp        = (string)($ctx['fp'] ?? '');
        $screen    = (string)($ctx['screen'] ?? '');
        $js        = (int)($ctx['js'] ?? 0);
        $events    = max(0, (int)($ctx['events_count'] ?? 0));
        $duration  = max(0, (int)($ctx['duration'] ?? 0));
        $provider  = (string)($ctx['provider'] ?? '');

        $score = 0;

        // --- Явные боты (тяжёлые флаги) ---

        if (self::isBot($ua)) {
            $score += 20;
        }

        if (self::isHeadless($ua)) {
            $score += 15;
        }

        // --- Без JS (не прошёл клиентскую проверку) ---
        if ($js !== 1) {
            $score += 6;
        }

        // --- UA-аномалии ---

        if (self::isWeirdUA($ua)) {
            $score += 4;
        }

        if (self::isFakeChrome($ua)) {
            $score += 3;
        }

        if (self::isStaleChrome($ua)) {
            $score += 2;
        }

        // --- Поведенческие ---
        // Только одно событие И прошло меньше 5 сек — очень подозрительно,
        // но НЕ достаточно само по себе, чтобы стать ботом.
        if ($events <= 1 && $duration < 5) {
            $score += 3;
        }

        // --- Технические сигналы ---

        if (self::isBadFingerprint($fp)) {
            $score += 3;
        }

        if (self::isBadScreen($screen, $ua)) {
            $score += 3;
        }

        // --- Путь + контекст ---

        if (self::isScanLikePath($path) && $referrer === '') {
            $score += 4;
        }

        // --- Провайдер ---

        if (self::isSuspiciousProvider($provider)) {
            $score += 3;
        }

        return min($score, 100);
    }

    /**
     * Классификация по итоговому баллу.
     *
     * Нормальный реальный пользователь:
     *   js=1, нормальный UA, нормальный экран, fingerprint есть, 1-2 события → ~3-6 баллов → good
     *
     * Подозрительный (без referrer, мало событий, мобильный с маленьким экраном):
     *   ~8-14 баллов → suspicious (считается как трафик, не бот)
     *
     * Очевидный бот:
     *   кривой UA + без JS + без экрана → 13+ баллов → bot
     */
    public static function classify(int $score): string
    {
        if ($score >= 15) {
            return 'bot';
        }

        if ($score >= 8) {
            return 'suspicious';
        }

        return 'good';
    }

    // ----------------------------------------------------------------
    // Real-time блокировка (применяется на входе, до записи в БД)
    // ----------------------------------------------------------------

    /**
     * Блокирует только явно плохих: известных ботов, headless и
     * сканеры без JS по подозрительным путям.
     * НЕ блокирует по провайдеру — это только скоринг.
     */
    public static function shouldBlockRealtime(array $ctx): bool
    {
        $ua   = (string)($ctx['ua'] ?? '');
        $js   = (int)($ctx['js'] ?? 0);
        $path = (string)($ctx['path'] ?? '');

        if (self::isBot($ua)) {
            return true;
        }

        if (self::isHeadless($ua)) {
            return true;
        }

        // Без JS И сканирует подозрительный путь
        if ($js !== 1 && self::isScanLikePath($path)) {
            return true;
        }

        return false;
    }
}
