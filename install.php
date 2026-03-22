<?php
/**
 * Statist — install.php
 *
 * One-file installer. Steps:
 *   1  Requirements check
 *   2  Database connection
 *   3  Admin account
 *   4  Install (write db.php, run SQL, create user, write lock)
 *   done  Success + self-delete
 *
 * Blocked after /storage/installed.lock exists.
 */

define('ROOT',      __DIR__);
define('LOCK_FILE', ROOT . '/storage/installed.lock');
define('DB_FILE',   ROOT . '/inc/db.php');
define('SQL_FILE',  ROOT . '/install.sql');

session_start();

// ── Self-delete — must run BEFORE the lock check ─────────────
// The lock already exists at this point; we still need to handle
// the delete request that arrives right after successful install.
if (
    isset($_GET['action']) && $_GET['action'] === 'delete_self' &&
    isset($_GET['token'])  && $_GET['token'] !== '' &&
    isset($_SESSION['install_token']) &&
    hash_equals($_SESSION['install_token'], $_GET['token'])
) {
    session_destroy();
    header('Location: /list/');
    header('Connection: close');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // Flush output so the browser gets the redirect before we unlink
        ob_end_flush();
        flush();
    }
    @unlink(__FILE__);
    exit;
}

// ── Already installed ────────────────────────────────────────
// Check lock file. If storage/ dir doesn't exist yet — we're definitely not installed.
if (file_exists(LOCK_FILE)) {
    // Allow through only if it's a valid delete_self (handled above already)
    http_response_code(404);
    die('Not found.');
}

$step  = (int)($_GET['step'] ?? 1);
$error = null;

// ── Helper: render a status row ──────────────────────────────
function check(string $label, bool $ok, string $detail = ''): void {
    $icon  = $ok ? '✓' : '✗';
    $color = $ok ? 'var(--ok)' : 'var(--err)';
    echo '<div class="check-row">';
    echo '<span class="check-icon" style="color:'.$color.'">'.$icon.'</span>';
    echo '<span class="check-label">'.htmlspecialchars($label).'</span>';
    if ($detail) echo '<span class="check-detail">'.htmlspecialchars($detail).'</span>';
    echo '</div>';
}

// ── Step 1 — Requirements ────────────────────────────────────
function runChecks(): array {
    $php    = version_compare(PHP_VERSION, '7.4.0', '>=');
    $pdo    = extension_loaded('pdo_mysql');
    $json   = extension_loaded('json');
    $mbstr  = extension_loaded('mbstring');
    $incW   = is_writable(ROOT . '/inc');
    $storW  = is_writable(ROOT . '/storage');

    return [
        'php'   => ['label' => 'PHP ≥ 7.4',            'ok' => $php,   'detail' => PHP_VERSION],
        'pdo'   => ['label' => 'ext-pdo_mysql',         'ok' => $pdo,   'detail' => $pdo   ? 'loaded' : 'missing'],
        'json'  => ['label' => 'ext-json',              'ok' => $json,  'detail' => $json  ? 'loaded' : 'missing'],
        'mbstr' => ['label' => 'ext-mbstring',          'ok' => $mbstr, 'detail' => $mbstr ? 'loaded' : 'missing'],
        'incW'  => ['label' => '/inc  writable',        'ok' => $incW,  'detail' => $incW  ? 'ok' : 'chmod 755 inc/'],
        'storW' => ['label' => '/storage  writable',    'ok' => $storW, 'detail' => $storW ? 'ok' : 'chmod 755 storage/'],
    ];
}

$checks  = runChecks();
$canNext = array_reduce($checks, fn($c, $r) => $c && $r['ok'], true);

// ── Step 2 POST — test DB ────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass =       $_POST['db_pass'] ?? '';

    $_SESSION['setup_db'] = compact('dbHost','dbPort','dbName','dbUser','dbPass');

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Quick sanity — can we query?
        $pdo->query("SELECT 1");
        header('Location: install.php?step=3');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ── Step 3 POST — validate admin creds, go to step 4 ─────────
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass =       $_POST['admin_pass'] ?? '';
    $adminPass2 =      $_POST['admin_pass2'] ?? '';

    if (!$adminUser || strlen($adminUser) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($adminPass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'Passwords do not match.';
    } else {
        $_SESSION['setup_admin'] = ['user' => $adminUser, 'pass' => $adminPass];
        header('Location: install.php?step=4');
        exit;
    }
}

// ── Step 4 — Run install ──────────────────────────────────────
if ($step === 4) {
    $db    = $_SESSION['setup_db']    ?? null;
    $admin = $_SESSION['setup_admin'] ?? null;

    if (!$db || !$admin) {
        header('Location: install.php?step=1');
        exit;
    }

    $errors  = [];
    $success = false;

    try {
        // 1. Connect
        $dsn = "mysql:host={$db['dbHost']};port={$db['dbPort']};dbname={$db['dbName']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['dbUser'], $db['dbPass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // 2. Run install.sql (split on semicolons, skip comments/empty)
        if (!file_exists(SQL_FILE)) {
            throw new RuntimeException('install.sql not found. Please upload it to the root directory.');
        }
        $sql = file_get_contents(SQL_FILE);
        // Strip full-line comments
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => strlen($s) > 4
        );
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }

        // 3. Create admin user (replace default if exists)
        $hash = password_hash($admin['pass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("
            INSERT INTO users (username, password_hash, role, locale)
            VALUES (?, ?, 'admin', 'en')
            ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin'
        ")->execute([$admin['user'], $hash]);

        // 4. Write inc/db.php
        $dbPhp = <<<PHP
<?php
// Auto-generated by Statist installer — do not edit manually.
\$pdo = new PDO(
    "mysql:host={$db['dbHost']};port={$db['dbPort']};dbname={$db['dbName']};charset=utf8mb4",
    {$pdo->quote($db['dbUser'])},
    {$pdo->quote($db['dbPass'])},
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
PHP;
        if (file_put_contents(DB_FILE, $dbPhp) === false) {
            throw new RuntimeException('Cannot write inc/db.php — check directory permissions.');
        }

        // 5. Patch tracker.js — update ENDPOINT to current host
        $trackerFile = ROOT . '/tracker.js';
        if (file_exists($trackerFile)) {
            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host       = $_SERVER['HTTP_HOST'] ?? '';
            $trackerJs  = file_get_contents($trackerFile);
            $trackerJs  = preg_replace(
                '/const ENDPOINT\s*=\s*["\'].*?["\'];/',
                'const ENDPOINT = "' . $proto . '://' . $host . '/";',
                $trackerJs
            );
            file_put_contents($trackerFile, $trackerJs);
        }

        // 6. Write lock file
        @mkdir(dirname(LOCK_FILE), 0755, true);
        file_put_contents(LOCK_FILE, date('c') . "\nInstalled by install.php\n");

        $success = true;

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// ── HTML helpers ─────────────────────────────────────────────

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Statist — Setup</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f5f5f7;--surface:#fff;--border:#e5e5ea;--border2:#d1d1d6;
  --accent:#4f46e5;--accent-l:#ede9fe;
  --text:#1c1c1e;--text2:#48484a;--muted:#8e8e93;
  --ok:#34c759;--ok-l:#f0fdf4;--ok-bd:#bbf7d0;
  --err:#ff3b30;--err-l:#fff1f0;--err-bd:#ffc9c9;
  --warn:#f59e0b;--warn-l:#fffbeb;--warn-bd:#fde68a;
  --radius:12px;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);
  min-height:100vh;display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:24px;gap:0}

/* ── Stepper ── */
.stepper{display:flex;align-items:center;gap:0;margin-bottom:28px;width:100%;max-width:520px}
.step{display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;position:relative}
.step-circle{width:32px;height:32px;border-radius:50%;border:2px solid var(--border2);
  background:var(--surface);display:flex;align-items:center;justify-content:center;
  font-size:12px;font-weight:600;color:var(--muted);z-index:1;transition:all .2s}
.step.done .step-circle{background:var(--ok);border-color:var(--ok);color:#fff}
.step.active .step-circle{background:var(--accent);border-color:var(--accent);color:#fff;
  box-shadow:0 0 0 4px var(--accent-l)}
.step-label{font-size:10px;font-weight:500;color:var(--muted);text-align:center;
  letter-spacing:.04em;white-space:nowrap}
.step.active .step-label{color:var(--accent);font-weight:600}
.step.done .step-label{color:var(--ok)}
.step-line{position:absolute;top:16px;left:calc(50% + 16px);right:calc(-50% + 16px);
  height:2px;background:var(--border);z-index:0}
.step.done .step-line{background:var(--ok)}
.step:last-child .step-line{display:none}

/* ── Card ── */
.card{width:100%;max-width:520px;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);box-shadow:0 2px 20px rgba(0,0,0,.06);overflow:hidden}
.card-head{padding:24px 28px 0;border-bottom:0}
.logo{font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:500;
  letter-spacing:.18em;color:var(--accent);text-transform:uppercase;margin-bottom:20px}
.card-title{font-size:18px;font-weight:600;margin-bottom:4px}
.card-sub{font-size:13px;color:var(--muted);margin-bottom:24px}
.card-body{padding:0 28px 28px}

/* ── Checks ── */
.checks{display:flex;flex-direction:column;gap:8px;margin-bottom:24px}
.check-row{display:flex;align-items:center;gap:10px;padding:9px 12px;
  background:var(--bg);border:1px solid var(--border);border-radius:8px}
.check-icon{font-size:15px;flex-shrink:0;width:20px;text-align:center}
.check-label{font-size:13px;font-weight:500;flex:1}
.check-detail{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted)}

/* ── Form ── */
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.field label{font-size:11px;font-weight:600;letter-spacing:.06em;color:var(--muted);text-transform:uppercase}
.field input{background:var(--bg);border:1px solid var(--border);border-radius:8px;
  padding:10px 13px;font-size:14px;font-family:'JetBrains Mono',monospace;color:var(--text);
  outline:none;transition:border-color .15s,box-shadow .15s;width:100%}
.field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-l)}
.field-hint{font-size:11px;color:var(--muted);margin-top:2px}

.row2{display:grid;grid-template-columns:1fr 100px;gap:10px}

/* ── Alerts ── */
.alert{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px;line-height:1.5}
.alert-err {background:var(--err-l);border:1px solid var(--err-bd);color:#c0392b}
.alert-ok  {background:var(--ok-l);border:1px solid var(--ok-bd);color:#166534}
.alert-warn{background:var(--warn-l);border:1px solid var(--warn-bd);color:#92400e}

/* ── Buttons ── */
.btn-row{display:flex;justify-content:space-between;align-items:center;margin-top:20px;gap:10px}
.btn{padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
  border:1px solid transparent;transition:opacity .15s,transform .1s;
  font-family:'Inter',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.btn:active{transform:scale(.98)}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{opacity:.9}
.btn-ghost{background:var(--bg);border-color:var(--border);color:var(--text2)}
.btn-ghost:hover{background:var(--border)}
.btn-success{background:var(--ok);color:#fff}
.btn-success:hover{opacity:.9}
.btn:disabled,.btn[disabled]{opacity:.45;cursor:not-allowed;pointer-events:none}

/* ── Done screen ── */
.done-icon{font-size:48px;margin-bottom:12px;display:block;text-align:center}
.done-info{background:var(--bg);border:1px solid var(--border);border-radius:8px;
  padding:14px 16px;font-size:12px;font-family:'JetBrains Mono',monospace;
  color:var(--text2);margin:16px 0;line-height:1.8}

/* ── Log ── */
.install-log{background:#1c1c1e;border-radius:8px;padding:14px 16px;margin-bottom:20px;
  font-family:'JetBrains Mono',monospace;font-size:12px;color:#e5e5ea;line-height:1.7;
  max-height:220px;overflow-y:auto}
.log-ok  {color:#30d158}
.log-err {color:#ff453a}
.log-info{color:#ffd60a}

@media(max-width:560px){
  .card-body,.card-head{padding-left:18px;padding-right:18px}
  .stepper{gap:0}
  .step-label{display:none}
  .row2{grid-template-columns:1fr}
}
</style>
</head>
<body>

<?php
// ── Stepper state ─────────────────────────────────────────────
$steps = [1 => 'Requirements', 2 => 'Database', 3 => 'Admin', 4 => 'Install'];
$effectiveStep = ($step === 4 && ($success ?? false)) ? 5 : $step;
?>

<?php if ($step < 4 || (!isset($success) || !$success)): ?>
<div class="stepper">
  <?php foreach ($steps as $n => $label): ?>
    <?php
      $cls = 'step';
      if ($n < $effectiveStep)  $cls .= ' done';
      if ($n === $effectiveStep) $cls .= ' active';
    ?>
    <div class="<?= $cls ?>">
      <div class="step-circle">
        <?php if ($n < $effectiveStep): ?>✓<?php else: echo $n; endif; ?>
      </div>
      <div class="step-label"><?= $label ?></div>
      <div class="step-line"></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
<div class="card-head">
  <div class="logo">Statist</div>

<?php /* ═══════════════════ STEP 1 — REQUIREMENTS ═══════════════════ */ ?>
<?php if ($step === 1): ?>
  <div class="card-title">System requirements</div>
  <div class="card-sub">Checking your server environment before installation.</div>
</div>
<div class="card-body">
  <div class="checks">
    <?php foreach ($checks as $c): check($c['label'], $c['ok'], $c['detail']); endforeach; ?>
  </div>
  <?php if (!$canNext): ?>
    <div class="alert alert-err">
      Please fix the issues above before continuing. After making changes, reload this page.
    </div>
  <?php endif; ?>
  <div class="btn-row">
    <span style="font-size:12px;color:var(--muted)">Step 1 of 4</span>
    <a href="install.php?step=2" class="btn btn-primary"
      <?= $canNext ? '' : 'onclick="return false" style="opacity:.4;cursor:not-allowed"' ?>>
      Continue →
    </a>
  </div>
</div>

<?php /* ═══════════════════ STEP 2 — DATABASE ═══════════════════ */ ?>
<?php elseif ($step === 2): ?>
  <div class="card-title">Database connection</div>
  <div class="card-sub">Enter your MySQL credentials. The database must already exist.</div>
</div>
<div class="card-body">
  <?php if ($error): ?>
    <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="install.php?step=2">
    <div class="row2">
      <div class="field">
        <label>Host</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($_SESSION['setup_db']['dbHost'] ?? 'localhost') ?>" required>
      </div>
      <div class="field">
        <label>Port</label>
        <input type="text" name="db_port" value="<?= htmlspecialchars($_SESSION['setup_db']['dbPort'] ?? '3306') ?>" required>
      </div>
    </div>
    <div class="field">
      <label>Database name</label>
      <input type="text" name="db_name" value="<?= htmlspecialchars($_SESSION['setup_db']['dbName'] ?? '') ?>" placeholder="stats" required>
      <span class="field-hint">The database must already exist and be accessible by the user below.</span>
    </div>
    <div class="field">
      <label>Username</label>
      <input type="text" name="db_user" value="<?= htmlspecialchars($_SESSION['setup_db']['dbUser'] ?? '') ?>" placeholder="stats_usr" required>
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="db_pass" placeholder="••••••••">
    </div>
    <div class="btn-row">
      <a href="install.php?step=1" class="btn btn-ghost">← Back</a>
      <button type="submit" class="btn btn-primary">Test &amp; Continue →</button>
    </div>
  </form>
</div>

<?php /* ═══════════════════ STEP 3 — ADMIN ═══════════════════ */ ?>
<?php elseif ($step === 3): ?>
  <div class="card-title">Administrator account</div>
  <div class="card-sub">Create the first admin user for the dashboard.</div>
</div>
<div class="card-body">
  <?php if ($error): ?>
    <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="install.php?step=3">
    <div class="field">
      <label>Username</label>
      <input type="text" name="admin_user"
        value="<?= htmlspecialchars($_SESSION['setup_admin']['user'] ?? '') ?>"
        placeholder="admin" minlength="3" required>
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="admin_pass" placeholder="min 8 characters" minlength="8" required>
    </div>
    <div class="field">
      <label>Confirm password</label>
      <input type="password" name="admin_pass2" placeholder="repeat password" minlength="8" required>
    </div>
    <div class="btn-row">
      <a href="install.php?step=2" class="btn btn-ghost">← Back</a>
      <button type="submit" class="btn btn-primary">Continue →</button>
    </div>
  </form>
</div>

<?php /* ═══════════════════ STEP 4 — INSTALL ═══════════════════ */ ?>
<?php elseif ($step === 4): ?>
  <div class="card-title"><?= $success ? 'Installation complete!' : 'Installation failed' ?></div>
  <div class="card-sub"><?= $success ? 'Statist has been successfully installed.' : 'Please review the errors below.' ?></div>
</div>
<div class="card-body">

  <?php if ($success): ?>
    <span class="done-icon">🎉</span>

    <!-- Install log -->
    <div class="install-log">
      <span class="log-ok">✓</span> Connected to database <span class="log-info"><?= htmlspecialchars($db['dbName']) ?></span><br>
      <span class="log-ok">✓</span> Schema imported from install.sql<br>
      <span class="log-ok">✓</span> Admin user <span class="log-info"><?= htmlspecialchars($admin['user']) ?></span> created<br>
      <span class="log-ok">✓</span> Configuration written to <span class="log-info">inc/db.php</span><br>
      <span class="log-ok">✓</span> Tracker endpoint set to <span class="log-info"><?= htmlspecialchars((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'').'/') ?></span><br>
      <span class="log-ok">✓</span> Lock file written to <span class="log-info">storage/installed.lock</span>
    </div>

    <div class="done-info">
      Dashboard: <strong>/list/</strong><br>
      Login:&nbsp;&nbsp;&nbsp;&nbsp; <strong><?= htmlspecialchars($admin['user']) ?></strong><br>
      Password:  <strong>as entered above</strong>
    </div>

    <div class="alert alert-warn" style="margin-bottom:20px">
      <strong>Security:</strong> Click the button below to delete <code>install.php</code>.
      If automatic deletion fails, remove the file manually via FTP/SSH.
    </div>

    <div class="btn-row" style="justify-content:center">
      <a href="install.php?action=delete_self&token=<?= $_SESSION['install_token'] = bin2hex(random_bytes(16)) ?>"
         class="btn btn-success" style="flex:1;justify-content:center">
        🗑 Delete install.php &amp; go to dashboard →
      </a>
    </div>

  <?php else: ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-err"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <div class="btn-row">
      <a href="install.php?step=3" class="btn btn-ghost">← Back</a>
      <a href="install.php?step=4" class="btn btn-primary">Retry →</a>
    </div>
  <?php endif; ?>

</div>
<?php endif; ?>

</div><!-- .card -->

</body>
</html>
