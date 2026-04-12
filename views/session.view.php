<?php
// Variables from session.php:
// $session, $events, $site, $sessionId, $siteId
// $parsedUA, $duration, $isBot, $isSuspicious, $botScore, $blockedReason
// $reasonLabels, $eventLabels
?>

<div class="content" style="max-width:860px">

  <a href="dashboard.php?site=<?= $siteId ?><?= $isBot ? '&tab=bots' : '' ?>" class="back" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;color:var(--muted);text-decoration:none;margin-bottom:16px">
    ← <?= __('session.back') ?>
  </a>

  <div class="page-title" style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
    <span style="font-size:17px;font-weight:600"><?= __('session.title') ?></span>
    <?php if ($isBot): ?>
      <span class="status-badge status-bot"><?= e(__('session.status.bot')) ?></span>
    <?php elseif ($isSuspicious): ?>
      <span class="status-badge status-suspicious"><?= e(__('session.status.suspicious')) ?></span>
    <?php elseif ((int)$session['is_valid']): ?>
      <span class="status-badge status-valid"><?= e(__('session.status.valid')) ?></span>
    <?php else: ?>
      <span class="status-badge status-pending"><?= e(__('session.status.pending')) ?></span>
    <?php endif; ?>
  </div>
  <div class="page-sub" style="font-size:11px;color:var(--muted);font-family:var(--mono);margin-bottom:20px;word-break:break-all">
    <?= htmlspecialchars($sessionId) ?> · <?= htmlspecialchars($site['domain'] ?? '') ?>
  </div>

  <?php if ($isBot): ?>
  <div class="bot-banner" style="margin-bottom:20px">
    <div>
      <div class="bot-banner-label"><?= e(__('session.bot_score')) ?></div>
      <div class="bot-banner-score"><?= $botScore ?></div>
    </div>
    <div>
      <div class="bot-banner-label"><?= e(__('session.reason')) ?></div>
      <div class="bot-banner-reason">
        <?= htmlspecialchars($reasonLabels[$blockedReason] ?? ($blockedReason ?: '—')) ?>
      </div>
    </div>
  </div>
  <?php elseif ($isSuspicious): ?>
  <div class="suspicious-banner" style="margin-bottom:20px">
    <span style="font-size:16px">~</span>
    <span><?= e(sprintf(__('session.suspicious_score'), $botScore)) ?></span>
  </div>
  <?php endif; ?>

  <div class="info-grid">
    <div class="info-card <?= $isBot ? 'bot-card' : '' ?>">
      <div class="info-card-label"><?= __('session.ip') ?></div>
      <div class="info-card-value mono"><?= htmlspecialchars($session['ip'] ?? '—') ?></div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.location') ?></div>
      <div class="info-card-value" style="display:flex;align-items:center;gap:6px;white-space:normal">
        <?= flag_img(strtolower($session['country_code'] ?? ''), $session['country'] ?? '') ?>
        <?= htmlspecialchars(implode(', ', array_filter([$session['country'], $session['city']])) ?: '—') ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.started') ?></div>
      <div class="info-card-value mono" style="font-size:11px">
        <?= $session['started_at'] ? date('d.m.Y H:i:s', strtotime($session['started_at'])) : '—' ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.duration') ?></div>
      <div class="info-card-value">
        <?php
        if ($duration >= 60)   echo floor($duration / 60) . ' ' . __('session.duration_min') . ' ' . ($duration % 60) . ' ' . __('session.duration_sec');
        elseif ($duration > 0) echo $duration . ' ' . __('session.duration_sec');
        else                   echo __('session.duration_lt1');
        ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.browser') ?></div>
      <div class="info-card-value" style="display:flex;align-items:center;gap:6px">
        <?= sessionIcon('browser', $parsedUA['browser']) ?>
        <?= htmlspecialchars($parsedUA['browser']) ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.os') ?></div>
      <div class="info-card-value" style="display:flex;align-items:center;gap:6px">
        <?= sessionIcon('os', $parsedUA['os']) ?>
        <?= htmlspecialchars($parsedUA['os']) ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.screen') ?></div>
      <div class="info-card-value" style="display:flex;align-items:center;gap:6px">
        <?php $stype = sessionScreenType($session['screen'] ?? ''); ?>
        <?= sessionIcon('screen', $stype) ?>
        <span class="mono"><?= htmlspecialchars($session['screen'] ?? '—') ?></span>
      </div>
    </div>
    <div class="info-card">
      <div class="info-card-label"><?= __('session.language') ?></div>
      <div class="info-card-value mono"><?= htmlspecialchars($session['language'] ?? '—') ?></div>
    </div>
  </div>

  <?php if (!empty($session['referrer'])): ?>
  <div class="meta-block">
    <strong><?= __('session.referrer') ?>:</strong>
    <a href="<?= htmlspecialchars($session['referrer']) ?>" target="_blank" rel="noopener"
       style="color:var(--accent);text-decoration:none;word-break:break-all">
      <?= htmlspecialchars($session['referrer']) ?>
    </a>
  </div>
  <?php endif; ?>

  <div class="meta-block">
    <strong><?= __('session.user_agent') ?>:</strong>
    <span style="word-break:break-all"><?= htmlspecialchars($session['user_agent'] ?? '—') ?></span>
  </div>

  <!-- Timeline -->
  <div class="timeline-wrap">
    <div class="timeline-header">
      <span><?= __('session.timeline') ?></span>
      <span class="event-count"><?= count($events) ?> <?= __('session.events_count') ?></span>
    </div>

    <?php if ($events): ?>
    <div class="timeline">
      <?php foreach ($events as $ev):
        $meta = $eventLabels[$ev['event_type']] ?? ['label' => $ev['event_type'], 'color' => '#8e8e93'];
        $bg   = $meta['color'] . '18';
      ?>
      <div class="tl-item">
        <div class="tl-left">
          <div class="tl-dot" style="background:<?= $meta['color'] ?>;box-shadow:0 0 0 1px <?= $meta['color'] ?>"></div>
          <div class="tl-line"></div>
        </div>
        <div class="tl-body">
          <div class="tl-top">
            <span class="tl-type" style="background:<?= $bg ?>;color:<?= $meta['color'] ?>">
              <?= htmlspecialchars($meta['label']) ?>
            </span>
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

</div><!-- .content -->
