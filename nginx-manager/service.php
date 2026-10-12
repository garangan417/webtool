<?php
/**
 * Halaman status layanan Nginx (read-only).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/nginx.php';

auth_require();

$user   = auth_user();
$config = require __DIR__ . '/config.php';

$version = nginx_version();
$status  = nginx_status();
$test    = nginx_test();
$uptime  = nginx_uptime();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Layanan &mdash; <?= htmlspecialchars($config['app_name']) ?></title>
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
            <a href="index.php">Dashboard</a>
            <a href="sites.php">Situs</a>
            <a href="service.php" class="active">Layanan</a>
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

    <h1 class="page-title">Layanan Nginx</h1>
    <p class="page-subtitle">Status dan kesehatan layanan Nginx di server ini.</p>

    <!-- Status Utama -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon <?= $status['active'] ? 'green' : 'red' ?>">
                    <?= $status['active'] ? '&#9989;' : '&#10060;' ?>
                </div>
                <div>
                    <div class="label">Status</div>
                    <div class="value">
                        <?= htmlspecialchars(ucfirst($status['state'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon blue">&#128295;</div>
                <div>
                    <div class="label">Versi Nginx</div>
                    <div class="value"><?= htmlspecialchars($version) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon amber">&#9200;</div>
                <div>
                    <div class="label">Uptime</div>
                    <div class="value"><?= htmlspecialchars($uptime) ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="stat-card">
                <div class="icon <?= $test['ok'] ? 'green' : 'red' ?>">
                    <?= $test['ok'] ? '&#128220;' : '&#9888;&#65039;' ?>
                </div>
                <div>
                    <div class="label">Config Test</div>
                    <div class="value">
                        <?= $test['ok'] ? 'OK' : 'ERROR' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hasil nginx -t -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Hasil <code>nginx -t</code></span>
            <span class="badge-soft-<?= $test['ok'] ? 'green' : 'red' ?>">
                <?= $test['ok'] ? 'Valid' : 'Invalid' ?>
            </span>
        </div>
        <div class="card-body">
            <pre class="mb-0" style="white-space: pre-wrap; font-size: 12.5px; background: #f9fafb; padding: 14px; border-radius: 8px; color: <?= $test['ok'] ? '#166534' : '#991b1b' ?>;"><?= htmlspecialchars($test['output']) ?></pre>
        </div>
    </div>

    <!-- Info Konfigurasi -->
    <div class="card">
        <div class="card-header">Informasi Server</div>
        <div class="card-body p-0">
            <table class="table table-panel mb-0">
                <tbody>
                    <tr>
                        <td style="width: 260px; color: #6b7280;">Binary Nginx</td>
                        <td><code><?= htmlspecialchars($config['nginx_bin']) ?></code></td>
                    </tr>
                    <tr>
                        <td style="color: #6b7280;">Direktori Managed</td>
                        <td><code><?= htmlspecialchars($config['managed_dir']) ?></code></td>
                    </tr>
                    <tr>
                        <td style="color: #6b7280;">Jumlah File Managed</td>
                        <td><?= count(nginx_list_managed_files()) ?> file</td>
                    </tr>
                    <tr>
                        <td style="color: #6b7280;">PHP-FPM Tersedia</td>
                        <td>
                            <?php foreach ($config['php_fpm_sockets'] as $name => $sock): ?>
                                <span class="badge-soft-blue me-1"><?= htmlspecialchars($name) ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/panel.js"></script>
</body>
</html>