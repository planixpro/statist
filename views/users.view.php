<?php
// Variables: $message, $msgType, $users, $allSites, $userSiteMap
?>
<div class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <div class="topbar-left">
    <h1><?= e(__('users.title')) ?></h1>
    <div class="domain"><?= e(__('users.subtitle')) ?></div>
  </div>
</div>

<div class="content">

  <?php if ($message): ?>
    <div class="msg <?= e($msgType) ?>"><?= e($message) ?></div>
  <?php endif; ?>

  <!-- USERS TABLE -->
  <div class="card">
    <div class="card-header">
      <?= e(__('users.title')) ?> (<?= count($users) ?>)
    </div>

    <div class="scroll-x">
      <table class="tbl">
        <thead>
          <tr>
            <th><?= e(__('users.col.login')) ?></th>
            <th><?= e(__('users.col.role')) ?></th>
            <th><?= e(__('users.col.created')) ?></th>
            <th><?= e(__('users.col.last_login')) ?></th>
            <th><?= e(__('users.col.actions')) ?></th>
          </tr>
        </thead>

        <tbody>
        <?php foreach ($users as $u): ?>
          <?php
            $uid = (int)$u['id'];
            $isSelf = ($uid === (int)$_SESSION['user_id']);
            $assignedSites = $userSiteMap[$uid] ?? [];
          ?>

          <tr>
            <!-- LOGIN -->
            <td>
              <strong><?= e($u['username']) ?></strong>
              <?php if ($isSelf): ?>
                <span class="mono"> (<?= e(__('users.you')) ?>)</span>
              <?php endif; ?>
            </td>

            <!-- ROLE -->
            <td>

              <?php if (!$isSelf): ?>
                <form method="post" class="role-form" id="role-form-<?= $uid ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="uid" value="<?= $uid ?>">

                  <select name="role"
                          id="role-select-<?= $uid ?>"
                          data-original="<?= e($u['role']) ?>"
                          onchange="confirmRoleChange(<?= $uid ?>)">
                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                    <option value="viewer" <?= $u['role'] === 'viewer' ? 'selected' : '' ?>>viewer</option>
                    <option value="site_viewer" <?= $u['role'] === 'site_viewer' ? 'selected' : '' ?>>site viewer</option>
                  </select>
                </form>
              <?php else: ?>
                <span class="badge badge-<?= e($u['role']) ?>">
                  <?= e(__('users.role.' . $u['role'])) ?>
                </span>
              <?php endif; ?>

              <!-- SITES -->
              <?php if ($u['role'] === 'site_viewer' && $allSites): ?>
                <form method="post" class="sites-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_sites">
                  <input type="hidden" name="uid" value="<?= $uid ?>">

                  <details style="margin-top:6px">
                    <summary class="link-like"><?= e(__('users.sites')) ?></summary>

                    <div class="sites-check">
                      <?php foreach ($allSites as $s): ?>
                        <label>
                          <input type="checkbox"
                                 name="site_ids[]"
                                 value="<?= (int)$s['id'] ?>"
                                 <?= in_array((int)$s['id'], $assignedSites, true) ? 'checked' : '' ?>>
                          <?= e($s['name'] ?: $s['domain']) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-ghost small">
                      <?= e(__('users.save_sites')) ?>
                    </button>
                  </details>
                </form>
              <?php endif; ?>

            </td>

            <!-- CREATED -->
            <td class="mono">
              <?= $u['created_at'] ? e(date('d.m.Y', strtotime($u['created_at']))) : '—' ?>
            </td>

            <!-- LAST LOGIN -->
            <td class="mono">
              <?= $u['last_login']
                  ? e(date('d.m.Y H:i', strtotime($u['last_login'])))
                  : e(__('users.never')) ?>
            </td>

            <!-- ACTIONS -->
            <td>
              <div class="actions">

                <!-- CHANGE PASSWORD -->
                <form method="post" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="change_password">
                  <input type="hidden" name="uid" value="<?= $uid ?>">

                  <div class="action-row">
                    <input type="password"
                           name="password"
                           placeholder="<?= e(__('users.new_password')) ?>"
                           minlength="8"
                           required>

                    <button type="submit" class="btn btn-ghost">
                      <?= e(__('users.save_password')) ?>
                    </button>
                  </div>
                </form>

                <!-- DELETE -->
                <?php if (!$isSelf): ?>
                  <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="uid" value="<?= $uid ?>">

                    <button type="submit"
                            class="btn btn-danger"
                            onclick="return confirmDelete('<?= addslashes($u['username']) ?>')">
                      <?= e(__('users.delete')) ?>
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

  <!-- CREATE USER -->
  <div class="card">
    <div class="card-header"><?= e(__('users.create')) ?></div>

    <form method="post" id="create-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="create-grid">

        <div class="field">
          <label><?= e(__('users.create.login')) ?></label>
          <input type="text" name="username" required autocomplete="off">
        </div>

        <div class="field">
          <label><?= e(__('users.create.password')) ?></label>
          <input type="password" name="password" minlength="8" required>
        </div>

        <div class="field">
          <label><?= e(__('users.create.role')) ?></label>
          <select name="role" id="create-role" onchange="toggleSiteBlock()">
            <option value="viewer">viewer</option>
            <option value="site_viewer">site viewer</option>
            <option value="admin">admin</option>
          </select>
        </div>

      </div>

      <div id="site-block" style="display:none;padding:0 18px 16px">
        <div class="field">
          <label><?= e(__('users.create.sites')) ?></label>

          <div class="sites-check">
            <?php foreach ($allSites as $s): ?>
              <label>
                <input type="checkbox" name="site_ids[]" value="<?= (int)$s['id'] ?>">
                <?= e($s['name'] ?: $s['domain']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div style="padding:0 18px 18px">
        <button type="submit" class="btn btn-accent">
          <?= e(__('users.create.submit')) ?>
        </button>
      </div>

    </form>
  </div>

  <div class="role-note"><?= e(__('users.roles_note')) ?></div>

</div>

<script>
function toggleSiteBlock() {
  const el = document.getElementById('site-block');
  el.style.display = document.getElementById('create-role').value === 'site_viewer'
    ? 'block'
    : 'none';
}

function confirmRoleChange(uid) {
  const sel = document.getElementById('role-select-' + uid);
  const original = sel.dataset.original;
  const newRole = sel.value;

  if (newRole === original) return;

  if (confirm('Change role to "' + newRole + '"?')) {
    document.getElementById('role-form-' + uid).submit();
  } else {
    sel.value = original;
  }
}

function confirmDelete(username) {
  return confirm('Delete user "' + username + '"?');
}

// disable double submit
document.querySelectorAll('form').forEach(f => {
  f.addEventListener('submit', () => {
    f.querySelectorAll('button').forEach(b => b.disabled = true);
  });
});

toggleSiteBlock();
</script>