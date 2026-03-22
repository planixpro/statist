<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ── Redirect to installer if not configured ──────────────────
// Triggers when: db.php missing, lock missing, or DB unreachable
$dbFile   = __DIR__ . '/inc/db.php';
$lockFile = __DIR__ . '/storage/installed.lock';

$needsInstall = false;

if (!file_exists($dbFile) || !file_exists($lockFile)) {
    $needsInstall = true;
} else {
    // Config exists — try connecting to catch corrupted/empty DB
    try {
        require_once $dbFile;
        // Check that core tables exist
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if (!$tableCheck) {
            $needsInstall = true;
        }
    } catch (Throwable $e) {
        $needsInstall = true;
    }
}

if ($needsInstall) {
    header('Location: /install.php');
    exit;
}

// Already required above if install not needed
if (!isset($pdo)) {
    require_once $dbFile;
}
require_once __DIR__ . '/app/BotDetector.php';
require_once __DIR__ . '/app/GeoService.php';
require_once __DIR__ . '/app/SessionService.php';

/*
--------------------------------
GET — показываем заглушку
--------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    header("Content-Type: text/html; charset=utf-8");
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="robots" content="noindex,nofollow"><title>Statist</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f5f5f7;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#8e8e93}p{font-size:13px;letter-spacing:0.04em}</style></head><body><p>nothing here</p></body></html>';
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

/*
--------------------------------
Получаем payload
--------------------------------
*/
$data = [];

$raw = file_get_contents("php://input");
if ($raw) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $data = $json;
    }
}

if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!isset($data['js'])) {
    $data['js'] = 1;
}

/*
--------------------------------
IP / UA
--------------------------------
*/
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
$ip = trim(explode(',', $ip)[0]);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

/*
--------------------------------
Bot check
--------------------------------
*/
if (BotDetector::isBot($ua)) {
    echo json_encode(["status" => "ignored"]);
    exit;
}

/*
--------------------------------
Geo
--------------------------------
*/
$geo = GeoService::lookup($ip);

/*
--------------------------------
Normalize
--------------------------------
*/
$normalized = [
    'site'         => $data['h']     ?? null,
    'session_id'   => $data['sid']   ?? null,
    'event'        => $data['ev']    ?? 'page_view',
    'path'         => $data['p']     ?? null,
    'query'        => $data['query'] ?? null,
    'referrer'     => $data['r']     ?? null,
    'screen'       => $data['s']     ?? null,
    'lang'         => $data['l']     ?? null,
    'tz'           => $data['tz']    ?? null,
    'ip'           => $ip,
    'ua'           => $ua,
    'country'      => $geo['country']      ?? null,
    'country_code' => $geo['country_code'] ?? null,
    'city'         => $geo['city']         ?? null,
];

/*
--------------------------------
Track
--------------------------------
*/
try {
    $service = new SessionService($pdo);
    $service->track($normalized);
    echo json_encode(["status" => "ok"]);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
