<?php
// Переменные от users.php:
// $message, $msgType, $users, $allSites, $userSiteMap
?>

<!-- Topbar -->
<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <div class="topbar-left">
    <h1><?= __('users.title') ?></h1>
    <div class="domain"><?= __('users.subtitle') ?></div>
  </div>
</div>

<!-- Content -->
<div class="content">

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Users table -->
  <div class="card">
    <div class="card-header"><?= __('users.title') ?> (<?= count($users) ?>)</div>
    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th><?= __('users.col.login') ?></th>
            <th><?= __('users.col.role') ?></th>
            <th><?= __('users.col.created') ?></th>
            <th><?= __('users.col.last_login') ?></th>
            <th><?= __('users.col.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            $isSelf        = ((int)$u['id'] === $_SESSION['user_id']);
            $assignedSites = $userSiteMap[(int)$u['id']] ?? [];
          ?>
          <tr>
            <!-- Login -->
            <td>
              <strong><?= htmlspecialchars($u['username']) ?></strong>
              <?php if ($isSelf): ?>
                <span class="mono"> (<?= __('users.you') ?>)</span>
              <?php endif; ?>
            </td>

            <!-- Role -->
            <td>
              <?php if (!$isSelf): ?>
                <form method="post" class="role-form" id="role-form-<?= $u['id'] ?>">
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                  <select name="role" id="role-select-<?= $u['id'] ?>"
                          data-original="<?= htmlspecialchars($u['role']) ?>"
                          onchange="confirmRoleChange(<?= $u['id'] ?>)">
                    <option value="admin"       <?= $u['role'] === 'admin'       ? 'selected' : '' ?>>admin</option>
                    <option value="viewer"      <?= $u['role'] === 'viewer'      ? 'selected' : '' ?>>viewer</option>
                    <option value="site_viewer" <?= $u['role'] === 'site_viewer' ? 'selected' : '' ?>>site viewer</option>
                  </select>
                </form>
              <?php else: ?>
                <span class="badge badge-<?= $u['role'] ?>">
                  <?= htmlspecialchars(__('users.role.' . $u['role'])) ?>
                </span>
              <?php endif; ?>

              <?php if ($u['role'] === 'site_viewer' && $allSites): ?>
              <form method="post" style="margin-top:8px">
                <input type="hidden" name="action" value="update_sites">
                <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                <div class="sites-check">
                  <?php foreach ($allSites as $s): ?>
                  <label>
                    <input type="checkbox" name="site_ids[]" value="<?= $s['id'] ?>"
                           <?= in_array((int)$s['id'], $assignedSites) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($s['name'] ?: $s['domain']) ?>
                  </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-ghost" style="margin-top:6px;font-size:11px">
                  <?= __('users.save_sites') ?>
                </button>
              </form>
              <?php endif; ?>
            </td>

            <!-- Created -->
            <td class="mono">
              <?= $u['created_at'] ? date('d.m.Y', strtotime($u['created_at'])) : '—' ?>
            </td>

            <!-- Last login -->
            <td class="mono">
              <?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : __('users.never') ?>
            </td>

            <!-- Actions -->
            <td>
              <div class="actions">
                <form method="post">
                  <input type="hidden" name="action" value="change_password">
                  <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                  <div class="action-row">
                    <input type="password" name="password"
                           placeholder="<?= htmlspecialchars(__('users.new_password')) ?>"
                           minlength="8" style="width:145px">
                    <button type="submit" class="btn btn-ghost"
                            onclick="return confirm('<?= addslashes(__('users.save_password')) ?>?')">
                      <?= __('users.save_password') ?>
                    </button>
                  </div>
                </form>

                <?php if (!$isSelf): ?>
                <form method="post">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-danger"
                          onclick="return confirm('<?= addslashes(__('users.delete_confirm', $u['username'])) ?>')">
                    <?= __('users.delete') ?>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Create user -->
  <div class="card">
    <div class="card-header"><?= __('users.create') ?></div>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="create-grid">
        <div class="field">
          <label><?= __('users.create.login') ?></label>
          <input type="text" name="username"
                 placeholder="<?= htmlspecialchars(__('users.create.login')) ?>"
                 required autocomplete="off">
        </div>
        <div class="field">
          <label><?= __('users.create.password') ?></label>
          <input type="password" name="password" placeholder="••••••••" minlength="8" required>
        </div>
        <div class="field">
          <label><?= __('users.create.role') ?></label>
          <select name="role" id="create-role" onchange="toggleSiteBlock()">
            <option value="viewer">viewer</option>
            <option value="site_viewer">site viewer</option>
            <option value="admin">admin</option>
          </select>
        </div>
      </div>

      <div id="site-block" style="display:none;padding:0 18px 16px">
        <div class="field">
          <label><?= __('users.create.sites') ?></label>
          <div class="sites-check">
            <?php foreach ($allSites as $s): ?>
            <label>
              <input type="checkbox" name="site_ids[]" value="<?= $s['id'] ?>">
              <?= htmlspecialchars($s['name'] ?: $s['domain']) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div style="padding:0 18px 18px">
        <button type="submit" class="btn btn-accent"><?= __('users.create.submit') ?></button>
      </div>
    </form>
  </div>

  <div class="role-note"><?= __('users.roles_note') ?></div>

</div><!-- .content -->

<script>
function toggleSiteBlock() {
  document.getElementById('site-block').style.display =
    document.getElementById('create-role').value === 'site_viewer' ? 'block' : 'none';
}

function confirmRoleChange(uid) {
  const sel      = document.getElementById('role-select-' + uid);
  const original = sel.dataset.original;
  const newRole  = sel.value;
  if (newRole === original) return;
  if (confirm('Change role to "' + newRole + '"?')) {
    document.getElementById('role-form-' + uid).submit();
  } else {
    sel.value = original;
  }
}
</script>
