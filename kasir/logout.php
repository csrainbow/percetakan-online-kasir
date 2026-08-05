<?php
require_once __DIR__ . '/config.php';
log_aktivitas('Logout', $_SESSION['username'] ?? '');
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
