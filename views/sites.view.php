<?php
// Переменные от sites.php:
// $message, $msgType, $sites, $siteViewers, $userSiteMap, $trackerUrl
?>

<!-- Topbar -->
<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <div class="topbar-left">
    <h1><?= __('sites.title') ?></h1>
    <div class="domain"><?= __('sites.subtitle') ?></div>
  </div>
</div>

<!-- Content -->
<div class="content">

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Sites table -->
  <div class="card">
    <div class="card-header"><?= __('sites.title') ?> (<?= count($sites) ?>)</div>
    <?php if (!$sites): ?>
      <div style="padding:28px 18px;color:var(--muted);font-size:13px"><?= __('sites.empty') ?></div>
    <?php else: ?>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th><?= __('sites.col.domain') ?></th>
            <th><?= __('sites.col.name') ?></th>
            <th><?= __('sites.col.created') ?></th>
            <th><?= __('sites.col.sessions') ?></th>
            <th><?= __('sites.col.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sites as $site):
            $assigned   = $userSiteMap[(int)$site['id']] ?? [];
            $snippetStr = siteSnippet($trackerUrl);
          ?>
          <tr>
            <td>
              <div class="mono" style="font-size:12px"><?= htmlspecialchars($site['domain']) ?></div>
              <div class="snippet-wrap">
                <div class="snippet-row">
                  <div class="snippet-code" id="snip-<?= $site['id'] ?>"><?= htmlspecialchars($snippetStr) ?></div>
                  <button class="btn btn-ghost btn-sm"
                    onclick="copySnippet(<?= $site['id'] ?>)" id="copybtn-<?= $site['id'] ?>">
                    <?= __('sites.snippet.copy') ?>
                  </button>
                </div>
              </div>
            </td>

            <td>
              <form method="post" class="inline-edit">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                <input type="text" name="name" value="<?= htmlspecialchars($site['name']) ?>" style="width:140px">
                <button type="submit" class="btn btn-ghost btn-sm"><?= __('sites.edit') ?></button>
              </form>

              <?php if ($siteViewers): ?>
              <form method="post" style="margin-top:10px">
                <input type="hidden" name="action" value="update_users">
                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                <div style="font-size:10px;font-weight:600;letter-spacing:.07em;color:var(--muted);text-transform:uppercase;margin-bottom:4px">
                  <?= __('sites.assign_users') ?>
                </div>
                <div class="user-chips">
                  <?php foreach ($siteViewers as $u): ?>
                  <label>
                    <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>"
                      <?= in_array((int)$u['id'], $assigned) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($u['username']) ?>
                  </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-ghost btn-sm" style="margin-top:6px">
                  <?= __('sites.save_users') ?>
                </button>
              </form>
              <?php endif; ?>
            </td>

            <td class="mono"><?= $site['created_at'] ? date('d.m.Y', strtotime($site['created_at'])) : '—' ?></td>

            <td><span class="mono" style="font-size:12px"><?= number_format((int)$site['session_count']) ?></span></td>

            <td>
              <form method="post" onsubmit="return confirm('<?= addslashes(__('sites.delete_confirm', $site['name'] ?: $site['domain'])) ?>')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"><?= __('sites.delete') ?></button>
              </form>
              <div style="margin-top:6px">
                <a href="dashboard.php?site=<?= $site['id'] ?>" class="btn btn-ghost btn-sm">Dashboard →</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Add site form -->
  <div class="card">
    <div class="card-header"><?= __('sites.add') ?></div>
    <form method="post" class="add-form">
      <input type="hidden" name="action" value="create">
      <div class="field">
        <label><?= __('sites.col.domain') ?></label>
        <input type="text" name="domain" placeholder="<?= htmlspecialchars(__('sites.add.domain')) ?>" required>
      </div>
      <div class="field">
        <label><?= __('sites.col.name') ?></label>
        <input type="text" name="name" placeholder="<?= htmlspecialchars(__('sites.add.name')) ?>">
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-accent"><?= __('sites.add.submit') ?></button>
      </div>
    </form>
  </div>

</div><!-- .content -->

<script>
function copySnippet(id) {
  const code = document.getElementById('snip-' + id).textContent;
  const btn  = document.getElementById('copybtn-' + id);
  navigator.clipboard.writeText(code).then(() => {
    const orig = btn.textContent;
    btn.textContent = '<?= addslashes(__('sites.snippet.copied')) ?>';
    setTimeout(() => { btn.textContent = orig; }, 1800);
  });
}
</script>
