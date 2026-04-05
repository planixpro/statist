<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message  = null;
$msgType  = 'ok';
$allSites = $pdo->query("SELECT id, name, domain FROM sites ORDER BY name")->fetchAll();

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
        $role     = in_array($_POST['role'] ?? '', ['admin','viewer','site_viewer'])
                    ? $_POST['role'] : 'viewer';
        if ($username === '' || strlen($password) < 8) {
            $message = __('users.err.password'); $msgType = 'error';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?,?,?)")
                    ->execute([$username, $hash, $role]);
                $newUid = (int)$pdo->lastInsertId();
                if ($role === 'site_viewer') assignSites($pdo, $newUid, $siteIds);
                $message = __('users.created', $username);
            } catch (PDOException $e) {
                $message = $e->getCode() == 23000 ? __('users.err.exists') : $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'change_password' && $uid > 0) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) {
            $message = __('users.err.short_password'); $msgType = 'error';
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $uid]);
            $message = __('users.password_updated');
        }
    }

    if ($action === 'change_role' && $uid > 0) {
        $role = in_array($_POST['role'] ?? '', ['admin','viewer','site_viewer'])
                ? $_POST['role'] : 'viewer';
        if ($uid === $_SESSION['user_id']) {
            $message = __('users.err.self_role'); $msgType = 'error';
        } else {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
            if ($role !== 'site_viewer') {
                $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
            }
            $message = __('users.role_updated');
        }
    }

    if ($action === 'update_sites' && $uid > 0) {
        assignSites($pdo, $uid, $siteIds);
        $message = __('users.sites_updated');
    }

    if ($action === 'delete' && $uid > 0) {
        if ($uid === $_SESSION['user_id']) {
            $message = __('users.err.self_delete'); $msgType = 'error';
        } else {
            $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            $message = __('users.deleted');
        }
    }
}

$users = $pdo->query(
    "SELECT id, username, role, created_at, last_login FROM users ORDER BY id"
)->fetchAll();

$userSiteMap = [];
foreach ($pdo->query("SELECT user_id, site_id FROM user_sites")->fetchAll() as $r) {
    $userSiteMap[(int)$r['user_id']][] = (int)$r['site_id'];
}

// ── Sites for sidebar ─────────────────────────────────────────────
$sites      = $allSites; // admin sees all
$activeSite = (int)($_GET['site'] ?? 0);
$period     = $_GET['period'] ?? 'today';

// ── Render ────────────────────────────────────────────────────────
$layoutTitle    = __('users.title') . ' — Statist';
$layoutSection  = 'users';
$layoutExtraCss = ['/assets/css/users.css'];
$layoutExtraJs  = [];
$view           = __DIR__ . '/../views/users.view.php';

require __DIR__ . '/../views/layout.php';
