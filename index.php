<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/error.log');

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/app/BotDetector.php';
require_once __DIR__ . '/app/GeoService.php';
require_once __DIR__ . '/app/SessionService.php';
require_once __DIR__ . '/app/BlockService.php';

/*
--------------------------------
Helpers
--------------------------------
*/
function statist_log(string $message): void
{
    error_log('[statist] ' . $message);
}

function strField(array $data, string $key, int $maxLen = 255, string $default = ''): string
{
    $val = $data[$key] ?? $default;

    if (is_array($val) || is_object($val)) {
        return $default;
    }

    $val = trim((string)$val);

    if ($val === '') {
        return $default;
    }

    return mb_substr($val, 0, $maxLen);
}

function getClientIp(): string
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';

    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }

    $ip = trim($ip);

    if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
        return '';
    }

    return $ip;
}

function ensureLogDirExists(string $path): void
{
    $dir = dirname($path);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

ensureLogDirExists(__DIR__ . '/storage/logs/error.log');

/*
--------------------------------
Routing
--------------------------------
*/
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($requestPath !== '/api/collect') {
    http_response_code(404);
    exit;
}

/*
--------------------------------
GET stub
--------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"></head><body>ok</body></html>';
    exit;
}

/*
--------------------------------
CORS
--------------------------------
*/
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Vary: Origin');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
--------------------------------
IP / UA
--------------------------------
*/
$ip = getClientIp();
$ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

/*
--------------------------------
Rate limit (APCu)
--------------------------------
*/
if ($ip !== '' && function_exists('apcu_fetch')) {
    $rlKey = 'statist_rl_' . md5($ip);
    $rlCount = apcu_fetch($rlKey);

    if ($rlCount === false) {
        apcu_store($rlKey, 1, 60);
    } elseif ((int)$rlCount >= 120) {
        http_response_code(429);
        echo json_encode(['status' => 'rate_limited'], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        apcu_inc($rlKey);
    }
}

/*
--------------------------------
Read payload
--------------------------------
*/
$data = [];
$raw = file_get_contents('php://input', false, null, 0, 16384);

if ($raw !== false && $raw !== '') {
    $json = json_decode($raw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $data = $json;
    } else {
        statist_log('invalid_json ip=' . $ip);
    }
}

if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'ignored_empty'], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
--------------------------------
Required fields
--------------------------------
*/
$site  = strField($data, 'h', 253);
$sid   = strField($data, 'sid', 64);
$event = strField($data, 'ev', 50, 'page_view');

if ($sid === '' || !preg_match('~^[a-zA-Z0-9\-_]{8,64}$~', $sid)) {
    statist_log('invalid_sid ip=' . $ip);
    echo json_encode(['status' => 'ignored_invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($site === '' || !preg_match('~^[a-zA-Z0-9.\-]{3,253}$~', $site)) {
    statist_log('invalid_site ip=' . $ip);
    echo json_encode(['status' => 'ignored_invalid'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedEvents = ['page_view', 'heartbeat', 'click', 'session_end'];
if (!in_array($event, $allowedEvents, true)) {
    $event = 'page_view';
}

/*
--------------------------------
Other fields
--------------------------------
*/
$path     = strField($data, 'p', 512, '/');
$query    = strField($data, 'query', 512, '');
$referrer = strField($data, 'r', 512, '');
$title    = strField($data, 't', 255, '');
$screen   = strField($data, 's', 20, '');
$lang     = strField($data, 'l', 10, '');
$tz       = strField($data, 'tz', 64, '');
$fp       = strField($data, 'fp', 200, '');
$js       = isset($data['js']) ? (int)$data['js'] : 0;

if ($path === '' || $path[0] !== '/') {
    $path = '/';
}

/*
--------------------------------
Block check
--------------------------------
*/
try {
    if ($ip !== '' && BlockService::isBlocked($pdo, $ip)) {
        statist_log('blocked_ip ip=' . $ip . ' site=' . $site);
        http_response_code(403);
        echo json_encode(['status' => 'blocked'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    statist_log('block_check_failed: ' . $e->getMessage());
}

/*
--------------------------------
Realtime bot filter
--------------------------------
*/
$prefilterCtx = [
    'ua'       => $ua,
    'path'     => $path,
    'referrer' => $referrer,
    'js'       => $js,
    'fp'       => $fp,
    'screen'   => $screen,
];

if (BotDetector::shouldBlockRealtime($prefilterCtx)) {
    echo json_encode(['status' => 'ignored_suspicious'], JSON_UNESCAPED_UNICODE);
    exit;
}

/*
--------------------------------
Geo
--------------------------------
*/
$geo = [];

if ($ip !== '') {
    try {
        $geo = GeoService::lookup($ip);
    } catch (Throwable $e) {
        statist_log('geo_failed ip=' . $ip);
    }
}

/*
--------------------------------
Normalized payload
--------------------------------
*/
$normalized = [
    'site'         => $site,
    'session_id'   => $sid,
    'event'        => $event,

    'path'         => $path,
    'query'        => $query,
    'referrer'     => $referrer,
    'title'        => $title,

    'screen'       => $screen,
    'lang'         => $lang,
    'tz'           => $tz,
    'fp'           => $fp,

    'ip'           => $ip,
    'ua'           => $ua,

    'country'      => $geo['country']      ?? null,
    'country_code' => $geo['country_code'] ?? null,
    'city'         => $geo['city']         ?? null,

    'asn'          => $geo['asn']          ?? null,
    'provider'     => $geo['provider']     ?? null,

    'js'           => $js,
];

/*
--------------------------------
Track
--------------------------------
*/
try {
    $service = new SessionService($pdo);
    $service->track($normalized);

    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    statist_log(
        'track_failed: ' . $e->getMessage() .
        ' file=' . $e->getFile() .
        ' line=' . $e->getLine()
    );

    http_response_code(500);
    echo json_encode(['error' => 'internal_error'], JSON_UNESCAPED_UNICODE);
}