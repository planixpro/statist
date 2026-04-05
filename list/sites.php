<?php
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = null;
$msgType = 'ok';

$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$trackerHost = $_SERVER['HTTP_HOST'] ?? 'your-tracker.example.com';
$trackerUrl  = $proto . '://' . $trackerHost . '/tracker.js';

function siteSnippet(string $url): string {
    return '<script src="' . htmlspecialchars($url, ENT_QUOTES) . '" async></script>';
}

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $siteId = (int)($_POST['site_id'] ?? 0);

    if ($action === 'create') {
        $domain = trim(strtolower($_POST['domain'] ?? ''));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        $name   = trim($_POST['name'] ?? '') ?: $domain;

        if (!$domain) {
            $message = __('sites.err.domain'); $msgType = 'error';
        } else {
            try {
                $pdo->prepare("INSERT INTO sites (domain, name) VALUES (?,?)")->execute([$domain, $name]);
                $message = __('sites.created');
            } catch (PDOException $e) {
                $message = $e->getCode() == 23000 ? __('sites.err.exists') : $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    if ($action === 'update' && $siteId) {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $pdo->prepare("UPDATE sites SET name=? WHERE id=?")->execute([$name, $siteId]);
            $message = __('sites.updated');
        }
    }

    if ($action === 'delete' && $siteId) {
        $row = $pdo->prepare("SELECT name FROM sites WHERE id=?");
        $row->execute([$siteId]);
        $siteName = $row->fetchColumn() ?: $siteId;
        $pdo->prepare("DELETE FROM user_sites WHERE site_id=?")->execute([$siteId]);
        $pdo->prepare("DELETE FROM events   WHERE site_id=?")->execute([$siteId]);
        $pdo->prepare("DELETE FROM sessions WHERE site_id=?")->execute([$siteId]);
        $pdo->prepare("DELETE FROM sites    WHERE id=?")->execute([$siteId]);
        $message = __('sites.deleted');
    }

    if ($action === 'update_users' && $siteId) {
        $userIds = array_map('intval', (array)($_POST['user_ids'] ?? []));
        $pdo->prepare("DELETE FROM user_sites WHERE site_id=?")->execute([$siteId]);
        if ($userIds) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_sites (user_id,site_id) VALUES (?,?)");
            foreach ($userIds as $uid) { $stmt->execute([$uid, $siteId]); }
        }
        $message = __('sites.users_updated');
    }
}

// ---- Load data ----
$sites = $pdo->query("
    SELECT s.*, COUNT(DISTINCT se.id) as session_count
    FROM sites s
    LEFT JOIN sessions se ON se.site_id = s.id
    GROUP BY s.id
    ORDER BY s.name
")->fetchAll();

$siteViewers = $pdo->query("SELECT id, username FROM users WHERE role='site_viewer' ORDER BY username")->fetchAll();

$userSiteMap = [];
foreach ($pdo->query("SELECT user_id, site_id FROM user_sites")->fetchAll() as $r) {
    $userSiteMap[(int)$r['site_id']][] = (int)$r['user_id'];
}

// ── Sites for sidebar ─────────────────────────────────────────────
$activeSite = (int)($_GET['site'] ?? 0);
$period     = $_GET['period'] ?? 'today';

// ── Render ────────────────────────────────────────────────────────
$layoutTitle    = __('sites.title') . ' — Statist';
$layoutSection  = 'sites';
$layoutExtraCss = ['/assets/css/sites.css'];
$layoutExtraJs  = [];
$view           = __DIR__ . '/../views/sites.view.php';

require __DIR__ . '/../views/layout.php';
