<?php
// Variables:
// $message, $msgType, $available, $current
// $blockedIps, $blockedSubnets, $blockedAsns
?>

<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <div class="topbar-left">
    <h1><?= e(__('settings.title')) ?></h1>
    <div class="domain"><?= e(__('settings.subtitle')) ?></div>
  </div>
</div>

<div class="content">

  <?php if ($message): ?>
    <div class="msg <?= e($msgType) ?>"><?= e($message) ?></div>
  <?php endif; ?>

  <!-- LANGUAGE -->

  <div class="card">
    <div class="card-header"><?= e(__('settings.language')) ?></div>

    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="language">

        <div class="lang-grid">
          <?php foreach ($available as $code => $name): ?>
            <div class="lang-option">
              <input
                type="radio"
                name="locale"
                id="lang-<?= e($code) ?>"
                value="<?= e($code) ?>"
                <?= $code === $current ? 'checked' : '' ?>
              >
              <label for="lang-<?= e($code) ?>">
                <?= flag_img($code, $name, '18px') ?>
                <?= e($name) ?>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <button class="btn btn-accent">
          <?= e(__('settings.language.save')) ?>
        </button>
      </form>
    </div>
  </div>

  <!-- PASSWORD -->

  <div class="card">
    <div class="card-header"><?= e(__('settings.password')) ?></div>

    <div class="card-body">
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">

        <div class="field">
          <label><?= e(__('settings.password.current')) ?></label>
          <input type="password" name="current_password" required>
        </div>

        <div class="field">
          <label><?= e(__('settings.password.new')) ?></label>
          <input type="password" name="new_password" minlength="8" required>
        </div>

        <button class="btn btn-accent">
          <?= e(__('settings.password.save')) ?>
        </button>
      </form>
    </div>
  </div>

  <!-- BLOCKS -->

  <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
  <div class="card">
    <div class="card-header"><?= e(__('settings.blocks.title')) ?></div>

    <div class="card-body">

      <!-- IP -->

      <form method="post" style="margin-bottom:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="block_rule">
        <input type="hidden" name="rule_type" value="ip">

        <div class="field">
          <label><?= e(__('settings.blocks.ip')) ?></label>
          <input type="text" name="ip" placeholder="203.0.113.10" required>
        </div>

        <div class="field">
          <label><?= e(__('settings.blocks.reason')) ?></label>
          <input type="text" name="reason" maxlength="255">
        </div>

        <button class="btn btn-accent">
          <?= e(__('settings.blocks.add_ip')) ?>
        </button>
      </form>

      <!-- SUBNET -->

      <form method="post" style="margin-bottom:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="block_rule">
        <input type="hidden" name="rule_type" value="subnet">

        <div class="field">
          <label><?= e(__('settings.blocks.subnet')) ?></label>
          <input type="text" name="subnet" placeholder="203.0.113.0/24" required>
        </div>

        <div class="field">
          <label><?= e(__('settings.blocks.reason')) ?></label>
          <input type="text" name="reason" maxlength="255">
        </div>

        <button class="btn btn-accent">
          <?= e(__('settings.blocks.add_subnet')) ?>
        </button>
      </form>

      <!-- ASN -->

      <form method="post" style="margin-bottom:20px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="block_rule">
        <input type="hidden" name="rule_type" value="asn">

        <div class="field">
          <label><?= e(__('settings.blocks.asn')) ?></label>
          <input type="text" name="asn" placeholder="AS15169" required>
        </div>

        <div class="field">
          <label><?= e(__('settings.blocks.reason')) ?></label>
          <input type="text" name="reason" maxlength="255">
        </div>

        <button class="btn btn-accent">
          <?= e(__('settings.blocks.add_asn')) ?>
        </button>
      </form>

      <div class="divider"></div>

      <!-- ACTIVE IPs -->

      <div class="field">
        <label><?= e(__('settings.blocks.active_ips')) ?></label>

        <?php if (empty($blockedIps)): ?>
          <div class="empty">—</div>
        <?php else: ?>
          <?php foreach ($blockedIps as $row): ?>
            <form method="post" class="inline-row">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_block">
              <input type="hidden" name="block_type" value="ip">
              <input type="hidden" name="ip" value="<?= e($row['ip']) ?>">

              <span class="mono"><?= e($row['ip']) ?></span>
              <span><?= e($row['reason'] ?: '—') ?></span>

              <button class="btn btn-ghost">
                <?= e(__('settings.blocks.remove')) ?>
              </button>
            </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- SUBNETS -->

      <div class="field">
        <label><?= e(__('settings.blocks.active_subnets')) ?></label>

        <?php if (empty($blockedSubnets)): ?>
          <div class="empty">—</div>
        <?php else: ?>
          <?php foreach ($blockedSubnets as $row): ?>
            <form method="post" class="inline-row">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_block">
              <input type="hidden" name="block_type" value="subnet">
              <input type="hidden" name="cidr" value="<?= e($row['cidr']) ?>">

              <span class="mono"><?= e($row['cidr']) ?></span>
              <span><?= e($row['reason'] ?: '—') ?></span>

              <button class="btn btn-ghost">
                <?= e(__('settings.blocks.remove')) ?>
              </button>
            </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ASN -->

      <div class="field">
        <label><?= e(__('settings.blocks.active_asns')) ?></label>

        <?php if (empty($blockedAsns)): ?>
          <div class="empty">—</div>
        <?php else: ?>
          <?php foreach ($blockedAsns as $row): ?>
            <form method="post" class="inline-row">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_block">
              <input type="hidden" name="block_type" value="asn">
              <input type="hidden" name="asn" value="<?= e($row['asn']) ?>">

              <span class="mono"><?= e($row['asn']) ?></span>
              <span><?= e($row['reason'] ?: '—') ?></span>

              <button class="btn btn-ghost">
                <?= e(__('settings.blocks.remove')) ?>
              </button>
            </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
  <?php endif; ?>

</div>