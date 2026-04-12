<?php
// Переменные от list/dashboard.php:
// $activeSiteName, $activeDomain, $activeSite, $period, $periodMap
// $totalVisitors, $totalPageviews, $totalSessions, $totalBots
// $topPages, $topCountries, $topCities, $referrers, $browsers, $oses, $screens
// $chartDays, $chartCounts
// $allSessions, $totalAllSess, $pgSess, $totalPgSess, $offsetSess, $perPage
// $country

// ── Font Awesome icons ───────────────────────────────────────────
function _icons(): array {
    static $d = null;
    if ($d !== null) return $d;
    return $d = [
        'browser' => [
            'Chrome' => 'fa-brands fa-chrome',
            'Firefox' => 'fa-brands fa-firefox-browser',
            'Safari' => 'fa-brands fa-safari',
            'Edge' => 'fa-brands fa-edge',
            'Opera' => 'fa-brands fa-opera',
            'Other' => 'fa-solid fa-globe',
        ],
        'os' => [
            'Windows' => 'fa-brands fa-windows',
            'macOS' => 'fa-brands fa-apple',
            'iOS' => 'fa-solid fa-mobile-screen',
            'Android' => 'fa-brands fa-android',
            'Linux' => 'fa-brands fa-linux',
            'Other' => 'fa-solid fa-desktop',
        ],
        'screen' => [
            'mobile' => 'fa-solid fa-mobile-screen',
            'tablet' => 'fa-solid fa-tablet-screen-button',
            'desktop' => 'fa-solid fa-desktop',
        ],
    ];
}

function browserIcon(string $name): string {
    $icons = _icons()['browser'];
    $class = $icons[$name] ?? $icons['Other'];
    return '<span class="icon-svg"><i class="' . e($class) . '"></i></span>';
}

function osIcon(string $name): string {
    $icons = _icons()['os'];
    $class = $icons[$name] ?? $icons['Other'];
    return '<span class="icon-svg"><i class="' . e($class) . '"></i></span>';
}

function screenType(string $screen): string {
    if (!preg_match('/^(\d+)x\d+$/', $screen, $m)) return 'desktop';
    $w = (int)$m[1];
    if ($w <= 480) return 'mobile';
    if ($w <= 1024) return 'tablet';
    return 'desktop';
}

function screenIcon(string $type): string {
    $icons = _icons()['screen'];
    $class = $icons[$type] ?? $icons['desktop'];
    return '<span class="icon-svg"><i class="' . e($class) . '"></i></span>';
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
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=pages" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxP = $topPages ? max(array_column($topPages, 'cnt')) : 1; ?>

        <?php foreach ($topPages as $row): ?>
          <?php
            $title = trim($row['title'] ?? '');
            $path  = $row['path'] ?? '/';
            $cnt   = (int)$row['cnt'];

            $displayTitle = $title !== '' ? $title : $path;
          ?>

          <div class="bar-row">

            <div class="bar-label" title="<?= htmlspecialchars($displayTitle) ?>">
              
              <div class="page-title">
                <?= htmlspecialchars($displayTitle) ?>
              </div>

              <div class="page-path">
                <?= htmlspecialchars($path) ?>
              </div>

            </div>

            <div class="bar-track">
              <div class="bar-fill" style="width:<?= pct($cnt, $maxP) ?>%"></div>
            </div>

            <div class="bar-cnt"><?= $cnt ?></div>

          </div>

        <?php endforeach; ?>

        <?php if (!$topPages): ?>
          <div class="empty"><?= __('table.no_data') ?></div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Referrers -->
    <div class="card">
      <div class="card-header card-header-link">
        <span><?= __('table.referrers') ?></span>
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=referrers" class="card-more">→</a>
      </div>
      <div class="card-body">
        <?php $maxR = $referrers ? max(array_column($referrers, 'cnt')) : 1; ?>
<?php foreach ($referrers as $row): ?>
  <?php
    $host = $row['host'] ?? '';
    $cnt  = (int)$row['cnt'];
    $fav  = $host !== '' ? refFavicon($host) : '';
  ?>
  <div class="bar-row">
    <div class="bar-label" title="<?= htmlspecialchars($host) ?>">
      <span class="icon-label">
        <?= $fav ?>
        <span><?= htmlspecialchars($host ?: '-') ?></span>
      </span>
    </div>
    <div class="bar-track">
      <div class="bar-fill" style="width:<?= pct($cnt, $maxR) ?>%;background:var(--accent2)"></div>
    </div>
    <div class="bar-cnt"><?= $cnt ?></div>
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
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=countries" class="card-more">→</a>
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
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=cities" class="card-more">→</a>
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
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=browsers" class="card-more">→</a>
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
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=os" class="card-more">→</a>
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
        <a href="stats?site=<?= $activeSite ?>&period=<?= $period ?>&type=screens" class="card-more">→</a>
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
                 href="session?id=<?= urlencode($s['session_id']) ?>&site=<?= $activeSite ?>">
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
          <a href="session?id=<?= urlencode($s['session_id']) ?>&site=<?= $activeSite ?>"
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
