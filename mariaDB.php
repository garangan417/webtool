<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$configFile = __DIR__ . '/config.json';
$config = [
    'host' => 'localhost',
    'port' => '3306',
    'user' => 'root',
    'pass' => '',
    'dbname' => ''
];

// Baca file konfigurasi jika ada
if (file_exists($configFile)) {
    $savedConfig = json_decode(file_get_contents($configFile), true);
    if (is_array($savedConfig)) {
        $config = array_merge($config, $savedConfig);
    }
}

// Ganti database aktif
if (isset($_GET['select_db'])) {
    $config['dbname'] = trim($_GET['select_db']);
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Simpan konfigurasi baru dari Form
$successMsg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $config['host'] = trim($_POST['host'] ?? 'localhost');
    $config['port'] = trim($_POST['port'] ?? '3306');
    $config['user'] = trim($_POST['user'] ?? 'root');
    $config['pass'] = $_POST['pass'] ?? '';
    $config['dbname'] = trim($_POST['dbname'] ?? '');

    if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
        $successMsg = 'Konfigurasi database berhasil disimpan!';
    } else {
        $error = 'Gagal menyimpan file config.json. Pastikan folder memiliki izin akses (write permission).';
    }
}

// Reset / Hapus Konfigurasi
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    if (file_exists($configFile)) {
        unlink($configFile);
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Koneksi PDO
$pdo = null;
if (!empty($config['host']) && !empty($config['user'])) {
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        if (!empty($config['dbname'])) {
            $dsn .= ";dbname={$config['dbname']}";
        }
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        $error = "Koneksi ke MariaDB Gagal: " . $e->getMessage();
    }
}

// FITUR PROSES HAPUS DATA (Satu baris atau Semua)
if ($pdo && !empty($config['dbname'])) {
    // 1. Hapus Satu Baris Data
    if (isset($_GET['action']) && $_GET['action'] === 'delete_row' && isset($_GET['table'], $_GET['key'], $_GET['val'])) {
        try {
            $table = "`" . str_replace("`", "``", $_GET['table']) . "`";
            $key = "`" . str_replace("`", "``", $_GET['key']) . "`";
            $val = $_GET['val'];

            $stmt = $pdo->prepare("DELETE FROM $table WHERE $key = :val LIMIT 1");
            $stmt->execute([':val' => $val]);
            
            $successMsg = "Berhasil menghapus 1 baris data di mana <b>" . htmlspecialchars($_GET['key']) . " = " . htmlspecialchars($val) . "</b>";
        } catch (PDOException $e) {
            $error = "Gagal menghapus data: " . $e->getMessage();
        }
    }

    // 2. Hapus SEMUA Data di Tabel (TRUNCATE)
    if (isset($_GET['action']) && $_GET['action'] === 'truncate_table' && isset($_GET['table'])) {
        try {
            $table = "`" . str_replace("`", "``", $_GET['table']) . "`";
            $pdo->exec("TRUNCATE TABLE $table");
            $successMsg = "Berhasil mengosongkan/menghapus <b>SEMUA</b> data dari tabel <code>" . htmlspecialchars($_GET['table']) . "</code>!";
        } catch (PDOException $e) {
            $error = "Gagal mengosongkan tabel: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MariaDB Manager Mini</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; color: #333; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3, h4 { color: #2c3e50; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #27ae60; color: white; padding: 6px 12px; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; display: inline-block; font-size: 13px; }
        .btn:hover { background: #219150; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .alert-error { padding: 12px; background: #f8d7da; color: #721c24; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .alert-success { padding: 12px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .nav { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background: #ecf0f1; padding: 10px 15px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background: #34495e; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        
        /* Grid Database Cards */
        .db-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-top: 10px; }
        .db-card { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; text-decoration: none; color: #1e293b; font-weight: bold; text-align: center; transition: 0.2s; }
        .db-card:hover { background: #3b82f6; color: #fff; border-color: #2563eb; }
        .db-card.active { background: #1e293b; color: #fff; border-color: #0f172a; }
    </style>
</head>
<body>

<div class="container">
    <h1>MariaDB Manager Mini</h1>

    <?php if ($successMsg): ?>
        <div class="alert-success"><?= $successMsg ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Form Pengaturan Koneksi -->
    <details <?= (!$pdo) ? 'open' : '' ?>>
        <summary style="cursor:pointer; font-weight:bold; font-size: 1.1em; margin-bottom: 15px;">
            ⚙️ Pengaturan Server <?= ($pdo) ? '<span style="color:green;">(Terhubung)</span>' : '<span style="color:red;">(Belum Terhubung)</span>' ?>
        </summary>
        <form method="POST" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <div class="form-group">
                <label>Host / IP Server:</label>
                <input type="text" name="host" value="<?= htmlspecialchars($config['host']) ?>" required>
            </div>
            <div class="form-group">
                <label>Port:</label>
                <input type="text" name="port" value="<?= htmlspecialchars($config['port']) ?>" required>
            </div>
            <div class="form-group">
                <label>Username Database:</label>
                <input type="text" name="user" value="<?= htmlspecialchars($config['user']) ?>" required>
            </div>
            <div class="form-group">
                <label>Password Database:</label>
                <input type="password" name="pass" value="<?= htmlspecialchars($config['pass']) ?>">
            </div>
            <button type="submit" name="save_config" class="btn">Simpan & Hubungkan</button>
            <a href="?action=reset" class="btn btn-danger" onclick="return confirm('Hapus pengaturan koneksi?')">Reset Config</a>
        </form>
    </details>

    <hr>

    <?php if ($pdo): ?>
        <!-- Info Status Koneksi -->
        <div class="nav">
            <div>
                <strong>Server:</strong> <code><?= htmlspecialchars($config['user']) ?>@<?= htmlspecialchars($config['host']) ?>:<?= htmlspecialchars($config['port']) ?></code>
                | <strong>DB Aktif:</strong> <code style="color:#2563eb; font-weight:bold;"><?= htmlspecialchars($config['dbname'] ?: 'Belum dipilih') ?></code>
            </div>
        </div>

        <!-- Menampilkan Daftar Semua Database -->
        <h3>📁 Pilih Database</h3>
        <?php
        try {
            $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
            if ($databases) {
                echo "<div class='db-grid'>";
                foreach ($databases as $db) {
                    $isActive = ($db === $config['dbname']) ? 'active' : '';
                    $label = htmlspecialchars($db);
                    if ($isActive) {
                        $label .= " (Aktif)";
                    }
                    echo "<a href='?select_db=" . urlencode($db) . "' class='db-card $isActive'>🗄️ $label</a>";
                }
                echo "</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='alert-error'>Gagal mengambil daftar database: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>

        <!-- Jika Database Sudah Dipilih -->
        <?php if (!empty($config['dbname'])): ?>
            <hr style="margin-top:25px;">
            
            <!-- Daftar Tabel -->
            <h3>📋 Tabel di Database: <u><?= htmlspecialchars($config['dbname']) ?></u></h3>
            <?php
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if ($tables) {
                    echo "<div style='display:flex; gap:10px; flex-wrap:wrap;'>";
                    foreach ($tables as $table) {
                        echo "<a href='?view_table=" . urlencode($table) . "' class='btn' style='background:#475569;'>📄 " . htmlspecialchars($table) . "</a>";
                    }
                    echo "</div>";
                } else {
                    echo "<p><em>Database ini belum memiliki tabel.</em></p>";
                }
            } catch (PDOException $e) {
                echo "<div class='alert-error'>Gagal mengambil daftar tabel: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>

            <!-- Preview Data Tabel & Opsi Hapus -->
            <?php if (isset($_GET['view_table'])): ?>
                <?php $currentTable = $_GET['view_table']; ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:25px;">
                    <h4>Data Tabel: <code><?= htmlspecialchars($currentTable) ?></code> (Limit 50)</h4>
                    
                    <!-- TOMBOL HAPUS SEMUA DATA -->
                    <a href="?view_table=<?= urlencode($currentTable) ?>&action=truncate_table&table=<?= urlencode($currentTable) ?>" 
                       class="btn btn-danger" 
                       onclick="return confirm('⚠️ PERINGATAN BERSYARAT:\nApakah Anda yakin ingin MENGHAPUS SEMUA DATA di tabel \'<?= htmlspecialchars($currentTable) ?>\'?\n\nData yang dihapus TIDAK DAPAT DIKEMBALIKAN!');">
                       🗑️ Hapus Semua Data (Empty Table)
                    </a>
                </div>

                <?php
                try {
                    $tableNameEsc = "`" . str_replace("`", "``", $currentTable) . "`";
                    
                    // Cari Primary Key dari Tabel
                    $pkQuery = $pdo->query("SHOW KEYS FROM $tableNameEsc WHERE Key_name = 'PRIMARY'");
                    $pkResult = $pkQuery->fetch();
                    $primaryKey = $pkResult['Column_name'] ?? null;

                    $rows = $pdo->query("SELECT * FROM $tableNameEsc LIMIT 50")->fetchAll();
                    
                    if ($rows) {
                        // Jika tidak ada Primary Key, ambil kolom pertama sebagai acuan hapus per baris
                        $firstCol = array_keys($rows[0])[0];
                        $targetKey = $primaryKey ?: $firstCol;

                        echo "<div style='overflow-x:auto;'><table><tr>";
                        echo "<th>Aksi</th>"; // Kolom Tombol Hapus
                        foreach (array_keys($rows[0]) as $col) {
                            echo "<th>" . htmlspecialchars($col) . "</th>";
                        }
                        echo "</tr>";
                        
                        foreach ($rows as $row) {
                            echo "<tr>";
                            // TOMBOL HAPUS SATU PER SATU
                            $valToDel = $row[$targetKey];
                            echo "<td style='text-align:center;'>
                                    <a href='?view_table=" . urlencode($currentTable) . "&action=delete_row&table=" . urlencode($currentTable) . "&key=" . urlencode($targetKey) . "&val=" . urlencode($valToDel) . "' 
                                       class='btn btn-danger btn-sm' 
                                       onclick=\"return confirm('Hapus baris data ini (" . htmlspecialchars($targetKey) . " = " . htmlspecialchars($valToDel) . ")?');\">
                                       🗑️ Hapus
                                    </a>
                                  </td>";

                            foreach ($row as $val) {
                                echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table></div>";
                    } else {
                        echo "<p><em>Tabel ini tidak memiliki data (kosong).</em></p>";
                    }
                } catch (PDOException $e) {
                    echo "<div class='alert-error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            <?php endif; ?>

            <hr style="margin-top:25px;">

            <!-- Form Console Query SQL -->
            <h3>💻 Console Query SQL</h3>
            <form method="POST">
                <textarea name="sql_query" rows="4" placeholder="SELECT * FROM nama_tabel..." required><?= htmlspecialchars($_POST['sql_query'] ?? '') ?></textarea>
                <button type="submit" name="run_sql" class="btn" style="margin-top:8px;">Eksekusi SQL</button>
            </form>

            <?php
            if (isset($_POST['run_sql']) && !empty($_POST['sql_query'])) {
                try {
                    $stmt = $pdo->query($_POST['sql_query']);
                    if ($stmt->columnCount() > 0) {
                        $results = $stmt->fetchAll();
                        echo "<h4>Hasil Query (" . count($results) . " baris):</h4>";
                        if ($results) {
                            echo "<div style='overflow-x:auto;'><table><tr>";
                            foreach (array_keys($results[0]) as $col) {
                                echo "<th>" . htmlspecialchars($col) . "</th>";
                            }
                            echo "</tr>";
                            foreach ($results as $row) {
                                echo "<tr>";
                                foreach ($row as $val) {
                                    echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</table></div>";
                        } else {
                            echo "<p>Query berhasil, data kosong.</p>";
                        }
                    } else {
                        echo "<div class='alert-success'>Query berhasil dieksekusi.</div>";
                    }
                } catch (PDOException $e) {
                    echo "<div class='alert-error'>SQL Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            ?>
        <?php else: ?>
            <p style="margin-top: 15px; color: #64748b;"><em>👈 Silakan klik salah satu database di atas untuk melihat isi tabel dan mengelola data.</em></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>