<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = null;
$msgType = 'ok';
$allSites = $pdo->query("SELECT id, name, domain FROM sites ORDER BY name")->fetchAll();

// ---- Helper ----
function assignSites(PDO $pdo, int $uid, array $siteIds): void {
    $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
    if ($siteIds) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_sites (user_id, site_id) VALUES (?,?)");
        foreach ($siteIds as $sid) { $stmt->execute([$uid, (int)$sid]); }
    }
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $uid     = (int)($_POST['uid'] ?? 0);
    $siteIds = array_map('intval', (array)($_POST['site_ids'] ?? []));

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role']??'', ['admin','viewer','site_viewer'])
                    ? $_POST['role'] : 'viewer';
        if ($username === '' || strlen($password) < 8) {
            $message = __('users.err.password');
            $msgType = 'error';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
                $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES(?,?,?)")
                    ->execute([$username,$hash,$role]);
                $newUid = (int)$pdo->lastInsertId();
                if ($role === 'site_viewer') assignSites($pdo, $newUid, $siteIds);
                $message = __("users.created", $username);
            } catch (PDOException $e) {
                $message = $e->getCode()==23000
                    ? __('users.err.exists')
                    : 'Ошибка БД: '.$e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'change_password' && $uid > 0) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) { $message=__('users.err.short_password'); $msgType='error'; }
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($password,PASSWORD_BCRYPT,['cost'=>12]), $uid]);
            $message = __('users.password_updated');
        }
    }

    if ($action === 'change_role' && $uid > 0) {
        $role = in_array($_POST['role']??'',['admin','viewer','site_viewer'])
                ? $_POST['role'] : 'viewer';
        if ($uid === $_SESSION['user_id']) {
            $message=__('users.err.self_role'); $msgType='error';
        } else {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role,$uid]);
            if ($role !== 'site_viewer') {
                $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
            }
            $message=__('users.role_updated');
        }
    }

    if ($action === 'update_sites' && $uid > 0) {
        assignSites($pdo, $uid, $siteIds);
        $message=__('users.sites_updated');
    }

    if ($action === 'delete' && $uid > 0) {
        if ($uid === $_SESSION['user_id']) {
            $message=__('users.err.self_delete'); $msgType='error';
        } else {
            $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            $message=__('users.deleted');
        }
    }
}

$users = $pdo->query("SELECT id,username,role,created_at,last_login FROM users ORDER BY id")->fetchAll();
$userSiteMap = [];
foreach ($pdo->query("SELECT user_id,site_id FROM user_sites")->fetchAll() as $r) {
    $userSiteMap[(int)$r['user_id']][] = (int)$r['site_id'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Пользователи — Statist</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f5f5f7;--surface:#fff;--surface2:#f9f9fb;--border:#e5e5ea;
  --accent:#4f46e5;--accent-l:#ede9fe;--text:#1c1c1e;--text2:#48484a;--muted:#8e8e93;
  --danger:#ff3b30;--danger-l:#fff1f0;--ok-l:#f0fdf4;--radius:10px}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
.page{max-width:980px;margin:0 auto;padding:32px 24px}
.back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);
  text-decoration:none;margin-bottom:20px;transition:color .12s}
.back:hover{color:var(--accent)}
h1{font-size:20px;font-weight:600;margin-bottom:4px}
.sub{font-size:13px;color:var(--muted);margin-bottom:26px}
.msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:20px}
.msg.ok{background:var(--ok-l);border:1px solid #bbf7d0;color:#166534}
.msg.error{background:var(--danger-l);border:1px solid #ffc9c9;color:#c0392b}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.card-header{padding:12px 18px;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;
  letter-spacing:.08em;color:var(--muted);text-transform:uppercase;background:var(--surface2)}
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th{text-align:left;padding:9px 14px;font-size:10px;font-weight:600;letter-spacing:.08em;
  color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border);background:var(--surface2)}
.tbl td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:top}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:var(--bg)}
.mono{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted)}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600}
.badge-admin{background:var(--accent-l);color:var(--accent)}
.badge-viewer{background:var(--bg);color:var(--muted);border:1px solid var(--border)}
.badge-site_viewer{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.btn{padding:5px 11px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;
  border:1px solid transparent;transition:opacity .12s;font-family:'Inter',sans-serif}
.btn:hover{opacity:.8}
.btn-ghost{background:var(--bg);border-color:var(--border);color:var(--text2)}
.btn-danger{background:var(--danger-l);border-color:#ffc9c9;color:var(--danger)}
.btn-accent{background:var(--accent);color:#fff}
select,input[type=text],input[type=password]{background:var(--bg);border:1px solid var(--border);
  border-radius:6px;padding:5px 9px;font-size:12px;color:var(--text);
  font-family:'JetBrains Mono',monospace;outline:none;transition:border-color .12s}
select:focus,input:focus{border-color:var(--accent)}
.actions{display:flex;flex-direction:column;gap:7px}
.action-row{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.create-grid{padding:18px;display:grid;grid-template-columns:1fr 1fr 180px;gap:12px;align-items:end}
.field{display:flex;flex-direction:column;gap:5px}
.field>label{font-size:10px;font-weight:600;letter-spacing:.07em;color:var(--muted);text-transform:uppercase}
.sites-check{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px}
.sites-check label{display:flex;align-items:center;gap:4px;font-size:12px;
  background:var(--bg);border:1px solid var(--border);border-radius:5px;
  padding:3px 8px;cursor:pointer;user-select:none}
.sites-check label:hover{background:var(--accent-l)}
.sites-check input[type=checkbox]{accent-color:var(--accent)}
.role-note{font-size:12px;color:var(--muted);padding:12px 16px;
  background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius)}
.role-note strong{color:var(--text2)}
@media(max-width:640px){
  .create-grid{grid-template-columns:1fr}
  .tbl th:nth-child(3),.tbl td:nth-child(3){display:none}
}
</style>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body>
<div class="page">
  <a href="dashboard.php" class="back">← Дашборд</a>
  <h1><?= __('users.title') ?></h1>
  <p class="sub"><?= __('users.subtitle') ?></p>

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">Пользователи (<?= count($users) ?>)</div>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead>
          <tr><th><?= __('users.col.login') ?></th><th><?= __('users.col.role') ?></th><th><?= __('users.col.created') ?></th><th><?= __('users.col.last_login') ?></th><th><?= __('users.col.actions') ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            $isSelf        = ((int)$u['id'] === $_SESSION['user_id']);
            $assignedSites = $userSiteMap[(int)$u['id']] ?? [];
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($u['username']) ?></strong>
              <?php if ($isSelf): ?><span class="mono"> (<?= __('users.you') ?>)</span><?php endif; ?>
            </td>
            <td>
              <?php if (!$isSelf): ?>
                <form method="post" style="display:inline-flex;gap:5px;align-items:center">
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <select name="role" onchange="this.form.submit()" style="font-family:'Inter',sans-serif">
                    <option value="admin"       <?= $u['role']==='admin'       ?'selected':'' ?>>admin</option>
                    <option value="viewer"      <?= $u['role']==='viewer'      ?'selected':'' ?>>viewer</option>
                    <option value="site_viewer" <?= $u['role']==='site_viewer' ?'selected':'' ?>>site viewer</option>
                  </select>
                </form>
              <?php else: ?>
                <span class="badge badge-<?= $u['role'] ?>"><?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?></span>
              <?php endif; ?>

              <?php if ($u['role'] === 'site_viewer' && $allSites): ?>
              <form method="post" style="margin-top:8px">
                <input type="hidden" name="action" value="update_sites">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <div class="sites-check">
                  <?php foreach ($allSites as $s): ?>
                  <label>
                    <input type="checkbox" name="site_ids[]" value="<?= $s['id'] ?>"
                      <?= in_array((int)$s['id'],$assignedSites)?'checked':'' ?>>
                    <?= htmlspecialchars($s['name']?:$s['domain']) ?>
                  </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-ghost" style="margin-top:6px;font-size:11px">Сохранить сайты</button>
              </form>
              <?php endif; ?>
            </td>
            <td class="mono"><?= $u['created_at'] ? date('d.m.Y',strtotime($u['created_at'])) : '—' ?></td>
            <td class="mono"><?= $u['last_login']  ? date('d.m.Y H:i',strtotime($u['last_login'])) : __('users.never') ?></td>
            <td>
              <div class="actions">
                <form method="post">
                  <input type="hidden" name="action" value="change_password">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <div class="action-row">
                    <input type="password" name="password" placeholder="Новый пароль" minlength="8" style="width:145px">
                    <button type="submit" class="btn btn-ghost"
                      onclick="return confirm('Установить новый пароль?')">Сохранить</button>
                  </div>
                </form>
                <?php if (!$isSelf): ?>
                <form method="post">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Удалить «<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>»?')">
                    Удалить
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

  <!-- Create -->
  <div class="card">
    <div class="card-header">Создать пользователя</div>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="create-grid">
        <div class="field">
          <label>Логин</label>
          <input type="text" name="username" placeholder="username" required autocomplete="off">
        </div>
        <div class="field">
          <label>Пароль (мин. 8 символов)</label>
          <input type="password" name="password" placeholder="••••••••" minlength="8" required>
        </div>
        <div class="field">
          <label>Роль</label>
          <select name="role" id="create-role" onchange="toggleSiteBlock()" style="font-family:'Inter',sans-serif">
            <option value="viewer">viewer</option>
            <option value="site_viewer">site viewer</option>
            <option value="admin">admin</option>
          </select>
        </div>
      </div>
      <div id="site-block" style="display:none;padding:0 18px 16px">
        <div class="field">
          <label>Разрешённые сайты</label>
          <div class="sites-check">
            <?php foreach ($allSites as $s): ?>
            <label>
              <input type="checkbox" name="site_ids[]" value="<?= $s['id'] ?>">
              <?= htmlspecialchars($s['name']?:$s['domain']) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div style="padding:0 18px 18px">
        <button type="submit" class="btn btn-accent">Создать →</button>
      </div>
    </form>
  </div>

  <div class="role-note">
    <strong>admin</strong> — полный доступ, управление пользователями &nbsp;·&nbsp;
    <strong>viewer</strong> — просмотр всех сайтов &nbsp;·&nbsp;
    <strong>site viewer</strong> — только назначенные сайты
  </div>
</div>
<script>
function toggleSiteBlock(){
  document.getElementById('site-block').style.display=
    document.getElementById('create-role').value==='site_viewer'?'block':'none';
}
</script>
</body>
</html>
