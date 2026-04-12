<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';
require_once __DIR__ . '/../inc/helpers.php';

// ── Sites ─────────────────────────────────────────────────────────
$allSites = $pdo->query("SELECT * FROM sites ORDER BY name")->fetchAll();

if (($_SESSION['role'] ?? '') === 'site_viewer' && !empty($_SESSION['allowed_sites'])) {
    $allowed = $_SESSION['allowed_sites'];
    $sites   = array_values(array_filter($allSites, fn($s) => in_array((int)$s['id'], $allowed)));
} else {
    $sites = $allSites;
}

$siteIds    = array_column($sites, 'id');
$activeSite = (int)($_GET['site'] ?? ($siteIds[0] ?? 0));

if ($siteIds && !in_array($activeSite, array_map('intval', $siteIds))) {
    $activeSite = (int)($siteIds[0] ?? 0);
}

$period = $_GET['period'] ?? 'today';
$type   = $_GET['type']   ?? 'countries';

$validTypes = ['countries','cities','os','browsers','screens','pages','referrers'];
if (!in_array($type, $validTypes, true)) $type = 'countries';

// ── Period ────────────────────────────────────────────────────────
$periodMap = [
    'today'     => ['label' => __('period.today'),     'days' => 0],
    'yesterday' => ['label' => __('period.yesterday'), 'days' => 1],
    'week'      => ['label' => __('period.7'),         'days' => 7],
    'month'     => ['label' => __('period.30'),        'days' => 30],
    'all'       => ['label' => __('period.all'),       'days' => null],
];

if (!isset($periodMap[$period])) $period = 'today';

if ($period === 'all') {
    $dateCond  = '';
    $dateCond2 = '';
} elseif ($period === 'today') {
    $d = date('Y-m-d');
    $dateCond  = " AND DATE(started_at) = '$d'";
    $dateCond2 = " AND DATE(e.created_at) = '$d'";
} elseif ($period === 'yesterday') {
    $d = date('Y-m-d', strtotime('-1 day'));
    $dateCond  = " AND DATE(started_at) = '$d'";
    $dateCond2 = " AND DATE(e.created_at) = '$d'";
} else {
    $days = $periodMap[$period]['days'];
    $df = date('Y-m-d', strtotime('-' . $days . ' days'));
    $dt = date('Y-m-d');
    $dateCond  = " AND DATE(started_at) BETWEEN '$df' AND '$dt'";
    $dateCond2 = " AND DATE(e.created_at) BETWEEN '$df' AND '$dt'";
}

// ── Helpers ───────────────────────────────────────────────────────
if (!function_exists('q')) {
    function q(PDO $pdo, string $sql, array $p = []): array {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    function qv(PDO $pdo, string $sql, array $p = []) {
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return $s->fetchColumn();
    }

    function pct(int $part, int $total): int {
        return $total > 0 ? (int)round($part / $total * 100) : 0;
    }
}

if (!function_exists('parseFamily')) {
    function parseFamily(string $ua, string $t): string {
        if ($t === 'browser') {
            if (str_contains($ua, 'Edg/'))                               return 'Edge';
            if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) return 'Opera';
            if (str_contains($ua, 'Chrome'))                             return 'Chrome';
            if (str_contains($ua, 'Firefox'))                            return 'Firefox';
            if (str_contains($ua, 'Safari'))                             return 'Safari';
            return 'Other';
        }

        if (str_contains($ua, 'Windows'))                                return 'Windows';
        if (str_contains($ua, 'Mac OS'))                                 return 'macOS';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))    return 'iOS';
        if (str_contains($ua, 'Android'))                                return 'Android';
        if (str_contains($ua, 'Linux'))                                  return 'Linux';

        return 'Other';
    }
}

// ── Pagination ────────────────────────────────────────────────────
$perPage = 50;
$pg      = max(1, (int)($_GET['pg'] ?? 1));
$offset  = ($pg - 1) * $perPage;

// ── Load data ─────────────────────────────────────────────────────
$rows     = [];
$total    = 0;
$maxCount = 1;

if ($type === 'countries') {

    $total = (int)qv($pdo,
        "SELECT COUNT(DISTINCT country_code)
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND country IS NOT NULL$dateCond",
        [$activeSite]);

    $rows = q($pdo,
        "SELECT country, country_code, COUNT(DISTINCT session_id) as cnt
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND country IS NOT NULL$dateCond
         GROUP BY country, country_code
         ORDER BY cnt DESC
         LIMIT $perPage OFFSET $offset",
        [$activeSite]);

} elseif ($type === 'cities') {

    $total = (int)qv($pdo,
        "SELECT COUNT(DISTINCT city)
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND city IS NOT NULL$dateCond",
        [$activeSite]);

    $rows = q($pdo,
        "SELECT city, country, country_code, COUNT(DISTINCT session_id) as cnt
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND city IS NOT NULL$dateCond
         GROUP BY city, country, country_code
         ORDER BY cnt DESC
         LIMIT $perPage OFFSET $offset",
        [$activeSite]);

} elseif ($type === 'os' || $type === 'browsers') {

    $parseType = ($type === 'browsers') ? 'browser' : 'os';

    $uaRows = q($pdo,
        "SELECT user_agent, COUNT(*) as cnt
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND user_agent IS NOT NULL$dateCond
         GROUP BY user_agent",
        [$activeSite]);

    $grouped = [];

    foreach ($uaRows as $row) {
        $key = parseFamily($row['user_agent'], $parseType);
        $grouped[$key] = ($grouped[$key] ?? 0) + (int)$row['cnt'];
    }

    arsort($grouped);

    $total = count($grouped);

    foreach (array_slice($grouped, $offset, $perPage, true) as $name => $cnt) {
        $rows[] = ['name' => $name, 'cnt' => $cnt];
    }

} elseif ($type === 'screens') {

    $total = (int)qv($pdo,
        "SELECT COUNT(DISTINCT screen)
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND screen IS NOT NULL$dateCond",
        [$activeSite]);

    $rows = q($pdo,
        "SELECT screen, COUNT(*) as cnt
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1 AND screen IS NOT NULL$dateCond
         GROUP BY screen
         ORDER BY cnt DESC
         LIMIT $perPage OFFSET $offset",
        [$activeSite]);

} elseif ($type === 'pages') {

    $total = (int)qv($pdo,
        "SELECT COUNT(DISTINCT e.path)
         FROM events e
         JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id
         WHERE e.site_id=? AND e.event_type='page_view'
           AND s.is_bot=0 AND s.is_valid=1$dateCond2",
        [$activeSite]);

    $rows = q($pdo,
        "SELECT 
            e.path,
            ANY_VALUE(e.title) as title,
            COUNT(*) as cnt
         FROM events e
         JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id
         WHERE e.site_id=? AND e.event_type='page_view'
           AND s.is_bot=0 AND s.is_valid=1$dateCond2
         GROUP BY e.path
         ORDER BY cnt DESC
         LIMIT $perPage OFFSET $offset",
        [$activeSite]);

} elseif ($type === 'referrers') {

    $total = (int)qv($pdo,
        "SELECT COUNT(DISTINCT LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '/', 3), '//', -1)))
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1
           AND referrer IS NOT NULL AND referrer != ''$dateCond",
        [$activeSite]);

    $rows = q($pdo,
        "SELECT 
            LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '/', 3), '//', -1)) as host,
            MIN(referrer) as referrer,
            COUNT(*) as cnt
         FROM sessions
         WHERE site_id=? AND is_bot=0 AND is_valid=1
           AND referrer IS NOT NULL AND referrer != ''$dateCond
         GROUP BY host
         ORDER BY cnt DESC
         LIMIT $perPage OFFSET $offset",
        [$activeSite]);
}

if ($rows) {
    $maxCount = max(array_column($rows, 'cnt'));
}

$totalPages = max(1, (int)ceil($total / $perPage));
$pg         = min($pg, $totalPages);

// ── Active site ───────────────────────────────────────────────────
$activeSiteName = '';
$activeDomain   = '';

foreach ($sites as $s) {
    if ((int)$s['id'] === $activeSite) {
        $activeSiteName = $s['name'];
        $activeDomain   = $s['domain'];
    }
}

$typeLabels = [
    'countries' => __('table.countries'),
    'cities'    => __('table.cities'),
    'os'        => __('table.os'),
    'browsers'  => __('table.browsers'),
    'screens'   => __('table.screens'),
    'pages'     => __('table.top_pages'),
    'referrers' => __('table.referrers'),
];

$layoutTitle    = ($typeLabels[$type] ?? $type) . ' — Statist';
$layoutSection  = 'dashboard';
$layoutExtraCss = ['/assets/css/dashboard.css', '/assets/css/stats.css'];
$layoutExtraJs  = [];
$view           = __DIR__ . '/../views/stats.view.php';

require __DIR__ . '/../views/layout.php';