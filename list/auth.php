<?php
/**
 * auth.php — DB-backed auth gate. Include at top of every admin page.
 *
 * Session keys set after login:
 *   $_SESSION['user']          — username
 *   $_SESSION['role']          — admin | viewer | site_viewer
 *   $_SESSION['user_id']       — int
 *   $_SESSION['locale']        — e.g. 'en'
 *   $_SESSION['allowed_sites'] — int[] for site_viewer, null = all
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/Lang.php';
require_once __DIR__ . '/../inc/flags.php';

// ---- Locale resolution (login page, before auth) ----
// Priority: 1) ?lang=xx in URL  2) statist_lang cookie  3) session  4) 'en'
$available = Lang::available();

if (isset($_GET['lang'])) {
    $urlLang = preg_replace('/[^a-z]/', '', strtolower($_GET['lang']));
    if (array_key_exists($urlLang, $available)) {
        setcookie('statist_lang', $urlLang, time() + 60*60*24*365, '/');
        $_COOKIE['statist_lang'] = $urlLang;
    }
}

$guestLocale = 'en';
if (!empty($_SESSION['locale'])) {
    $guestLocale = $_SESSION['locale'];
} elseif (!empty($_COOKIE['statist_lang']) && array_key_exists($_COOKIE['statist_lang'], $available)) {
    $guestLocale = $_COOKIE['statist_lang'];
}

Lang::load($guestLocale);

// ---- Already authenticated ----
if (!empty($_SESSION['user'])) {
    return;
}

// ---- Handle login POST ----
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']    ?? '');
    $pass  =      $_POST['password'] ?? '';

    if ($login !== '' && $pass !== '') {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, locale FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user']    = $user['username'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['user_id'] = (int)$user['id'];
            // User's saved locale wins; fall back to what they had on the login page
            $_SESSION['locale']  = ($user['locale'] ?: null) ?? $guestLocale;

            if ($user['role'] === 'site_viewer') {
                $s = $pdo->prepare("SELECT site_id FROM user_sites WHERE user_id = ?");
                $s->execute([$user['id']]);
                $_SESSION['allowed_sites'] = array_column($s->fetchAll(PDO::FETCH_ASSOC), 'site_id');
            } else {
                $_SESSION['allowed_sites'] = null;
            }

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            header("Location: /list/");
            exit;
        }

        $error = __('auth.error');
    } else {
        $error = __('auth.error_empty');
    }
}

$currentLocale = Lang::locale();
?>
<!DOCTYPE html>
<html lang="<?= $currentLocale ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Statist — <?= __('auth.title') ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f5f5f7;--surface:#fff;--border:#e5e5ea;
  --accent:#4f46e5;--accent-l:#ede9fe;
  --text:#1c1c1e;--muted:#8e8e93;
  --error-bg:#fff1f0;--error-bd:#ffc9c9;--error-tx:#c0392b;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);
  min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;gap:16px}

.card{width:100%;max-width:360px;background:var(--surface);border:1px solid var(--border);
  border-radius:14px;padding:36px 32px;box-shadow:0 2px 16px rgba(0,0,0,.06)}

.logo{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:500;
  letter-spacing:.16em;color:var(--accent);text-transform:uppercase;margin-bottom:24px}
h2{font-size:20px;font-weight:600;margin-bottom:4px}
.sub{font-size:13px;color:var(--muted);margin-bottom:28px}

label{display:block;font-size:11px;font-weight:600;letter-spacing:.07em;
  color:var(--muted);text-transform:uppercase;margin-bottom:7px}
input[type=text],input[type=password]{
  width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;
  padding:11px 13px;font-family:'JetBrains Mono',monospace;font-size:14px;color:var(--text);
  outline:none;transition:border-color .15s,box-shadow .15s;margin-bottom:18px;-webkit-appearance:none}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-l)}

button[type=submit]{width:100%;background:var(--accent);color:#fff;border:none;border-radius:8px;
  padding:12px;font-family:'Inter',sans-serif;font-size:14px;font-weight:600;
  cursor:pointer;transition:opacity .15s,transform .1s;margin-top:4px}
button[type=submit]:hover{opacity:.9}
button[type=submit]:active{transform:scale(.99)}

.error{background:var(--error-bg);border:1px solid var(--error-bd);border-radius:8px;
  padding:10px 13px;font-size:13px;color:var(--error-tx);margin-bottom:20px}

/* ---- Language switcher ---- */
.lang-switcher{
  width:100%;max-width:360px;
  display:flex;flex-wrap:wrap;justify-content:center;gap:6px;
}
.lang-switcher a{
  display:inline-flex;align-items:center;gap:5px;
  font-size:12px;font-weight:500;
  font-family:'Inter',sans-serif;
  padding:5px 10px;border-radius:6px;text-decoration:none;
  border:1px solid var(--border);background:var(--surface);color:var(--muted);
  transition:border-color .12s,color .12s,background .12s;
}
.lang-switcher a:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-l)}
.lang-switcher a.active{
  background:var(--accent-l);border-color:var(--accent);
  color:var(--accent);font-weight:600;
}
</style>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body>

<div class="card">
  <div class="logo">Statist</div>
  <h2><?= __('auth.title') ?></h2>
  <p class="sub"><?= __('auth.subtitle') ?></p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <label><?= __('auth.login') ?></label>
    <input type="text" name="login" autocomplete="username" autofocus>
    <label><?= __('auth.password') ?></label>
    <input type="password" name="password" autocomplete="current-password">
    <button type="submit"><?= __('auth.submit') ?></button>
  </form>
</div>

<!-- Language switcher — below the card -->
<div class="lang-switcher">
  <?php foreach ($available as $code => $name): ?>
    <a href="?lang=<?= $code ?>"
       class="<?= $code === $currentLocale ? 'active' : '' ?>"
       title="<?= htmlspecialchars($name) ?>">
      <?= flag_img($code, $name, '16px') ?>
      <?= htmlspecialchars($name) ?>
    </a>
  <?php endforeach; ?>
</div>

</body>
</html>
<?php exit; ?>
