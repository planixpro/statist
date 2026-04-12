<?php

require __DIR__ . '/auth.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';
require_once __DIR__ . '/../inc/helpers.php';

$allSites = $pdo->query("SELECT * FROM sites ORDER BY name")->fetchAll();

if (($_SESSION['role'] ?? '') === 'site_viewer' && !empty($_SESSION['allowed_sites'])) {
    $allowed = $_SESSION['allowed_sites'];
    $sites   = array_values(array_filter($allSites, fn($s) => in_array((int)$s['id'], $allowed)));
} else {
    $sites = $allSites;
}

$sessionId = trim($_GET['id'] ?? '');
$siteId    = (int)($_GET['site'] ?? 0);

if ($sessionId === '' || $siteId <= 0) {
    header('Location: dashboard.php');
    exit;
}

// ── ACCESS CONTROL ───────────────────────────────────────────────
if (($_SESSION['role'] ?? '') === 'site_viewer') {
    $allowed = $_SESSION['allowed_sites'] ?? [];
    if (!in_array($siteId, $allowed, true)) {
        header('Location: dashboard.php');
        exit;
    }
}

// ── FLASH INIT ──────────────────────────────────────────────────
$blockMessage = null;
$blockMsgType = 'ok';

// ── POST ACTIONS (ADMIN ONLY) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] ?? '') === 'admin') {

    csrf_verify();

    $action   = $_POST['action'] ?? '';
    $targetIp = trim($_POST['target_ip'] ?? '');
    $reason   = trim(mb_substr($_POST['reason'] ?? '', 0, 255));

    $ipValid = $targetIp !== '' && filter_var($targetIp, FILTER_VALIDATE_IP);

    if (!$ipValid) {
        $blockMessage = __('session.invalid_ip');
        $blockMsgType = 'error';
    } else {

        switch ($action) {

            case 'block_ip':

                $exists = $pdo->prepare("
                    SELECT 1 FROM blocked_ips
                    WHERE ip = ?
                      AND is_active = 1
                      AND (expires_at IS NULL OR expires_at > NOW())
                    LIMIT 1
                ");
                $exists->execute([$targetIp]);

                if ($exists->fetchColumn()) {
                    $blockMessage = __('session.already_blocked');
                    $blockMsgType = 'error';
                    break;
                }

                $pdo->prepare("
                    INSERT INTO blocked_ips (ip, reason, source, is_active)
                    VALUES (?, ?, 'manual', 1)
                    ON DUPLICATE KEY UPDATE
                        is_active = 1,
                        reason = VALUES(reason)
                ")->execute([$targetIp, $reason ?: 'manual block']);

                // invalidate sessions
                $pdo->prepare("
                    UPDATE sessions
                    SET is_bot = 1,
                        is_valid = 0,
                        blocked_reason = 'blocked_ip'
                    WHERE ip = ?
                ")->execute([$targetIp]);

                $blockMessage = __('session.block_success');
                break;

            case 'block_subnet':

                $parts = explode('.', $targetIp);
                if (count($parts) !== 4) break;

                $subnet = implode('.', array_slice($parts, 0, 3));
                $cidr   = $subnet . '.0/24';

                // нормальная таблица есть, давай пользоваться ей
                $pdo->prepare("
                    INSERT INTO blocked_networks (cidr, reason, source, is_active)
                    VALUES (?, ?, 'manual', 1)
                    ON DUPLICATE KEY UPDATE
                        is_active = 1,
                        reason = VALUES(reason)
                ")->execute([
                    $cidr,
                    ($reason ?: 'manual subnet block')
                ]);

                // invalidate sessions
                $pdo->prepare("
                    UPDATE sessions
                    SET is_bot = 1,
                        is_valid = 0,
                        blocked_reason = 'blocked_ip'
                    WHERE ip LIKE ?
                ")->execute([$subnet . '.%']);

                $blockMessage = __('session.block_success') . ' (' . $cidr . ')';
                break;

            case 'unblock_ip':

                $pdo->prepare("
                    UPDATE blocked_ips
                    SET is_active = 0
                    WHERE ip = ?
                ")->execute([$targetIp]);

                $blockMessage = __('session.unblock_success');
                break;
        }
    }

    // redirect (PRG pattern)
    $qs = http_build_query([
        'id'   => $sessionId,
        'site' => $siteId
    ]);

    $_SESSION['block_msg']      = $blockMessage;
    $_SESSION['block_msg_type'] = $blockMsgType;

    header("Location: session.php?{$qs}");
    exit;
}

// ── FLASH READ ──────────────────────────────────────────────────
if (!empty($_SESSION['block_msg'])) {
    $blockMessage = $_SESSION['block_msg'];
    $blockMsgType = $_SESSION['block_msg_type'] ?? 'ok';
    unset($_SESSION['block_msg'], $_SESSION['block_msg_type']);
}

// ── SESSION DATA ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT *
    FROM sessions
    WHERE session_id = ?
      AND site_id = ?
    LIMIT 1
");
$stmt->execute([$sessionId, $siteId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: dashboard.php');
    exit;
}

// ── EVENTS ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT *
    FROM events
    WHERE session_id = ?
      AND site_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$sessionId, $siteId]);
$events = $stmt->fetchAll();

// ── SITE ────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->execute([$siteId]);
$site = $stmt->fetch();

// ── HELPERS ─────────────────────────────────────────────────────
function parseUA(string $ua): array {
    $browser = 'Other';

    if (str_contains($ua, 'Edg/')) $browser = 'Edge';
    elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) $browser = 'Opera';
    elseif (str_contains($ua, 'Chrome')) $browser = 'Chrome';
    elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari')) $browser = 'Safari';

    $os = 'Other';

    if (str_contains($ua, 'Windows')) $os = 'Windows';
    elseif (str_contains($ua, 'Mac OS')) $os = 'macOS';
    elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) $os = 'iOS';
    elseif (str_contains($ua, 'Android')) $os = 'Android';
    elseif (str_contains($ua, 'Linux')) $os = 'Linux';

    return compact('browser', 'os');
}

function sessionScreenType(string $screen): string {
    if (!preg_match('/^(\d+)x/', $screen, $m)) return 'desktop';
    $w = (int)$m[1];
    if ($w <= 480) return 'mobile';
    if ($w <= 1024) return 'tablet';
    return 'desktop';
}

function sessionIcon(string $group, string $name): string {
    $icons = [
        'browser' => [
            'Chrome' => 'fa-brands fa-chrome',
            'Firefox' => 'fa-brands fa-firefox-browser',
            'Safari' => 'fa-brands fa-safari',
            'Edge' => 'fa-brands fa-edge',
            'Opera' => 'fa-brands fa-opera',
            'Other' => 'fa-solid fa-globe',
        ],
        'os' => [
            'Windows' => 'fa-brands fa-windows',
            'macOS' => 'fa-brands fa-apple',
            'iOS' => 'fa-solid fa-mobile-screen',
            'Android' => 'fa-brands fa-android',
            'Linux' => 'fa-brands fa-linux',
            'Other' => 'fa-solid fa-desktop',
        ],
        'screen' => [
            'mobile' => 'fa-solid fa-mobile-screen',
            'tablet' => 'fa-solid fa-tablet-screen-button',
            'desktop' => 'fa-solid fa-desktop',
        ],
    ];

    $class = $icons[$group][$name] ?? 'fa-solid fa-circle';
    return '<span class="session-fa-icon"><i class="' . e($class) . '"></i></span>';
}

// ── DERIVED DATA ────────────────────────────────────────────────
$parsedUA = parseUA($session['user_agent'] ?? '');

$duration = 0;
if (!empty($session['last_activity']) && !empty($session['started_at'])) {
    $duration = strtotime($session['last_activity']) - strtotime($session['started_at']);
}

$isBot        = (int)($session['is_bot'] ?? 0);
$isSuspicious = (int)($session['is_suspicious'] ?? 0);
$botScore     = (int)($session['bot_score'] ?? 0);
$blockedReason = $session['blocked_reason'] ?? '';

// ── LABELS ──────────────────────────────────────────────────────
$reasonLabels = [
    'blocked_ip'       => __('session.block.blocked_ip'),
    'blocked_asn'      => __('session.block.blocked_asn'),
    'realtime_bot'     => __('session.block.realtime_bot'),
    'bot_score'        => __('session.block.bot_score'),
    'suspicious_score' => __('session.block.suspicious_score'),
];

$eventLabels = [
    'page_view'   => ['label' => __('event.page_view'),  'color' => '#4f46e5'],
    'heartbeat'   => ['label' => __('event.heartbeat'),  'color' => '#0ea5e9'],
    'click'       => ['label' => __('event.click'),      'color' => '#f59e0b'],
    'session_end' => ['label' => __('event.session_end'),'color' => '#8e8e93'],
];

// ── CHECK BLOCKED ───────────────────────────────────────────────
$ipIsBlocked = false;

if (!empty($session['ip'])) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM blocked_ips
        WHERE ip = ?
          AND is_active = 1
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$session['ip']]);
    $ipIsBlocked = (bool)$stmt->fetchColumn();
}

// ── LAYOUT ──────────────────────────────────────────────────────
$layoutTitle    = __('session.title') . ' — Statist';
$layoutSection  = 'dashboard';
$layoutExtraCss = ['/assets/css/session.css'];
$view           = __DIR__ . '/../views/session.view.php';

require __DIR__ . '/../views/layout.php';