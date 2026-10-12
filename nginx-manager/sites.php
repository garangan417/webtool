<?php
/**
 * Halaman manajemen situs (CRUD).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/nginx.php';

auth_require();

$user   = auth_user();
$config = require __DIR__ . '/config.php';

// =========================================================
// PROSES POST: tambah / edit / hapus
// =========================================================

$notice = null;   // ['type' => 'success'|'danger', 'message' => string]
$formOld = [];    // Isi form untuk repopulate saat error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string) ($_POST['action'] ?? '');

    // ---------- HAPUS ----------
    if ($action === 'delete') {
        $filename = basename((string) ($_POST['filename'] ?? ''));

        if ($filename === '' || !preg_match('/^[a-zA-Z0-9._-]+\.conf$/', $filename)) {
            $notice = ['type' => 'danger', 'message' => 'Nama file tidak valid.'];
        } else {
            $result = nginx_delete_vhost($filename);
            $notice = [
                'type'    => $result['ok'] ? 'success' : 'danger',
                'message' => $result['message'],
            ];
        }
    }

    // ---------- SIMPAN (tambah atau edit) ----------
    if ($action === 'save') {
        $isEdit    = ($_POST['mode'] ?? 'create') === 'edit';
        $oldFile   = basename((string) ($_POST['old_filename'] ?? ''));
        $domain    = trim((string) ($_POST['server_name'] ?? ''));
        $root      = trim((string) ($_POST['root'] ?? ''));
        $php       = trim((string) ($_POST['php'] ?? ''));

        $formOld = [
            'server_name' => $domain,
            'root'        => $root,
            'php'         => $php,
        ];

        // Validasi server-side
        [$valid, $errors] = nginx_validate_site_input([
            'server_name' => $domain,
            'root'        => $root,
            'php'         => $php,
        ]);

        // Cek duplikat domain (kecuali saat edit file yang sama)
        if ($valid) {
            foreach (nginx_list_managed_files() as $f) {
                if ($f['name'] === $oldFile) continue;
                $parsed = nginx_parse_vhost($f['path']);
                if ($parsed['server_name'] === $domain) {
                    $errors['server_name'] = 'Domain sudah dipakai situs lain.';
                    $valid = false;
                    break;
                }
            }
        }

        if (!$valid) {
            $notice = [
                'type'    => 'danger',
                'message' => 'Validasi gagal: ' . implode(' ', array_values($errors)),
            ];
        } else {
            // Nama file: domain + .conf
            $filename = $domain . '.conf';

            // Kalau edit dan domain berubah, hapus file lama dulu
            if ($isEdit && $oldFile !== '' && $oldFile !== $filename) {
                $delResult = nginx_delete_vhost($oldFile);
                if (!$delResult['ok']) {
                    $notice = [
                        'type'    => 'danger',
                        'message' => 'Gagal menghapus file lama: ' . $delResult['message'],
                    ];
                    goto render;
                }
            }

            $content = nginx_build_vhost([
                'server_name' => $domain,
                'root'        => $root,
                'php'         => $php,
            ]);

            $writeAction = $isEdit ? 'site.edit' : 'site.create';
            $result = nginx_write_vhost($filename, $content, $writeAction);

            $notice = [
                'type'    => $result['ok'] ? 'success' : 'danger',
                'message' => $result['message'],
            ];

            if ($result['ok']) {
                $formOld = [];  // reset form
            }
        }
    }
}

render:
// =========================================================
// LOAD DATA
// =========================================================

$files = nginx_list_managed_files();

$sites = [];
foreach ($files as $f) {
    $parsed = nginx_parse_vhost($f['path']);
    $sites[] = array_merge($f, $parsed);
}

// Cek apakah ada ?edit=xxx untuk auto-buka modal edit
$editTarget = null;
if (isset($_GET['edit'])) {
    $editName = basename((string) $_GET['edit']);
    foreach ($sites as $s) {
        if ($s['name'] === $editName) {
            $editTarget = $s;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Situs &mdash; <?= htmlspecialchars($config['app_name']) ?></title>
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
            <a href="sites.php" class="active">Situs</a>
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

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="page-title">Situs</h1>
            <p class="page-subtitle mb-0">
                Kelola vhost dari <code><?= htmlspecialchars($config['managed_dir']) ?></code>
            </p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSite" id="btnAddSite">
            <strong>+</strong> Tambah Situs
        </button>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-<?= $notice['type'] === 'success' ? 'success' : 'danger' ?> alert-auto-dismiss">
            <?= nl2br(htmlspecialchars($notice['message'])) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Daftar Situs</span>
            <span class="badge-soft-gray"><?= count($sites) ?> situs</span>
        </div>

        <?php if (empty($sites)): ?>
            <div class="empty-state">
                <div class="icon">&#128230;</div>
                <p>Belum ada situs yang dikelola.</p>
                <p class="small mt-2">Klik <strong>+ Tambah Situs</strong> untuk memulai.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-panel mb-0">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Root</th>
                            <th>PHP</th>
                            <th>Listen</th>
                            <th>SSL</th>
                            <th>Diubah</th>
                            <th style="width: 130px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['server_name']) ?></strong>
                                    <div class="text-muted small"><?= htmlspecialchars($s['name']) ?></div>
                                </td>
                                <td><code class="small"><?= htmlspecialchars($s['root']) ?></code></td>
                                <td class="small">
                                    <?= htmlspecialchars($s['php_socket']) ?>
                                </td>
                                <td>
                                    <?php foreach ($s['listen'] as $l): ?>
                                        <span class="badge-soft-gray me-1"><?= htmlspecialchars($l) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if ($s['ssl']): ?>
                                        <span class="badge-soft-green">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge-soft-gray">Tidak</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= htmlspecialchars(date('d M Y H:i', $s['mtime'])) ?>
                                </td>
                                <td>
                                    <button
                                        class="btn btn-sm btn-outline-primary btn-edit"
                                        data-site='<?= htmlspecialchars(json_encode([
                                            'name'        => $s['name'],
                                            'server_name' => $s['server_name'],
                                            'root'        => $s['root'],
                                            'php_socket'  => $s['php_socket'],
                                        ]), ENT_QUOTES) ?>'
                                    >Edit</button>

                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('Yakin hapus situs <?= htmlspecialchars($s['server_name']) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($s['name']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- =========================================================
     MODAL: Tambah / Edit Situs
     ========================================================= -->
<div class="modal fade" id="modalSite" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="formSite">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="mode" id="formMode" value="create">
                <input type="hidden" name="old_filename" id="formOldFilename" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Situs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label for="f_server_name" class="form-label fw-semibold small">Domain</label>
                        <input
                            type="text"
                            class="form-control"
                            id="f_server_name"
                            name="server_name"
                            placeholder="toko.example.com"
                            pattern="[a-z0-9.\-]+"
                            required
                        >
                        <div class="form-text small">Huruf kecil, angka, titik, dan tanda hubung. Contoh: <code>toko.mafaza.id</code></div>
                    </div>

                    <div class="mb-3">
                        <label for="f_root" class="form-label fw-semibold small">Document Root</label>
                        <input
                            type="text"
                            class="form-control"
                            id="f_root"
                            name="root"
                            placeholder="/var/www/html"
                            required
                        >
                        <div class="form-text small">
                            Harus di dalam:
                            <?php foreach ($config['allowed_roots'] as $r): ?>
                                <code><?= htmlspecialchars($r) ?></code>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="f_php" class="form-label fw-semibold small">PHP-FPM</label>
                        <select class="form-select" id="f_php" name="php" required>
                            <?php foreach ($config['php_fpm_sockets'] as $name => $sock): ?>
                                <option value="<?= htmlspecialchars($name) ?>">
                                    <?= htmlspecialchars($name) ?> (<?= htmlspecialchars($sock) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/panel.js"></script>
<script>
(function () {
    var modalEl   = document.getElementById('modalSite');
    var modal     = new bootstrap.Modal(modalEl);
    var form      = document.getElementById('formSite');
    var fMode     = document.getElementById('formMode');
    var fOldFile  = document.getElementById('formOldFilename');
    var fDomain   = document.getElementById('f_server_name');
    var fRoot     = document.getElementById('f_root');
    var fPhp      = document.getElementById('f_php');
    var titleEl   = document.getElementById('modalTitle');

    // Tambah
    document.getElementById('btnAddSite').addEventListener('click', function () {
        titleEl.textContent = 'Tambah Situs';
        fMode.value = 'create';
        fOldFile.value = '';
        form.reset();
    });

    // Edit
    document.querySelectorAll('.btn-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = JSON.parse(btn.getAttribute('data-site'));

            titleEl.textContent = 'Edit Situs';
            fMode.value = 'edit';
            fOldFile.value = data.name;
            fDomain.value = data.server_name;
            fRoot.value = data.root;

            // Cari opsi PHP yang cocok dengan socket yang ada di config
            var socket = data.php_socket;
            Array.from(fPhp.options).forEach(function (opt) {
                // opt.value = nama php (php8.3), opt.text = "php8.3 (unix:/...)"; cek kecocokan via text
                if (opt.text.indexOf(socket) !== -1) {
                    fPhp.value = opt.value;
                }
            });

            modal.show();
        });
    });

    // Auto-buka modal edit kalau ada ?edit=
    <?php if ($editTarget !== null): ?>
    titleEl.textContent = 'Edit Situs';
    fMode.value = 'edit';
    fOldFile.value = <?= json_encode($editTarget['name']) ?>;
    fDomain.value = <?= json_encode($editTarget['server_name']) ?>;
    fRoot.value = <?= json_encode($editTarget['root']) ?>;

    (function () {
        var socket = <?= json_encode($editTarget['php_socket']) ?>;
        Array.from(fPhp.options).forEach(function (opt) {
            if (opt.text.indexOf(socket) !== -1) {
                fPhp.value = opt.value;
            }
        });
    })();

    modal.show();
    <?php endif; ?>
})();
</script>
</body>
</html>