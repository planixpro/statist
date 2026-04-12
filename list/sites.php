<?php

require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . admin_url('dashboard'));
    exit;
}

/**
 * ------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------
 */

function sites_set_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['sites_flash'] = [
        'message' => $message,
        'type'    => $type,
    ];
}

function sites_pull_flash(): array
{
    $flash = $_SESSION['sites_flash'] ?? null;
    unset($_SESSION['sites_flash']);

    if (!is_array($flash)) {
        return [null, 'ok'];
    }

    return [
        (string)($flash['message'] ?? null),
        (string)($flash['type'] ?? 'ok'),
    ];
}

function sites_redirect(array $params = []): void
{
    header('Location: ' . admin_url('sites', $params));
    exit;
}

function current_scheme(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim((string)$_SERVER['HTTP_X_FORWARDED_PROTO']));
        if (in_array($proto, ['http', 'https'], true)) {
            return $proto;
        }
    }

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }

    return 'http';
}

function tracker_base_url(): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'your-tracker.example.com'));
    if ($host === '') {
        $host = 'your-tracker.example.com';
    }

    return current_scheme() . '://' . $host . '/';
}

function normalize_site_domain(string $value): string
{
    $value = trim(mb_strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('#^\s*https?://#i', '', $value);
    $value = preg_replace('#^//#', '', $value);
    $value = trim($value);

    $parsed = parse_url('http://' . $value);
    if ($parsed === false) {
        return '';
    }

    $host = trim((string)($parsed['host'] ?? ''));
    if ($host === '') {
        return '';
    }

    return rtrim($host, '.');
}

function siteSnippet(string $url, string $domain): string
{
    $endpoint = rtrim($url, '/');
    $endpointJs = json_encode($endpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $hostJs     = json_encode($domain, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return <<<HTML
<script src="{$endpoint}/assets/js/s.js" defer></script>
HTML;
}

/**
 * ------------------------------------------------------------
 * Flash message
 * ------------------------------------------------------------
 */

[$message, $msgType] = sites_pull_flash();

/**
 * ------------------------------------------------------------
 * POST actions
 * ------------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = trim((string)($_POST['action'] ?? ''));
    $siteId = (int)($_POST['site_id'] ?? 0);

    try {
        if ($action === 'create') {
            $domain = normalize_site_domain((string)($_POST['domain'] ?? ''));
            $name   = trim((string)($_POST['name'] ?? ''));

            if ($domain === '' || !is_valid_domain_name($domain)) {
                sites_set_flash(__('sites.err.domain'), 'error');
                sites_redirect();
            }

            if ($name === '') {
                $name = $domain;
            }

            $stmt = $pdo->prepare('INSERT INTO sites (domain, name) VALUES (?, ?)');
            $stmt->execute([$domain, $name]);

            sites_set_flash(__('sites.created'), 'ok');
            sites_redirect(['site' => (int)$pdo->lastInsertId()]);
        }

        if ($action === 'update') {
            if ($siteId <= 0) {
                sites_set_flash(__('common.error_generic'), 'error');
                sites_redirect();
            }

            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                sites_set_flash(__('common.error_generic'), 'error');
                sites_redirect(['site' => $siteId]);
            }

            $stmt = $pdo->prepare('UPDATE sites SET name = ? WHERE id = ?');
            $stmt->execute([$name, $siteId]);

            sites_set_flash(__('sites.updated'), 'ok');
            sites_redirect(['site' => $siteId]);
        }

        if ($action === 'delete') {
            if ($siteId <= 0) {
                sites_set_flash(__('common.error_generic'), 'error');
                sites_redirect();
            }

            $check = $pdo->prepare('SELECT id FROM sites WHERE id = ? LIMIT 1');
            $check->execute([$siteId]);

            if (!$check->fetch()) {
                sites_set_flash(__('common.error_generic'), 'error');
                sites_redirect();
            }

            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM user_sites WHERE site_id = ?')->execute([$siteId]);
            $pdo->prepare('DELETE FROM events WHERE site_id = ?')->execute([$siteId]);
            $pdo->prepare('DELETE FROM sessions WHERE site_id = ?')->execute([$siteId]);
            $pdo->prepare('DELETE FROM sites WHERE id = ?')->execute([$siteId]);

            $pdo->commit();

            sites_set_flash(__('sites.deleted'), 'ok');
            sites_redirect();
        }

        if ($action === 'update_users') {
            if ($siteId <= 0) {
                sites_set_flash(__('common.error_generic'), 'error');
                sites_redirect();
            }

            $rawUserIds = array_map('intval', (array)($_POST['user_ids'] ?? []));
            $rawUserIds = array_values(array_unique(array_filter($rawUserIds, static fn($id) => $id > 0)));

            $allowedIds = $pdo->query("SELECT id FROM users WHERE role = 'site_viewer'")
                ->fetchAll(PDO::FETCH_COLUMN);

            $allowedIds = array_map('intval', $allowedIds);
            $allowedMap = array_fill_keys($allowedIds, true);

            $userIds = [];
            foreach ($rawUserIds as $uid) {
                if (isset($allowedMap[$uid])) {
                    $userIds[] = $uid;
                }
            }

            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM user_sites WHERE site_id = ?')->execute([$siteId]);

            if ($userIds) {
                $stmt = $pdo->prepare('INSERT INTO user_sites (user_id, site_id) VALUES (?, ?)');
                foreach ($userIds as $uid) {
                    $stmt->execute([$uid, $siteId]);
                }
            }

            $pdo->commit();

            sites_set_flash(__('sites.users_updated'), 'ok');
            sites_redirect(['site' => $siteId]);
        }

        sites_set_flash(__('common.error_generic'), 'error');
        sites_redirect();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($action === 'create' && $e->getCode() === '23000') {
            sites_set_flash(__('sites.err.exists'), 'error');
        } else {
            sites_set_flash(__('common.error_generic'), 'error');
        }

        sites_redirect($siteId > 0 ? ['site' => $siteId] : []);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        sites_set_flash(__('common.error_generic'), 'error');
        sites_redirect($siteId > 0 ? ['site' => $siteId] : []);
    }
}

/**
 * ------------------------------------------------------------
 * Page data
 * ------------------------------------------------------------
 */

$trackerUrl = tracker_base_url();

$sitesStmt = $pdo->query(
    "SELECT
        s.*,
        COUNT(DISTINCT se.id) AS session_count
     FROM sites s
     LEFT JOIN sessions se ON se.site_id = s.id
     GROUP BY s.id, s.domain, s.name, s.created_at
     ORDER BY s.name ASC, s.domain ASC"
);
$sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

$siteViewersStmt = $pdo->query(
    "SELECT id, username
     FROM users
     WHERE role = 'site_viewer'
     ORDER BY username ASC"
);
$siteViewers = $siteViewersStmt->fetchAll(PDO::FETCH_ASSOC);

$userSiteMap = [];
$userSitesStmt = $pdo->query('SELECT user_id, site_id FROM user_sites');
foreach ($userSitesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $siteKey = (int)$row['site_id'];
    if (!isset($userSiteMap[$siteKey])) {
        $userSiteMap[$siteKey] = [];
    }
    $userSiteMap[$siteKey][] = (int)$row['user_id'];
}

$activeSite = (int)($_GET['site'] ?? 0);
$period     = $_GET['period'] ?? 'today';

$layoutTitle    = __('sites.title') . ' — Statist';
$layoutSection  = 'sites';
$layoutExtraCss = ['/assets/css/sites.css'];
$layoutExtraJs  = [];
$view           = __DIR__ . '/../views/sites.view.php';

require __DIR__ . '/../views/layout.php';