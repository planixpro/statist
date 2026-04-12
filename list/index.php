<?php

// Проверка установки
$installLock = __DIR__ . '/../storage/installed.lock';

if (!file_exists($installLock)) {
    header('Location: ../install.php');
    exit;
}

// Подключаем auth (сессия + доступ)
require __DIR__ . '/auth.php';

// На всякий случай проверим, что helper доступен
if (!function_exists('admin_url')) {
    http_response_code(500);
    echo 'admin_url() not defined';
    exit;
}

// Редирект в дашборд
header('Location: ' . admin_url('dashboard'));
exit;