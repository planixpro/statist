<?php
// Переменные от list/dashboard.php:
// $activeSiteName, $activeDomain, $activeSite, $period, $periodMap
// $totalVisitors, $totalPageviews, $totalSessions, $totalBots
// $topPages, $topCountries, $topCities, $referrers, $browsers, $oses, $screens
// $chartDays, $chartCounts
// $allSessions, $totalAllSess, $pgSess, $totalPgSess, $offsetSess, $perPage
// $country

// ── SVG-иконки через heredoc (без проблем с кавычками) ───────────
function _icons(): array {
    static $d = null;
    if ($d !== null) return $d;
    $d = [];

    $d['browser']['Chrome'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#fff" stroke="#e5e5ea" stroke-width=".5"/><circle cx="12" cy="12" r="4" fill="#4285F4"/><path d="M12 8h9" stroke="#EA4335" stroke-width="2.5" stroke-linecap="round"/><path d="M12 8 7.3 16" stroke="#FBBC05" stroke-width="2.5" stroke-linecap="round"/><path d="M7.3 16H21" stroke="#34A853" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="0 13.7 6 99"/><circle cx="12" cy="12" r="1.8" fill="#fff"/></svg>';

    $d['browser']['Firefox'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#FF7139"/><circle cx="12" cy="12" r="5" fill="#FF980E"/><circle cx="12" cy="12" r="2.5" fill="#FFCA00"/></svg>';

    $d['browser']['Safari'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#1C8EF9"/><line x1="12" y1="4" x2="12" y2="6.5" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="12" y1="17.5" x2="12" y2="20" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="4" y1="12" x2="6.5" y2="12" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="17.5" y1="12" x2="20" y2="12" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><polygon points="12,7 13.5,13.5 12,12.5 10.5,13.5" fill="#fff"/><polygon points="12,17 10.5,10.5 12,11.5 13.5,10.5" fill="rgba(255,255,255,0.45)"/></svg>';

    $d['browser']['Edge'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><path d="M12 3C7.03 3 3 7.03 3 12c0 3.86 2.42 7.16 5.87 8.46C7.5 19 7 17.5 7 16c0-3.5 2.8-6 6-6 .34 0 .68.03 1 .08V8c-3.87 0-7 3.13-7 7 0 1.3.35 2.5.97 3.54A9 9 0 1 1 12 3z" fill="#0078D4"/><path d="M20.5 14.5c0 3.04-2.46 5.5-5.5 5.5-2.4 0-4.46-1.54-5.2-3.68.44.12.9.18 1.2.18 2.2 0 4-1.8 4-4 0-.46-.1-.9-.24-1.3A5.5 5.5 0 0 1 20.5 14.5z" fill="#50E6FF"/></svg>';

    $d['browser']['Opera'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#FF1B2D"/><ellipse cx="12" cy="12" rx="4" ry="6.5" fill="none" stroke="#fff" stroke-width="1.8"/></svg>';

    $d['browser']['Other'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#c7c7cc"/><circle cx="12" cy="12" r="4" fill="none" stroke="#fff" stroke-width="1.5"/><line x1="12" y1="3" x2="12" y2="21" stroke="#fff" stroke-width="1" opacity=".5"/><line x1="3" y1="12" x2="21" y2="12" stroke="#fff" stroke-width="1" opacity=".5"/></svg>';

    $d['os']['Windows'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect x="3" y="3" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="12.5" y="3" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="3" y="12.5" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="12.5" y="12.5" width="8.5" height="8.5" rx=".5" fill="#0078D4"/></svg>';

    $d['os']['macOS'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><path d="M15.5 4.5c.1 1.7-1.2 3-2.8 3-.2-1.6 1.3-3.1 2.8-3z" fill="#555"/><path d="M18 16.8c-.8.8-1.7.7-2.6.3-.9-.4-1.7-.4-2.7 0-1.2.5-1.8.4-2.5-.3C6.5 13.5 7 8 11.3 7.7c1.1.1 1.9.6 2.5.7 1-.2 1.9-.8 2.9-.7 1.2.1 2.2.6 2.8 1.5-2.5 1.5-1.9 4.9.4 5.8-.5 1.2-1.1 2.4-1.9 3.8z" fill="#555"/></svg>';

    $d['os']['iOS'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect x="7" y="2" width="10" height="20" rx="2.5" fill="#555"/><rect x="9" y="4" width="6" height="13.5" rx=".5" fill="#aaa"/><rect x="10.5" y="18.5" width="3" height="1" rx=".5" fill="#888"/></svg>';

    $d['os']['Android'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><ellipse cx="12" cy="10" rx="6.5" ry="5" fill="#3DDC84"/><rect x="5.5" y="10" width="13" height="8" fill="#3DDC84"/><rect x="5.5" y="15" width="13" height="3" rx="1.5" fill="#2BB873"/><circle cx="9.5" cy="9.5" r="1" fill="#fff"/><circle cx="14.5" cy="9.5" r="1" fill="#fff"/><line x1="8.5" y1="6.5" x2="6.5" y2="3.5" stroke="#3DDC84" stroke-width="1.5" stroke-linecap="round"/><line x1="15.5" y1="6.5" x2="17.5" y2="3.5" stroke="#3DDC84" stroke-width="1.5" stroke-linecap="round"/><rect x="2.5" y="10" width="2" height="5" rx="1" fill="#3DDC84"/><rect x="19.5" y="10" width="2" height="5" rx="1" fill="#3DDC84"/></svg>';

    $d['os']['Linux'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><ellipse cx="12" cy="9" rx="4.5" ry="5.5" fill="#E8A000"/><rect x="7.5" y="9" width="9" height="7" rx=".5" fill="#E8A000"/><circle cx="10" cy="8" r="1" fill="#5c3a00"/><circle cx="14" cy="8" r="1" fill="#5c3a00"/><path d="M9 17.5 7 20h10l-2-2.5" fill="#c47c00"/><ellipse cx="9" cy="20" rx="2" ry="1" fill="#888"/><ellipse cx="15" cy="20" rx="2" ry="1" fill="#888"/></svg>';

    $d['os']['Other'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect x="3" y="4" width="18" height="12" rx="2" fill="#c7c7cc"/><rect x="8" y="16" width="8" height="2" fill="#c7c7cc"/><rect x="6" y="18" width="12" height="1.5" rx=".5" fill="#c7c7cc"/></svg>';

    $d['screen']['mobile']  = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect x="7" y="2" width="10" height="20" rx="2.5" fill="#6366f1"/><rect x="9" y="4" width="6" height="13.5" rx=".5" fill="rgba(255,255,255,0.22)"/><rect x="10.5" y="18.5" width="3" height="1" rx=".5" fill="rgba(255,255,255,0.7)"/></svg>';

    $d['screen']['tablet']  = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect x="4" y="2" width="16" height="20" rx="2.5" fill="#0ea5e9"/><rect x="6" y="4" width="12" height="14" rx=".5" fill="rgba(255,255,255,0.22)"/><rect x="10.5" y="19.5" width="3" height="1" rx=".5" fill="rgba(255,255,255,0.7)"/></svg>';

    $d['screen']['desktop'] = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><rect x="2" y="3" width="20" height="14" rx="2" fill="#34c759"/><rect x="4" y="5" width="16" height="10" rx=".5" fill="rgba(255,255,255,0.22)"/><path d="M8 21h8M12 17v4" stroke="#34c759" stroke-width="1.5" stroke-linecap="round"/></svg>';

    return $d;
}

function browserIcon(string $name): string {
    $icons = _icons()['browser'];
    return '<span class="icon-svg">' . ($icons[$name] ?? $icons['Other']) . '</span>';
}

function osIcon(string $name): string {
    $icons = _icons()['os'];
    return '<span class="icon-svg">' . ($icons[$name] ?? $icons['Other']) . '</span>';
}

function screenType(string $screen): string {
    if (!preg_match('/^(\d+)x\d+$/', $screen, $m)) return 'desktop';
    $w = (int)$m[1];
    if ($w <= 480)  return 'mobile';
    if ($w <= 1024) return 'tablet';
    return 'desktop';
}

function screenIcon(string $type): string {
    $icons = _icons()['screen'];
    return '<span class="icon-svg">' . ($icons[$type] ?? $icons['desktop']) . '</span>';
}

// ── Favicon реферреров ────────────────────────────────────────────
function refFavicon(string $host): string {
    if ($host === '') return '';
    $safe = preg_replace('/[^a-zA-Z0-9.\-]/', '', $host);
    if ($safe === '') return '';
    $dir  = dirname(__DIR__) . '/assets/img/favicons/';
    $file = $dir . $safe . '.png';
    $web  = '/assets/img/favicons/' . htmlspecialchars($safe) . '.png';
    if (file_exists($file) && (time() - filemtime($file)) < 86400 * 30) {
        return filesize($file) > 100
            ? '<img src="' . $web . '" width="14" height="14" style="border-radius:2px;flex-shrink:0;vertical-align:middle" loading="lazy" onerror="this.style.display=\'none\'">'
            : '';
    }
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $img = @file_get_contents('https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=32', false, $ctx);
    if ($img && strlen($img) > 100) {
        file_put_contents($file, $img);
        return '<img src="' . $web . '" width="14" height="14" style="border-radius:2px;flex-shrink:0;vertical-align:middle" loading="lazy" onerror="this.style.display=\'none\'">';
    }
    file_put_contents($file, '');
    return '';
}
?>

<!-- Topbar -->
<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <div class="topbar-left">
    <h1><?= htmlspecialchars($activeSiteName) ?></h1>
    <div class="domain"><?= htmlspecialchars($activeDomain) ?></div>
  </div>
  <div class="topbar-right">
    <div class="periods">
      <?php foreach ($periodMap as $key => $info): ?>
        <a href="?site=<?= $activeSite ?>&period=<?= $key ?>"
           class="<?= $period === $key ? 'active' : '' ?>">
          <?= $info['label'] ?>
        </a>
      <?php endforeach; ?>
    <button id="autorefresh-btn" class="autorefresh-btn" title="Автообновление">⟳ <span id="autorefresh-counter" class="ar-counter"></span></button>
    </div> 
  </div>
</div>

<!-- Content -->
<div class="content">

  <!-- Metrics -->
  <div class="metrics">
    <div class="metric">
      <div class="metric-label"><?= __('metric.visitors') ?></div>
      <div class="metric-value"><?= number_format($totalVisitors) ?></div>
    </div>
    <div class="metric">
      <div class="metric-label"><?= __('metric.pageviews') ?></div>
      <div class="metric-value"><?= number_format($totalPageviews) ?></div>
    </div>
    <div class="metric">
      <div class="metric-label"><?= __('metric.sessions') ?></div>
      <div class="metric-value"><?= number_format($totalSessions) ?></div>
    </div>
    <?php if ($totalBots > 0): ?>
    <div class="metric metric--danger">
      <div class="metric-label"><?= __('metric.bots') ?></div>
      <div class="metric-value">
        <?= number_format($totalBots) ?>
        <span style="font-size:12px;color:var(--muted)"><?= __('metric.bots_filtered') ?></span>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Chart -->
  <div class="card">
    <div class="card-header"><?= __('chart.daily') ?></div>
    <div class="card-body">
      <div class="chart-wrap">
        <canvas id="chart"
          data-labels="<?= htmlspecialchars(json_encode($chartDays), ENT_QUOTES) ?>"
          data-pageviews="<?= htmlspecialchars(json_encode($chartPageviews), ENT_QUOTES) ?>"
          data-sessions="<?= htmlspecialchars(json_encode($chartSessCounts), ENT_QUOTES) ?>"
          data-visitors="<?= htmlspecialchars(json_encode($chartVistCounts), ENT_QUOTES) ?>"
          data-label-pageviews="<?= htmlspecialchars(__('metric.pageviews')) ?>"
          data-label-sessions="<?= htmlspecialchars(__('metric.sessions')) ?>"
          data-label-visitors="<?= htmlspecialchars(__('metric.visitors')) ?>">
        </canvas>
      </div>
    </div>
  </div>

  <!-- Top pages + Referrers -->
  <div class="grid2">
    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.top_pages') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=pages" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxP = $topPages ? max(array_column($topPages, 'cnt')) : 1; ?>
        <?php foreach ($topPages as $row): ?>
          <div class="bar-row">
            <div class="bar-label" title="<?= htmlspecialchars($row['path']) ?>">
              <?= htmlspecialchars($row['path'] ?: '/') ?>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'], $maxP) ?>%"></div></div>
            <div class="bar-cnt"><?= $row['cnt'] ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$topPages): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.referrers') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=referrers" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxR = $referrers ? max(array_column($referrers, 'cnt')) : 1; ?>
        <?php foreach ($referrers as $row):
          $host = parse_url($row['referrer'], PHP_URL_HOST) ?: $row['referrer'];
          $fav  = $host !== '' ? refFavicon($host) : '';
        ?>
          <div class="bar-row">
            <div class="bar-label" title="<?= htmlspecialchars($row['referrer']) ?>">
              <span class="icon-label"><?= $fav ?><span><?= htmlspecialchars($host) ?></span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'], $maxR) ?>%;background:var(--accent2)"></div></div>
            <div class="bar-cnt"><?= $row['cnt'] ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$referrers): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Countries + Cities -->
  <div class="grid2">
    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.countries') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=countries" class="card-more">→</a>
      </div>
      <div class="card-body" style="padding:6px 10px">
        <?php foreach ($topCountries as $row):
          $cc       = strtoupper($row['country_code'] ?? '');
          $isActive = ($country === $cc);
          $params   = $_GET;
          if ($isActive) { unset($params['country']); }
          else           { $params['country'] = $cc; unset($params['pg']); }
        ?>
        <a href="?<?= http_build_query($params) ?>" class="country-item <?= $isActive ? 'active' : '' ?>">
          <span class="country-item-left">
            <?= flag_img(strtolower($row['country_code'] ?? ''), $row['country'] ?? '') ?>
            <?= htmlspecialchars($row['country'] ?? '—') ?>
          </span>
          <span class="country-item-cnt"><?= $row['cnt'] ?></span>
        </a>
        <?php endforeach; ?>
        <?php if (!$topCountries): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.cities') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=cities" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxCi = $topCities ? max(array_column($topCities, 'cnt')) : 1; ?>
        <?php foreach ($topCities as $row): ?>
          <div class="bar-row">
            <div class="bar-label"><?= htmlspecialchars($row['city'] ?? '—') ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= pct((int)$row['cnt'], $maxCi) ?>%;background:var(--accent2)"></div></div>
            <div class="bar-cnt"><?= $row['cnt'] ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$topCities): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Browsers + OS + Screens -->
  <div class="grid3">
    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.browsers') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=browsers" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxB = $browsers ? max($browsers) : 1; ?>
        <?php foreach ($browsers as $name => $cnt): ?>
          <div class="bar-row">
            <div class="bar-label">
              <span class="icon-label"><?= browserIcon($name) ?><span><?= htmlspecialchars($name) ?></span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= pct($cnt, $maxB) ?>%"></div></div>
            <div class="bar-cnt"><?= $cnt ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$browsers): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.os') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=os" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxO = $oses ? max($oses) : 1; ?>
        <?php foreach ($oses as $name => $cnt): ?>
          <div class="bar-row">
            <div class="bar-label">
              <span class="icon-label"><?= osIcon($name) ?><span><?= htmlspecialchars($name) ?></span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= pct($cnt, $maxO) ?>%;background:var(--accent2)"></div></div>
            <div class="bar-cnt"><?= $cnt ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$oses): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.screens') ?></span>
        <a href="stats.php?site=<?= $activeSite ?>&period=<?= $period ?>&type=screens" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php
          // Группируем по типу: Mobile / Tablet / PC
          $sg = ['mobile' => 0, 'tablet' => 0, 'desktop' => 0];
          foreach ($screens as $row) { $sg[screenType($row['screen'])] += (int)$row['cnt']; }
          $sg = array_filter($sg);
          arsort($sg);
          $maxSc  = $sg ? max($sg) : 1;
          $labels = ['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'PC'];
        ?>
        <?php foreach ($sg as $type => $cnt): ?>
          <div class="bar-row">
            <div class="bar-label">
              <span class="icon-label"><?= screenIcon($type) ?><span><?= $labels[$type] ?></span></span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= pct($cnt, $maxSc) ?>%"></div></div>
            <div class="bar-cnt"><?= $cnt ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$sg): ?><div class="empty"><?= __('table.no_data') ?></div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Sessions (все, без вкладок) -->
  <?php
    $cntFrom = $totalAllSess > 0 ? $offsetSess + 1 : 0;
    $cntTo   = min($offsetSess + $perPage, $totalAllSess);
  ?>
  <div class="card">
    <div class="sessions-card-header">
      <?= __('sessions.title') ?>
      <span class="sess-count-badge"><?= number_format($totalAllSess) ?></span>
      <?php if ($country !== ''):
        $resetParams = $_GET; unset($resetParams['country'], $resetParams['pg']);
        $countryLabel = '';
        foreach ($topCountries as $tc) {
            if (strtoupper($tc['country_code']) === $country) { $countryLabel = $tc['country']; break; }
        }
      ?>
      <a href="?<?= http_build_query($resetParams) ?>" class="country-chip">
        <?= flag_img(strtolower($country), $countryLabel) ?>
        <?= htmlspecialchars($countryLabel ?: $country) ?>
        <span class="country-chip-x">×</span>
      </a>
      <?php endif; ?>
      <span class="sess-tab-counter"><?= $cntFrom ?>–<?= $cntTo ?> / <?= $totalAllSess ?></span>
    </div>

    <!-- Desktop table -->
    <div class="sessions-table scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th><?= __('sessions.col.time') ?></th>
            <th><?= __('sessions.col.session') ?></th>
            <th>IP</th>
            <th><?= __('sessions.col.location') ?></th>
            <th><?= __('sessions.col.referrer') ?></th>
            <th><?= __('sessions.col.screen') ?></th>
            <th><?= __('sessions.col.lang') ?></th>
            <th><?= __('sessions.col.events') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allSessions as $s):
            $isBot = (int)$s['is_bot'];
          ?>
          <tr<?= $isBot ? ' style="background:#fffafa"' : '' ?>>
            <td class="mono"><?= date('d.m H:i', strtotime($s['started_at'])) ?></td>
            <td>
              <a class="link-session"
                 <?= $isBot ? 'style="color:var(--danger);border-bottom-color:var(--danger)"' : '' ?>
                 href="session.php?id=<?= urlencode($s['session_id']) ?>&site=<?= $activeSite ?>">
                <?= htmlspecialchars(substr($s['session_id'], 0, 12)) ?>…
              </a>
              <?php if ($isBot): ?>
                <span class="bot-pill" title="<?= htmlspecialchars($s['blocked_reason'] ?? '') ?>">bot <?= (int)$s['bot_score'] ?></span>
              <?php endif; ?>
            </td>
            <td class="mono"><?= htmlspecialchars($s['ip'] ?? '—') ?></td>
            <td>
              <span style="display:flex;align-items:center;gap:6px">
                <?= flag_img(strtolower($s['country_code'] ?? ''), $s['country'] ?? '') ?>
                <?= htmlspecialchars(implode(', ', array_filter([$s['country'], $s['city']])) ?: '—') ?>
              </span>
            </td>
            <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?php
                $ref = $s['referrer'] ?? '';
                $rfh = $ref ? (parse_url($ref, PHP_URL_HOST) ?: $ref) : '—';
                $fav = ($ref !== '') ? refFavicon(parse_url($ref, PHP_URL_HOST) ?: '') : '';
              ?>
              <span class="icon-label" title="<?= htmlspecialchars($ref) ?>"><?= $fav ?><span><?= htmlspecialchars($rfh) ?></span></span>
            </td>
            <td class="mono"><?= htmlspecialchars($s['screen'] ?? '—') ?></td>
            <td class="mono"><?= htmlspecialchars($s['language'] ?? '—') ?></td>
            <td>
              <span class="badge <?= $isBot ? 'badge-sky' : 'badge-indigo' ?>"><?= (int)$s['events'] ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$allSessions): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:28px"><?= __('table.no_data') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile cards -->
    <div class="sessions-cards">
      <?php foreach ($allSessions as $s):
        $ref   = $s['referrer'] ?? '';
        $rfh   = $ref ? (parse_url($ref, PHP_URL_HOST) ?: $ref) : null;
        $isBot = (int)$s['is_bot'];
      ?>
      <div class="session-card"<?= $isBot ? ' style="background:#fffafa"' : '' ?>>
        <div class="session-card-top">
          <div class="session-card-loc" style="display:flex;align-items:center;gap:6px">
            <?= flag_img(strtolower($s['country_code'] ?? ''), $s['country'] ?? '') ?>
            <?= htmlspecialchars(implode(', ', array_filter([$s['country'], $s['city']])) ?: '—') ?>
          </div>
          <div class="session-card-time"><?= date('d.m H:i', strtotime($s['started_at'])) ?></div>
        </div>
        <div class="session-card-meta">
          <a href="session.php?id=<?= urlencode($s['session_id']) ?>&site=<?= $activeSite ?>"
             <?= $isBot ? 'style="color:var(--danger);border-bottom-color:var(--danger)"' : '' ?>>
            <?= htmlspecialchars(substr($s['session_id'], 0, 10)) ?>…
          </a>
          <?php if ($isBot): ?><span class="bot-pill">bot <?= (int)$s['bot_score'] ?></span><?php endif; ?>
          <span><?= htmlspecialchars($s['ip'] ?? '—') ?></span>
          <?php if ($rfh): ?><span><?= htmlspecialchars($rfh) ?></span><?php endif; ?>
          <span class="badge <?= $isBot ? 'badge-sky' : 'badge-indigo' ?>"><?= (int)$s['events'] ?> <?= __('sessions.events_count') ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$allSessions): ?>
        <div style="text-align:center;color:var(--muted);padding:28px;font-size:13px"><?= __('table.no_data') ?></div>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPgSess > 1): ?>
    <div class="pagination">
      <div class="pagination-info"><?= $pgSess ?> / <?= $totalPgSess ?></div>
      <div class="pagination-pages">
        <?php if ($pgSess > 1): ?><a class="pg-nav" href="<?= pageUrl($pgSess - 1) ?>">‹</a><?php endif; ?>
        <?php
          $window = 2; $pages = [];
          for ($i = 1; $i <= $totalPgSess; $i++) {
              if ($i === 1 || $i === $totalPgSess || ($i >= $pgSess - $window && $i <= $pgSess + $window))
                  $pages[] = $i;
          }
          $prev = null;
          foreach ($pages as $pg):
              if ($prev !== null && $pg - $prev > 1): ?><span class="pg-dots">…</span><?php endif;
              if ($pg === $pgSess): ?><span class="pg-current"><?= $pg ?></span>
              <?php else: ?><a href="<?= pageUrl($pg) ?>"><?= $pg ?></a><?php endif;
              $prev = $pg;
          endforeach;
        ?>
        <?php if ($pgSess < $totalPgSess): ?><a class="pg-nav" href="<?= pageUrl($pgSess + 1) ?>">›</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- .card sessions -->

</div><!-- .content -->
