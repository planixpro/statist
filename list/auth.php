<?php
/**
 * auth.php — DB-backed auth gate
 */

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/Lang.php';
require_once __DIR__ . '/../inc/flags.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/csrf.php';

const STATIST_REMEMBER_COOKIE = 'statist_remember';
const STATIST_REMEMBER_TTL    = 2592000; // 30 days

start_statist_session();

/* =========================
   Locale
========================= */

$available = Lang::available();

if (isset($_GET['lang'])) {
    $urlLang = preg_replace('/[^a-z]/', '', strtolower($_GET['lang']));
    if (array_key_exists($urlLang, $available)) {
        setcookie('statist_lang', $urlLang, [
            'expires'  => time() + 86400 * 365,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['statist_lang'] = $urlLang;
    }
}

$guestLocale = $_SESSION['locale']
    ?? ($_COOKIE['statist_lang'] ?? 'en');

if (!isset($available[$guestLocale])) {
    $guestLocale = 'en';
}

Lang::load($guestLocale);

/* =========================
   Restore login
========================= */

if (empty($_SESSION['user'])) {
    try_restore_login_from_remember_cookie($pdo, $guestLocale);
}

if (!empty($_SESSION['user'])) {
    return;
}

/* =========================
   Login POST
========================= */

$error = null;
$loginValue = (string)($_SESSION['login_form_login'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $login = mb_substr(trim($_POST['login'] ?? ''), 0, 64);
    $pass  = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    $loginValue = $login;
    $_SESSION['login_form_login'] = $loginValue;

    // ---- Rate limit ----
    if (too_many_attempts($pdo, $login, client_ip())) {
        usleep(random_int(200000, 500000));
        $error = __('auth.error');
    }

    if (!$error && $login !== '' && $pass !== '') {

        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, role, locale
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pass, $user['password_hash'])) {

            clear_login_attempts($pdo, $login, client_ip());

            session_regenerate_id(true);

            complete_login($pdo, $user, $user['locale'] ?: $guestLocale);

            if ($remember) {
                create_remember_session($pdo, (int)$user['id']);
            } else {
                clear_remember_cookie();
            }

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                ->execute([$user['id']]);

            unset($_SESSION['login_form_login']);

            header('Location: ' . admin_url('dashboard'));
            exit;
        }

        register_login_attempt($pdo, $login, client_ip());
        usleep(random_int(100000, 300000));

        $error = __('auth.error');
    } else {
        if (!$error) {
            $error = __('auth.error_empty');
        }
    }
}

$currentLocale = Lang::locale();
$loginValue = mb_substr($loginValue, 0, 64);

/* =========================
   View
========================= */

include __DIR__ . '/../views/auth.view.php';
exit;

/* =========================
   Helpers
========================= */

function start_statist_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function is_https(): bool
{
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
         strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    );
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

function current_user_agent(): string
{
    return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}

function fingerprint(): string
{
    return hash('sha256', current_user_agent() . '|' . substr(client_ip(), 0, 7));
}

/* =========================
   Login logic
========================= */

function complete_login(PDO $pdo, array $user, string $locale): void
{
    $_SESSION['user']    = $user['username'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['locale']  = $locale;

    if ($user['role'] === 'site_viewer') {
        $s = $pdo->prepare("SELECT site_id FROM user_sites WHERE user_id = ?");
        $s->execute([$user['id']]);
        $_SESSION['allowed_sites'] = array_map(
            'intval',
            array_column($s->fetchAll(PDO::FETCH_ASSOC), 'site_id')
        );
    } else {
        $_SESSION['allowed_sites'] = null;
    }
}

/* =========================
   Remember me
========================= */

function create_remember_session(PDO $pdo, int $userId): void
{
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $expiresAt = date('Y-m-d H:i:s', time() + STATIST_REMEMBER_TTL);

    // cleanup (limit to avoid heavy queries)
    $pdo->exec("DELETE FROM user_sessions WHERE expires_at <= NOW() LIMIT 100");

    // limit sessions per user
    $pdo->prepare("
        DELETE FROM user_sessions
        WHERE user_id = ?
          AND id NOT IN (
              SELECT id FROM (
                  SELECT id FROM user_sessions
                  WHERE user_id = ?
                  ORDER BY created_at DESC
                  LIMIT 5
              ) t
          )
    ")->execute([$userId, $userId]);

    $pdo->prepare("
        INSERT INTO user_sessions
        (user_id, token, user_agent, ip, fingerprint, expires_at, created_at, last_used_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ")->execute([
        $userId,
        $tokenHash,
        current_user_agent(),
        client_ip(),
        fingerprint(),
        $expiresAt
    ]);

    setcookie(STATIST_REMEMBER_COOKIE, $rawToken, [
        'expires'  => time() + STATIST_REMEMBER_TTL,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    setcookie(STATIST_REMEMBER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* =========================
   Restore
========================= */

function try_restore_login_from_remember_cookie(PDO $pdo, string $guestLocale): void
{
    $rawToken = $_COOKIE[STATIST_REMEMBER_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        clear_remember_cookie();
        return;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare("
        SELECT us.*, u.username, u.role, u.locale
        FROM user_sessions us
        JOIN users u ON u.id = us.user_id
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

    // мягкая проверка fingerprint
    if ($row['fingerprint'] && $row['fingerprint'] !== fingerprint()) {
        // не убиваем сразу, но можно логировать
    }

    session_regenerate_id(true);

    complete_login($pdo, $row, $row['locale'] ?: $guestLocale);

    $pdo->prepare("
        UPDATE user_sessions
        SET expires_at = ?, last_used_at = NOW()
        WHERE id = ?
    ")->execute([
        date('Y-m-d H:i:s', time() + STATIST_REMEMBER_TTL),
        $row['id']
    ]);
}

/* =========================
   Rate limit
========================= */

function too_many_attempts(PDO $pdo, string $login, string $ip): bool
{
    $stmt = $pdo->prepare("
        SELECT attempts
        FROM login_attempts
        WHERE login = ? AND ip = ? AND last_attempt > NOW() - INTERVAL 15 MINUTE
        LIMIT 1
    ");
    $stmt->execute([$login, $ip]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row && $row['attempts'] >= 5;
}

function register_login_attempt(PDO $pdo, string $login, string $ip): void
{
    $pdo->prepare("
        INSERT INTO login_attempts (login, ip, attempts, last_attempt)
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            last_attempt = NOW()
    ")->execute([$login, $ip]);
}

function clear_login_attempts(PDO $pdo, string $login, string $ip): void
{
    $pdo->prepare("
        DELETE FROM login_attempts WHERE login = ? AND ip = ?
    ")->execute([$login, $ip]);
}