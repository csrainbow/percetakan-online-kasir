<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = DB::one('SELECT * FROM users WHERE username = ?', [$username]);
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?: 'kasir';
        $_SESSION['scope_user_id'] = ($user['role'] === 'superadmin') ? 0 : (int)$user['id'];
        log_aktivitas('Login', $username);
        header('Location: index.php');
        exit;
    }
    $err = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - KASIR RAINBOW PRINTING</title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body class="login-body">
<div class="login-box">
    <img src="assets/logo-login.png" alt="Logo Rainbow Printing" class="login-logo">
    <h1>KASIR RAINBOW PRINTING</h1>
    <p class="muted">Sistem Kasir Percetakan</p>
    <?php if ($err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Username
            <input type="text" name="username" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="btn besar" type="submit">Masuk</button>
    </form>
</div>
</body>
</html>
