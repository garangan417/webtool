<?php
/**
 * Dashboard panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/nginx.php';

auth_require();

$user   = auth_user();
$config = require __DIR__ . '/config.php';

$status  = nginx_status();
$test    = nginx_test();
$version = nginx_version();
$sites   = nginx_list_managed_files();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard &mdash; <?= htmlspecialchars($config['app_name']) ?></title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/panel.css">
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="brand">
            &#9881;&#65039; Nginx <span>Manager</span>
        </a>
        <nav>
            <a href="index.php" class="active">Dashboard</a>
            <a href="sites.php">Situs</a>
            <a href="service.php">Layanan</a>
            <a href="logs.php">Log</a>
        </nav>
        <div class="user-box">
            <strong><?= htmlspecialchars($user['username']) ?></strong>
            &middot;
            <a href="logout.php" class="text-decoration-none">Logout</a>
        </div>
    </div>
</header>

<main class="content">

    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Ringkasan status layanan Nginx dan situs yang dikelola.</p>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon <?= $status['active'] ? 'green' : 'red' ?>">
                    <?= $status['active'] ? '&#9989;' : '&#10060;' ?>
                </div>
                <div>
                    <div class="label">Nginx</div>
                    <div class="value"><?= htmlspecialchars(ucfirst($status['state'])) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon blue">&#128230;</div>
                <div>
                    <div class="label">Situs Dikelola</div>
                    <div class="value"><?= count($sites) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon <?= $test['ok'] ? 'green' : 'red' ?>">
                    &#128220;
                </div>
                <div>
                    <div class="label">Config Test</div>
                    <div class="value"><?= $test['ok'] ? 'Valid' : 'Error' ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon amber">&#128295;</div>
                <div>
                    <div class="label">Versi</div>
                    <div class="value"><?= htmlspecialchars($version) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$status['active'] || !$test['ok']): ?>
        <div class="alert alert-danger">
            <strong>&#9888;&#65039; Perhatian:</strong>
            <?php if (!$status['active']): ?>
                Layanan Nginx sedang tidak aktif.
            <?php endif; ?>
            <?php if (!$test['ok']): ?>
                Konfigurasi Nginx mengandung error.
            <?php endif; ?>
            Lihat halaman <a href="service.php" class="alert-link">Layanan</a> untuk detail.
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            &#9989; Semua layanan berjalan normal.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Situs Terbaru</span>
            <a href="sites.php" class="text-decoration-none small">Lihat semua &rarr;</a>
        </div>
        <?php if (empty($sites)): ?>
            <div class="empty-state">
                <div class="icon">&#128230;</div>
                <p>Belum ada situs yang dikelola.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-panel mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Diubah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($sites, 0, 5) as $s): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($s['name']) ?></code></td>
                                <td class="small text-muted">
                                    <?= htmlspecialchars(date('d M Y H:i', $s['mtime'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/panel.js"></script>
</body>
</html>