<?php

require_once __DIR__ . '/../inc/db.php';

const STATIST_REMEMBER_COOKIE = 'statist_remember';

// --------------------------------------------------
// Определение HTTPS (с учетом прокси / Cloudflare)
// --------------------------------------------------
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
        $data = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
        if (!empty($data['scheme']) && $data['scheme'] === 'https') {
            return true;
        }
    }

    return false;
}

// --------------------------------------------------
// Старт сессии
// --------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------------------------
// Удаляем remember-токен из БД
// --------------------------------------------------
$rawToken = trim($_COOKIE[STATIST_REMEMBER_COOKIE] ?? '');

if ($rawToken !== '' && preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE token = ?");
    $stmt->execute([$tokenHash]);
}

// --------------------------------------------------
// Чистим remember-cookie
// --------------------------------------------------
$cookieParams = session_get_cookie_params();

setcookie(STATIST_REMEMBER_COOKIE, '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

unset($_COOKIE[STATIST_REMEMBER_COOKIE]);

// --------------------------------------------------
// Чистим PHP-сессию
// --------------------------------------------------
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $cookieParams['path'] ?? '/',
        'domain'   => $cookieParams['domain'] ?? '',
        'secure'   => $cookieParams['secure'] ?? false,
        'httponly' => $cookieParams['httponly'] ?? true,
        'samesite' => $cookieParams['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

// --------------------------------------------------
// Редирект на логин
// --------------------------------------------------
header("Location: /list/");
exit;