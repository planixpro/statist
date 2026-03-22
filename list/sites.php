<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = null;
$msgType = 'ok';

// Tracker base URL (used in snippet)
$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$trackerHost = $_SERVER['HTTP_HOST'] ?? 'your-tracker.example.com';
$trackerUrl  = $proto . '://' . $trackerHost . '/tracker.js';

// ---- Helpers ----
function siteSnippet(string $url): string {
    return '<script src="' . htmlspecialchars($url, ENT_QUOTES) . '" async></script>';
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $siteId  = (int)($_POST['site_id'] ?? 0);

    if ($action === 'create') {
        $domain = trim(strtolower($_POST['domain'] ?? ''));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        $name   = trim($_POST['name'] ?? '') ?: $domain;

        if (!$domain) {
            $message = __('sites.err.domain'); $msgType = 'error';
        } else {
            try {
                $pdo->prepare("INSERT INTO sites (domain, name) VALUES (?,?)")->execute([$domain,$name]);
                $message = __('sites.created');
            } catch (PDOException $e) {
                $message = $e->getCode()==23000 ? __('sites.err.exists') : $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'update' && $siteId) {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $pdo->prepare("UPDATE sites SET name=? WHERE id=?")->execute([$name,$siteId]);
            $message = __('sites.updated');
        }
    }

    if ($action === 'delete' && $siteId) {
        $row = $pdo->prepare("SELECT name FROM sites WHERE id=?");
        $row->execute([$siteId]);
        $siteName = $row->fetchColumn() ?: $siteId;
        // Cascade: events, sessions, user_sites
        $pdo->prepare("DELETE FROM user_sites WHERE site_id=?")->execute([$siteId]);
        $pdo->prepare("DELETE FROM events   WHERE site_id=?")->execute([$siteId]);
        $pdo->prepare("DELETE FROM sessions WHERE site_id=?")->execute([$siteId]);
        $pdo->prepare("DELETE FROM sites    WHERE id=?")->execute([$siteId]);
        $message = __('sites.deleted');
    }

    if ($action === 'update_users' && $siteId) {
        $userIds = array_map('intval', (array)($_POST['user_ids'] ?? []));
        // Only touch site_viewer users (admins/viewers have global access)
        $pdo->prepare("DELETE FROM user_sites WHERE site_id=?")->execute([$siteId]);
        if ($userIds) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_sites (user_id,site_id) VALUES (?,?)");
            foreach ($userIds as $uid) { $stmt->execute([$uid,$siteId]); }
        }
        $message = __('sites.users_updated');
    }
}

// ---- Load data ----
$sites = $pdo->query("
    SELECT s.*,
           COUNT(DISTINCT se.id) as session_count
    FROM sites s
    LEFT JOIN sessions se ON se.site_id = s.id
    GROUP BY s.id
    ORDER BY s.name
")->fetchAll();

// site_viewer users for assignment panel
$siteViewers = $pdo->query("SELECT id, username FROM users WHERE role='site_viewer' ORDER BY username")->fetchAll();

// Current user_sites map: site_id → [user_id, ...]
$userSiteMap = [];
foreach ($pdo->query("SELECT user_id, site_id FROM user_sites")->fetchAll() as $r) {
    $userSiteMap[(int)$r['site_id']][] = (int)$r['user_id'];
}
?>
<!DOCTYPE html>
<html lang="<?= Lang::locale() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= __('sites.title') ?> — Statist</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f5f5f7;--surface:#fff;--surface2:#f9f9fb;--border:#e5e5ea;
  --accent:#4f46e5;--accent-l:#ede9fe;--text:#1c1c1e;--text2:#48484a;--muted:#8e8e93;
  --danger:#ff3b30;--danger-l:#fff1f0;--ok-l:#f0fdf4;--radius:10px;
  --mono:'JetBrains Mono',monospace;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
.layout{display:flex;min-height:100vh}

/* ---- Sidebar (shared) ---- */
.sidebar{width:210px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-logo{padding:20px 18px 16px;border-bottom:1px solid var(--border)}
.sidebar-logo-mark{font-family:var(--mono);font-size:11px;font-weight:500;letter-spacing:.14em;color:var(--accent);text-transform:uppercase}
.sidebar-logo-sub{font-size:11px;color:var(--muted);margin-top:2px}
.sidebar-section{font-size:10px;font-weight:600;letter-spacing:.1em;color:var(--muted);text-transform:uppercase;padding:16px 18px 6px}
.sidebar a{display:flex;align-items:center;gap:8px;padding:8px 18px;font-size:13px;color:var(--text2);
  text-decoration:none;transition:background .12s,color .12s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar a .dot{width:6px;height:6px;border-radius:50%;background:var(--border);flex-shrink:0}
.sidebar a:hover{background:var(--bg);color:var(--text)}
.sidebar a.active{background:var(--accent-l);color:var(--accent);font-weight:500}
.sidebar a.active .dot{background:var(--accent)}
.sidebar-footer{margin-top:auto;padding:14px 18px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px}
.sidebar-footer a{font-size:12px;color:var(--muted);text-decoration:none;display:flex;align-items:center;gap:6px}
.sidebar-footer a:hover{color:var(--accent)}

/* ---- Main ---- */
.main{flex:1;min-width:0}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:14px 28px;
  background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10}
.topbar h1{font-size:15px;font-weight:600}
.topbar .sub{font-size:12px;color:var(--muted);margin-top:2px}
.content{padding:24px 28px}

/* ---- Utilities ---- */
.msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
.msg.ok{background:var(--ok-l);border:1px solid #bbf7d0;color:#166534}
.msg.error{background:var(--danger-l);border:1px solid #ffc9c9;color:#c0392b}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px}
.card-header{padding:12px 18px;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;
  letter-spacing:.08em;color:var(--muted);text-transform:uppercase;background:var(--surface2)}

.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th{text-align:left;padding:9px 14px;font-size:10px;font-weight:600;letter-spacing:.08em;
  color:var(--muted);text-transform:uppercase;border-bottom:1px solid var(--border);background:var(--surface2)}
.tbl td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:top}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:var(--bg)}

.mono{font-family:var(--mono);font-size:11px;color:var(--muted)}

.btn{padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;
  border:1px solid transparent;transition:opacity .12s;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:5px}
.btn:hover{opacity:.8}
.btn-ghost{background:var(--bg);border-color:var(--border);color:var(--text2)}
.btn-danger{background:var(--danger-l);border-color:#ffc9c9;color:var(--danger)}
.btn-accent{background:var(--accent);color:#fff}
.btn-sm{padding:4px 9px;font-size:11px}

input[type=text],input[type=password],select{background:var(--bg);border:1px solid var(--border);
  border-radius:6px;padding:6px 10px;font-size:13px;color:var(--text);outline:none;transition:border-color .12s}
input:focus,select:focus{border-color:var(--accent)}

/* ---- Add form ---- */
.add-form{padding:18px;display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:10px;font-weight:600;letter-spacing:.07em;color:var(--muted);text-transform:uppercase}

/* ---- Snippet block ---- */
.snippet-wrap{margin-top:8px}
.snippet-row{display:flex;align-items:center;gap:6px}
.snippet-code{font-family:var(--mono);font-size:11px;background:var(--bg);
  border:1px solid var(--border);border-radius:6px;padding:6px 10px;
  flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text2)}
.copy-btn{flex-shrink:0}

/* ---- User chips ---- */
.user-chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.user-chips label{display:flex;align-items:center;gap:4px;font-size:11px;
  background:var(--bg);border:1px solid var(--border);border-radius:5px;
  padding:3px 7px;cursor:pointer}
.user-chips label:hover{background:var(--accent-l)}
.user-chips input[type=checkbox]{accent-color:var(--accent)}

/* ---- Inline edit ---- */
.inline-edit{display:flex;gap:6px;align-items:center}
.inline-edit input{width:160px}

.actions-col{display:flex;flex-direction:column;gap:6px;min-width:200px}

/* ---- Burger / mobile ---- */
.burger{display:none;background:none;border:1px solid var(--border);border-radius:7px;
  padding:7px 10px;cursor:pointer;font-size:18px;line-height:1}
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:99}
.overlay.open{display:block}

@media(max-width:700px){
  .burger{display:flex;align-items:center;justify-content:center}
  .sidebar{position:fixed;left:-220px;top:0;height:100vh;z-index:100;
    transition:left .22s ease;width:200px;box-shadow:4px 0 20px rgba(0,0,0,.1)}
  .sidebar.open{left:0}
  .layout{display:block}
  .topbar{padding:12px 14px}
  .content{padding:12px}
  .add-form{grid-template-columns:1fr}
  .tbl th:nth-child(3),.tbl td:nth-child(3),
  .tbl th:nth-child(4),.tbl td:nth-child(4){display:none}
}
</style>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body>
<div class="layout">

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">Statist</div>
    <div class="sidebar-logo-sub">Analytics</div>
  </div>
  <div class="sidebar-section"><?= __('nav.sites') ?></div>
  <a href="sites.php" class="active"><span class="dot"></span><?= __('nav.sites') ?></a>
  <a href="dashboard.php"><span class="dot"></span>Dashboard</a>
  <div class="sidebar-footer">
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <a href="users.php">⚙ <?= __('nav.users') ?></a>
    <?php endif; ?>
    <a href="settings.php">◎ <?= __('nav.settings') ?></a>
    <a href="logout.php">← <?= __('nav.logout') ?></a>
  </div>
</nav>

<div class="main">
  <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
  <div class="topbar">
    <button class="burger" onclick="toggleSidebar()">☰</button>
    <div>
      <h1><?= __('sites.title') ?></h1>
      <div class="sub"><?= __('sites.subtitle') ?></div>
    </div>
  </div>

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
      <div style="overflow-x:auto">
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
                <!-- Tracking snippet -->
                <div class="snippet-wrap">
                  <div class="snippet-row">
                    <div class="snippet-code" id="snip-<?= $site['id'] ?>"><?= htmlspecialchars($snippetStr) ?></div>
                    <button class="btn btn-ghost btn-sm copy-btn"
                      onclick="copySnippet(<?= $site['id'] ?>)" id="copybtn-<?= $site['id'] ?>">
                      <?= __('sites.snippet.copy') ?>
                    </button>
                  </div>
                </div>
              </td>

              <td>
                <!-- Inline name edit -->
                <form method="post" class="inline-edit">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                  <input type="text" name="name" value="<?= htmlspecialchars($site['name']) ?>" style="width:140px">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= __('sites.edit') ?></button>
                </form>

                <!-- User assignment (site_viewer only) -->
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

              <td><span style="font-family:var(--mono);font-size:12px"><?= number_format((int)$site['session_count']) ?></span></td>

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

  </div>
</div>
</div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('open');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('overlay').classList.remove('open');
}

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
</body>
</html>
