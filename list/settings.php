<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';

$message = null;
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Save language ----
    if ($action === 'language') {
        $locale    = preg_replace('/[^a-z]/', '', strtolower($_POST['locale'] ?? 'en'));
        $available = array_keys(Lang::available());
        if (!in_array($locale, $available)) $locale = 'en';

        $pdo->prepare("UPDATE users SET locale=? WHERE id=?")->execute([$locale, $_SESSION['user_id']]);
        $_SESSION['locale'] = $locale;
        Lang::load($locale);
        $message = __('settings.language.saved');
    }

    // ---- Change password ----
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';

        $row = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $row->execute([$_SESSION['user_id']]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            $message = __('settings.password.err_current'); $msgType = 'error';
        } elseif (strlen($new) < 8) {
            $message = __('settings.password.err_short'); $msgType = 'error';
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$newHash, $_SESSION['user_id']]);
            $message = __('settings.password.saved');
        }
    }
}

$available = Lang::available();
$current   = Lang::locale();
?>
<!DOCTYPE html>
<html lang="<?= $current ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= __('settings.title') ?> — Statist</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f5f5f7;--surface:#fff;--surface2:#f9f9fb;--border:#e5e5ea;
  --accent:#4f46e5;--accent-l:#ede9fe;--text:#1c1c1e;--text2:#48484a;--muted:#8e8e93;
  --danger:#ff3b30;--danger-l:#fff1f0;--ok-l:#f0fdf4;--radius:10px}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
.page{max-width:560px;margin:0 auto;padding:32px 24px}
.back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);
  text-decoration:none;margin-bottom:20px;transition:color .12s}
.back:hover{color:var(--accent)}
h1{font-size:20px;font-weight:600;margin-bottom:4px}
.sub{font-size:13px;color:var(--muted);margin-bottom:26px}
.msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
.msg.ok{background:var(--ok-l);border:1px solid #bbf7d0;color:#166534}
.msg.error{background:var(--danger-l);border:1px solid #ffc9c9;color:#c0392b}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px}
.card-header{padding:12px 18px;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;
  letter-spacing:.08em;color:var(--muted);text-transform:uppercase;background:var(--surface2)}
.card-body{padding:20px 18px}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.field:last-child{margin-bottom:0}
.field label{font-size:11px;font-weight:600;letter-spacing:.07em;color:var(--muted);text-transform:uppercase}
input,select{background:var(--bg);border:1px solid var(--border);border-radius:8px;
  padding:9px 12px;font-size:13px;color:var(--text);outline:none;
  transition:border-color .15s,box-shadow .15s;width:100%;font-family:'Inter',sans-serif}
input:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-l)}
.btn{padding:9px 18px;border-radius:7px;font-size:13px;font-weight:500;cursor:pointer;
  border:1px solid transparent;transition:opacity .12s;font-family:'Inter',sans-serif}
.btn:hover{opacity:.85}
.btn-accent{background:var(--accent);color:#fff}

/* Language grid */
.lang-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:16px}
.lang-option{position:relative}
.lang-option input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.lang-option label{display:flex;align-items:center;gap:8px;padding:10px 12px;
  border:1px solid var(--border);border-radius:8px;cursor:pointer;
  font-size:13px;background:var(--bg);transition:border-color .15s,background .15s}
.lang-option label:hover{background:var(--accent-l);border-color:var(--accent)}
.lang-option input[type=radio]:checked + label{background:var(--accent-l);border-color:var(--accent);color:var(--accent);font-weight:500}
</style>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body>
<div class="page">
  <a href="dashboard.php" class="back"><?= __('settings.back') ?></a>
  <h1><?= __('settings.title') ?></h1>
  <p class="sub"><?= __('settings.subtitle') ?></p>

  <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <!-- Language -->
  <div class="card">
    <div class="card-header"><?= __('settings.language') ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="language">
        <div class="lang-grid">
          <?php foreach ($available as $code => $name): ?>
          <div class="lang-option">
            <input type="radio" name="locale" id="lang-<?= $code ?>" value="<?= $code ?>"
              <?= $code === $current ? 'checked' : '' ?>>
            <label for="lang-<?= $code ?>">
              <?= flag_img($code, $name, '18px') ?>
              <?= htmlspecialchars($name) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-accent"><?= __('settings.language.save') ?></button>
      </form>
    </div>
  </div>

  <!-- Password -->
  <div class="card">
    <div class="card-header"><?= __('settings.password') ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="password">
        <div class="field">
          <label><?= __('settings.password.current') ?></label>
          <input type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label><?= __('settings.password.new') ?></label>
          <input type="password" name="new_password" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-accent"><?= __('settings.password.save') ?></button>
      </form>
    </div>
  </div>

</div>
</body>
</html>
