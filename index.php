<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/app/BotDetector.php';
require_once __DIR__ . '/app/GeoService.php';
require_once __DIR__ . '/app/SessionService.php';

/*
--------------------------------
GET — заглушка
--------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta name="robots" content="noindex,nofollow"></head><body>ok</body></html>';
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
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

/*
--------------------------------
IP / UA  (до всего остального —
нужны для rate limit и bot check)
--------------------------------
*/
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

// Берём только первый IP из цепочки прокси
$ip = trim(explode(',', $ip)[0]);

// Базовая валидация IP (IPv4 / IPv6)
if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
    $ip = '';
}

$ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

/*
--------------------------------
HARD FILTER — явные боты по UA
(до чтения тела запроса, дёшево)
--------------------------------
*/
if (BotDetector::isBot($ua)) {
    echo json_encode(['status' => 'ignored_bot']);
    exit;
}

/*
--------------------------------
Rate limit — не более 30 запросов
в минуту с одного IP (через APCu
если доступен, иначе пропускаем)
--------------------------------
*/
if ($ip !== '' && function_exists('apcu_fetch')) {
    $rlKey   = 'statist_rl_' . md5($ip);
    $rlCount = (int)apcu_fetch($rlKey);
    if ($rlCount === 0) {
        apcu_store($rlKey, 1, 60);
    } elseif ($rlCount >= 30) {
        http_response_code(429);
        echo json_encode(['status' => 'rate_limited']);
        exit;
    } else {
        apcu_inc($rlKey);
    }
}

/*
--------------------------------
Payload — читаем тело
--------------------------------
*/
$data = [];

// Ограничиваем размер тела: 16 KB достаточно для любого легитимного запроса
$raw = stream_get_contents(fopen('php://input', 'r'), 16384);

if ($raw !== false && $raw !== '') {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $data = $json;
    }
}

// Фолбэк на $_POST (на случай form-encoded)
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'ignored_empty']);
    exit;
}

/*
--------------------------------
Вспомогательная функция:
безопасное извлечение строки
--------------------------------
*/
function strField(array $data, string $key, int $maxLen = 255, string $default = ''): string
{
    $val = $data[$key] ?? $default;
    if (is_array($val)) {
        return $default;
    }
    return mb_substr(trim((string)$val), 0, $maxLen);
}

/*
--------------------------------
Минимальная валидация обязательных полей
--------------------------------
*/
$site = strField($data, 'h', 253);   // домен — макс 253 символа
$sid  = strField($data, 'sid', 64);  // session_id — varchar(64)

// Только допустимые символы в session_id (UUID-like)
if (!preg_match('~^[a-zA-Z0-9\-_]{8,64}$~', $sid)) {
    echo json_encode(['status' => 'ignored_invalid']);
    exit;
}

// Домен: только разумные символы
if ($site === '' || !preg_match('~^[a-zA-Z0-9.\-]{3,253}$~', $site)) {
    echo json_encode(['status' => 'ignored_invalid']);
    exit;
}

/*
--------------------------------
JS flag
--------------------------------
*/
$js = isset($data['js']) ? (int)$data['js'] : 0;

/*
--------------------------------
Извлечение остальных полей
--------------------------------
*/
$path     = strField($data, 'p',     512, '/');
$referrer = strField($data, 'r',     512, '');
$query    = strField($data, 'query', 512, '');
$event    = strField($data, 'ev',    50,  'page_view');

// Допустимые события
$allowedEvents = ['page_view', 'heartbeat', 'click', 'session_end'];
if (!in_array($event, $allowedEvents, true)) {
    $event = 'page_view';
}

// path должен начинаться с /
if ($path === '' || $path[0] !== '/') {
    $path = '/';
}

/*
--------------------------------
PRE-FILTER (без Geo — быстро)
--------------------------------
*/
$prefilterCtx = [
    'ua'       => $ua,
    'path'     => $path,
    'referrer' => $referrer,
    'js'       => $js,
];

if (BotDetector::shouldBlockRealtime($prefilterCtx)) {
    echo json_encode(['status' => 'ignored_suspicious']);
    exit;
}

/*
--------------------------------
Geo lookup (дорогая операция —
только для прошедших pre-filter)
--------------------------------
*/
$geo = GeoService::lookup($ip);

/*
--------------------------------
Нормализация финального payload
--------------------------------
*/
$normalized = [
    'site'         => $site,
    'session_id'   => $sid,
    'event'        => $event,

    'path'         => $path,
    'query'        => $query,
    'referrer'     => $referrer,

    'screen'       => strField($data, 's',  20),
    'lang'         => strField($data, 'l',  10),
    'tz'           => strField($data, 'tz', 64),
    'fp'           => strField($data, 'fp', 200),

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
    if (!isset($pdo)) {
        throw new RuntimeException('PDO not initialized');
    }

    $service = new SessionService($pdo);
    $service->track($normalized);

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    error_log('[statist] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'internal_error']);
}
