<?php
require __DIR__ . '/auth.php';   // also loads Lang
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';

// ── Сайты ────────────────────────────────────────────────────────
$allSites = $pdo->query("SELECT * FROM sites ORDER BY name")->fetchAll();

if (($_SESSION['role'] ?? '') === 'site_viewer' && !empty($_SESSION['allowed_sites'])) {
    $allowed = $_SESSION['allowed_sites'];
    $sites   = array_values(array_filter($allSites, fn($s) => in_array((int)$s['id'], $allowed)));
} else {
    $sites = $allSites;
}

$siteIds    = array_column($sites, 'id');
$activeSite = (int)($_GET['site'] ?? ($siteIds[0] ?? 0));
$period     = $_GET['period'] ?? 'today';

// ── Период ───────────────────────────────────────────────────────
$periodMap = [
    'today'     => ['label' => __('period.today'),     'days' => 0],
    'yesterday' => ['label' => __('period.yesterday'), 'days' => 1],
    '7'         => ['label' => __('period.7'),         'days' => 7],
    '30'        => ['label' => __('period.30'),        'days' => 30],
    'all'       => ['label' => __('period.all'),       'days' => null],
];
if (!isset($periodMap[$period])) $period = 'today';

if ($period === 'all') {
    $dateFrom = null;
    $dateTo   = null;
} elseif ($period === 'today') {
    $dateFrom = $dateTo = date('Y-m-d');
} elseif ($period === 'yesterday') {
    $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
} else {
    $dateFrom = date('Y-m-d', strtotime('-' . $periodMap[$period]['days'] . ' days'));
    $dateTo   = date('Y-m-d');
}

// ── Фильтр страны ────────────────────────────────────────────────
$country = strtoupper(trim($_GET['country'] ?? ''));
if (!preg_match('/^[A-Z]{2}$/', $country)) $country = '';

// ── Вспомогательные функции ──────────────────────────────────────
if (!function_exists('q')) {
    function q(PDO $pdo, string $sql, array $p = []): array {
        $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll();
    }
    function qv(PDO $pdo, string $sql, array $p = []) {
        $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn();
    }
    function pct(int $part, int $total): int {
        return $total > 0 ? (int)round($part / $total * 100) : 0;
    }
}

if (!function_exists('pageUrl')) {
    function pageUrl(int $pg, string $key = 'pg'): string {
        $params = $_GET; $params[$key] = $pg;
        return '?' . http_build_query($params);
    }
}

if (!function_exists('parseFamily')) {
    function parseFamily(string $ua, string $type): string {
        if ($type === 'browser') {
            if (str_contains($ua, 'Edg/'))                              return 'Edge';
            if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) return 'Opera';
            if (str_contains($ua, 'Chrome'))                            return 'Chrome';
            if (str_contains($ua, 'Firefox'))                           return 'Firefox';
            if (str_contains($ua, 'Safari'))                            return 'Safari';
            return 'Other';
        }
        if (str_contains($ua, 'Windows'))                               return 'Windows';
        if (str_contains($ua, 'Mac OS'))                                return 'macOS';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))  return 'iOS';
        if (str_contains($ua, 'Android'))                               return 'Android';
        if (str_contains($ua, 'Linux'))                                 return 'Linux';
        return 'Other';
    }
}

// ── Построитель WHERE для дат ────────────────────────────────────
// Возвращает [whereClause, params]
function dateWhere(string $col, ?string $from, ?string $to): array {
    if ($from === null) return ['', []];
    return [" AND DATE($col) BETWEEN ? AND ?", [$from, $to]];
}

[$dateWhereSess,  $dateParamsSess]  = dateWhere('started_at',   $dateFrom, $dateTo);
[$dateWhereEvt,   $dateParamsEvt]   = dateWhere('e.created_at', $dateFrom, $dateTo);
[$dateWhereSessS, $dateParamsSessS] = dateWhere('s.started_at', $dateFrom, $dateTo);

$cWhere  = $country !== '' ? ' AND UPPER(country_code) = ?'   : '';
$cWhereJ = $country !== '' ? ' AND UPPER(s.country_code) = ?' : '';
$cParam  = $country !== '' ? [$country] : [];

// Базовый набор параметров
$pBase  = [$activeSite];
$pBaseJ = [$activeSite];

// ── Метрики ──────────────────────────────────────────────────────
$totalVisitors  = (int)qv($pdo,
    "SELECT COUNT(DISTINCT session_id) FROM sessions WHERE site_id=? AND is_bot=0 AND is_valid=1$dateWhereSess$cWhere",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

$totalPageviews = (int)qv($pdo,
    "SELECT COUNT(*) FROM events e JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id
     WHERE e.site_id=? AND e.event_type='page_view' AND s.is_bot=0 AND s.is_valid=1$dateWhereEvt$cWhereJ",
    [...$pBaseJ, ...$dateParamsEvt, ...$cParam]);

$totalSessions  = (int)qv($pdo,
    "SELECT COUNT(*) FROM sessions WHERE site_id=? AND is_bot=0 AND is_valid=1$dateWhereSess$cWhere",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

$totalBots      = (int)qv($pdo,
    "SELECT COUNT(*) FROM sessions WHERE site_id=? AND is_bot=1$dateWhereSess$cWhere",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

// ── График ───────────────────────────────────────────────────────
function buildChartSeries(array $rawData, ?string $dateFrom, ?string $dateTo, array $chartDaysRef = []): array {
    if ($dateFrom !== null) {
        $cur  = strtotime($dateFrom);
        $end  = strtotime($dateTo);
        $map  = array_column($rawData, 'cnt', 'd');
        $days = $vals = [];
        while ($cur <= $end) {
            $d      = date('Y-m-d', $cur);
            $days[] = date('d.m', $cur);
            $vals[] = (int)($map[$d] ?? 0);
            $cur    = strtotime('+1 day', $cur);
        }
        return ['days' => $days, 'vals' => $vals];
    }
    $monthMap = [];
    foreach ($rawData as $row) {
        $m = date('Y-m', strtotime($row['d']));
        $monthMap[$m] = ($monthMap[$m] ?? 0) + (int)$row['cnt'];
    }
    return [
        'days' => array_map(fn($k) => date('m.Y', strtotime($k . '-01')), array_keys($monthMap)),
        'vals' => array_values($monthMap),
    ];
}

$rawPageviews = q($pdo,
    "SELECT DATE(e.created_at) as d, COUNT(*) as cnt
     FROM events e JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id
     WHERE e.site_id=? AND e.event_type='page_view' AND s.is_bot=0 AND s.is_valid=1$dateWhereEvt$cWhereJ
     GROUP BY DATE(e.created_at) ORDER BY d ASC",
    [...$pBaseJ, ...$dateParamsEvt, ...$cParam]);

$rawSessions = q($pdo,
    "SELECT DATE(started_at) as d, COUNT(*) as cnt
     FROM sessions WHERE site_id=? AND is_bot=0 AND is_valid=1$dateWhereSess$cWhere
     GROUP BY DATE(started_at) ORDER BY d ASC",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

$rawVisitors = q($pdo,
    "SELECT DATE(started_at) as d, COUNT(DISTINCT session_id) as cnt
     FROM sessions WHERE site_id=? AND is_bot=0 AND is_valid=1$dateWhereSess$cWhere
     GROUP BY DATE(started_at) ORDER BY d ASC",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

$pvSeries  = buildChartSeries($rawPageviews, $dateFrom, $dateTo);
$ssSeries  = buildChartSeries($rawSessions,  $dateFrom, $dateTo);
$viSeries  = buildChartSeries($rawVisitors,  $dateFrom, $dateTo);

$chartDays       = $pvSeries['days'];
$chartPageviews  = $pvSeries['vals'];
$chartSessCounts = $ssSeries['vals'];
$chartVistCounts = $viSeries['vals'];

// ── Топ страниц ───────────────────────────────────────────────────
$topPages = q($pdo,
    "SELECT e.path, COUNT(*) as cnt
     FROM events e JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id
     WHERE e.site_id=? AND e.event_type='page_view' AND s.is_bot=0 AND s.is_valid=1$dateWhereEvt$cWhereJ
     GROUP BY e.path ORDER BY cnt DESC LIMIT 10",
    [...$pBaseJ, ...$dateParamsEvt, ...$cParam]);

// Страны — без фильтра по стране, чтобы список был полным
$topCountries = q($pdo,
    "SELECT country, country_code, COUNT(DISTINCT session_id) as cnt
     FROM sessions
     WHERE site_id=? AND is_bot=0 AND is_valid=1 AND country IS NOT NULL$dateWhereSess
     GROUP BY country, country_code ORDER BY cnt DESC LIMIT 8",
    [...$pBase, ...$dateParamsSess]);

$topCities = q($pdo,
    "SELECT city, COUNT(DISTINCT session_id) as cnt
     FROM sessions
     WHERE site_id=? AND is_bot=0 AND is_valid=1 AND city IS NOT NULL$dateWhereSess$cWhere
     GROUP BY city ORDER BY cnt DESC LIMIT 8",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

$referrers = q($pdo,
    "SELECT referrer, COUNT(*) as cnt
     FROM sessions
     WHERE site_id=? AND is_bot=0 AND is_valid=1
       AND referrer IS NOT NULL AND referrer != ''$dateWhereSess$cWhere
     GROUP BY referrer ORDER BY cnt DESC LIMIT 10",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

// ── Браузеры и ОС ─────────────────────────────────────────────────
$uaRows = q($pdo,
    "SELECT user_agent, COUNT(*) as cnt
     FROM sessions
     WHERE site_id=? AND is_bot=0 AND is_valid=1 AND user_agent IS NOT NULL$dateWhereSess$cWhere
     GROUP BY user_agent ORDER BY cnt DESC",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

$browsers = [];
$oses     = [];
foreach ($uaRows as $row) {
    $b = parseFamily($row['user_agent'], 'browser');
    $o = parseFamily($row['user_agent'], 'os');
    $browsers[$b] = ($browsers[$b] ?? 0) + (int)$row['cnt'];
    $oses[$o]     = ($oses[$o]     ?? 0) + (int)$row['cnt'];
}
arsort($browsers);
arsort($oses);

$screens = q($pdo,
    "SELECT screen, COUNT(*) as cnt
     FROM sessions
     WHERE site_id=? AND is_bot=0 AND is_valid=1 AND screen IS NOT NULL$dateWhereSess$cWhere
     GROUP BY screen ORDER BY cnt DESC LIMIT 6",
    [...$pBase, ...$dateParamsSess, ...$cParam]);

// ── Сессии (трафик + боты, единый список) ─────────────────────────
$perPage = 50;

$pgSess       = max(1, (int)($_GET['pg'] ?? 1));
$totalAllSess = (int)qv($pdo,
    "SELECT COUNT(*) FROM sessions WHERE site_id=? AND (is_bot=1 OR is_valid=1)$dateWhereSess$cWhere",
    [...$pBase, ...$dateParamsSess, ...$cParam]);
$totalPgSess  = max(1, (int)ceil($totalAllSess / $perPage));
$pgSess       = min($pgSess, $totalPgSess);
$offsetSess   = ($pgSess - 1) * $perPage;

$allSessions = q($pdo,
    "SELECT s.session_id, s.ip, s.country, s.country_code, s.city, s.referrer,
            s.screen, s.language, s.started_at, s.user_agent,
            s.is_bot, s.bot_score, s.blocked_reason, s.is_valid,
            COUNT(e.id) as events
     FROM sessions s
     LEFT JOIN events e ON e.session_id = s.session_id AND e.site_id = s.site_id
     WHERE s.site_id=? AND (s.is_bot=1 OR s.is_valid=1)$dateWhereSessS$cWhereJ
     GROUP BY s.id ORDER BY s.started_at DESC
     LIMIT $perPage OFFSET $offsetSess",
    [...$pBaseJ, ...$dateParamsSessS, ...$cParam]);

// ── Активный сайт ─────────────────────────────────────────────────
$activeSiteName = '';
$activeDomain   = '';
foreach ($sites as $s) {
    if ((int)$s['id'] === $activeSite) {
        $activeSiteName = $s['name'];
        $activeDomain   = $s['domain'];
    }
}

// ── Рендер ───────────────────────────────────────────────────────
$layoutTitle    = 'Statist — ' . $activeSiteName;
$layoutSection  = 'dashboard';
$layoutExtraCss = ['/assets/css/dashboard.css'];
$layoutExtraJs  = [
    'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
    '/assets/js/dashboard.js',
];
$view = __DIR__ . '/../views/dashboard.view.php';

require __DIR__ . '/../views/layout.php';
