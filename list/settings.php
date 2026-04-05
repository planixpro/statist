<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';

$message = null;
$msgType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'language') {
        $locale    = preg_replace('/[^a-z]/', '', strtolower($_POST['locale'] ?? 'en'));
        $available = array_keys(Lang::available());
        if (!in_array($locale, $available)) $locale = 'en';

        $pdo->prepare("UPDATE users SET locale=? WHERE id=?")->execute([$locale, $_SESSION['user_id']]);
        $_SESSION['locale'] = $locale;
        Lang::load($locale);
        $message = __('settings.language.saved');
    }

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

// ── Sites for sidebar ─────────────────────────────────────────────
$allSites = $pdo->query("SELECT * FROM sites ORDER BY name")->fetchAll();
if (($_SESSION['role'] ?? '') === 'site_viewer' && !empty($_SESSION['allowed_sites'])) {
    $allowed = $_SESSION['allowed_sites'];
    $sites   = array_values(array_filter($allSites, fn($s) => in_array((int)$s['id'], $allowed)));
} else {
    $sites = $allSites;
}

$activeSite = (int)($_GET['site'] ?? 0);
$period     = $_GET['period'] ?? 'today';

// ── Render ────────────────────────────────────────────────────────
$layoutTitle    = __('settings.title') . ' — Statist';
$layoutSection  = 'settings';
$layoutExtraCss = ['/assets/css/settings.css'];
$layoutExtraJs  = [];
$view           = __DIR__ . '/../views/settings.view.php';

require __DIR__ . '/../views/layout.php';
