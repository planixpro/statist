<?php
/**
 * CSRF protection helpers.
 * Included via auth.php (called on every admin page).
 *
 * Usage in controllers:
 *   csrf_verify();          // aborts with 403 if token invalid
 *
 * Usage in views:
 *   <?= csrf_field() ?>     // renders hidden input
 */

if (!function_exists('csrf_token')) {

    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }

    function csrf_verify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            exit('403 Forbidden — invalid CSRF token');
        }
    }
}
