<?php
// Redirect to installer if not yet installed
if (!file_exists(__DIR__ . '/../storage/installed.lock')) {
    header('Location: /install.php');
    exit;
}

require 'auth.php';
header("Location: dashboard.php");
exit;
