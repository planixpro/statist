<?php
/**
 * auth.php — DB-backed auth gate. Include at top of every admin page.
 *
 * Session keys set after login:
 *   $_SESSION['user']          — username
 *   $_SESSION['role']          — admin | viewer | site_viewer
 *   $_SESSION['user_id']       — int
 *   $_SESSION['locale']        — e.g. 'en'
 *   $_SESSION['allowed_sites'] — int[] for site_viewer, null = all
 */

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/Lang.php';
require_once __DIR__ . '/../inc/flags.php';

const STATIST_REMEMBER_COOKIE = 'statist_remember';
const STATIST_REMEMBER_TTL    = 86400; // 24 часа

start_statist_session();

// ---- Locale resolution (login page, before auth) ----
$available = Lang::available();

if (isset($_GET['lang'])) {
    $urlLang = preg_replace('/[^a-z]/', '', strtolower($_GET['lang']));
    if (array_key_exists($urlLang, $available)) {
        setcookie('statist_lang', $urlLang, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['statist_lang'] = $urlLang;
    }
}

$guestLocale = 'en';
if (!empty($_SESSION['locale'])) {
    $guestLocale = $_SESSION['locale'];
} elseif (!empty($_COOKIE['statist_lang']) && array_key_exists($_COOKIE['statist_lang'], $available)) {
    $guestLocale = $_COOKIE['statist_lang'];
}

Lang::load($guestLocale);

// ---- Already authenticated ----
if (empty($_SESSION['user'])) {
    try_restore_login_from_remember_cookie($pdo, $guestLocale);
}

if (!empty($_SESSION['user'])) {
    return;
}

// ---- Handle login POST ----
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($login !== '' && $pass !== '') {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, locale FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);

            complete_login($pdo, $user, ($user['locale'] ?: null) ?? $guestLocale);

            if ($remember) {
                create_remember_session($pdo, (int)$user['id']);
            } else {
                clear_remember_cookie();
            }

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            header("Location: /list/");
            exit;
        }

        $error = __('auth.error');
    } else {
        $error = __('auth.error_empty');
    }
}

$currentLocale = Lang::locale();

// ---- Render login form ----
include __DIR__ . '/../views/auth.view.php';
exit;

/* =========================
   Helpers
========================= */

function start_statist_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

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

function client_ip(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return trim($_SERVER['REMOTE_ADDR']);
    }

    return '';
}

function current_user_agent(): string
{
    return trim($_SERVER['HTTP_USER_AGENT'] ?? '');
}

function complete_login(PDO $pdo, array $user, string $locale): void
{
    $_SESSION['user']    = $user['username'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['locale']  = $locale;

    if ($user['role'] === 'site_viewer') {
        $s = $pdo->prepare("SELECT site_id FROM user_sites WHERE user_id = ?");
        $s->execute([$user['id']]);
        $_SESSION['allowed_sites'] = array_map('intval', array_column($s->fetchAll(PDO::FETCH_ASSOC), 'site_id'));
    } else {
        $_SESSION['allowed_sites'] = null;
    }
}

function create_remember_session(PDO $pdo, int $userId): void
{
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $ip        = client_ip();
    $userAgent = mb_substr(current_user_agent(), 0, 255);
    $expiresAt = date('Y-m-d H:i:s', time() + STATIST_REMEMBER_TTL);

    $pdo->prepare("DELETE FROM user_sessions WHERE expires_at <= NOW()")->execute();

    $maxSessions = 5;
    $stmt = $pdo->prepare("
        DELETE FROM user_sessions
        WHERE user_id = ?
          AND id NOT IN (
              SELECT id FROM (
                  SELECT id FROM user_sessions
                  WHERE user_id = ?
                  ORDER BY created_at DESC
                  LIMIT $maxSessions
              ) t
          )
    ");
    $stmt->execute([$userId, $userId]);

    $pdo->prepare("
        INSERT INTO user_sessions (user_id, token, user_agent, ip, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([$userId, $tokenHash, $userAgent, $ip, $expiresAt]);

    setcookie(STATIST_REMEMBER_COOKIE, $rawToken, [
        'expires'  => time() + STATIST_REMEMBER_TTL,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[STATIST_REMEMBER_COOKIE] = $rawToken;
}

function clear_remember_cookie(): void
{
    setcookie(STATIST_REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_COOKIE[STATIST_REMEMBER_COOKIE]);
}

function try_restore_login_from_remember_cookie(PDO $pdo, string $guestLocale): void
{
    $rawToken = $_COOKIE[STATIST_REMEMBER_COOKIE] ?? '';
    if ($rawToken === '') {
        return;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        clear_remember_cookie();
        return;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare("
        SELECT
            us.id AS remember_id,
            us.user_id,
            us.user_agent AS remember_user_agent,
            us.expires_at,
            u.id,
            u.username,
            u.password_hash,
            u.role,
            u.locale
        FROM user_sessions us
        INNER JOIN users u ON u.id = us.user_id
        WHERE us.token = ?
          AND us.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        clear_remember_cookie();
        return;
    }

    $currentUa = mb_substr(current_user_agent(), 0, 255);
    $storedUa  = (string)($row['remember_user_agent'] ?? '');

    if ($storedUa !== '' && $currentUa !== '' && $storedUa !== $currentUa) {
        $pdo->prepare("DELETE FROM user_sessions WHERE id = ?")->execute([$row['remember_id']]);
        clear_remember_cookie();
        return;
    }

    session_regenerate_id(true);

    complete_login($pdo, $row, ($row['locale'] ?: null) ?? $guestLocale);

    $newExpiresAt = date('Y-m-d H:i:s', time() + STATIST_REMEMBER_TTL);
    $pdo->prepare("UPDATE user_sessions SET expires_at = ? WHERE id = ?")->execute([$newExpiresAt, $row['remember_id']]);

    setcookie(STATIST_REMEMBER_COOKIE, $rawToken, [
        'expires'  => time() + STATIST_REMEMBER_TTL,
        'path'     => '/',
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[STATIST_REMEMBER_COOKIE] = $rawToken;

    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$row['user_id']]);
}
