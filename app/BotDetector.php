<?php

/**
 * BotDetector — two-stage bot/spam filter.
 *
 * Stage 1 (isBot):  UA-string check at request time — fast, no DB needed.
 * Stage 2 (isSuspiciousSession): called from SessionService on session_end /
 *          heartbeat absence to flag zero-second / datacenter sessions.
 */
class BotDetector
{
    // ------------------------------------------------------------------ UA markers
    private const BOT_MARKERS = [
        // Search engines
        'googlebot', 'bingbot', 'yandex', 'duckduckbot', 'baiduspider',
        'sogou', 'exabot', 'semrushbot', 'ahrefsbot', 'mj12bot', 'dotbot',
        'petalbot', 'slurp',
        // Generic keywords
        'bot', 'crawl', 'spider',
        // Social / preview
        'facebookexternalhit', 'twitterbot', 'linkedinbot',
        'whatsapp', 'telegrambot', 'vkshare',
        // Monitoring / tools
        'lighthouse', 'pingdom', 'uptimerobot', 'statuscake',
        'wget', 'curl', 'python-requests', 'go-http-client',
        'java/', 'okhttp', 'httpclient', 'axios/', 'node-fetch',
        'scrapy', 'phantomjs', 'headless',
    ];

    // ------------------------------------------------------------------ Known datacenter ASN/IP ranges (CIDR)
    // Singapore / Vultr / DigitalOcean / Linode clusters that generate fake traffic.
    // Extend freely — these are the most common offenders seen in the wild.
    private const SUSPECT_RANGES = [
        // Vultr Singapore
        '45.32.0.0/13',
        '108.61.0.0/16',
        // DigitalOcean
        '143.198.0.0/16',
        '159.89.0.0/16',
        '178.128.0.0/16',
        // Linode / Akamai
        '139.162.0.0/16',
        '172.104.0.0/14',
        // OVH SG
        '51.79.0.0/16',
        '51.161.0.0/16',
        // Alibaba Cloud SG
        '8.209.0.0/16',
        '47.74.0.0/16',
        // Tencent Cloud SG
        '43.153.0.0/16',
        '43.156.0.0/14',
    ];

    // ------------------------------------------------------------------ Stage 1: UA check

    public static function isBot(string $ua): bool
    {
        if (empty($ua)) {
            return true; // empty UA — almost certainly a script
        }

        $lower = strtolower($ua);

        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ Stage 2: Session-level heuristics

    /**
     * Returns true when a session looks like bot/spam traffic.
     *
     * Checks:
     *  - Duration ≤ 1 second with no heartbeat (is_valid = 0)
     *  - IP falls inside a known datacenter range
     *
     * @param array $session  Row from the sessions table or equivalent array:
     *                        ['started_at', 'last_activity', 'is_valid', 'ip']
     */
    public static function isSuspiciousSession(array $session): bool
    {
        $start    = strtotime($session['started_at']    ?? '') ?: 0;
        $last     = strtotime($session['last_activity'] ?? '') ?: 0;
        $isValid  = (int)($session['is_valid'] ?? 0);
        $ip       = $session['ip'] ?? '';

        // Zero/one-second session without a heartbeat
        $duration = $last - $start;
        if ($duration <= 1 && $isValid === 0) {
            return true;
        }

        // IP in known datacenter range
        if ($ip && self::isDatacenterIp($ip)) {
            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------ Helpers

    private static function isDatacenterIp(string $ip): bool
    {
        // IPv6 — skip range check for now (extend later)
        if (str_contains($ip, ':')) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        foreach (self::SUSPECT_RANGES as $cidr) {
            [$net, $bits] = explode('/', $cidr);
            $mask    = ~((1 << (32 - (int)$bits)) - 1);
            $netLong = ip2long($net);
            if (($long & $mask) === ($netLong & $mask)) {
                return true;
            }
        }

        return false;
    }
}
