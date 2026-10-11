<?php
session_start();

// --- KONFIGURASI ---
define('PASSWORD', '1234'); // Ubah password ini!
define('ROOT_DIR', realpath('/var/www'));     // Batas folder root yang boleh diakses

// --- AUTENTIKASI ---
if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === PASSWORD) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "Password salah!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (empty($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login File Manager</title>
        <style>
            :root {
                --bg: #f4f6f8;
                --card-bg: #ffffff;
                --text: #333333;
                --border: #cccccc;
                --input-bg: #ffffff;
                --alert-bg: #f8d7da;
                --alert-text: #721c24;
                --primary: #0d6efd;
                --primary-hover: #0b5ed7;
            }

            [data-theme="dark"] {
                --bg: #121212;
                --card-bg: #1e1e1e;
                --text: #e0e0e0;
                --border: #333333;
                --input-bg: #2a2a2a;
                --alert-bg: #441c24;
                --alert-text: #f8d7da;
            }

            * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
            body { background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; transition: background 0.2s, color 0.2s; }
            .login-card { background: var(--card-bg); padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.3); width: 100%; max-width: 360px; border: 1px solid var(--border); }
            h4 { margin-bottom: 1.5rem; text-align: center; }
            .alert { background: var(--alert-bg); color: var(--alert-text); padding: 0.5rem 1rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.875rem; text-align: center; }
            input[type="password"] { width: 100%; padding: 0.6rem; background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 4px; margin-bottom: 1rem; outline: none; }
            input[type="password"]:focus { border-color: var(--primary); }
            button { width: 100%; padding: 0.6rem; background: var(--primary); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; }
            button:hover { background: var(--primary-hover); }
            .theme-toggle { position: absolute; top: 1rem; right: 1rem; background: none; border: 1px solid var(--border); color: var(--text); padding: 0.4rem 0.8rem; border-radius: 4px; cursor: pointer; width: auto; }
        </style>
    </head>
    <body>
        <button class="theme-toggle" onclick="toggleTheme()" id="themeBtn">🌙 Dark Mode</button>
        <div class="login-card">
            <h4>Login File Manager</h4>
            <?php if (!empty($error)): ?>
                <div class="alert"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="login_pass" placeholder="Password" required>
                <button type="submit">Masuk</button>
            </form>
        </div>
        <script>
            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                const btn = document.getElementById('themeBtn');
                if (btn) {
                    btn.innerText = theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
                }
            }
            function toggleTheme() {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                applyTheme(current === 'dark' ? 'light' : 'dark');
            }
            (function initTheme() {
                const savedTheme = localStorage.getItem('theme')
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                applyTheme(savedTheme);
            })();
        </script>
    </body>
    </html>
    <?php
    exit;
}

// --- LOGIK DIREKTORI & KEAMANAN ---
$dir = isset($_GET['dir']) ? $_GET['dir'] : ROOT_DIR;
$dir = realpath($dir);

if ($dir === false || strpos($dir, ROOT_DIR) !== 0) {
    $dir = ROOT_DIR;
}

// --- FUNGSI HELPER ---
function copyDirRecursive($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $items = array_diff(scandir($src), ['.', '..']);
    foreach ($items as $item) {
        $s = $src . DIRECTORY_SEPARATOR . $item;
        $d = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($s)) {
            copyDirRecursive($s, $d);
        } else {
            copy($s, $d);
        }
    }
}

function deleteDirRecursive($dirPath) {
    if (!is_dir($dirPath)) {
        return unlink($dirPath);
    }
    $items = array_diff(scandir($dirPath), ['.', '..']);
    foreach ($items as $item) {
        $full = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            deleteDirRecursive($full);
        } else {
            unlink($full);
        }
    }
    return rmdir($dirPath);
}

function addDirToZip($zip, $dirPath, $zipPath) {
    $zip->addEmptyDir($zipPath);
    $items = array_diff(scandir($dirPath), ['.', '..']);
    foreach ($items as $item) {
        $full = $dirPath . DIRECTORY_SEPARATOR . $item;
        $rel = $zipPath . '/' . $item;
        if (is_dir($full)) {
            addDirToZip($zip, $full, $rel);
        } else {
            $zip->addFile($full, $rel);
        }
    }
}

// --- AKSI (ACTION HANDLER) ---
$msg = '';

if (isset($_FILES['upload_file'])) {
    $target = $dir . DIRECTORY_SEPARATOR . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $target)) {
        $msg = "File berhasil diunggah!";
    } else {
        $msg = "Gagal mengunggah file.";
    }
}

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'new_folder' && !empty($_POST['name'])) {
        mkdir($dir . DIRECTORY_SEPARATOR . $_POST['name']);
    } elseif ($_POST['action'] === 'new_file' && !empty($_POST['name'])) {
        file_put_contents($dir . DIRECTORY_SEPARATOR . $_POST['name'], '');
    } elseif ($_POST['action'] === 'save_file' && isset($_POST['file_path'])) {
        $filePath = realpath($_POST['file_path']);
        if ($filePath && strpos($filePath, ROOT_DIR) === 0) {
            file_put_contents($filePath, $_POST['content']);
            $msg = "File berhasil disimpan!";
        }
    } elseif ($_POST['action'] === 'unzip' && isset($_POST['zip_file'])) {
        $zipPath = realpath($_POST['zip_file']);
        if ($zipPath && strpos($zipPath, ROOT_DIR) === 0 && is_file($zipPath)) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $extractTo = $dir . DIRECTORY_SEPARATOR . pathinfo($zipPath, PATHINFO_FILENAME);
                if (!is_dir($extractTo)) {
                    mkdir($extractTo, 0755, true);
                }
                $zip->extractTo($extractTo);
                $zip->close();
                $msg = "File ZIP berhasil diekstrak ke folder: " . htmlspecialchars(basename($extractTo));
            } else {
                $msg = "Gagal membuka file ZIP.";
            }
        } else {
            $msg = "File ZIP tidak valid.";
        }
    } elseif ($_POST['action'] === 'unzip_here' && isset($_POST['zip_file'])) {
        $zipPath = realpath($_POST['zip_file']);
        if ($zipPath && strpos($zipPath, ROOT_DIR) === 0 && is_file($zipPath)) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo($dir);
                $zip->close();
                $msg = "File ZIP berhasil diekstrak ke folder saat ini.";
            } else {
                $msg = "Gagal membuka file ZIP.";
            }
        } else {
            $msg = "File ZIP tidak valid.";
        }
    } elseif ($_POST['action'] === 'rename' && !empty($_POST['old_path']) && !empty($_POST['new_name'])) {
        $oldPath = realpath($_POST['old_path']);
        if ($oldPath && strpos($oldPath, ROOT_DIR) === 0) {
            $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . basename($_POST['new_name']);
            if (!file_exists($newPath)) {
                if (rename($oldPath, $newPath)) {
                    $msg = "Berhasil di-rename!";
                } else {
                    $msg = "Gagal rename.";
                }
            } else {
                $msg = "Nama sudah digunakan!";
            }
        }
    } elseif ($_POST['action'] === 'copy' && !empty($_POST['source']) && !empty($_POST['dest'])) {
        $src = realpath($_POST['source']);
        $destDir = realpath($_POST['dest']);
        if ($src && $destDir && strpos($src, ROOT_DIR) === 0 && strpos($destDir, ROOT_DIR) === 0) {
            $target = $destDir . DIRECTORY_SEPARATOR . basename($src);
            if (!file_exists($target)) {
                if (is_dir($src)) {
                    copyDirRecursive($src, $target);
                } else {
                    copy($src, $target);
                }
                $msg = "Berhasil dicopy!";
            } else {
                $msg = "Tujuan sudah ada file/folder dengan nama sama!";
            }
        } else {
            $msg = "Path tidak valid.";
        }
    } elseif ($_POST['action'] === 'move' && !empty($_POST['source']) && !empty($_POST['dest'])) {
        $src = realpath($_POST['source']);
        $destDir = realpath($_POST['dest']);
        if ($src && $destDir && strpos($src, ROOT_DIR) === 0 && strpos($destDir, ROOT_DIR) === 0) {
            $target = $destDir . DIRECTORY_SEPARATOR . basename($src);
            if (!file_exists($target)) {
                if (rename($src, $target)) {
                    $msg = "Berhasil dipindahkan!";
                } else {
                    $msg = "Gagal memindahkan.";
                }
            } else {
                $msg = "Tujuan sudah ada file/folder dengan nama sama!";
            }
        } else {
            $msg = "Path tidak valid.";
        }
    } elseif ($_POST['action'] === 'zip_create' && !empty($_POST['zip_name']) && !empty($_POST['zip_items'])) {
        $zipName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $_POST['zip_name']);
        if (substr(strtolower($zipName), -4) !== '.zip') {
            $zipName .= '.zip';
        }
        $zipPath = $dir . DIRECTORY_SEPARATOR . $zipName;
        $items = json_decode($_POST['zip_items'], true);
        if (is_array($items) && count($items) > 0) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($items as $item) {
                    $itemPath = realpath($item);
                    if ($itemPath && strpos($itemPath, ROOT_DIR) === 0) {
                        if (is_dir($itemPath)) {
                            addDirToZip($zip, $itemPath, basename($itemPath));
                        } else {
                            $zip->addFile($itemPath, basename($itemPath));
                        }
                    }
                }
                $zip->close();
                $msg = "File ZIP berhasil dibuat: " . htmlspecialchars($zipName);
            } else {
                $msg = "Gagal membuat file ZIP.";
            }
        } else {
            $msg = "Pilih minimal satu item untuk di-zip.";
        }
    } elseif ($_POST['action'] === 'bulk_delete' && !empty($_POST['items'])) {
        $items = json_decode($_POST['items'], true);
        $count = 0;
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                $itemPath = realpath($item);
                if ($itemPath && strpos($itemPath, ROOT_DIR) === 0 && $itemPath !== ROOT_DIR) {
                    if (is_dir($itemPath)) {
                        deleteDirRecursive($itemPath);
                    } else {
                        unlink($itemPath);
                    }
                    $count++;
                }
            }
            $msg = "$count item berhasil dihapus!";
        } else {
            $msg = "Tidak ada item yang dipilih.";
        }
    }
}

if (isset($_GET['delete'])) {
    $target = realpath($_GET['delete']);
    if ($target && strpos($target, ROOT_DIR) === 0) {
        if (is_dir($target)) {
            deleteDirRecursive($target);
        } else {
            unlink($target);
        }
        header("Location: index.php?dir=" . urlencode($dir));
        exit;
    }
}

$files = array_diff(scandir($dir), ['.', '..']);

// --- FUNGSI UNTUK DROPDOWN DESTINASI ---
function listDirs($base, $rel = '') {
    $dirs = [];
    $items = array_diff(scandir($base), ['.', '..']);
    foreach ($items as $item) {
        $full = $base . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            $relPath = $rel ? $rel . '/' . $item : $item;
            $dirs[$full] = $relPath;
            $dirs = array_merge($dirs, listDirs($full, $relPath));
        }
    }
    return $dirs;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Manager</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card-bg: #ffffff;
            --text: #333333;
            --muted: #6c757d;
            --border: #dddddd;
            --hover-bg: #f8f9fa;
            --input-bg: #ffffff;
            --alert-bg: #cff4fc;
            --alert-border: #b6effb;
            --alert-text: #055160;
            --primary: #0d6efd;
            --success: #198754;
            --danger: #dc3545;
        }

        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1e1e1e;
            --text: #e0e0e0;
            --muted: #a0a0a0;
            --border: #333333;
            --hover-bg: #2a2a2a;
            --input-bg: #2a2a2a;
            --alert-bg: #052c38;
            --alert-border: #084252;
            --alert-text: #6edff6;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); font-size: 0.9rem; padding: 1.5rem; transition: background 0.2s, color 0.2s; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .header-actions { display: flex; gap: 0.5rem; }
        .alert { background: var(--alert-bg); color: var(--alert-text); padding: 0.6rem 1rem; border-radius: 4px; margin-bottom: 1rem; border: 1px solid var(--alert-border); }
        .breadcrumb { background: var(--card-bg); padding: 0.6rem 1rem; border-radius: 4px; margin-bottom: 1rem; border: 1px solid var(--border); list-style: none; display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .breadcrumb li+li:before { content: "/"; padding-right: 0.5rem; color: var(--muted); }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .toolbar { background: var(--card-bg); padding: 1rem; border-radius: 4px; border: 1px solid var(--border); margin-bottom: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .form-group { display: flex; gap: 0.5rem; }
        input[type="text"], input[type="file"] { flex: 1; padding: 0.4rem 0.6rem; background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; outline: none; }
        .btn { padding: 0.4rem 0.8rem; border-radius: 4px; border: none; cursor: pointer; font-size: 0.85rem; text-align: center; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-secondary { background: var(--muted); color: #fff; }
        .btn-outline-danger { background: transparent; color: var(--danger); border: 1px solid var(--danger); }
        .btn-outline-primary { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
        .btn-outline-secondary { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn-outline-success { background: transparent; color: var(--success); border: 1px solid var(--success); }
        .btn:hover { opacity: 0.9; text-decoration: none; }
        .card { background: var(--card-bg); border-radius: 4px; border: 1px solid var(--border); padding: 1rem; margin-bottom: 1rem; }
        textarea { width: 100%; height: 300px; padding: 0.6rem; background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 4px; font-family: monospace; font-size: 0.9rem; margin-bottom: 0.5rem; outline: none; resize: vertical; }
        table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 4px; overflow: hidden; border: 1px solid var(--border); }
        th, td { padding: 0.6rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--hover-bg); font-weight: 600; color: var(--text); }
        tr:hover { background: var(--hover-bg); }
        .text-right { text-align: right; }
        .muted { color: var(--muted); font-size: 0.8rem; }
        .fw-bold { font-weight: bold; }
        select { width: 100%; padding: 0.5rem; background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 4px; }
        .modal-input { width: 100%; margin-bottom: 0.5rem; padding: 0.4rem 0.6rem; background: var(--input-bg); color: var(--text); border: 1px solid var(--border); border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>File Manager</h2>
        <div class="header-actions">
            <button class="btn btn-outline-secondary" onclick="toggleTheme()" id="themeBtn">🌙 Dark Mode</button>
            <a href="?logout=1" class="btn btn-outline-danger">Logout</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Breadcrumb Navigasi -->
    <ul class="breadcrumb">
        <li><a href="?dir=<?= urlencode(ROOT_DIR) ?>">Root</a></li>
        <?php
        $relative = ltrim(substr($dir, strlen(ROOT_DIR)), DIRECTORY_SEPARATOR);
        if ($relative) {
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            $pathAccum = ROOT_DIR;
            foreach ($parts as $part) {
                $pathAccum .= DIRECTORY_SEPARATOR . $part;
                echo '<li><a href="?dir=' . urlencode($pathAccum) . '">' . htmlspecialchars($part) . '</a></li>';
            }
        }
        ?>
    </ul>

    <!-- Toolbar Aksi -->
    <div class="toolbar">
        <form method="POST" enctype="multipart/form-data" class="form-group">
            <input type="file" name="upload_file" required>
            <button type="submit" class="btn btn-primary">Upload</button>
        </form>
        <form method="POST" class="form-group">
            <input type="hidden" name="action" value="new_folder">
            <input type="text" name="name" placeholder="Folder Baru" required>
            <button type="submit" class="btn btn-success">+ Folder</button>
        </form>
        <form method="POST" class="form-group">
            <input type="hidden" name="action" value="new_file">
            <input type="text" name="name" placeholder="File Baru" required>
            <button type="submit" class="btn btn-success">+ File</button>
        </form>
    </div>

    <!-- Panel Aksi Bulk (Zip & Delete) -->
    <div class="card" id="bulkPanel" style="display:none;">
        <h3 style="margin-bottom: 0.5rem; font-size: 1rem;">⚡ Aksi Massal</h3>
        <p class="muted" style="margin-bottom: 0.5rem;">Item terpilih: <span id="bulkCount">0</span></p>
        <div class="form-group" style="flex-wrap: wrap;">
            <input type="text" id="zipName" placeholder="Nama file zip (tanpa .zip)" style="flex: 1; min-width: 200px;">
            <button type="button" class="btn btn-success" onclick="submitZip()">📦 Buat ZIP</button>
            <button type="button" class="btn btn-danger" onclick="submitBulkDelete()">🗑️ Hapus Terpilih</button>
            <button type="button" class="btn btn-secondary" onclick="clearSelection()">Batal</button>
        </div>
        <form method="POST" id="zipForm" style="display:none;">
            <input type="hidden" name="action" value="zip_create">
            <input type="hidden" name="zip_name" id="zipNameInput">
            <input type="hidden" name="zip_items" id="zipItemsInput">
        </form>
        <form method="POST" id="bulkDeleteForm" style="display:none;">
            <input type="hidden" name="action" value="bulk_delete">
            <input type="hidden" name="items" id="bulkDeleteItems">
        </form>
    </div>

    <!-- Mode Edit File -->
    <?php if (isset($_GET['edit'])): 
        $editPath = realpath($_GET['edit']);
        if ($editPath && strpos($editPath, ROOT_DIR) === 0 && is_file($editPath)):
            $content = file_get_contents($editPath);
    ?>
        <div class="card">
            <h3 style="margin-bottom: 0.5rem;">Edit: <?= htmlspecialchars(basename($editPath)) ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_file">
                <input type="hidden" name="file_path" value="<?= htmlspecialchars($editPath) ?>">
                <textarea name="content"><?= htmlspecialchars($content) ?></textarea>
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="?dir=<?= urlencode($dir) ?>" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    <?php endif; endif; ?>

    <!-- Tabel Daftar File & Folder -->
    <table>
        <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                <th>Nama</th>
                <th>Ukuran</th>
                <th>Izin</th>
                <th class="text-right">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($dir !== ROOT_DIR): ?>
            <tr>
                <td></td>
                <td colspan="4">
                    <a href="?dir=<?= urlencode(dirname($dir)) ?>" class="fw-bold">📁 .. (Kembali)</a>
                </td>
            </tr>
            <?php endif; ?>

            <?php foreach ($files as $f): 
                $fullPath = $dir . DIRECTORY_SEPARATOR . $f;
                $isDir = is_dir($fullPath);
            ?>
            <tr>
                <td><input type="checkbox" class="zip-check" value="<?= htmlspecialchars($fullPath) ?>"></td>
                <td>
                    <?php if ($isDir): ?>
                        📁 <a href="?dir=<?= urlencode($fullPath) ?>" class="fw-bold"><?= htmlspecialchars($f) ?></a>
                    <?php else: ?>
                        📄 <?= htmlspecialchars($f) ?>
                    <?php endif; ?>
                </td>
                <td><?= $isDir ? '-' : number_format(filesize($fullPath)) . ' B' ?></td>
                <td><span class="muted"><?= substr(sprintf('%o', fileperms($fullPath)), -4) ?></span></td>
                <td class="text-right">
                    <?php if (!$isDir): ?>
                        <a href="?dir=<?= urlencode($dir) ?>&edit=<?= urlencode($fullPath) ?>" class="btn btn-outline-primary" style="padding: 0.2rem 0.5rem; font-size:0.75rem;">Edit</a>
                        <?php if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'zip'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="unzip">
                                <input type="hidden" name="zip_file" value="<?= htmlspecialchars($fullPath) ?>">
                                <button type="submit" class="btn btn-outline-success" style="padding: 0.2rem 0.5rem; font-size:0.75rem;" onclick="return confirm('Ekstrak ke folder baru?')">Unzip</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="unzip_here">
                                <input type="hidden" name="zip_file" value="<?= htmlspecialchars($fullPath) ?>">
                                <button type="submit" class="btn btn-outline-success" style="padding: 0.2rem 0.5rem; font-size:0.75rem;" onclick="return confirm('Ekstrak ke folder saat ini?')">Unzip Here</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary" style="padding: 0.2rem 0.5rem; font-size:0.75rem;" onclick="openRename('<?= htmlspecialchars(addslashes($fullPath), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($f), ENT_QUOTES) ?>')">Rename</button>
                    <button type="button" class="btn btn-outline-secondary" style="padding: 0.2rem 0.5rem; font-size:0.75rem;" onclick="openCopyMove('<?= htmlspecialchars(addslashes($fullPath), ENT_QUOTES) ?>', 'copy')">Copy</button>
                    <button type="button" class="btn btn-outline-secondary" style="padding: 0.2rem 0.5rem; font-size:0.75rem;" onclick="openCopyMove('<?= htmlspecialchars(addslashes($fullPath), ENT_QUOTES) ?>', 'move')">Move</button>
                    <a href="?dir=<?= urlencode($dir) ?>&delete=<?= urlencode($fullPath) ?>" 
                       class="btn btn-outline-danger" 
                       style="padding: 0.2rem 0.5rem; font-size:0.75rem;"
                       onclick="return confirm('Yakin ingin menghapus item ini?')">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Rename -->
<div id="renameModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="max-width:400px; width:90%; margin:0;">
        <h3 style="margin-bottom: 0.5rem; font-size: 1rem;">✏️ Rename</h3>
        <form method="POST">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="old_path" id="renameOldPath">
            <input type="text" name="new_name" id="renameNewName" required class="modal-input">
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('renameModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Copy/Move -->
<div id="copyMoveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="max-width:500px; width:90%; margin:0;">
        <h3 style="margin-bottom: 0.5rem; font-size: 1rem;" id="copyMoveTitle">📋 Copy</h3>
        <p class="muted" style="margin-bottom: 0.5rem;">Pilih folder tujuan:</p>
        <form method="POST">
            <input type="hidden" name="action" id="copyMoveAction" value="copy">
            <input type="hidden" name="source" id="copyMoveSource">
            <select name="dest" id="copyMoveDest" required style="margin-bottom: 0.5rem;">
                <option value="<?= htmlspecialchars(ROOT_DIR) ?>">/ (Root)</option>
                <?php foreach (listDirs(ROOT_DIR) as $path => $rel): ?>
                    <option value="<?= htmlspecialchars($path) ?>">/<?= htmlspecialchars($rel) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Lanjutkan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('copyMoveModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- THEME HANDLING ---
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        const btn = document.getElementById('themeBtn');
        if (btn) {
            btn.innerText = theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
        }
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    // Inisialisasi tema saat halaman dimuat
    (function initTheme() {
        const savedTheme = localStorage.getItem('theme')
            || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        applyTheme(savedTheme);
    })();

    // --- Rename ---
    function openRename(path, name) {
        document.getElementById('renameOldPath').value = path;
        document.getElementById('renameNewName').value = name;
        document.getElementById('renameModal').style.display = 'flex';
    }

    // --- Copy/Move ---
    function openCopyMove(path, action) {
        document.getElementById('copyMoveSource').value = path;
        document.getElementById('copyMoveAction').value = action;
        document.getElementById('copyMoveTitle').innerText = action === 'copy' ? '📋 Copy' : '📦 Move';
        document.getElementById('copyMoveModal').style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // --- BULK SELECTION (ZIP & DELETE) ---
    function updateBulkPanel() {
        const checks = document.querySelectorAll('.zip-check:checked');
        const panel = document.getElementById('bulkPanel');
        const count = document.getElementById('bulkCount');
        count.innerText = checks.length;
        panel.style.display = checks.length > 0 ? 'block' : 'none';

        // Update state select all checkbox
        const allChecks = document.querySelectorAll('.zip-check');
        const selectAllBox = document.getElementById('selectAll');
        if (selectAllBox) {
            selectAllBox.checked = allChecks.length > 0 && checks.length === allChecks.length;
            selectAllBox.indeterminate = checks.length > 0 && checks.length < allChecks.length;
        }
    }

    function submitZip() {
        const name = document.getElementById('zipName').value.trim();
        if (!name) { alert('Masukkan nama file zip!'); return; }
        const checks = document.querySelectorAll('.zip-check:checked');
        const items = Array.from(checks).map(c => c.value);
        document.getElementById('zipNameInput').value = name;
        document.getElementById('zipItemsInput').value = JSON.stringify(items);
        document.getElementById('zipForm').submit();
    }

    function submitBulkDelete() {
        const checks = document.querySelectorAll('.zip-check:checked');
        if (checks.length === 0) { alert('Tidak ada item yang dipilih!'); return; }
        if (!confirm('Yakin ingin menghapus ' + checks.length + ' item terpilih? Tindakan ini tidak bisa dibatalkan!')) return;
        const items = Array.from(checks).map(c => c.value);
        document.getElementById('bulkDeleteItems').value = JSON.stringify(items);
        document.getElementById('bulkDeleteForm').submit();
    }

    function clearSelection() {
        document.querySelectorAll('.zip-check').forEach(c => c.checked = false);
        document.getElementById('zipName').value = '';
        updateBulkPanel();
    }

    function toggleSelectAll(el) {
        document.querySelectorAll('.zip-check').forEach(c => c.checked = el.checked);
        updateBulkPanel();
    }

    // Attach event listener ke semua checkbox
    document.querySelectorAll('.zip-check').forEach(c => {
        c.addEventListener('change', updateBulkPanel);
    });

    // Klik di luar modal untuk menutup
    document.querySelectorAll('#renameModal, #copyMoveModal').forEach(m => {
        m.addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    });
</script>
</body>
</html>