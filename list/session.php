<?php
require __DIR__ . '/auth.php'; // also loads Lang
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/flags.php';

$sessionId = $_GET['id']   ?? '';
$siteId    = (int)($_GET['site'] ?? 0);

if (!$sessionId || !$siteId) {
    header('Location: dashboard.php');
    exit;
}

// site_viewer: check site access
if (($_SESSION['role'] ?? '') === 'site_viewer') {
    $allowed = $_SESSION['allowed_sites'] ?? [];
    if (!in_array($siteId, $allowed)) {
        header('Location: dashboard.php');
        exit;
    }
}

// Session data
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = ? AND site_id = ? LIMIT 1");
$stmt->execute([$sessionId, $siteId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: dashboard.php');
    exit;
}

// Events
$stmt = $pdo->prepare("
    SELECT * FROM events
    WHERE session_id = ? AND site_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$sessionId, $siteId]);
$events = $stmt->fetchAll();

// Site info
$stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
$stmt->execute([$siteId]);
$site = $stmt->fetch();

if (!function_exists('parseUA')) {
    function parseUA(string $ua): array {
        $browser = 'Other';
        if (str_contains($ua, 'Edg/'))                                    $browser = 'Edge';
        elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) $browser = 'Opera';
        elseif (str_contains($ua, 'Chrome'))                              $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox'))                             $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari'))                              $browser = 'Safari';

        $os = 'Other';
        if (str_contains($ua, 'Windows'))                                        $os = 'Windows';
        elseif (str_contains($ua, 'Mac OS'))                                     $os = 'macOS';
        elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))       $os = 'iOS';
        elseif (str_contains($ua, 'Android'))                                    $os = 'Android';
        elseif (str_contains($ua, 'Linux'))                                      $os = 'Linux';

        return ['browser' => $browser, 'os' => $os];
    }
}

if (!function_exists('sessionIcon')) {
    function sessionScreenType(string $screen): string {
        if (!preg_match('/^(\d+)x/', $screen, $m)) return 'desktop';
        $w = (int)$m[1];
        if ($w <= 480)  return 'mobile';
        if ($w <= 1024) return 'tablet';
        return 'desktop';
    }

    function sessionIcon(string $group, string $name): string {
        static $icons = null;
        if ($icons === null) {
            $icons = [
                'browser' => [
                    'Chrome'  => '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="#fff" stroke="#e5e5ea" stroke-width=".5"/><circle cx="12" cy="12" r="4" fill="#4285F4"/><path d="M12 8h9" stroke="#EA4335" stroke-width="2.5" stroke-linecap="round"/><path d="M12 8 7.3 16" stroke="#FBBC05" stroke-width="2.5" stroke-linecap="round"/><path d="M7.3 16H21" stroke="#34A853" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="0 13.7 6 99"/><circle cx="12" cy="12" r="1.8" fill="#fff"/></svg>',
                    'Firefox' => '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#FF7139"/><circle cx="12" cy="12" r="5" fill="#FF980E"/><circle cx="12" cy="12" r="2.5" fill="#FFCA00"/></svg>',
                    'Safari'  => '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#1C8EF9"/><line x1="12" y1="4" x2="12" y2="6.5" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="12" y1="17.5" x2="12" y2="20" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="4" y1="12" x2="6.5" y2="12" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><line x1="17.5" y1="12" x2="20" y2="12" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/><polygon points="12,7 13.5,13.5 12,12.5 10.5,13.5" fill="#fff"/><polygon points="12,17 10.5,10.5 12,11.5 13.5,10.5" fill="rgba(255,255,255,0.45)"/></svg>',
                    'Edge'    => '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 3C7.03 3 3 7.03 3 12c0 3.86 2.42 7.16 5.87 8.46C7.5 19 7 17.5 7 16c0-3.5 2.8-6 6-6 .34 0 .68.03 1 .08V8c-3.87 0-7 3.13-7 7 0 1.3.35 2.5.97 3.54A9 9 0 1 1 12 3z" fill="#0078D4"/><path d="M20.5 14.5c0 3.04-2.46 5.5-5.5 5.5-2.4 0-4.46-1.54-5.2-3.68.44.12.9.18 1.2.18 2.2 0 4-1.8 4-4 0-.46-.1-.9-.24-1.3A5.5 5.5 0 0 1 20.5 14.5z" fill="#50E6FF"/></svg>',
                    'Opera'   => '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#FF1B2D"/><ellipse cx="12" cy="12" rx="4" ry="6.5" fill="none" stroke="#fff" stroke-width="1.8"/></svg>',
                    'Other'   => '<svg viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="9" fill="#c7c7cc"/><circle cx="12" cy="12" r="4" fill="none" stroke="#fff" stroke-width="1.5"/><line x1="12" y1="3" x2="12" y2="21" stroke="#fff" stroke-width="1" opacity=".5"/><line x1="3" y1="12" x2="21" y2="12" stroke="#fff" stroke-width="1" opacity=".5"/></svg>',
                ],
                'os' => [
                    'Windows' => '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="3" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="12.5" y="3" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="3" y="12.5" width="8.5" height="8.5" rx=".5" fill="#0078D4"/><rect x="12.5" y="12.5" width="8.5" height="8.5" rx=".5" fill="#0078D4"/></svg>',
                    'macOS'   => '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M15.5 4.5c.1 1.7-1.2 3-2.8 3-.2-1.6 1.3-3.1 2.8-3z" fill="#555"/><path d="M18 16.8c-.8.8-1.7.7-2.6.3-.9-.4-1.7-.4-2.7 0-1.2.5-1.8.4-2.5-.3C6.5 13.5 7 8 11.3 7.7c1.1.1 1.9.6 2.5.7 1-.2 1.9-.8 2.9-.7 1.2.1 2.2.6 2.8 1.5-2.5 1.5-1.9 4.9.4 5.8-.5 1.2-1.1 2.4-1.9 3.8z" fill="#555"/></svg>',
                    'iOS'     => '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="7" y="2" width="10" height="20" rx="2.5" fill="#555"/><rect x="9" y="4" width="6" height="13.5" rx=".5" fill="#aaa"/><rect x="10.5" y="18.5" width="3" height="1" rx=".5" fill="#888"/></svg>',
                    'Android' => '<svg viewBox="0 0 24 24" width="16" height="16"><ellipse cx="12" cy="10" rx="6.5" ry="5" fill="#3DDC84"/><rect x="5.5" y="10" width="13" height="8" fill="#3DDC84"/><rect x="5.5" y="15" width="13" height="3" rx="1.5" fill="#2BB873"/><circle cx="9.5" cy="9.5" r="1" fill="#fff"/><circle cx="14.5" cy="9.5" r="1" fill="#fff"/><line x1="8.5" y1="6.5" x2="6.5" y2="3.5" stroke="#3DDC84" stroke-width="1.5" stroke-linecap="round"/><line x1="15.5" y1="6.5" x2="17.5" y2="3.5" stroke="#3DDC84" stroke-width="1.5" stroke-linecap="round"/><rect x="2.5" y="10" width="2" height="5" rx="1" fill="#3DDC84"/><rect x="19.5" y="10" width="2" height="5" rx="1" fill="#3DDC84"/></svg>',
                    'Linux'   => '<svg viewBox="0 0 24 24" width="16" height="16"><ellipse cx="12" cy="9" rx="4.5" ry="5.5" fill="#E8A000"/><rect x="7.5" y="9" width="9" height="7" rx=".5" fill="#E8A000"/><circle cx="10" cy="8" r="1" fill="#5c3a00"/><circle cx="14" cy="8" r="1" fill="#5c3a00"/><path d="M9 17.5 7 20h10l-2-2.5" fill="#c47c00"/><ellipse cx="9" cy="20" rx="2" ry="1" fill="#888"/><ellipse cx="15" cy="20" rx="2" ry="1" fill="#888"/></svg>',
                    'Other'   => '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="4" width="18" height="12" rx="2" fill="#c7c7cc"/><rect x="8" y="16" width="8" height="2" fill="#c7c7cc"/><rect x="6" y="18" width="12" height="1.5" rx=".5" fill="#c7c7cc"/></svg>',
                ],
                'screen' => [
                    'mobile'  => '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="7" y="2" width="10" height="20" rx="2.5" fill="#6366f1"/><rect x="9" y="4" width="6" height="13.5" rx=".5" fill="rgba(255,255,255,0.22)"/><rect x="10.5" y="18.5" width="3" height="1" rx=".5" fill="rgba(255,255,255,0.7)"/></svg>',
                    'tablet'  => '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="4" y="2" width="16" height="20" rx="2.5" fill="#0ea5e9"/><rect x="6" y="4" width="12" height="14" rx=".5" fill="rgba(255,255,255,0.22)"/><rect x="10.5" y="19.5" width="3" height="1" rx=".5" fill="rgba(255,255,255,0.7)"/></svg>',
                    'desktop' => '<svg viewBox="0 0 24 24" width="16" height="16"><rect x="2" y="3" width="20" height="14" rx="2" fill="#34c759"/><rect x="4" y="5" width="16" height="10" rx=".5" fill="rgba(255,255,255,0.22)"/><path d="M8 21h8M12 17v4" stroke="#34c759" stroke-width="1.5" stroke-linecap="round"/></svg>',
                ],
            ];
        }
        $svg = $icons[$group][$name] ?? ($icons[$group]['Other'] ?? '');
        return $svg ? '<span style="display:inline-flex;align-items:center;flex-shrink:0">' . $svg . '</span>' : '';
    }
}


$duration = 0;
if ($session['last_activity'] && $session['started_at']) {
    $duration = strtotime($session['last_activity']) - strtotime($session['started_at']);
}

$isBot         = (int)($session['is_bot']         ?? 0);
$isSuspicious  = (int)($session['is_suspicious']  ?? 0);
$botScore      = (int)($session['bot_score']       ?? 0);
$blockedReason =       $session['blocked_reason']  ?? '';

$reasonLabels = [
    'blocked_ip'       => 'IP blacklist',
    'blocked_asn'      => 'ASN blacklist',
    'realtime_bot'     => 'Real-time block',
    'bot_score'        => 'Bot score',
    'suspicious_score' => 'Suspicious score',
];

$eventLabels = [
    'page_view'   => ['label' => __('event.page_view'),  'color' => '#4f46e5'],
    'heartbeat'   => ['label' => __('event.heartbeat'),  'color' => '#0ea5e9'],
    'click'       => ['label' => __('event.click'),       'color' => '#f59e0b'],
    'session_end' => ['label' => __('event.session_end'), 'color' => '#8e8e93'],
];

include __DIR__ . '/../views/session.view.php';
