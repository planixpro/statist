<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . admin_url('dashboard'));
    exit;
}

$message  = null;
$msgType  = 'ok';

$allSites = $pdo->query("SELECT id, name, domain FROM sites ORDER BY name")->fetchAll();

function logAction(string $msg): void {
    file_put_contents(
        __DIR__ . '/../storage/logs/statist.log',
        date('[Y-m-d H:i:s] ') . $msg . PHP_EOL,
        FILE_APPEND
    );
}

function userExists(PDO $pdo, int $uid): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id=?");
    $stmt->execute([$uid]);
    return (bool)$stmt->fetchColumn();
}

function assignSites(PDO $pdo, int $uid, array $siteIds, array $validSiteIds): void {
    // фильтруем только существующие сайты
    $siteIds = array_values(array_intersect($siteIds, $validSiteIds));

    $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);

    if ($siteIds) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_sites (user_id, site_id) VALUES (?,?)");
        foreach ($siteIds as $sid) {
            $stmt->execute([$uid, (int)$sid]);
        }
    }
}

// допустимые ID сайтов
$validSiteIds = array_column($allSites, 'id');

// ---- Handle POST ----
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action  = $_POST['action'] ?? '';
    $uid     = (int)($_POST['uid'] ?? 0);
    $siteIds = array_map('intval', (array)($_POST['site_ids'] ?? []));

    // ---------------- CREATE ----------------
    if ($action === 'create') {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin','viewer','site_viewer'])
                    ? $_POST['role'] : 'viewer';

        // валидация username
        if (!preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $username)) {
            $message = __('users.err.login_invalid');
            $msgType = 'error';
        }

        // валидация пароля
        elseif (strlen($password) < 8) {
            $message = __('users.err.short_password');
            $msgType = 'error';
        }

        else {
            // проверка дубликата
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username=?");
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                $message = __('users.err.exists');
                $msgType = 'error';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?,?,?)")
                        ->execute([$username, $hash, $role]);

                    $newUid = (int)$pdo->lastInsertId();

                    if ($role === 'site_viewer') {
                        assignSites($pdo, $newUid, $siteIds, $validSiteIds);
                    }

                    logAction("user_created uid={$newUid} username={$username} by={$_SESSION['user_id']}");

                    $message = __('users.created', $username);

                } catch (PDOException $e) {
                    $message = __('common.error_generic');
                    $msgType = 'error';
                }
            }
        }
    }

    // ---------------- CHANGE PASSWORD ----------------
    if ($action === 'change_password' && $uid > 0) {

        if (!userExists($pdo, $uid)) {
            $message = __('users.err.not_found');
            $msgType = 'error';
        } else {

            $password = $_POST['password'] ?? '';

            if (strlen($password) < 8) {
                $message = __('users.err.short_password');
                $msgType = 'error';
            } else {

                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([
                        password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                        $uid
                    ]);

                logAction("password_changed uid={$uid} by={$_SESSION['user_id']}");

                $message = __('users.password_updated');
            }
        }
    }

    // ---------------- CHANGE ROLE ----------------
    if ($action === 'change_role' && $uid > 0) {

        if (!userExists($pdo, $uid)) {
            $message = __('users.err.not_found');
            $msgType = 'error';
        } else {

            $role = in_array($_POST['role'] ?? '', ['admin','viewer','site_viewer'])
                    ? $_POST['role'] : 'viewer';

            if ($uid === (int)$_SESSION['user_id']) {
                $message = __('users.err.self_role');
                $msgType = 'error';
            } else {

                // защита от удаления последнего админа
                if ($role !== 'admin') {
                    $countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

                    $currentRole = $pdo->prepare("SELECT role FROM users WHERE id=?");
                    $currentRole->execute([$uid]);
                    $currentRole = $currentRole->fetchColumn();

                    if ($currentRole === 'admin' && $countAdmins <= 1) {
                        $message = __('users.err.last_admin');
                        $msgType = 'error';
                    } else {

                        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);

                        if ($role !== 'site_viewer') {
                            $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
                        }

                        logAction("role_changed uid={$uid} role={$role} by={$_SESSION['user_id']}");

                        $message = __('users.role_updated');
                    }
                } else {

                    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);

                    logAction("role_changed uid={$uid} role={$role} by={$_SESSION['user_id']}");

                    $message = __('users.role_updated');
                }
            }
        }
    }

    // ---------------- UPDATE SITES ----------------
    if ($action === 'update_sites' && $uid > 0) {

        if (!userExists($pdo, $uid)) {
            $message = __('users.err.not_found');
            $msgType = 'error';
        } else {

            assignSites($pdo, $uid, $siteIds, $validSiteIds);

            logAction("sites_updated uid={$uid} by={$_SESSION['user_id']}");

            $message = __('users.sites_updated');
        }
    }

    // ---------------- DELETE ----------------
    if ($action === 'delete' && $uid > 0) {

        if (!userExists($pdo, $uid)) {
            $message = __('users.err.not_found');
            $msgType = 'error';
        } else {

            if ($uid === (int)$_SESSION['user_id']) {
                $message = __('users.err.self_delete');
                $msgType = 'error';
            } else {

                // защита последнего админа
                $currentRole = $pdo->prepare("SELECT role FROM users WHERE id=?");
                $currentRole->execute([$uid]);
                $currentRole = $currentRole->fetchColumn();

                if ($currentRole === 'admin') {
                    $countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

                    if ($countAdmins <= 1) {
                        $message = __('users.err.last_admin');
                        $msgType = 'error';
                    } else {

                        $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
                        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);

                        logAction("user_deleted uid={$uid} by={$_SESSION['user_id']}");

                        $message = __('users.deleted');
                    }
                } else {

                    $pdo->prepare("DELETE FROM user_sites WHERE user_id=?")->execute([$uid]);
                    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);

                    logAction("user_deleted uid={$uid} by={$_SESSION['user_id']}");

                    $message = __('users.deleted');
                }
            }
        }
    }
}

// ---- DATA ----
$users = $pdo->query(
    "SELECT id, username, role, created_at, last_login FROM users ORDER BY id"
)->fetchAll();

$userSiteMap = [];
foreach ($pdo->query("SELECT user_id, site_id FROM user_sites")->fetchAll() as $r) {
    $userSiteMap[(int)$r['user_id']][] = (int)$r['site_id'];
}

// ── Sites for sidebar ─────────────────────────────────────────────
$sites      = $allSites;
$activeSite = (int)($_GET['site'] ?? 0);
$period     = $_GET['period'] ?? 'today';

// ── Render ────────────────────────────────────────────────────────
$layoutTitle    = __('users.title') . ' — Statist';
$layoutSection  = 'users';
$layoutExtraCss = ['/assets/css/users.css'];
$layoutExtraJs  = [];
$view           = __DIR__ . '/../views/users.view.php';

require __DIR__ . '/../views/layout.php';