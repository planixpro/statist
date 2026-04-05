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

// ── SVG иконки для браузеров, ОС и экранов ──────────────────────
function statsIcon(string $group, string $name): string {
    static $icons = null;
    if ($icons === null) {
        $icons = [
            'browser' => [
                'Chrome'  => '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="#fff" stroke="#e5e5ea" stroke-width=".5"/><circle cx="12" cy="12" r="4" fill="#4285F4"/><path d="M12 8h9" stroke="#EA4335" stroke-width="2.5" stroke-linecap="round"/><path d="M12 8 7.3 16" stroke="#FBBC05" stroke-width="2.5" stroke-linecap="round"/><path d="M7.3 16H21" stroke="#34A853" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="0 13.7 6 99"/><circle cx="12" cy="12" r="1.8" fill="#fff"/></svg>',
                'Firefox' => '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="9" fill="#FF7139"/><circle cx="12" cy="12" r="5" fill="#FF980E"/><circle cx="12" cy="12" r="2.5" fill="#FFCA00"/></svg>',
                'Safari'  => '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="9" fill="#1C8EF9"/><line x1="12" y1="4" x2="12" y2="6.5" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="12" y1="17.5" x2="12" y2="20" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="4" y1="12" x2="6.5" y2="12" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="17.5" y1="12" x2="20" y2="12" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><polygon points="12,7 13.5,13.5 12,12.5 10.5,13.5" fill="#fff"/><polygon points="12,17 10.5,10.5 12,11.5 13.5,10.5" fill="rgba(255,255,255,0.45)"/></svg>',
                'Edge'    => '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 3C7.03 3 3 7.03 3 12c0 3.86 2.42 7.16 5.87 8.46C7.5 19 7 17.5 7 16c0-3.5 2.8-6 6-6 .34 0 .68.03 1 .08V8c-3.87 0-7 3.13-7 7 0 1.3.35 2.5.97 3.54A9 9 0 1 1 12 3z" fill="#0078D4"/><path d="M20.5 14.5c0 3.04-2.46 5.5-5.5 5.5-2.4 0-4.46-1.54-5.2-3.68.44.12.9.18 1.2.18 2.2 0 4-1.8 4-4 0-.46-.1-.9-.24-1.3A5.5 5.5 0 0 1 20.5 14.5z" fill="#50E6FF"/></svg>',
                'Opera'   => '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="9" fill="#FF1B2D"/><ellipse cx="12" cy="12" rx="4" ry="6.5" fill="none" stroke="#fff" stroke-width="1.8"/></svg>',
                'Other'   => '<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="9" fill="#c7c7cc"/><circle cx="12" cy="12" r="4" fill="none" stroke="#fff" stroke-width="1.5"/><line x1="12" y1="3" x2="12" y2="21" stroke="#fff" stroke-width="1" opacity=".5"/><line x1="3" y1="12" x2="21" y2="12" stroke="#fff" stroke-width="1" opacity=".5"/></svg>',
            ],
            'os' => [
                'Windows' => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="3" y="3" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="12.5" y="3" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="3" y="12.5" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="12.5" y="12.5" width="8.5" height="8.5" rx=".5" fill="#0078D4"/></svg>',
                'macOS'   => '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M15.5 4.5c.1 1.7-1.2 3-2.8 3-.2-1.6 1.3-3.1 2.8-3z" fill="#555"/><path d="M18 16.8c-.8.8-1.7.7-2.6.3-.9-.4-1.7-.4-2.7 0-1.2.5-1.8.4-2.5-.3C6.5 13.5 7 8 11.3 7.7c1.1.1 1.9.6 2.5.7 1-.2 1.9-.8 2.9-.7 1.2.1 2.2.6 2.8 1.5-2.5 1.5-1.9 4.9.4 5.8-.5 1.2-1.1 2.4-1.9 3.8z" fill="#555"/></svg>',
                'iOS'     => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="7" y="2" width="10" height="20" rx="2.5" fill="#555"/><rect x="9" y="4" width="6" height="13.5" rx=".5" fill="#aaa"/><rect x="10.5" y="18.5" width="3" height="1" rx=".5" fill="#888"/></svg>',
                'Android' => '<svg viewBox="0 0 24 24" width="18" height="18"><ellipse cx="12" cy="10" rx="6.5" ry="5" fill="#3DDC84"/><rect x="5.5" y="10" width="13" height="8" fill="#3DDC84"/><rect x="5.5" y="15" width="13" height="3" rx="1.5" fill="#2BB873"/><circle cx="9.5" cy="9.5" r="1" fill="#fff"/><circle cx="14.5" cy="9.5" r="1" fill="#fff"/><line x1="8.5" y1="6.5" x2="6.5" y2="3.5" stroke="#3DDC84" stroke-width="1.5" stroke-linecap="round"/><line x1="15.5" y1="6.5" x2="17.5" y2="3.5" stroke="#3DDC84" stroke-width="1.5" stroke-linecap="round"/><rect x="2.5" y="10" width="2" height="5" rx="1" fill="#3DDC84"/><rect x="19.5" y="10" width="2" height="5" rx="1" fill="#3DDC84"/></svg>',
                'Linux'   => '<svg viewBox="0 0 24 24" width="18" height="18"><ellipse cx="12" cy="9" rx="4.5" ry="5.5" fill="#E8A000"/><rect x="7.5" y="9" width="9" height="7" rx=".5" fill="#E8A000"/><circle cx="10" cy="8" r="1" fill="#5c3a00"/><circle cx="14" cy="8" r="1" fill="#5c3a00"/><path d="M9 17.5 7 20h10l-2-2.5" fill="#c47c00"/><ellipse cx="9" cy="20" rx="2" ry="1" fill="#888"/><ellipse cx="15" cy="20" rx="2" ry="1" fill="#888"/></svg>',
                'Other'   => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="3" y="4" width="18" height="12" rx="2" fill="#c7c7cc"/><rect x="8" y="16" width="8" height="2" fill="#c7c7cc"/><rect x="6" y="18" width="12" height="1.5" rx=".5" fill="#c7c7cc"/></svg>',
            ],
            'screen' => [
                'mobile'  => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="7" y="2" width="10" height="20" rx="2.5" fill="#6366f1"/><rect x="9" y="4" width="6" height="13.5" rx=".5" fill="rgba(255,255,255,0.22)"/><rect x="10.5" y="18.5" width="3" height="1" rx=".5" fill="rgba(255,255,255,0.7)"/></svg>',
                'tablet'  => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="4" y="2" width="16" height="20" rx="2.5" fill="#0ea5e9"/><rect x="6" y="4" width="12" height="14" rx=".5" fill="rgba(255,255,255,0.22)"/><rect x="10.5" y="19.5" width="3" height="1" rx=".5" fill="rgba(255,255,255,0.7)"/></svg>',
                'desktop' => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="2" y="3" width="20" height="14" rx="2" fill="#34c759"/><rect x="4" y="5" width="16" height="10" rx=".5" fill="rgba(255,255,255,0.22)"/><path d="M8 21h8M12 17v4" stroke="#34c759" stroke-width="1.5" stroke-linecap="round"/></svg>',
            ],
        ];
    }
    $svg = $icons[$group][$name] ?? ($icons[$group]['Other'] ?? '');
    return $svg ? '<span class="stats-icon">' . $svg . '</span>' : '';
}

function statsScreenType(string $screen): string {
    if (!preg_match('/^(\d+)x/', $screen, $m)) return 'desktop';
    $w = (int)$m[1];
    if ($w <= 480)  return 'mobile';
    if ($w <= 1024) return 'tablet';
    return 'desktop';
}

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
    </div>
  </div>
</div>

<!-- Content -->
<div class="content">

  <!-- Back link -->
  <a href="dashboard.php?site=<?= $activeSite ?>&period=<?= $period ?>" class="stats-back">
    ← <?= __('session.back') ?>
  </a>

  <!-- Type navigation tabs -->
  <div class="stats-tabs">
    <?php foreach ($typeNav as $t => $label): ?>
      <a href="<?= statsTypeUrl($t) ?>"
         class="stats-tab <?= $type === $t ? 'active' : '' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Main card -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
      <span><?= htmlspecialchars($typeLabels[$type] ?? $type) ?></span>
      <span style="font-size:11px;font-weight:400;color:var(--muted)">
        <?= number_format($total) ?> <?= __('stats.total') ?>
      </span>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (!$rows): ?>
        <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">
          <?= __('table.no_data') ?>
        </div>
      <?php else: ?>
      <table class="tbl stats-tbl">
        <thead>
          <tr>
            <th style="width:40px">#</th>
            <?php if ($type === 'countries' || $type === 'cities'): ?>
              <th><?= $type === 'cities' ? __('table.cities') : __('table.countries') ?></th>
            <?php elseif ($type === 'pages'): ?>
              <th><?= __('table.top_pages') ?></th>
            <?php elseif ($type === 'referrers'): ?>
              <th><?= __('table.referrers') ?></th>
            <?php else: ?>
              <th><?= $typeLabels[$type] ?? $type ?></th>
            <?php endif; ?>
            <th style="width:180px"><?= __('stats.share') ?></th>
            <th style="width:80px;text-align:right"><?= __('metric.sessions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rowNum = ($pg - 1) * $perPage;
          foreach ($rows as $row):
            $rowNum++;
            $cnt = (int)$row['cnt'];
          ?>
          <tr>
            <td class="mono" style="color:var(--muted);font-size:11px"><?= $rowNum ?></td>

            <?php if ($type === 'countries'): ?>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <?= flag_img(strtolower($row['country_code'] ?? ''), $row['country'] ?? '') ?>
                <?= htmlspecialchars($row['country'] ?? '—') ?>
                <span class="mono" style="color:var(--muted)"><?= strtoupper($row['country_code'] ?? '') ?></span>
              </div>
            </td>

            <?php elseif ($type === 'cities'): ?>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <?= flag_img(strtolower($row['country_code'] ?? ''), $row['country'] ?? '') ?>
                <span><?= htmlspecialchars($row['city'] ?? '—') ?></span>
                <span class="mono" style="color:var(--muted)"><?= htmlspecialchars($row['country'] ?? '') ?></span>
              </div>
            </td>

            <?php elseif ($type === 'referrers'): ?>
            <td style="max-width:0">
              <?php
                $ref  = $row['referrer'] ?? '';
                $host = parse_url($ref, PHP_URL_HOST) ?: $ref;
              ?>
              <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <a href="<?= htmlspecialchars($ref) ?>" target="_blank" rel="noopener"
                   class="stats-link" title="<?= htmlspecialchars($ref) ?>">
                  <?= htmlspecialchars($host) ?>
                </a>
              </div>
            </td>

            <?php elseif ($type === 'pages'): ?>
            <td style="max-width:0">
              <div class="mono" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px"
                   title="<?= htmlspecialchars($row['path'] ?? '') ?>">
                <?= htmlspecialchars($row['path'] ?? '/') ?>
              </div>
            </td>

            <?php else: ?>
            <td>
              <?php if ($type === 'browsers'): ?>
                <span class="stats-icon-row"><?= statsIcon('browser', $row['name'] ?? 'Other') ?><?= htmlspecialchars($row['name'] ?? '—') ?></span>
              <?php elseif ($type === 'os'): ?>
                <span class="stats-icon-row"><?= statsIcon('os', $row['name'] ?? 'Other') ?><?= htmlspecialchars($row['name'] ?? '—') ?></span>
              <?php elseif ($type === 'screens'): ?>
                <?php $stype = statsScreenType($row['screen'] ?? $row['name'] ?? ''); ?>
                <span class="stats-icon-row"><?= statsIcon('screen', $stype) ?><span class="mono" style="font-size:12px"><?= htmlspecialchars($row['screen'] ?? $row['name'] ?? '—') ?></span></span>
              <?php else: ?>
                <?= htmlspecialchars($row['name'] ?? '—') ?>
              <?php endif; ?>
            </td>
            <?php endif; ?>

            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="bar-track" style="width:100%;flex:1">
                  <div class="bar-fill" style="width:<?= pct($cnt, $maxCount) ?>%"></div>
                </div>
                <span class="mono" style="font-size:10px;color:var(--muted);min-width:30px;text-align:right">
                  <?= pct($cnt, array_sum(array_column($rows, 'cnt'))) ?>%
                </span>
              </div>
            </td>

            <td class="mono" style="text-align:right;font-size:13px;color:var(--text)">
              <?= number_format($cnt) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <div class="pagination-info"><?= $pg ?> / <?= $totalPages ?></div>
        <div class="pagination-pages">
          <?php if ($pg > 1): ?>
            <a class="pg-nav" href="<?= statsPageUrl($pg - 1) ?>">‹</a>
          <?php endif; ?>
          <?php
            $window = 2; $pages = [];
            for ($i = 1; $i <= $totalPages; $i++) {
                if ($i === 1 || $i === $totalPages
                    || ($i >= $pg - $window && $i <= $pg + $window))
                    $pages[] = $i;
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

</div><!-- .content -->
