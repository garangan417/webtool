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
            function setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                document.getElementById('themeBtn').innerText = theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
            }
            function toggleTheme() {
                const current = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                setTheme(current === 'dark' ? 'light' : 'dark');
            }
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            setTheme(savedTheme);
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
    }
}

if (isset($_GET['delete'])) {
    $target = realpath($_GET['delete']);
    if ($target && strpos($target, ROOT_DIR) === 0) {
        if (is_dir($target)) {
            rmdir($target);
        } else {
            unlink($target);
        }
        header("Location: index.php?dir=" . urlencode($dir));
        exit;
    }
}

$files = array_diff(scandir($dir), ['.', '..']);
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
                <th>Nama</th>
                <th>Ukuran</th>
                <th>Izin</th>
                <th class="text-right">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($dir !== ROOT_DIR): ?>
            <tr>
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
                    <?php endif; ?>
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

<script>
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        document.getElementById('themeBtn').innerText = theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
    }

    function toggleTheme() {
        const current = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        setTheme(current === 'dark' ? 'light' : 'dark');
    }

    // Inisialisasi tema saat halaman dimuat
    const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    setTheme(savedTheme);
</script>
</body>
</html>