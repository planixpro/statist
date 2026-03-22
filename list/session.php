<?php
require __DIR__ . '/auth.php'; // also loads Lang
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';

$sessionId = $_GET['id']   ?? '';
$siteId    = (int)($_GET['site'] ?? 0);

if (!$sessionId || !$siteId) {
    header('Location: dashboard.php');
    exit;
}

// Данные сессии
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = ? AND site_id = ? LIMIT 1");
$stmt->execute([$sessionId, $siteId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: dashboard.php');
    exit;
}

// Все события сессии
$events = $pdo->prepare("
    SELECT * FROM events
    WHERE session_id = ? AND site_id = ?
    ORDER BY created_at ASC
");
$events->execute([$sessionId, $siteId]);
$events = $events->fetchAll();

// Имя сайта
$site = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
$site->execute([$siteId]);
$site = $site->fetch();

function parseUA(string $ua): array {
    $browser = 'Other';
    if (str_contains($ua, 'Edg/'))    $browser = 'Edge';
    elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) $browser = 'Opera';
    elseif (str_contains($ua, 'Chrome'))  $browser = 'Chrome';
    elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari'))  $browser = 'Safari';

    $os = 'Other';
    if (str_contains($ua, 'Windows'))     $os = 'Windows';
    elseif (str_contains($ua, 'Mac OS'))  $os = 'macOS';
    elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) $os = 'iOS';
    elseif (str_contains($ua, 'Android')) $os = 'Android';
    elseif (str_contains($ua, 'Linux'))   $os = 'Linux';

    return ['browser' => $browser, 'os' => $os];
}

$ua      = parseUA($session['user_agent'] ?? '');
$duration = 0;
if ($session['last_activity'] && $session['started_at']) {
    $duration = strtotime($session['last_activity']) - strtotime($session['started_at']);
}

$eventLabels = [
    'page_view'   => ['label' => __('event.page_view'),  'color' => '#4f46e5'],
    'heartbeat'   => ['label' => __('event.heartbeat'),'color' => '#0ea5e9'],
    'click'       => ['label' => __('event.click'),      'color' => '#f59e0b'],
    'session_end' => ['label' => __('event.session_end'),     'color' => '#8e8e93'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Сессия <?= htmlspecialchars(substr($sessionId,0,12)) ?>… — Statist</title>
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
  --text:     #1c1c1e;
  --text2:    #48484a;
  --muted:    #8e8e93;
  --radius:   10px;
}

body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }

.page { max-width: 860px; margin: 0 auto; padding: 28px 24px; }

.back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted);
  text-decoration: none;
  margin-bottom: 20px;
  transition: color 0.12s;
}
.back:hover { color: var(--accent); }

.page-title { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
.page-sub { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); margin-bottom: 24px; }

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}

.info-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
}

.info-card-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 6px;
}

.info-card-value {
  font-size: 14px;
  font-weight: 500;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.info-card-value.mono {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  font-weight: 400;
}

/* UA block */
.ua-block {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 16px;
  margin-bottom: 24px;
  font-size: 12px;
  color: var(--muted);
}

.ua-block strong { color: var(--text2); font-weight: 500; }

/* Timeline */
.timeline-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}

.timeline-header {
  padding: 12px 18px;
  border-bottom: 1px solid var(--border);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: var(--muted);
  text-transform: uppercase;
  background: var(--surface2);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.event-count {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  background: var(--accent-l);
  color: var(--accent);
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 500;
}

.timeline { padding: 8px 0; }

.tl-item {
  display: flex;
  align-items: flex-start;
  gap: 0;
  padding: 0 18px;
  position: relative;
}

.tl-item:last-child .tl-line { display: none; }

.tl-left {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 32px;
  flex-shrink: 0;
  padding-top: 14px;
}

.tl-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 2px solid var(--surface);
  box-shadow: 0 0 0 1px var(--border2);
  flex-shrink: 0;
  z-index: 1;
}

.tl-line {
  width: 1px;
  flex: 1;
  min-height: 20px;
  background: var(--border);
  margin: 3px 0;
}

.tl-body {
  flex: 1;
  padding: 10px 0 10px 12px;
  border-bottom: 1px solid var(--border);
  min-width: 0;
}

.tl-item:last-child .tl-body { border-bottom: none; }

.tl-top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.tl-type {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}

.tl-time {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  color: var(--muted);
}

.tl-path {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--text);
  font-weight: 500;
  word-break: break-all;
}

.tl-query {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  color: var(--muted);
  margin-top: 2px;
  word-break: break-all;
}

.tl-extra {
  font-size: 12px;
  color: var(--muted);
  margin-top: 3px;
}

.empty-events {
  padding: 32px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}

@media (max-width: 600px) {
  .page { padding: 16px 12px; }
  .page-title { font-size: 16px; }
  .info-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
  .info-card { padding: 10px 12px; }
  .info-card-value { font-size: 13px; }
  .tl-body { padding: 8px 0 8px 10px; }
  .tl-path { font-size: 11px; }
}
</style>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body>
<div class="page">

  <a href="dashboard.php?site=<?= $siteId ?>" class="back"><?= __('session.back') ?></a>

  <div class="page-title"><?= __('session.title') ?></div>
  <div class="page-sub"><?= htmlspecialchars($sessionId) ?> · <?= htmlspecialchars($site['domain'] ?? '') ?></div>

  <div class="info-grid">
    <div class="info-card">
      <div class="info-card-label"><?= __('session.ip') ?></div>
      <div class="info-card-value mono"><?= htmlspecialchars($session['ip'] ?? '—') ?></div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.location') ?></div>
      <div class="info-card-value" style="display:flex;align-items:center;gap:6px">
        <?= flag_img(strtolower($session['country_code'] ?? ''), $session['country'] ?? '') ?>
        <?= htmlspecialchars(implode(', ', array_filter([$session['country'], $session['city']])) ?: '—') ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.started') ?></div>
      <div class="info-card-value mono"><?= $session['started_at'] ? date('d.m.Y H:i:s', strtotime($session['started_at'])) : '—' ?></div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.duration') ?></div>
      <div class="info-card-value">
        <?php
        if ($duration >= 60) echo floor($duration/60) . " " . __('session.duration_min') . " " . ($duration%60) . " " . __('session.duration_sec');
        elseif ($duration > 0) echo $duration . ' ' . __('session.duration_sec');
        else echo __('session.duration_lt1');
        ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.browser') ?></div>
      <div class="info-card-value"><?= htmlspecialchars($ua['browser']) ?></div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.os') ?></div>
      <div class="info-card-value"><?= htmlspecialchars($ua['os']) ?></div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.screen') ?></div>
      <div class="info-card-value mono"><?= htmlspecialchars($session['screen'] ?? '—') ?></div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.language') ?></div>
      <div class="info-card-value mono"><?= htmlspecialchars($session['language'] ?? '—') ?></div>
    </div>
  </div>

  <?php if (!empty($session['referrer'])): ?>
  <div class="ua-block" style="margin-bottom:12px">
    <strong><?= __('session.referrer') ?>:</strong> <?= htmlspecialchars($session['referrer']) ?>
  </div>
  <?php endif; ?>

  <div class="ua-block">
    <strong><?= __('session.user_agent') ?>:</strong> <?= htmlspecialchars($session['user_agent'] ?? '—') ?>
  </div>

  <!-- Timeline -->
  <div class="timeline-wrap">
    <div class="timeline-header">
      <span><?= __('session.timeline') ?></span>
      <span class="event-count"><?= count($events) ?> <?= __('session.events_count') ?></span>
    </div>

    <?php if ($events): ?>
    <div class="timeline">
      <?php foreach ($events as $i => $ev):
        $meta = $eventLabels[$ev['event_type']] ?? ['label' => $ev['event_type'], 'color' => '#8e8e93'];
        $bg   = $meta['color'] . '1a';
      ?>
      <div class="tl-item">
        <div class="tl-left">
          <div class="tl-dot" style="background:<?= $meta['color'] ?>; box-shadow: 0 0 0 1px <?= $meta['color'] ?>"></div>
          <div class="tl-line"></div>
        </div>
        <div class="tl-body">
          <div class="tl-top">
            <span class="tl-type" style="background:<?= $bg ?>;color:<?= $meta['color'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
            <span class="tl-time"><?= date('H:i:s', strtotime($ev['created_at'])) ?></span>
          </div>
          <?php if ($ev['path']): ?>
            <div class="tl-path"><?= htmlspecialchars($ev['path']) ?></div>
          <?php endif; ?>
          <?php if (!empty($ev['query'])): ?>
            <div class="tl-query"><?= htmlspecialchars($ev['query']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="empty-events"><?= __('session.no_events') ?></div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
