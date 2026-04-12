<?php
// Variables: $message, $msgType, $sites, $siteViewers, $userSiteMap, $trackerUrl
?>

<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>

  <div class="topbar-left">
    <h1><?= e(__('sites.title')) ?></h1>
    <div class="domain"><?= e(__('sites.subtitle')) ?></div>
  </div>
</div>

<div class="content">

  <?php if ($message): ?>
    <div class="msg <?= e($msgType) ?>">
      <?= e($message) ?>
    </div>
  <?php endif; ?>

  <!-- ───────────── Sites list ───────────── -->

  <div class="card">
    <div class="card-header">
      <?= e(__('sites.title')) ?>
      <span class="muted">(<?= count($sites) ?>)</span>
    </div>

    <?php if (!$sites): ?>
      <div class="empty">
        <?= e(__('sites.empty')) ?>
      </div>
    <?php else: ?>

      <div class="sites-list">

        <?php foreach ($sites as $site): ?>
          <?php
            $id       = (int)$site['id'];
            $assigned = $userSiteMap[$id] ?? [];
            $snippet  = siteSnippet($trackerUrl, $site['domain']);
          ?>

          <div class="site-card">

            <!-- ── LEFT ── -->
            <div class="site-main">

              <div class="site-domain">
                <?= e($site['domain']) ?>
              </div>

              <div class="site-meta">
                <span><?= e(__('sites.col.sessions')) ?>: <b><?= number_format((int)$site['session_count']) ?></b></span>
                <span>•</span>
                <span><?= $site['created_at'] ? e(date('d.m.Y', strtotime($site['created_at']))) : '—' ?></span>
              </div>

              <!-- snippet -->
              <div class="snippet">
                <div class="snippet-code" id="snip-<?= $id ?>">
                  <?= e($snippet) ?>
                </div>

                <button
                  class="btn btn-ghost btn-sm"
                  onclick="copySnippet(<?= $id ?>)"
                  id="copybtn-<?= $id ?>"
                >
                  <?= e(__('sites.snippet.copy')) ?>
                </button>
              </div>

            </div>

            <!-- ── RIGHT ── -->
            <div class="site-side">

              <!-- name edit -->
              <form method="post" class="inline-edit">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="site_id" value="<?= $id ?>">

                <input
                  type="text"
                  name="name"
                  value="<?= e($site['name']) ?>"
                  maxlength="255"
                >

                <button type="submit" class="btn btn-ghost btn-sm">
                  <?= e(__('sites.edit')) ?>
                </button>
              </form>

              <!-- users -->
              <?php if ($siteViewers): ?>
                <form method="post" class="assign-users">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_users">
                  <input type="hidden" name="site_id" value="<?= $id ?>">

                  <div class="assign-title">
                    <?= e(__('sites.assign_users')) ?>
                  </div>

                  <div class="user-chips">
                    <?php foreach ($siteViewers as $u): ?>
                      <label>
                        <input
                          type="checkbox"
                          name="user_ids[]"
                          value="<?= (int)$u['id'] ?>"
                          <?= in_array((int)$u['id'], $assigned, true) ? 'checked' : '' ?>
                        >
                        <?= e($u['username']) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>

                  <button type="submit" class="btn btn-ghost btn-sm">
                    <?= e(__('sites.save_users')) ?>
                  </button>
                </form>
              <?php endif; ?>

              <!-- actions -->
              <div class="site-actions">

                <a
                  href="<?= e(admin_url('dashboard', ['site' => $id])) ?>"
                  class="btn btn-ghost btn-sm"
                >
                  <?= e(__('sites.open_dashboard')) ?>
                </a>

                <form
                  method="post"
                  onsubmit="return confirm('<?= addslashes(__('sites.delete_confirm', $site['name'] ?: $site['domain'])) ?>')"
                >
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="site_id" value="<?= $id ?>">

                  <button type="submit" class="btn btn-danger btn-sm">
                    <?= e(__('sites.delete')) ?>
                  </button>
                </form>

              </div>

            </div>

          </div>

        <?php endforeach; ?>

      </div>

    <?php endif; ?>
  </div>

  <!-- ───────────── Add site ───────────── -->

  <div class="card">
    <div class="card-header"><?= e(__('sites.add')) ?></div>

    <form method="post" class="add-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="field">
        <label><?= e(__('sites.col.domain')) ?></label>
        <input
          type="text"
          name="domain"
          placeholder="<?= e(__('sites.add.domain')) ?>"
          required
        >
      </div>

      <div class="field">
        <label><?= e(__('sites.col.name')) ?></label>
        <input
          type="text"
          name="name"
          placeholder="<?= e(__('sites.add.name')) ?>"
        >
      </div>

      <div class="field">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-accent">
          <?= e(__('sites.add.submit')) ?>
        </button>
      </div>
    </form>
  </div>

</div>

<script>
function copySnippet(id) {
  const code = document.getElementById('snip-' + id).textContent;
  const btn  = document.getElementById('copybtn-' + id);

  navigator.clipboard.writeText(code).then(() => {
    const orig = btn.textContent;
    btn.textContent = '<?= addslashes(__('sites.snippet.copied')) ?>';

    setTimeout(() => {
      btn.textContent = orig;
    }, 1500);
  });
}
</script>