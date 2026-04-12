<?php
// Variables from stats.php:
// $type, $typeLabels, $rows, $total, $maxCount
// $pg, $totalPages, $perPage
// $activeSite, $activeSiteName, $activeDomain, $period, $periodMap

function statsPageUrl(int $p): string {
    $params = $_GET;
    $params['pg'] = $p;
    return '?' . http_build_query($params);
}

function statsTypeUrl(string $t): string {
    $params = $_GET;
    $params['type'] = $t;
    unset($params['pg']);
    return '?' . http_build_query($params);
}

// ── Icons ─────────────────────────────────────────────────────────
function statsIcon(string $group, string $name): string {
    static $icons = null;

    if ($icons === null) {
        $icons = [
            'browser' => [
                'Chrome'  => 'fa-brands fa-chrome',
                'Firefox' => 'fa-brands fa-firefox-browser',
                'Safari'  => 'fa-brands fa-safari',
                'Edge'    => 'fa-brands fa-edge',
                'Opera'   => 'fa-brands fa-opera',
                'Other'   => 'fa-solid fa-globe',
            ],
            'os' => [
                'Windows' => 'fa-brands fa-windows',
                'macOS'   => 'fa-brands fa-apple',
                'iOS'     => 'fa-solid fa-mobile-screen',
                'Android' => 'fa-brands fa-android',
                'Linux'   => 'fa-brands fa-linux',
                'Other'   => 'fa-solid fa-desktop',
            ],
            'screen' => [
                'mobile'  => 'fa-solid fa-mobile-screen',
                'tablet'  => 'fa-solid fa-tablet-screen-button',
                'desktop' => 'fa-solid fa-desktop',
            ],
        ];
    }

    $class = $icons[$group][$name] ?? ($icons[$group]['Other'] ?? 'fa-solid fa-circle');

    return '<span class="stats-icon"><i class="' . e($class) . '"></i></span>';
}

function statsScreenType(string $screen): string {
    if (!preg_match('/^(\d+)x/', $screen, $m)) return 'desktop';
    $w = (int)$m[1];

    if ($w <= 480)  return 'mobile';
    if ($w <= 1024) return 'tablet';
    return 'desktop';
}

// ── Favicon ───────────────────────────────────────────────────────
function statsFavicon(string $url): string {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return '';

    $slug = preg_replace('/[^a-z0-9.\-]/', '', strtolower($host));
    $localPath = __DIR__ . '/../assets/img/favicons/' . $slug . '.png';

    if (file_exists($localPath)) {
        $src = '/assets/img/favicons/' . htmlspecialchars($slug) . '.png';
    } else {
        $src = 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=16';
    }

    return '<img src="' . $src . '" width="14" height="14" style="border-radius:2px;flex-shrink:0" alt="" loading="lazy">';
}

// ── Tabs ──────────────────────────────────────────────────────────
$typeNav = [
    'pages'     => __('table.top_pages'),
    'countries' => __('table.countries'),
    'cities'    => __('table.cities'),
    'referrers' => __('table.referrers'),
    'browsers'  => __('table.browsers'),
    'os'        => __('table.os'),
    'screens'   => __('table.screens'),
];
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
        <a href="?site=<?= $activeSite ?>&period=<?= $key ?>&type=<?= $type ?>"
           class="<?= $period === $key ? 'active' : '' ?>">
          <?= $info['label'] ?>
        </a>
      <?php endforeach; ?>
	      <button id="autorefresh-btn" class="autorefresh-btn" title="Автообновление">⟳ <span id="autorefresh-counter" class="ar-counter"></span></button>
    </div>
  </div>
</div>

<div class="content">

  <a href="dashboard.php?site=<?= $activeSite ?>&period=<?= $period ?>" class="stats-back">
    ← <?= __('session.back') ?>
  </a>

  <div class="stats-tabs">
    <?php foreach ($typeNav as $t => $label): ?>
      <a href="<?= statsTypeUrl($t) ?>"
         class="stats-tab <?= $type === $t ? 'active' : '' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card">

    <div class="card-header">
      <span><?= htmlspecialchars($typeLabels[$type] ?? $type) ?></span>
      <span class="stats-total">
        <?= number_format($total) ?> <?= __('stats.total') ?>
      </span>
    </div>

    <div class="card-body stats-body">

      <?php if (!$rows): ?>
        <div class="stats-empty"><?= __('table.no_data') ?></div>
      <?php else: ?>

      <table class="tbl stats-tbl">
        <colgroup>
          <col style="width: 44px;">
          <col>
          <col style="width: 34%;">
          <col style="width: 90px;">
        </colgroup>

        <thead>
          <tr>
            <th class="stats-col-num">#</th>
            <th>
              <?php
              if ($type === 'countries') echo __('table.countries');
              elseif ($type === 'cities') echo __('table.cities');
              elseif ($type === 'pages') echo __('table.top_pages');
              elseif ($type === 'referrers') echo __('table.referrers');
              else echo $typeLabels[$type] ?? $type;
              ?>
            </th>
            <th class="stats-col-share"><?= __('stats.share') ?></th>
            <th class="tar stats-col-count"><?= __('metric.sessions') ?></th>
          </tr>
        </thead>

        <tbody>
        <?php
        $rowNum = ($pg - 1) * $perPage;
        $pageTotal = max(1, (int)array_sum(array_column($rows, 'cnt')));

        foreach ($rows as $row):
          $rowNum++;
          $cnt = (int)$row['cnt'];
        ?>
          <tr>

            <td class="mono muted stats-col-num"><?= $rowNum ?></td>

            <?php if ($type === 'countries'): ?>
            <td>
              <div class="stats-row">
                <?= flag_img(strtolower($row['country_code'] ?? ''), $row['country'] ?? '') ?>
                <span class="stats-main-text"><?= htmlspecialchars($row['country'] ?? '—') ?></span>
                <span class="mono muted"><?= strtoupper($row['country_code'] ?? '') ?></span>
              </div>
            </td>

            <?php elseif ($type === 'cities'): ?>
            <td>
              <div class="stats-row">
                <?= flag_img(strtolower($row['country_code'] ?? ''), $row['country'] ?? '') ?>
                <span class="stats-main-text"><?= htmlspecialchars($row['city'] ?? '—') ?></span>
                <span class="mono muted"><?= htmlspecialchars($row['country'] ?? '') ?></span>
              </div>
            </td>

            <?php elseif ($type === 'pages'): ?>
            <td class="stats-cell-ellipsis">
              <div class="stats-page">
                <?php if (!empty($row['title'])): ?>
                  <div class="stats-page-title" title="<?= htmlspecialchars($row['title']) ?>">
                    <?= htmlspecialchars($row['title']) ?>
                  </div>
                <?php endif; ?>

                <div class="stats-page-path" title="<?= htmlspecialchars($row['path'] ?? '') ?>">
                  <?= htmlspecialchars($row['path'] ?? '/') ?>
                </div>
              </div>
            </td>

            <?php elseif ($type === 'referrers'): ?>
            <td class="stats-cell-ellipsis">
              <?php
                $ref  = $row['referrer'] ?? '';
                $host = $row['host'] ?? '';
              ?>
              <div class="stats-row">
                <?= statsFavicon($ref) ?>
                <a href="<?= htmlspecialchars($ref) ?>" target="_blank" rel="noopener"
                   class="stats-link"
                   title="<?= htmlspecialchars($ref) ?>">
                  <?= htmlspecialchars($host ?: $ref) ?>
                </a>
              </div>
            </td>

            <?php else: ?>
            <td>
              <?php if ($type === 'browsers'): ?>
                <span class="stats-icon-row">
                  <?= statsIcon('browser', $row['name'] ?? 'Other') ?>
                  <span class="stats-main-text"><?= htmlspecialchars($row['name'] ?? '—') ?></span>
                </span>

              <?php elseif ($type === 'os'): ?>
                <span class="stats-icon-row">
                  <?= statsIcon('os', $row['name'] ?? 'Other') ?>
                  <span class="stats-main-text"><?= htmlspecialchars($row['name'] ?? '—') ?></span>
                </span>

              <?php elseif ($type === 'screens'): ?>
                <?php $stype = statsScreenType($row['screen'] ?? ''); ?>
                <span class="stats-icon-row">
                  <?= statsIcon('screen', $stype) ?>
                  <span class="mono stats-main-text"><?= htmlspecialchars($row['screen'] ?? '—') ?></span>
                </span>

              <?php else: ?>
                <span class="stats-main-text"><?= htmlspecialchars($row['name'] ?? '—') ?></span>
              <?php endif; ?>
            </td>
            <?php endif; ?>

            <td class="stats-col-share">
              <div class="stats-bar">
                <div class="bar-track">
                  <div class="bar-fill" style="width:<?= pct($cnt, $maxCount) ?>%"></div>
                </div>
                <span class="mono muted stats-bar-pct">
                  <?= pct($cnt, $pageTotal) ?>%
                </span>
              </div>
            </td>

            <td class="mono tar stats-col-count"><?= number_format($cnt) ?></td>

          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php endif; ?>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">

        <div class="pagination-info"><?= $pg ?> / <?= $totalPages ?></div>

        <div class="pagination-pages">
          <?php if ($pg > 1): ?>
            <a class="pg-nav" href="<?= statsPageUrl($pg - 1) ?>">‹</a>
          <?php endif; ?>

          <?php
          $window = 2;
          $pages = [];

          for ($i = 1; $i <= $totalPages; $i++) {
              if ($i === 1 || $i === $totalPages || ($i >= $pg - $window && $i <= $pg + $window)) {
                  $pages[] = $i;
              }
          }

          $prev = null;

          foreach ($pages as $p):
              if ($prev !== null && $p - $prev > 1): ?>
                <span class="pg-dots">…</span>
          <?php endif;

              if ($p === $pg): ?>
                <span class="pg-current"><?= $p ?></span>
          <?php else: ?>
                <a href="<?= statsPageUrl($p) ?>"><?= $p ?></a>
          <?php endif;

              $prev = $p;
          endforeach;
          ?>

          <?php if ($pg < $totalPages): ?>
            <a class="pg-nav" href="<?= statsPageUrl($pg + 1) ?>">›</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>

</div>