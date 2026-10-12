<?php
/**
 * Halaman login panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';

// Kalau sudah login, langsung ke index
if (auth_check()) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        [$ok, $msg] = auth_login($username, $password);

        if ($ok) {
            header('Location: index.php');
            exit;
        }

        $error = $msg;
    }
}

$config = require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &mdash; <?= htmlspecialchars($config['app_name']) ?></title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/panel.css">
</head>
<body class="login-page">

<div class="login-box">
    <div class="brand">
        <h1>&#9881;&#65039; <?= htmlspecialchars($config['app_name']) ?></h1>
        <p>Silakan login untuk melanjutkan</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 px-3 small" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="username" class="form-label fw-semibold small">Username</label>
            <input
                type="text"
                class="form-control"
                id="username"
                name="username"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                required
                autofocus
            >
        </div>

        <div class="mb-4">
            <label for="password" class="form-label fw-semibold small">Password</label>
            <input
                type="password"
                class="form-control"
                id="password"
                name="password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/panel.js"></script>
</body>
</html>