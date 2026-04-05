<?php
require_once __DIR__ . '/../inc/db.php';

const STATIST_REMEMBER_COOKIE = 'statist_remember';

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Удаляем remember-токен из БД
$rawToken = $_COOKIE[STATIST_REMEMBER_COOKIE] ?? '';
if ($rawToken !== '' && preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
    $tokenHash = hash('sha256', $rawToken);
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE token = ?");
    $stmt->execute([$tokenHash]);
}

// Чистим remember-cookie
setcookie(STATIST_REMEMBER_COOKIE, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'domain'   => '',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

unset($_COOKIE[STATIST_REMEMBER_COOKIE]);

// Чистим PHP-сессию
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => !empty($params['secure']),
        'httponly' => !empty($params['httponly']),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

header("Location: /list/");
exit;