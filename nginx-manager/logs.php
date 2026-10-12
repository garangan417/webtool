<?php
/**
 * Halaman log aktivitas panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/logger.php';

auth_require();

$user   = auth_user();
$config = require __DIR__ . '/config.php';

// Pagination sederhana
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$pdo   = panel_db();
$total = (int) $pdo->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();

$stmt = $pdo->prepare('
    SELECT id, username, action, target, detail, ip, created_at
    FROM activity_log
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log &mdash; <?= htmlspecialchars($config['app_name']) ?></title>
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
            <a href="service.php">Layanan</a>
            <a href="logs.php" class="active">Log</a>
        </nav>
        <div class="user-box">
            <strong><?= htmlspecialchars($user['username']) ?></strong>
            &middot;
            <a href="logout.php" class="text-decoration-none">Logout</a>
        </div>
    </div>
</header>

<main class="content">

    <h1 class="page-title">Log Aktivitas</h1>
    <p class="page-subtitle">Riwayat semua aksi yang dilakukan melalui panel ini.</p>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Riwayat</span>
            <span class="badge-soft-gray"><?= $total ?> entri</span>
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="icon">&#128220;</div>
                <p>Belum ada aktivitas tercatat.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-panel mb-0">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Waktu</th>
                            <th style="width: 120px;">User</th>
                            <th style="width: 140px;">Aksi</th>
                            <th>Target</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-muted">
                                    <?= htmlspecialchars(date('d M Y H:i:s', $log['created_at'])) ?>
                                </td>
                                <td><strong><?= htmlspecialchars($log['username']) ?></strong></td>
                                <td>
                                    <span class="badge-soft-blue"><?= htmlspecialchars($log['action']) ?></span>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars($log['target'] ?? '-') ?>
                                    <?php if ($log['detail']): ?>
                                        <div class="text-muted" style="font-size: 11.5px;">
                                            <?= htmlspecialchars($log['detail']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars($log['ip']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="card-body border-top">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/panel.js"></script>
</body>
</html>