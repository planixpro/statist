<?php

require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../app/BlockService.php';

$message = null;
$msgType = 'ok';

csrf_verify();

$blockService = new BlockService($pdo);

/*
|--------------------------------------------------------------------------
| POST actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    */

    if ($action === 'language') {

        $locale = preg_replace('/[^a-z]/', '', strtolower((string)($_POST['locale'] ?? 'en')));
        $availableLocales = array_keys(Lang::available());

        if (!in_array($locale, $availableLocales, true)) {
            $locale = 'en';
        }

        $pdo->prepare('UPDATE users SET locale = ? WHERE id = ?')
            ->execute([$locale, $_SESSION['user_id']]);

        $_SESSION['locale'] = $locale;
        Lang::load($locale);

        $message = __('settings.language.saved');
    }

    /*
    |--------------------------------------------------------------------------
    | Password
    |--------------------------------------------------------------------------
    */

    if ($action === 'password') {

        $current = trim((string)($_POST['current_password'] ?? ''));
        $new     = trim((string)($_POST['new_password'] ?? ''));

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);

        $hash = (string)$stmt->fetchColumn();

        if ($current === '' || !password_verify($current, $hash)) {
            $message = __('settings.password.err_current');
            $msgType = 'error';

        } elseif (strlen($new) < 8) {
            $message = __('settings.password.err_short');
            $msgType = 'error';

        } else {

            $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);

            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$newHash, $_SESSION['user_id']]);

            $message = __('settings.password.saved');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Block rules (admin)
    |--------------------------------------------------------------------------
    */

    if ($action === 'block_rule' && (($_SESSION['role'] ?? '') === 'admin')) {

        $type   = (string)($_POST['rule_type'] ?? 'ip');
        $reason = trim((string)($_POST['reason'] ?? '')) ?: 'manual block';

        $ok = false;

        if ($type === 'ip') {

            $ip = trim((string)($_POST['ip'] ?? ''));
            $ok = $blockService->blockIp($ip, $reason);

            if (!$ok) {
                $message = __('settings.blocks.invalid_ip');
            }

        } elseif ($type === 'subnet') {

            $subnet = trim((string)($_POST['subnet'] ?? ''));
            $ok = $blockService->blockSubnet($subnet, $reason);

            if (!$ok) {
                $message = __('settings.blocks.invalid_subnet');
            }

        } elseif ($type === 'asn') {

            $asn = trim((string)($_POST['asn'] ?? ''));
            $ok = $blockService->blockAsn($asn, $reason);

            if (!$ok) {
                $message = __('settings.blocks.invalid_asn');
            }
        }

        if ($ok) {
            $blockService->log('BLOCK_' . strtoupper($type), $type === 'ip' ? $ip ?? '' : ($subnet ?? $asn ?? ''));
            $message = __('settings.blocks.saved');
        } else {
            $msgType = 'error';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Remove block
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove_block' && (($_SESSION['role'] ?? '') === 'admin')) {

        $type = (string)($_POST['block_type'] ?? 'ip');

        if ($type === 'ip') {

            $ip = trim((string)($_POST['ip'] ?? ''));
            $blockService->unblockIp($ip);
            $blockService->log('UNBLOCK_IP', $ip);

        } elseif ($type === 'subnet') {

            $cidr = trim((string)($_POST['cidr'] ?? ''));
            $blockService->unblockSubnet($cidr);
            $blockService->log('UNBLOCK_SUBNET', $cidr);

        } elseif ($type === 'asn') {

            $asn = trim((string)($_POST['asn'] ?? ''));
            $blockService->unblockAsn($asn);
            $blockService->log('UNBLOCK_ASN', $asn);
        }

        $message = __('settings.blocks.removed');
    }
}

/*
|--------------------------------------------------------------------------
| Data for view
|--------------------------------------------------------------------------
*/

$available = Lang::available();
$current   = Lang::locale();

/*
|--------------------------------------------------------------------------
| Sites
|--------------------------------------------------------------------------
*/

$allSites = $pdo->query('SELECT * FROM sites ORDER BY name')->fetchAll();

if (($_SESSION['role'] ?? '') === 'site_viewer' && !empty($_SESSION['allowed_sites'])) {
    $allowed = $_SESSION['allowed_sites'];

    $sites = array_values(array_filter(
        $allSites,
        fn($s) => in_array((int)$s['id'], $allowed, true)
    ));
} else {
    $sites = $allSites;
}

/*
|--------------------------------------------------------------------------
| Blocks
|--------------------------------------------------------------------------
*/

$blockedIps = $pdo->query("
    SELECT ip, reason, created_at
    FROM blocked_ips
    WHERE is_active = 1
    ORDER BY created_at DESC
    LIMIT 100
")->fetchAll();

$blockedSubnets = [];
$blockedAsns    = [];

try {
    $blockedSubnets = $pdo->query("
        SELECT cidr, reason, created_at
        FROM blocked_networks
        WHERE is_active = 1
        ORDER BY created_at DESC
        LIMIT 100
    ")->fetchAll();
} catch (Throwable $e) {}

try {
    $blockedAsns = $pdo->query("
        SELECT asn, reason, created_at
        FROM blocked_asns
        WHERE is_active = 1
        ORDER BY created_at DESC
        LIMIT 100
    ")->fetchAll();
} catch (Throwable $e) {}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

$layoutTitle    = __('settings.title') . ' — Statist';
$layoutSection  = 'settings';
$layoutExtraCss = ['/assets/css/settings.css'];
$layoutExtraJs  = [];

$view = __DIR__ . '/../views/settings.view.php';

require __DIR__ . '/../views/layout.php';