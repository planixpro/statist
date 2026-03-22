<?php
require __DIR__ . '/auth.php';   // also loads Lang
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';

$allSites   = $pdo->query("SELECT * FROM sites ORDER BY name")->fetchAll();

// site_viewer sees only assigned sites
if (($_SESSION['role'] ?? '') === 'site_viewer' && !empty($_SESSION['allowed_sites'])) {
    $allowed = $_SESSION['allowed_sites'];
    $sites   = array_filter($allSites, fn($s) => in_array((int)$s['id'], $allowed));
    $sites   = array_values($sites);
} else {
    $sites = $allSites;
}
$siteIds    = array_column($sites, 'id');
$activeSite = (int)($_GET['site'] ?? ($siteIds[0] ?? 0));
$period     = $_GET['period'] ?? '7';

$periodMap = [
    'today'     => ['label' => __('period.today'),     'days' => 0],
    'yesterday' => ['label' => __('period.yesterday'), 'days' => 1],
    '7'         => ['label' => __('period.7'),         'days' => 7],
    '30'        => ['label' => __('period.30'),        'days' => 30],
];
if (!isset($periodMap[$period])) $period = '7';

if ($period === 'today') {
    $dateFrom = $dateTo = date('Y-m-d');
} elseif ($period === 'yesterday') {
    $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
} else {
    $dateFrom = date('Y-m-d', strtotime('-' . $periodMap[$period]['days'] . ' days'));
    $dateTo   = date('Y-m-d');
}

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

$p = [$activeSite, $dateFrom, $dateTo];

// All session queries exclude flagged bots (is_bot = 0)
$totalVisitors  = (int)qv($pdo, "SELECT COUNT(DISTINCT session_id) FROM sessions WHERE site_id=? AND is_bot=0 AND DATE(started_at) BETWEEN ? AND ?", $p);
$totalPageviews = (int)qv($pdo, "SELECT COUNT(*) FROM events e JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id WHERE e.site_id=? AND e.event_type='page_view' AND s.is_bot=0 AND DATE(e.created_at) BETWEEN ? AND ?", $p);
$totalSessions  = (int)qv($pdo, "SELECT COUNT(*) FROM sessions WHERE site_id=? AND is_bot=0 AND DATE(started_at) BETWEEN ? AND ?", $p);
$totalBots      = (int)qv($pdo, "SELECT COUNT(*) FROM sessions WHERE site_id=? AND is_bot=1 AND DATE(started_at) BETWEEN ? AND ?", $p);

$chartData = q($pdo, "SELECT DATE(e.created_at) as d, COUNT(*) as cnt FROM events e JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id WHERE e.site_id=? AND e.event_type='page_view' AND s.is_bot=0 AND DATE(e.created_at) BETWEEN ? AND ? GROUP BY DATE(e.created_at) ORDER BY d ASC", $p);

$topPages     = q($pdo, "SELECT e.path, COUNT(*) as cnt FROM events e JOIN sessions s ON s.session_id=e.session_id AND s.site_id=e.site_id WHERE e.site_id=? AND e.event_type='page_view' AND s.is_bot=0 AND DATE(e.created_at) BETWEEN ? AND ? GROUP BY e.path ORDER BY cnt DESC LIMIT 10", $p);
$topCountries = q($pdo, "SELECT country, country_code, COUNT(DISTINCT session_id) as cnt FROM sessions WHERE site_id=? AND is_bot=0 AND country IS NOT NULL AND DATE(started_at) BETWEEN ? AND ? GROUP BY country, country_code ORDER BY cnt DESC LIMIT 8", $p);
$topCities    = q($pdo, "SELECT city, COUNT(DISTINCT session_id) as cnt FROM sessions WHERE site_id=? AND is_bot=0 AND city IS NOT NULL AND DATE(started_at) BETWEEN ? AND ? GROUP BY city ORDER BY cnt DESC LIMIT 8", $p);
$referrers    = q($pdo, "SELECT referrer, COUNT(*) as cnt FROM sessions WHERE site_id=? AND is_bot=0 AND referrer IS NOT NULL AND referrer != '' AND DATE(started_at) BETWEEN ? AND ? GROUP BY referrer ORDER BY cnt DESC LIMIT 10", $p);

$uaRows = q($pdo, "SELECT user_agent, COUNT(*) as cnt FROM sessions WHERE site_id=? AND is_bot=0 AND user_agent IS NOT NULL AND DATE(started_at) BETWEEN ? AND ? GROUP BY user_agent ORDER BY cnt DESC", $p);

if (!function_exists('parseFamily')) {
function parseFamily(string $ua, string $type): string {
    if ($type === 'browser') {
        if (str_contains($ua, 'Edg/'))    return 'Edge';
        if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) return 'Opera';
        if (str_contains($ua, 'Chrome'))  return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari'))  return 'Safari';
        return 'Other';
    }
    if (str_contains($ua, 'Windows')) return 'Windows';
    if (str_contains($ua, 'Mac OS'))  return 'macOS';
    if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
    if (str_contains($ua, 'Android')) return 'Android';
    if (str_contains($ua, 'Linux'))   return 'Linux';
    return 'Other';
}
} // end function_exists('parseFamily')

$browsers = [];
$oses     = [];
foreach ($uaRows as $row) {
    $b = parseFamily($row['user_agent'], 'browser');
    $o = parseFamily($row['user_agent'], 'os');
    $browsers[$b] = ($browsers[$b] ?? 0) + (int)$row['cnt'];
    $oses[$o]     = ($oses[$o]     ?? 0) + (int)$row['cnt'];
}
arsort($browsers); arsort($oses);

$screens = q($pdo, "SELECT screen, COUNT(*) as cnt FROM sessions WHERE site_id=? AND is_bot=0 AND screen IS NOT NULL AND DATE(started_at) BETWEEN ? AND ? GROUP BY screen ORDER BY cnt DESC LIMIT 6", $p);

$lastSessions = q($pdo, "
    SELECT s.session_id, s.ip, s.country, s.country_code, s.city, s.referrer,
           s.screen, s.language, s.started_at,
           COUNT(e.id) as events
    FROM sessions s
    LEFT JOIN events e ON e.session_id = s.session_id AND e.site_id = s.site_id
    WHERE s.site_id=? AND s.is_bot=0 AND DATE(s.started_at) BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY s.started_at DESC
    LIMIT 30
", $p);

$chartDays = $chartCounts = [];
$cur = strtotime($dateFrom);
$end = strtotime($dateTo);
$map = array_column($chartData, 'cnt', 'd');
while ($cur <= $end) {
    $d = date('Y-m-d', $cur);
    $chartDays[]   = date('d.m', $cur);
    $chartCounts[] = (int)($map[$d] ?? 0);
    $cur = strtotime('+1 day', $cur);
}

$activeSiteName = '';
$activeDomain   = '';
foreach ($sites as $s) {
    if ((int)$s['id'] === $activeSite) {
        $activeSiteName = $s['name'];
        $activeDomain   = $s['domain'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Statist — <?= htmlspecialchars($activeSiteName) ?></title>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:       #f5f5f7;
  --surface:  #ffffff;
  --surface2: #f9f9fb;
  --border:   #e5e5ea;
  --border2:  #d1d1d6;
  --accent:   #4f46e5;
  --accent-l: #ede9fe;
  --accent2:  #0ea5e9;
  --accent2-l:#e0f2fe;
  --text:     #1c1c1e;
  --text2:    #48484a;
  --muted:    #8e8e93;
  --success:  #34c759;
  --danger:   #ff3b30;
  --radius:   10px;
}

body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: var(--text);
  font-size: 14px;
  line-height: 1.5;
}

.layout { display: flex; min-height: 100vh; }

/* Sidebar */
.sidebar {
  width: 210px;
  flex-shrink: 0;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  padding: 0;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
}

.sidebar-logo {
  padding: 20px 18px 16px;
  border-bottom: 1px solid var(--border);
}

.sidebar-logo-mark {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.14em;
  color: var(--accent);
  text-transform: uppercase;
}

.sidebar-logo-sub {
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
}

.sidebar-section {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.1em;
  color: var(--muted);
  text-transform: uppercase;
  padding: 16px 18px 6px;
}

.sidebar a {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 400;
  color: var(--text2);
  text-decoration: none;
  border-radius: 0;
  transition: background 0.12s, color 0.12s;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar a .dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--border2);
  flex-shrink: 0;
}

.sidebar a:hover { background: var(--bg); color: var(--text); }
.sidebar a.active { background: var(--accent-l); color: var(--accent); font-weight: 500; }
.sidebar a.active .dot { background: var(--accent); }

.sidebar-footer {
  margin-top: auto;
  padding: 14px 18px;
  border-top: 1px solid var(--border);
}

.sidebar-footer a {
  font-size: 12px;
  color: var(--muted);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 6px;
}
.sidebar-footer a:hover { color: var(--danger); }

/* Main */
.main { flex: 1; min-width: 0; }

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 28px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 10;
  gap: 16px;
}

.topbar-left h1 { font-size: 15px; font-weight: 600; color: var(--text); }
.topbar-left .domain { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); margin-top: 1px; }

.periods {
  display: flex;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 3px;
  gap: 2px;
}

.periods a {
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  transition: all 0.12s;
  white-space: nowrap;
}
.periods a:hover { color: var(--text); background: var(--surface); }
.periods a.active { background: var(--surface); color: var(--text); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }

.content { padding: 24px 28px; }

/* Metrics */
.metrics { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }

.metric {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
}

.metric-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  color: var(--muted);
  text-transform: uppercase;
  margin-bottom: 8px;
}

.metric-value {
  font-size: 28px;
  font-weight: 300;
  color: var(--text);
  letter-spacing: -0.03em;
  font-family: 'Inter', sans-serif;
}

/* Cards */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  margin-bottom: 14px;
}

.card-header {
  padding: 12px 18px;
  border-bottom: 1px solid var(--border);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: var(--muted);
  text-transform: uppercase;
  background: var(--surface2);
}

.card-body { padding: 14px 18px; }

.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 14px; }

/* Bar rows */
.bar-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 5px 0;
  border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.bar-row:last-child { border-bottom: none; }

.bar-label {
  flex: 1;
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--text);
}

.bar-track {
  width: 80px;
  height: 3px;
  background: var(--border);
  border-radius: 2px;
  flex-shrink: 0;
}

.bar-fill {
  height: 100%;
  border-radius: 2px;
  background: var(--accent);
}

.bar-cnt {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  color: var(--muted);
  min-width: 30px;
  text-align: right;
}

/* Chart */
.chart-wrap { position: relative; height: 180px; }

/* Table */
.tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
.tbl th {
  text-align: left;
  padding: 9px 14px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: var(--muted);
  text-transform: uppercase;
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
  white-space: nowrap;
}
.tbl td {
  padding: 9px 14px;
  border-bottom: 1px solid var(--border);
  color: var(--text2);
  vertical-align: middle;
}
.tbl tr:last-child td { border-bottom: none; }
.tbl tr:hover td { background: var(--bg); }

.mono { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); }

.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  padding: 2px 7px;
  border-radius: 4px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  font-weight: 500;
}
.badge-indigo { background: var(--accent-l); color: var(--accent); }
.badge-sky    { background: var(--accent2-l); color: var(--accent2); }

.link-session {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  color: var(--accent);
  text-decoration: none;
  border-bottom: 1px dashed var(--accent);
  padding-bottom: 1px;
}
.link-session:hover { border-bottom-style: solid; }

.scroll-x { overflow-x: auto; }

.empty { color: var(--muted); font-size: 13px; padding: 8px 0; }

.burger {
  display: none;
  background: none;
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 7px 10px;
  cursor: pointer;
  color: var(--text2);
  font-size: 18px;
  line-height: 1;
  flex-shrink: 0;
}

.overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.25);
  z-index: 99;
}
.overlay.open { display: block; }

@media (max-width: 700px) {
  .burger { display: flex; align-items: center; justify-content: center; }

  .sidebar {
    position: fixed;
    left: -220px;
    top: 0;
    height: 100vh;
    z-index: 100;
    transition: left 0.22s ease;
    width: 200px;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
  }
  .sidebar.open { left: 0; }

  .layout { display: block; }

  .topbar { padding: 12px 14px; flex-wrap: wrap; gap: 8px; }
  .topbar-left h1 { font-size: 14px; }
  .topbar-left .domain { display: none; }

  .periods { flex-wrap: wrap; }
  .periods a { padding: 5px 9px; font-size: 11px; }

  .content { padding: 12px; }

  .metrics { grid-template-columns: repeat(3,1fr); gap: 8px; margin-bottom: 14px; }
  .metric { padding: 12px; }
  .metric-label { font-size: 9px; margin-bottom: 4px; }
  .metric-value { font-size: 20px; }

  .grid2, .grid3 { grid-template-columns: 1fr; gap: 10px; }

  .sessions-table { display: none; }
  .sessions-cards { display: block; }
}

@media (min-width: 701px) {
  .sessions-cards { display: none; }
  .sessions-table { display: block; }
}

.session-card {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
}
.session-card:last-child { border-bottom: none; }

.session-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 5px;
  gap: 8px;
}

.session-card-loc { font-size: 13px; font-weight: 500; color: var(--text); }
.session-card-time { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); white-space: nowrap; }

.session-card-meta {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: var(--muted);
  flex-wrap: wrap;
  align-items: center;
}

.session-card-meta a {
  color: var(--accent);
  text-decoration: none;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  border-bottom: 1px dashed var(--accent);
}
</style>
</head>
<body>
<div class="layout">

<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">Statist</div>
    <div class="sidebar-logo-sub">Analytics</div>
  </div>
  <div class="sidebar-section"><?= __('nav.sites') ?></div>
  <?php foreach ($sites as $s): ?>
    <a href="?site=<?= $s['id'] ?>&period=<?= $period ?>"
       class="<?= (int)$s['id'] === $activeSite ? 'active' : '' ?>">
      <span class="dot"></span>
      <?= htmlspecialchars($s['name']) ?>
    </a>
  <?php endforeach; ?>
  <div class="sidebar-footer">
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="sites.php">⊕ <?= __('nav.sites') ?></a>
    <a href="users.php">⚙ <?= __('nav.users') ?></a>
    <?php endif; ?>
    <a href="settings.php">◎ <?= __('nav.settings') ?></a>
    <a href="logout.php">← <?= __('nav.logout') ?></a>
  </div>
</nav>

<div class="main">
  <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
  <div class="topbar">
    <button class="burger" onclick="toggleSidebar()">☰</button>
    <div class="topbar-left">
      <h1><?= htmlspecialchars($activeSiteName) ?></h1>
      <div class="domain"><?= htmlspecialchars($activeDomain) ?></div>
    </div>
    <div class="periods">
      <?php foreach ($periodMap as $key => $info): ?>
        <a href="?site=<?= $activeSite ?>&period=<?= $key ?>"
           class="<?= $period === $key ? 'active' : '' ?>">
          <?= $info['label'] ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="content">

    <div class="metrics">
      <div class="metric">
        <div class="metric-label"><?= __("metric.visitors") ?></div>
        <div class="metric-value"><?= number_format($totalVisitors) ?></div>
      </div>
      <div class="metric">
        <div class="metric-label"><?= __("metric.pageviews") ?></div>
        <div class="metric-value"><?= number_format($totalPageviews) ?></div>
      </div>
      <div class="metric">
        <div class="metric-label"><?= __('metric.sessions') ?></div>
        <div class="metric-value"><?= number_format($totalSessions) ?></div>
      </div>
      <?php if ($totalBots > 0): ?>
      <div class="metric" style="border-color:#ffc9c9;background:#fff8f8">
        <div class="metric-label" style="color:#c0392b"><?= __("metric.bots") ?></div>
        <div class="metric-value" style="color:#c0392b;font-size:20px"><?= number_format($totalBots) ?> <span style="font-size:12px;color:#8e8e93"><?= __("metric.bots_filtered") ?></span></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><?= __("chart.daily") ?></div>
      <div class="card-body">
        <div class="chart-wrap"><canvas id="chart"></canvas></div>
      </div>
    </div>

    <div class="grid2">
      <div class="card">
        <div class="card-header"><?= __("table.top_pages") ?></div>
        <div class="card-body">
          <?php $maxP = $topPages ? max(array_column($topPages, 'cnt')) : 1; ?>
          <?php foreach ($topPages as $row): ?>
            <div class="bar-row">
              <div class="bar-label" title="<?= htmlspecialchars($row['path']) ?>"><?= htmlspecialchars($row['path'] ?: '/') ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'],$maxP) ?>%"></div></div>
              <div class="bar-cnt"><?= $row['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$topPages): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= __("table.referrers") ?></div>
        <div class="card-body">
          <?php $maxR = $referrers ? max(array_column($referrers, 'cnt')) : 1; ?>
          <?php foreach ($referrers as $row):
            $host = parse_url($row['referrer'], PHP_URL_HOST) ?: $row['referrer'];
          ?>
            <div class="bar-row">
              <div class="bar-label" title="<?= htmlspecialchars($row['referrer']) ?>"><?= htmlspecialchars($host) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'],$maxR) ?>%;background:var(--accent2)"></div></div>
              <div class="bar-cnt"><?= $row['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$referrers): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid2">
      <div class="card">
        <div class="card-header"><?= __('table.countries') ?></div>
        <div class="card-body">
          <?php $maxC = $topCountries ? max(array_column($topCountries, 'cnt')) : 1; ?>
          <?php foreach ($topCountries as $row): ?>
            <div class="bar-row">
              <div class="bar-label" style="display:flex;align-items:center;gap:6px">
                <?= flag_img(strtolower($row['country_code'] ?? ''), $row['country'] ?? '') ?>
                <?= htmlspecialchars($row['country'] ?? '—') ?>
              </div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'],$maxC) ?>%"></div></div>
              <div class="bar-cnt"><?= $row['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$topCountries): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= __('table.cities') ?></div>
        <div class="card-body">
          <?php $maxCi = $topCities ? max(array_column($topCities, 'cnt')) : 1; ?>
          <?php foreach ($topCities as $row): ?>
            <div class="bar-row">
              <div class="bar-label"><?= htmlspecialchars($row['city'] ?? '—') ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'],$maxCi) ?>%;background:var(--accent2)"></div></div>
              <div class="bar-cnt"><?= $row['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$topCities): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid3">
      <div class="card">
        <div class="card-header"><?= __('table.browsers') ?></div>
        <div class="card-body">
          <?php $maxB = $browsers ? max($browsers) : 1; ?>
          <?php foreach ($browsers as $name => $cnt): ?>
            <div class="bar-row">
              <div class="bar-label"><?= htmlspecialchars($name) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct($cnt,$maxB) ?>%"></div></div>
              <div class="bar-cnt"><?= $cnt ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= __('table.os') ?></div>
        <div class="card-body">
          <?php $maxO = $oses ? max($oses) : 1; ?>
          <?php foreach ($oses as $name => $cnt): ?>
            <div class="bar-row">
              <div class="bar-label"><?= htmlspecialchars($name) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct($cnt,$maxO) ?>%;background:var(--accent2)"></div></div>
              <div class="bar-cnt"><?= $cnt ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= __('table.screens') ?></div>
        <div class="card-body">
          <?php $maxSc = $screens ? max(array_column($screens, 'cnt')) : 1; ?>
          <?php foreach ($screens as $row): ?>
            <div class="bar-row">
              <div class="bar-label mono"><?= htmlspecialchars($row['screen']) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'],$maxSc) ?>%"></div></div>
              <div class="bar-cnt"><?= $row['cnt'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><?= __('sessions.title') ?></div>

      <!-- Desktop table -->
      <div class="sessions-table scroll-x">
        <table class="tbl">
          <thead>
            <tr>
              <th><?= __("sessions.col.time") ?></th>
              <th><?= __("sessions.col.session") ?></th>
              <th>IP</th>
              <th><?= __("sessions.col.location") ?></th>
              <th><?= __("sessions.col.referrer") ?></th>
              <th><?= __("sessions.col.screen") ?></th>
              <th><?= __("sessions.col.lang") ?></th>
              <th><?= __("sessions.col.events") ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lastSessions as $s): ?>
            <tr>
              <td class="mono"><?= date('d.m H:i', strtotime($s['started_at'])) ?></td>
              <td>
                <a class="link-session"
                   href="session.php?id=<?= urlencode($s['session_id']) ?>&site=<?= $activeSite ?>">
                  <?= htmlspecialchars(substr($s['session_id'], 0, 12)) ?>…
                </a>
              </td>
              <td class="mono"><?= htmlspecialchars($s['ip'] ?? '—') ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <?= flag_img(strtolower($s['country_code'] ?? ''), $s['country'] ?? '') ?>
                  <?= htmlspecialchars(implode(', ', array_filter([$s['country'], $s['city']])) ?: '—') ?>
                </div>
              </td>
              <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php
                  $ref = $s['referrer'] ?? '';
                  $rfh = $ref ? (parse_url($ref, PHP_URL_HOST) ?: $ref) : '—';
                  echo '<span title="' . htmlspecialchars($ref) . '">' . htmlspecialchars($rfh) . '</span>';
                ?>
              </td>
              <td class="mono"><?= htmlspecialchars($s['screen'] ?? '—') ?></td>
              <td class="mono"><?= htmlspecialchars($s['language'] ?? '—') ?></td>
              <td><span class="badge badge-indigo"><?= (int)$s['events'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$lastSessions): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:28px"><?= __('table.no_data') ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="sessions-cards">
        <?php foreach ($lastSessions as $s):
          $ref = $s['referrer'] ?? '';
          $rfh = $ref ? (parse_url($ref, PHP_URL_HOST) ?: $ref) : null;
        ?>
        <div class="session-card">
          <div class="session-card-top">
            <div class="session-card-loc" style="display:flex;align-items:center;gap:6px">
                <?= flag_img(strtolower($s['country_code'] ?? ''), $s['country'] ?? '') ?>
                <?= htmlspecialchars(implode(', ', array_filter([$s['country'], $s['city']])) ?: '—') ?>
              </div>
            <div class="session-card-time"><?= date('d.m H:i', strtotime($s['started_at'])) ?></div>
          </div>
          <div class="session-card-meta">
            <a href="session.php?id=<?= urlencode($s['session_id']) ?>&site=<?= $activeSite ?>">
              <?= htmlspecialchars(substr($s['session_id'], 0, 10)) ?>…
            </a>
            <span><?= htmlspecialchars($s['ip'] ?? '—') ?></span>
            <?php if ($rfh): ?><span><?= htmlspecialchars($rfh) ?></span><?php endif; ?>
            <span class="badge badge-indigo"><?= (int)$s['events'] ?> событий</span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$lastSessions): ?>
          <div style="text-align:center;color:var(--muted);padding:28px;font-size:13px"><?= __('table.no_data') ?></div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
</div>

<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
}
function closeSidebar() {
  document.querySelector('.sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
}
</script>
<script>
new Chart(document.getElementById('chart').getContext('2d'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartDays) ?>,
    datasets: [{
      data: <?= json_encode($chartCounts) ?>,
      borderColor: '#4f46e5',
      backgroundColor: 'rgba(79,70,229,0.06)',
      borderWidth: 1.5,
      pointRadius: 3,
      pointBackgroundColor: '#4f46e5',
      fill: true,
      tension: 0.35,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: {
        grid: { color: '#e5e5ea' },
        ticks: { color: '#8e8e93', font: { family: 'JetBrains Mono', size: 10 } }
      },
      y: {
        beginAtZero: true,
        grid: { color: '#e5e5ea' },
        ticks: { color: '#8e8e93', font: { family: 'JetBrains Mono', size: 10 }, precision: 0 }
      }
    }
  }
});
</script>
</body>
</html>
