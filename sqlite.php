
<?php

require_once('/var/www/html/config.php');
require_once BASE_PATH . 'auth.php';

// Pasang pengaman di sini
// check_login();

/*
|--------------------------------------------------------------------------
| KONFIGURASI
|--------------------------------------------------------------------------
*/

// Root filesystem yang boleh dibrowse.
// Demi keamanan JANGAN gunakan "/" kecuali memang benar-benar diperlukan.
$browseRoot = '/var/www';

$extensions = ['db', 'sqlite', 'sqlite3', 'db3'];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

/**
 * Validasi path agar hanya boleh berada di dalam $root.
 *
 * realpath() juga membantu mencegah traversal dan symlink
 * yang mengarah keluar dari root.
 */
function safePath(string $path, string $root): ?string
{
    $realRoot = realpath($root);

    if ($realRoot === false) {
        return null;
    }

    $realPath = realpath($path);

    if ($realPath === false) {
        return null;
    }

    if ($realPath === $realRoot) {
        return $realPath;
    }

    $prefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (str_starts_with($realPath, $prefix)) {
        return $realPath;
    }

    return null;
}


/**
 * Quote identifier SQLite.
 *
 * Dipakai untuk nama tabel / kolom.
 */
function quoteIdentifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}


/**
 * Cek apakah file merupakan database SQLite yang diperbolehkan.
 */
function isAllowedDatabase(string $file, array $extensions): bool
{
    if (!is_file($file)) {
        return false;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return in_array($ext, $extensions, true);
}


/**
 * Scan database secara recursive dari root.
 *
 * Digunakan untuk daftar source database pada fitur Copy Data.
 */
function scanDatabasesRecursive(string $root, array $extensions): array
{
    $result = [];

    if (!is_dir($root)) {
        return $result;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {

            // Lewati symlink
            if ($fileInfo->isLink()) {
                continue;
            }

            // Hanya file
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filePath = $fileInfo->getPathname();

            if (isAllowedDatabase($filePath, $extensions)) {
                $real = realpath($filePath);

                if ($real !== false) {
                    $result[] = $real;
                }
            }
        }

    } catch (Throwable $e) {
        // Jika ada folder yang tidak bisa dibaca, lanjutkan saja.
    }

    $result = array_values(array_unique($result));

    sort($result, SORT_NATURAL | SORT_FLAG_CASE);

    return $result;
}


/**
 * Format ukuran file.
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}


/**
 * Path relatif terhadap browse root.
 */
function relativeToRoot(string $path, string $root): string
{
    $root = rtrim(realpath($root), DIRECTORY_SEPARATOR);
    $path = realpath($path);

    if ($path === false) {
        return '';
    }

    if ($path === $root) {
        return '/';
    }

    return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}


/*
|--------------------------------------------------------------------------
| BROWSE FOLDER AKTIF
|--------------------------------------------------------------------------
*/

$currentBrowseParam = $_GET['path'] ?? $browseRoot;

$currentBrowse = safePath($currentBrowseParam, $browseRoot);

if ($currentBrowse === null || !is_dir($currentBrowse)) {
    $currentBrowse = realpath($browseRoot);
}

if ($currentBrowse === false) {
    die('Browse root tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$msg = '';
$msgType = 'msg';


/*
|--------------------------------------------------------------------------
| PROSES DOWNLOAD DATABASE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'download_db'
) {
    $downloadParam = $_GET['db'] ?? '';

    $downloadFile = safePath(
        $downloadParam,
        $browseRoot
    );

    if (
        $downloadFile !== null &&
        isAllowedDatabase($downloadFile, $extensions)
    ) {

        $downloadName = basename($downloadFile);

        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header(
            'Content-Disposition: attachment; filename="' .
            str_replace('"', '', $downloadName) .
            '"'
        );
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($downloadFile));

        readfile($downloadFile);
        exit;
    }

    http_response_code(404);
    exit('Database tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| PROSES UPLOAD DATABASE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['upload_db'])
) {

    if (
        isset($_FILES['db_file']) &&
        $_FILES['db_file']['error'] === UPLOAD_ERR_OK
    ) {

        $fileName = basename($_FILES['db_file']['name']);
        $ext = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        if (in_array($ext, $extensions, true)) {

            $destPath = $currentBrowse .
                DIRECTORY_SEPARATOR .
                $fileName;

            /*
             * Jika file sudah ada, kita izinkan overwrite.
             */
            if (
                move_uploaded_file(
                    $_FILES['db_file']['tmp_name'],
                    $destPath
                )
            ) {

                $realUploaded = realpath($destPath);

                if ($realUploaded !== false) {

                    header(
                        'Location: ?path=' .
                        urlencode($currentBrowse) .
                        '&db=' .
                        urlencode($realUploaded)
                    );

                    exit;
                }

            } else {

                $msg = "Gagal memindahkan file yang diupload.";
                $msgType = 'alert';
            }

        } else {

            $msg =
                "Ekstensi file tidak valid! " .
                "Gunakan format (.db, .sqlite, .sqlite3, .db3).";

            $msgType = 'alert';
        }

    } else {

        $msg = "Gagal mengupload file database.";
        $msgType = 'alert';
    }
}


/*
|--------------------------------------------------------------------------
| SCAN FOLDER SAAT INI
|--------------------------------------------------------------------------
*/

$browseFolders = [];
$browseDatabases = [];

try {

    $items = scandir($currentBrowse);

    if ($items !== false) {

        foreach ($items as $item) {

            if ($item === '.' || $item === '..') {
                continue;
            }

            // Sembunyikan file/folder hidden
            if (str_starts_with($item, '.')) {
                continue;
            }

            $fullPath =
                $currentBrowse .
                DIRECTORY_SEPARATOR .
                $item;

            /*
             * Jangan mengikuti symlink.
             */
            if (is_link($fullPath)) {
                continue;
            }

            if (is_dir($fullPath)) {

                $realDir = realpath($fullPath);

                if ($realDir !== false) {
                    $browseFolders[] = $realDir;
                }

                continue;
            }

            if (
                isAllowedDatabase(
                    $fullPath,
                    $extensions
                )
            ) {

                $realFile = realpath($fullPath);

                if ($realFile !== false) {
                    $browseDatabases[] = $realFile;
                }
            }
        }
    }

} catch (Throwable $e) {
    $msg = "Folder tidak dapat dibaca: " . $e->getMessage();
    $msgType = 'alert';
}


/*
|--------------------------------------------------------------------------
| SORT
|--------------------------------------------------------------------------
*/

usort(
    $browseFolders,
    fn($a, $b) =>
        strcasecmp(basename($a), basename($b))
);

usort(
    $browseDatabases,
    fn($a, $b) =>
        strcasecmp(basename($a), basename($b))
);


/*
|--------------------------------------------------------------------------
| SCAN SEMUA DATABASE UNTUK COPY DATA
|--------------------------------------------------------------------------
*/

$foundDatabases = scanDatabasesRecursive(
    $browseRoot,
    $extensions
);


/*
|--------------------------------------------------------------------------
| PILIH DATABASE AKTIF
|--------------------------------------------------------------------------
|
| db sekarang menggunakan absolute path.
|
*/

$activeDbParam = $_GET['db'] ?? '';

$activeDb = null;

if ($activeDbParam !== '') {

    $candidateDb = safePath(
        $activeDbParam,
        $browseRoot
    );

    if (
        $candidateDb !== null &&
        isAllowedDatabase(
            $candidateDb,
            $extensions
        )
    ) {
        $activeDb = $candidateDb;
    }
}


/*
 * Jika belum memilih DB, otomatis pilih database pertama
 * dari folder yang sedang dibrowse.
 */
if ($activeDb === null && !empty($browseDatabases)) {
    $activeDb = $browseDatabases[0];
}

$dbFile = $activeDb;


/*
|--------------------------------------------------------------------------
| API ENDPOINT UNTUK AJAX
|--------------------------------------------------------------------------
*/

if (isset($_GET['api'])) {

    header('Content-Type: application/json; charset=utf-8');

    $reqDbParam = $_GET['api_db'] ?? '';

    $reqFile = safePath(
        $reqDbParam,
        $browseRoot
    );

    if (
        $reqFile === null ||
        !isAllowedDatabase(
            $reqFile,
            $extensions
        )
    ) {

        echo json_encode([]);
        exit;
    }

    try {

        $realPath = realpath($reqFile);

        $apiPdo = new PDO(
            'sqlite:' . $realPath
        );

        $apiPdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $apiPdo->setAttribute(
            PDO::ATTR_TIMEOUT,
            5
        );

        $apiPdo->exec(
            "PRAGMA busy_timeout = 5000;"
        );


        /*
         * GET TABLES
         */
        if ($_GET['api'] === 'get_tables') {

            $stmt = $apiPdo->query(
                "SELECT name
                 FROM sqlite_master
                 WHERE type='table'
                 AND name NOT LIKE 'sqlite_%'
                 ORDER BY name"
            );

            $data = $stmt->fetchAll(
                PDO::FETCH_COLUMN
            );

            $apiPdo = null;

            echo json_encode(
                array_values($data) ?: []
            );

            exit;
        }


        /*
         * GET COLUMNS
         */
        if (
            $_GET['api'] === 'get_cols' &&
            isset($_GET['api_table'])
        ) {

            $tbl = $_GET['api_table'];

            $quotedTable = quoteIdentifier($tbl);

            $stmt = $apiPdo->query(
                "PRAGMA table_info($quotedTable)"
            );

            $data = $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            $apiPdo = null;

            echo json_encode(
                array_values($data) ?: []
            );

            exit;
        }

        echo json_encode([]);

        exit;

    } catch (Throwable $e) {

        echo json_encode([]);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| DATABASE UTAMA
|--------------------------------------------------------------------------
*/

$pdo = null;
$tables = [];


/*
|--------------------------------------------------------------------------
| INISIALISASI DATABASE & PROSES FORM
|--------------------------------------------------------------------------
*/

if (
    $dbFile !== null &&
    is_file($dbFile)
) {

    try {

        $pdo = new PDO(
            'sqlite:' . realpath($dbFile)
        );

        $pdo->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $pdo->setAttribute(
            PDO::ATTR_TIMEOUT,
            10
        );

        $pdo->exec(
            "PRAGMA busy_timeout = 10000;"
        );


        $action = $_GET['action'] ?? 'tables';
        $table  = $_GET['table'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | MANAJEMEN STRUKTUR: TAMBAH KOLOM
        |--------------------------------------------------------------------------
        */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['add_column']) &&
            $table
        ) {

            $colName = trim(
                $_POST['new_col_name'] ?? ''
            );

            $colType = strtoupper(
                $_POST['new_col_type'] ?? 'TEXT'
            );

            $defaultVal = trim(
                $_POST['new_col_default'] ?? ''
            );


            $allowedTypes = [
                'INTEGER',
                'TEXT',
                'REAL',
                'BLOB'
            ];

            if (
                $colName !== '' &&
                in_array(
                    $colType,
                    $allowedTypes,
                    true
                )
            ) {

                $quotedTable =
                    quoteIdentifier($table);

                $quotedColumn =
                    quoteIdentifier($colName);

                $sql =
                    "ALTER TABLE $quotedTable " .
                    "ADD COLUMN $quotedColumn $colType";

                if ($defaultVal !== '') {

                    $sql .=
                        " DEFAULT " .
                        $pdo->quote($defaultVal);
                }

                $pdo->exec($sql);

                $msg =
                    "Kolom '$colName' ($colType) " .
                    "berhasil ditambahkan!";

                $action = 'structure';

            } else {

                $msg = "Nama kolom atau tipe data tidak valid.";
                $msgType = 'alert';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | MANAJEMEN STRUKTUR: RENAME KOLOM
        |--------------------------------------------------------------------------
        */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['rename_column']) &&
            $table
        ) {

            $oldCol =
                $_POST['old_col_name'] ?? '';

            $newCol =
                trim(
                    $_POST['new_col_name'] ?? ''
                );

            if (
                $oldCol !== '' &&
                $newCol !== ''
            ) {

                $quotedTable =
                    quoteIdentifier($table);

                $quotedOld =
                    quoteIdentifier($oldCol);

                $quotedNew =
                    quoteIdentifier($newCol);

                $sql =
                    "ALTER TABLE $quotedTable " .
                    "RENAME COLUMN $quotedOld TO $quotedNew";

                $pdo->exec($sql);

                $msg =
                    "Nama kolom '$oldCol' berhasil " .
                    "diubah menjadi '$newCol'!";

                $action = 'structure';

            } else {

                $msg = "Nama kolom tidak valid.";
                $msgType = 'alert';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PROSES COPY DATA ANTAR DB
        |--------------------------------------------------------------------------
        */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['copy_data'])
        ) {

            $sourceDb =
                $_POST['source_db'] ?? '';

            $sourceTable =
                $_POST['source_table'] ?? '';

            $targetTable =
                $_POST['target_table'] ?? '';

            $mapTypes =
                $_POST['map_type'] ?? [];

            $mapCols =
                $_POST['map_col'] ?? [];

            $mapValues =
                $_POST['map_value'] ?? [];

            $onConflict =
                $_POST['on_conflict'] ?? 'IGNORE';


            /*
             * Validasi source DB.
             */
            $sourceDbPath =
                safePath(
                    $sourceDb,
                    $browseRoot
                );


            if (
                $sourceDbPath !== null &&
                $sourceTable !== '' &&
                $targetTable !== '' &&
                isAllowedDatabase(
                    $sourceDbPath,
                    $extensions
                )
            ) {

                /*
                 * Cari MAX ID target.
                 */
                $maxId = 0;

                try {

                    $quotedTarget =
                        quoteIdentifier(
                            $targetTable
                        );

                    $maxIdStmt =
                        $pdo->query(
                            "SELECT MAX(
                                CAST(id AS INTEGER)
                            )
                            FROM $quotedTarget"
                        );

                    $maxId =
                        (int)
                        $maxIdStmt->fetchColumn();

                } catch (Throwable $e) {

                    $maxId = 0;
                }


                /*
                 * Buka source database.
                 */
                $srcPdo =
                    new PDO(
                        'sqlite:' .
                        realpath($sourceDbPath)
                    );

                $srcPdo->setAttribute(
                    PDO::ATTR_ERRMODE,
                    PDO::ERRMODE_EXCEPTION
                );

                $srcPdo->setAttribute(
                    PDO::ATTR_TIMEOUT,
                    5
                );

                $srcPdo->exec(
                    "PRAGMA busy_timeout = 5000;"
                );


                $quotedSourceTable =
                    quoteIdentifier(
                        $sourceTable
                    );


                $srcStmt =
                    $srcPdo->query(
                        "SELECT *
                         FROM $quotedSourceTable"
                    );

                $sourceRows =
                    $srcStmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );

                $srcPdo = null;


                if (!empty($sourceRows)) {

                    $targetFields = [];


                    foreach (
                        $mapTypes as $tgtCol => $type
                    ) {

                        if ($type !== 'ignore') {

                            $targetFields[] =
                                $tgtCol;
                        }
                    }


                    if (!empty($targetFields)) {

                        $insertKeyword = "INSERT";


                        if ($onConflict === 'IGNORE') {

                            $insertKeyword =
                                "INSERT OR IGNORE";

                        } elseif (
                            $onConflict === 'REPLACE'
                        ) {

                            $insertKeyword =
                                "INSERT OR REPLACE";
                        }


                        /*
                         * Quote semua target field.
                         */
                        $fieldList =
                            implode(
                                ', ',
                                array_map(
                                    'quoteIdentifier',
                                    $targetFields
                                )
                            );


                        $placeholders =
                            implode(
                                ', ',
                                array_fill(
                                    0,
                                    count($targetFields),
                                    '?'
                                )
                            );


                        $quotedTargetTable =
                            quoteIdentifier(
                                $targetTable
                            );


                        $sqlInsert =
                            "$insertKeyword INTO " .
                            "$quotedTargetTable " .
                            "($fieldList) " .
                            "VALUES ($placeholders)";


                        $pdo->beginTransaction();


                        try {

                            $insertStmt =
                                $pdo->prepare(
                                    $sqlInsert
                                );

                            $insertedCount = 0;

                            $autoIdCounter =
                                $maxId;


                            foreach (
                                $sourceRows as $row
                            ) {

                                $rowValues = [];

                                $autoIdCounter++;


                                foreach (
                                    $targetFields as $tgtCol
                                ) {

                                    $type =
                                        $mapTypes[$tgtCol]
                                        ?? 'ignore';


                                    if (
                                        $type === 'col'
                                    ) {

                                        $srcColName =
                                            $mapCols[$tgtCol]
                                            ?? '';

                                        $rowValues[] =
                                            $row[$srcColName]
                                            ?? null;

                                    } elseif (
                                        $type === 'auto_id'
                                    ) {

                                        $rowValues[] =
                                            $autoIdCounter;

                                    } elseif (
                                        $type === 'custom'
                                    ) {

                                        $val =
                                            $mapValues[$tgtCol]
                                            ?? '';

                                        $rowValues[] =
                                            (
                                                $val === '' ||
                                                strtoupper($val) === 'NULL'
                                            )
                                            ? null
                                            : $val;
                                    }
                                }


                                $insertStmt->execute(
                                    $rowValues
                                );

                                /*
                                 * Untuk INSERT OR IGNORE,
                                 * rowCount() bisa digunakan untuk
                                 * mengetahui yang benar-benar masuk.
                                 */
                                if (
                                    $insertStmt->rowCount() > 0
                                ) {
                                    $insertedCount++;
                                }
                            }


                            $pdo->commit();


                            $sourceName =
                                basename(
                                    $sourceDbPath
                                );

                            $targetName =
                                basename(
                                    $dbFile
                                );


                            $msg =
                                "Berhasil menyalin " .
                                "$insertedCount baris data " .
                                "dari '$sourceName " .
                                "($sourceTable)' ke " .
                                "'$targetName " .
                                "($targetTable)'!";

                        } catch (Throwable $copyError) {

                            if (
                                $pdo->inTransaction()
                            ) {
                                $pdo->rollBack();
                            }

                            throw $copyError;
                        }

                    } else {

                        $msg =
                            "Mohon pilih minimal satu " .
                            "pemetaan kolom yang diisi!";

                        $msgType = 'alert';
                    }

                } else {

                    $msg =
                        "Tabel asal '$sourceTable' " .
                        "tidak memiliki data untuk disalin.";

                    $msgType = 'alert';
                }

            } else {

                $msg =
                    "Database atau Tabel tidak valid!";

                $msgType = 'alert';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | HAPUS SATU BARIS DATA
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'delete_row' &&
            $table
        ) {

            $pkCol =
                $_GET['pk_col'] ?? '';

            $pkVal =
                $_GET['pk_val'] ?? '';


            if (
                $pkCol !== '' &&
                $pkVal !== ''
            ) {

                $quotedTable =
                    quoteIdentifier($table);

                $quotedPk =
                    quoteIdentifier($pkCol);


                $stmt =
                    $pdo->prepare(
                        "DELETE FROM $quotedTable
                         WHERE $quotedPk = :val"
                    );

                $stmt->execute([
                    ':val' => $pkVal
                ]);


                $msg =
                    "Data berhasil dihapus.";
            }


            $action = 'browse';
        }


        /*
        |--------------------------------------------------------------------------
        | KOSONGKAN TABEL
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'truncate' &&
            $table
        ) {

            $quotedTable =
                quoteIdentifier($table);


            $pdo->exec(
                "DELETE FROM $quotedTable"
            );


            /*
             * sqlite_sequence hanya ada pada tabel
             * AUTOINCREMENT. Jadi jangan biarkan error
             * kalau tabel tidak menggunakannya.
             */
            try {

                $pdo->exec(
                    "DELETE FROM sqlite_sequence
                     WHERE name = " .
                    $pdo->quote($table)
                );

            } catch (Throwable $e) {
                // Abaikan jika sqlite_sequence tidak tersedia.
            }


            $msg =
                "Semua data pada tabel '$table' " .
                "dikosongkan!";

            $action = 'browse';
        }


        /*
        |--------------------------------------------------------------------------
        | DAFTAR TABEL
        |--------------------------------------------------------------------------
        */

        $tablesStmt =
            $pdo->query(
                "SELECT name
                 FROM sqlite_master
                 WHERE type='table'
                 AND name NOT LIKE 'sqlite_%'
                 ORDER BY name"
            );


        $tables =
            $tablesStmt->fetchAll(
                PDO::FETCH_COLUMN
            );


    } catch (Throwable $e) {

        if (
            $pdo &&
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }

        $msg =
            "Error DB: " .
            $e->getMessage();

        $msgType = 'alert';
    }
}


$action =
    $_GET['action'] ?? 'tables';

$table =
    $_GET['table'] ?? '';


/*
|--------------------------------------------------------------------------
| INFORMASI BROWSE
|--------------------------------------------------------------------------
*/

$browseRelative =
    relativeToRoot(
        $currentBrowse,
        $browseRoot
    );


$parentBrowse = dirname(
    $currentBrowse
);


/*
 * Parent hanya boleh kalau masih berada
 * di bawah /var/www.
 */
$parentSafe =
    safePath(
        $parentBrowse,
        $browseRoot
    );


$activeDbName =
    $activeDb
    ? basename($activeDb)
    : null;

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>SQLite Admin & File Browser</title>


<style>

body {
    font-family: monospace, sans-serif;
    margin: 0;
    display: flex;
    height: 100vh;
}


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

#sidebar {
    width: 330px;
    min-width: 330px;
    background: #1e293b;
    color: #f8fafc;
    padding: 15px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}


#sidebar select,
#sidebar input[type="file"] {

    width: 100%;
    padding: 6px;
    margin: 6px 0 12px 0;

    background: #334155;
    color: #fff;

    border: 1px solid #475569;
    border-radius: 4px;

    box-sizing: border-box;

    font-size: 11px;
}


#sidebar a {

    color: #cbd5e1;

    text-decoration: none;

    display: block;

    margin: 4px 0;

    padding: 6px;

    border-radius: 4px;

    font-size: 13px;
}


#sidebar a:hover {

    background: #334155;
    color: #fff;
}


#sidebar a.active {

    background: #0284c7;
    color: #fff;
}


/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

#content {

    flex: 1;

    padding: 20px;

    overflow-y: auto;

    background: #f8fafc;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

table {

    border-collapse: collapse;

    width: 100%;

    background: #fff;

    margin-top: 10px;

    box-shadow:
        0 1px 3px rgba(0,0,0,0.1);
}


th,
td {

    border: 1px solid #cbd5e1;

    padding: 8px 10px;

    text-align: left;

    font-size: 13px;

    vertical-align: top;
}


th {

    background: #f1f5f9;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

.msg {

    padding: 10px;

    background: #e0f2fe;

    color: #0369a1;

    margin-bottom: 15px;

    border-radius: 4px;
}


.alert {

    padding: 10px;

    background: #fee2e2;

    color: #991b1b;

    margin-bottom: 15px;

    border-radius: 4px;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.btn {

    padding: 5px 10px;

    border-radius: 3px;

    cursor: pointer;

    text-decoration: none;

    font-size: 12px;

    display: inline-block;

    text-align: center;
}


.btn-danger {

    background: #ef4444;

    color: white;

    border: none;
}


.btn-primary {

    background: #0284c7;

    color: white;

    border: none;

    padding: 8px 15px;

    border-radius: 4px;
}


.btn-success {

    background: #16a34a;

    color: white;

    border: none;
}


.btn-download {

    background: #059669;

    color: white;

    width: 100%;

    box-sizing: border-box;

    margin-bottom: 15px;

    font-weight: bold;

    padding: 6px;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-card {

    background: #fff;

    padding: 20px;

    border-radius: 6px;

    box-shadow:
        0 1px 3px rgba(0,0,0,0.1);

    max-width: 950px;
}


.form-group {

    margin-bottom: 15px;
}


.form-group label {

    display: block;

    margin-bottom: 5px;

    font-weight: bold;
}


.form-group select,
.form-group input[type="text"] {

    width: 100%;

    padding: 8px;

    border: 1px solid #cbd5e1;

    border-radius: 4px;

    box-sizing: border-box;
}


/*
|--------------------------------------------------------------------------
| COPY MAPPING
|--------------------------------------------------------------------------
*/

.col-mapping-row {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 8px;

    background: #f8fafc;

    padding: 8px 12px;

    border-radius: 4px;

    border: 1px solid #e2e8f0;
}


.badge-notnull {

    background: #ef4444;

    color: white;

    padding: 2px 5px;

    border-radius: 3px;

    font-size: 10px;

    font-weight: bold;
}


.option-box {

    background: #f1f5f9;

    padding: 12px;

    border-radius: 6px;

    border: 1px solid #cbd5e1;

    margin: 15px 0;
}


.radio-label {

    font-weight: normal;

    margin-right: 15px;

    cursor: pointer;

    display: inline-flex;

    align-items: center;

    gap: 6px;
}


/*
|--------------------------------------------------------------------------
| TAB
|--------------------------------------------------------------------------
*/

.nav-tabs {

    display: flex;

    gap: 8px;

    margin-bottom: 15px;
}


.nav-tabs a {

    padding: 8px 16px;

    background: #e2e8f0;

    text-decoration: none;

    color: #334155;

    border-radius: 4px;

    font-weight: bold;
}


.nav-tabs a.active {

    background: #0284c7;

    color: #fff;
}


/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

.upload-box {

    background: #0f172a;

    padding: 10px;

    border-radius: 6px;

    margin-bottom: 15px;

    border: 1px dashed #475569;
}


/*
|--------------------------------------------------------------------------
| FILE BROWSER
|--------------------------------------------------------------------------
*/

.browser-box {

    background: #0f172a;

    border: 1px solid #334155;

    border-radius: 6px;

    padding: 8px;

    margin-bottom: 15px;
}


.browser-path {

    background: #020617;

    border: 1px solid #334155;

    border-radius: 4px;

    padding: 7px;

    margin-bottom: 8px;

    font-size: 10px;

    color: #94a3b8;

    word-break: break-all;
}


.browser-item {

    display: flex !important;

    align-items: center;

    gap: 6px;

    margin: 2px 0 !important;

    padding: 5px 6px !important;

    font-size: 11px !important;
}


.browser-item:hover {

    background: #334155;
}


.browser-item.folder {

    color: #facc15 !important;
}


.browser-item.database {

    color: #67e8f9 !important;
}


.browser-item.database.active {

    background: #0284c7 !important;

    color: #fff !important;
}


.browser-meta {

    margin-left: auto;

    color: #64748b;

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| ACTIVE DB
|--------------------------------------------------------------------------
*/

.active-db-box {

    background: #022c22;

    border: 1px solid #065f46;

    border-radius: 5px;

    padding: 8px;

    margin-bottom: 10px;

    font-size: 10px;

    color: #a7f3d0;

    word-break: break-all;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    body {
        flex-direction: column;
        height: auto;
        min-height: 100vh;
    }

    #sidebar {
        width: 100%;
        min-width: 0;
        max-height: 55vh;
    }

    .col-mapping-row {
        flex-wrap: wrap;
    }
}

</style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
-->

<div id="sidebar">

    <h3 style="margin-top:0;">
        📂 SQLite Admin
    </h3>


    <!--
    |--------------------------------------------------------------------------
    | SERVER FILE BROWSER
    |--------------------------------------------------------------------------
    -->

    <label>
        <small>📁 Browse Server:</small>
    </label>


    <div class="browser-box">

        <div class="browser-path">
            ROOT: <?= htmlspecialchars($browseRoot) ?><br>
            CURRENT:
            <?= htmlspecialchars($browseRelative ?: '/') ?>
        </div>


        <?php if ($parentSafe !== null && $currentBrowse !== realpath($browseRoot)): ?>

            <a
                href="?path=<?= urlencode($parentSafe) ?>"
                class="browser-item"
            >
                ⬆️ ..
            </a>

        <?php endif; ?>


        <?php foreach ($browseFolders as $folder): ?>

            <a
                href="?path=<?= urlencode($folder) ?>"
                class="browser-item folder"
            >

                📁
                <?= htmlspecialchars(
                    basename($folder)
                ) ?>

            </a>

        <?php endforeach; ?>


        <?php foreach ($browseDatabases as $db): ?>

            <a
                href="?path=<?= urlencode($currentBrowse) ?>&db=<?= urlencode($db) ?>&action=tables"
                class="browser-item database <?= $activeDb === $db ? 'active' : '' ?>"
            >

                🗄️
                <?= htmlspecialchars(
                    basename($db)
                ) ?>

                <span class="browser-meta">

                    <?= htmlspecialchars(
                        formatFileSize(
                            (int) filesize($db)
                        )
                    ) ?>

                </span>

            </a>

        <?php endforeach; ?>


        <?php if (
            empty($browseFolders) &&
            empty($browseDatabases)
        ): ?>

            <div
                style="
                    color:#64748b;
                    font-size:10px;
                    padding:8px;
                "
            >
                Folder kosong.
            </div>

        <?php endif; ?>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | ACTIVE DATABASE
    |--------------------------------------------------------------------------
    -->

    <?php if ($activeDb): ?>

        <div class="active-db-box">

            🟢 <strong>DATABASE AKTIF</strong><br>

            <?= htmlspecialchars(
                $activeDb
            ) ?>

        </div>


        <a
            href="?action=download_db&db=<?= urlencode($activeDb) ?>"
            class="btn btn-download"
        >

            ⬇️ Download
            <?= htmlspecialchars(
                basename($activeDb)
            ) ?>

        </a>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | UPLOAD
    |--------------------------------------------------------------------------
    -->

    <div class="upload-box">

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="upload_db"
                value="1"
            >


            <label
                style="
                    font-size:11px;
                    font-weight:bold;
                "
            >

                📤 Upload DB ke folder ini:

            </label>


            <div
                style="
                    color:#64748b;
                    font-size:9px;
                    margin:4px 0;
                    word-break:break-all;
                "
            >

                <?= htmlspecialchars(
                    $currentBrowse
                ) ?>

            </div>


            <input
                type="file"
                name="db_file"
                accept=".db,.sqlite,.sqlite3,.db3"
                required
            >


            <button
                type="submit"
                class="btn btn-primary"
                style="
                    width:100%;
                    font-size:11px;
                    padding:5px;
                "
            >

                Upload File

            </button>

        </form>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | DAFTAR TABEL
    |--------------------------------------------------------------------------
    -->

    <div
        style="
            flex:1;
            overflow-y:auto;
        "
    >

        <?php if ($pdo): ?>

            <strong>
                Daftar Tabel
                (<?= count($tables) ?>):
            </strong>


            <div style="margin-top:8px;">

                <?php foreach ($tables as $t): ?>

                    <a
                        href="?db=<?= urlencode($activeDb) ?>&path=<?= urlencode($currentBrowse) ?>&action=browse&table=<?= urlencode($t) ?>"
                        class="<?= $table === $t ? 'active' : '' ?>"
                    >

                        📊
                        <?= htmlspecialchars($t) ?>

                    </a>

                <?php endforeach; ?>

            </div>


            <hr
                style="
                    border-color:#334155;
                    margin:15px 0;
                "
            >


            <a
                href="?db=<?= urlencode($activeDb) ?>&path=<?= urlencode($currentBrowse) ?>&action=copy"
                class="<?= $action === 'copy' ? 'active' : '' ?>"
            >

                🔄 Copy Data Antar DB

            </a>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
-->

<div id="content">


    <?php if ($msg): ?>

        <div class="<?= htmlspecialchars($msgType) ?>">

            <?= htmlspecialchars($msg) ?>

        </div>

    <?php endif; ?>


    <?php if (!$activeDb): ?>

        <div class="form-card">

            <h2>📂 SQLite Admin</h2>

            <p>
                Pilih database SQLite dari
                <strong>File Browser</strong>
                di sebelah kiri.
            </p>

            <p style="color:#64748b;">

                Root yang diizinkan:
                <code><?= htmlspecialchars($browseRoot) ?></code>

            </p>

        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | TAB NAVIGASI
    |--------------------------------------------------------------------------
    -->

    <?php if (
        $pdo &&
        $table &&
        in_array(
            $action,
            ['browse', 'structure']
        )
    ): ?>

        <div class="nav-tabs">

            <a
                href="?db=<?= urlencode($activeDb) ?>&path=<?= urlencode($currentBrowse) ?>&action=browse&table=<?= urlencode($table) ?>"
                class="<?= $action === 'browse' ? 'active' : '' ?>"
            >

                📄 Data Tabel

            </a>


            <a
                href="?db=<?= urlencode($activeDb) ?>&path=<?= urlencode($currentBrowse) ?>&action=structure&table=<?= urlencode($table) ?>"
                class="<?= $action === 'structure' ? 'active' : '' ?>"
            >

                ⚙️ Kelola Skema Kolom

            </a>

        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | TAB 1: BROWSE DATA
    |--------------------------------------------------------------------------
    -->

    <?php if (
        $pdo &&
        $action === 'browse' &&
        $table
    ): ?>

        <?php

        $quotedTable =
            quoteIdentifier($table);


        $colsStmt =
            $pdo->query(
                "PRAGMA table_info($quotedTable)"
            );


        $columnsInfo =
            $colsStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        $columnNames =
            array_column(
                $columnsInfo,
                'name'
            );


        $pkCol =
            $columnsInfo[0]['name']
            ?? 'id';


        $dataStmt =
            $pdo->query(
                "SELECT *
                 FROM $quotedTable
                 LIMIT 100"
            );


        $rows =
            $dataStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        ?>


        <div
            style="
                display:flex;
                justify-content:space-between;
                align-items:center;
            "
        >

            <h2>

                Tabel:
                <u>
                    <?= htmlspecialchars($table) ?>
                </u>

            </h2>


            <?php if (!empty($rows)): ?>

                <a
                    href="?db=<?= urlencode($activeDb) ?>&path=<?= urlencode($currentBrowse) ?>&action=truncate&table=<?= urlencode($table) ?>"
                    class="btn btn-danger"
                    onclick="return confirm('Kosongkan tabel?')"
                >

                    ⚠️ Hapus Semua Data

                </a>

            <?php endif; ?>

        </div>


        <table>

            <thead>

                <tr>

                    <th style="width:60px;">
                        Aksi
                    </th>


                    <?php foreach ($columnNames as $c): ?>

                        <th>
                            <?= htmlspecialchars($c) ?>
                        </th>

                    <?php endforeach; ?>

                </tr>

            </thead>


            <tbody>

                <?php if (empty($rows)): ?>

                    <tr>

                        <td
                            colspan="<?= count($columnNames) + 1 ?>"
                            style="
                                text-align:center;
                                color:#64748b;
                                padding:20px;
                            "
                        >

                            <i>

                                Tabel ini masih kosong
                                (0 data).

                                Silakan gunakan menu

                                <b>
                                    "🔄 Copy Data Antar DB"
                                </b>

                                untuk mengisi data.

                            </i>

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($rows as $r): ?>

                        <tr>

                            <td>

                                <a
                                    href="?db=<?= urlencode($activeDb) ?>&path=<?= urlencode($currentBrowse) ?>&action=delete_row&table=<?= urlencode($table) ?>&pk_col=<?= urlencode($pkCol) ?>&pk_val=<?= urlencode($r[$pkCol] ?? '') ?>"
                                    class="btn btn-danger"
                                    onclick="return confirm('Hapus baris ini?')"
                                >

                                    Hapus

                                </a>

                            </td>


                            <?php foreach (
                                $columnNames as $colName
                            ): ?>

                                <td>

                                    <?= htmlspecialchars(
                                        $r[$colName]
                                        ?? 'NULL'
                                    ) ?>

                                </td>

                            <?php endforeach; ?>

                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>

            </tbody>

        </table>


    <!--
    |--------------------------------------------------------------------------
    | TAB 2: STRUCTURE
    |--------------------------------------------------------------------------
    -->

    <?php elseif (
        $pdo &&
        $action === 'structure' &&
        $table
    ): ?>


        <?php

        $quotedTable =
            quoteIdentifier($table);


        $colsStmt =
            $pdo->query(
                "PRAGMA table_info($quotedTable)"
            );


        $columns =
            $colsStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        ?>


        <h2>

            ⚙️ Kelola Struktur Kolom:
            <u>
                <?= htmlspecialchars($table) ?>
            </u>

        </h2>


        <div
            class="form-card"
            style="margin-bottom:25px;"
        >

            <h4
                style="
                    margin-top:0;
                    color:#16a34a;
                "
            >

                ➕ Tambah Kolom Baru

            </h4>


            <form method="POST">

                <input
                    type="hidden"
                    name="add_column"
                    value="1"
                >


                <div
                    style="
                        display:flex;
                        gap:10px;
                    "
                >

                    <input
                        type="text"
                        name="new_col_name"
                        placeholder="Nama Kolom Baru"
                        required
                        style="flex:2;"
                    >


                    <select
                        name="new_col_type"
                        style="flex:1;"
                    >

                        <option value="INTEGER">
                            INTEGER
                        </option>

                        <option
                            value="TEXT"
                            selected
                        >
                            TEXT
                        </option>

                        <option value="REAL">
                            REAL (Float)
                        </option>

                        <option value="BLOB">
                            BLOB
                        </option>

                    </select>


                    <input
                        type="text"
                        name="new_col_default"
                        placeholder="Nilai Default (Opsional)"
                        style="flex:1;"
                    >


                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        Tambah Kolom

                    </button>

                </div>

            </form>

        </div>


        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>
                        Nama Kolom Saat Ini
                    </th>

                    <th>
                        Tipe Data
                    </th>

                    <th>
                        Not Null?
                    </th>

                    <th>
                        Default Value
                    </th>

                    <th>
                        Ubah Nama Kolom
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php foreach (
                    $columns as $idx => $col
                ): ?>

                    <tr>

                        <td>
                            <?= $idx + 1 ?>
                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $col['name']
                                ) ?>
                            </strong>

                            <?= $col['pk']
                                ? '🔑 (PK)'
                                : '' ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $col['type']
                            ) ?>

                        </td>


                        <td>

                            <?= $col['notnull']
                                ? 'YES'
                                : 'NO' ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $col['dflt_value']
                                ?? 'NULL'
                            ) ?>

                        </td>


                        <td>

                            <form
                                method="POST"
                                style="
                                    display:flex;
                                    gap:5px;
                                "
                            >

                                <input
                                    type="hidden"
                                    name="rename_column"
                                    value="1"
                                >


                                <input
                                    type="hidden"
                                    name="old_col_name"
                                    value="<?= htmlspecialchars(
                                        $col['name'],
                                        ENT_QUOTES
                                    ) ?>"
                                >


                                <input
                                    type="text"
                                    name="new_col_name"
                                    value="<?= htmlspecialchars(
                                        $col['name'],
                                        ENT_QUOTES
                                    ) ?>"
                                    required
                                    style="
                                        padding:4px;
                                        font-size:12px;
                                    "
                                >


                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    style="
                                        padding:4px 8px;
                                    "
                                >

                                    Simpan

                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>


            </tbody>

        </table>


    <!--
    |--------------------------------------------------------------------------
    | TAB 3: COPY DATA
    |--------------------------------------------------------------------------
    -->

    <?php elseif (
        $pdo &&
        $action === 'copy'
    ): ?>


        <h2>
            🔄 Copy Data Antar Database
        </h2>


        <div class="form-card">

            <form method="POST">

                <input
                    type="hidden"
                    name="copy_data"
                    value="1"
                >


                <!-- SOURCE -->

                <h4
                    style="
                        margin-top:0;
                        color:#0284c7;
                    "
                >

                    1. Database & Tabel Asal
                    (Source)

                </h4>


                <div class="form-group">

                    <label>
                        Database Asal:
                    </label>


                    <select
                        name="source_db"
                        id="source_db"
                        onchange="loadSourceTables()"
                    >

                        <option value="">
                            -- Pilih Database Asal --
                        </option>


                        <?php foreach (
                            $foundDatabases as $db
                        ): ?>

                            <?php
                            if ($db === $activeDb) {
                                continue;
                            }

                            $relativeDb =
                                relativeToRoot(
                                    $db,
                                    $browseRoot
                                );
                            ?>


                            <option
                                value="<?= htmlspecialchars(
                                    $db,
                                    ENT_QUOTES
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $relativeDb
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Tabel Asal:
                    </label>


                    <select
                        name="source_table"
                        id="source_table"
                        onchange="loadColumnsMapping()"
                        disabled
                    >

                        <option value="">
                            -- Pilih DB Asal Terlebih Dahulu --
                        </option>

                    </select>

                </div>


                <hr
                    style="
                        margin:20px 0;
                        border:0;
                        border-top:1px solid #e2e8f0;
                    "
                >


                <!-- TARGET -->

                <h4
                    style="
                        margin-top:0;
                        color:#16a34a;
                    "
                >

                    2. Tabel Tujuan
                    (Target:
                    <?= htmlspecialchars(
                        $activeDbName ?? '-'
                    ) ?>)

                </h4>


                <div class="form-group">

                    <label>
                        Tabel Tujuan:
                    </label>


                    <select
                        name="target_table"
                        id="target_table"
                        onchange="loadColumnsMapping()"
                        required
                    >

                        <option value="">
                            -- Pilih Tabel Tujuan --
                        </option>


                        <?php foreach (
                            $tables as $t
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $t,
                                    ENT_QUOTES
                                ) ?>"
                            >

                                <?= htmlspecialchars($t) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CONFLICT -->

                <div class="option-box">

                    <label
                        style="
                            font-weight:bold;
                        "
                    >

                        Penanganan Data Duplikat
                        (Barcode / Primary Key):

                    </label>


                    <div style="margin-top:8px;">

                        <label class="radio-label">

                            <input
                                type="radio"
                                name="on_conflict"
                                value="IGNORE"
                                checked
                            >

                            <span>

                                <strong>
                                    INSERT OR IGNORE
                                </strong>

                                (Abaikan & lewati data
                                duplikat)

                            </span>

                        </label>


                        <br>


                        <label
                            class="radio-label"
                            style="margin-top:6px;"
                        >

                            <input
                                type="radio"
                                name="on_conflict"
                                value="REPLACE"
                            >

                            <span>

                                <strong>
                                    INSERT OR REPLACE
                                </strong>

                                (Timpa data lama jika
                                barcode bentrok)

                            </span>

                        </label>


                        <br>


                        <label
                            class="radio-label"
                            style="margin-top:6px;"
                        >

                            <input
                                type="radio"
                                name="on_conflict"
                                value=""
                            >

                            <span>

                                <strong>
                                    INSERT Standard
                                </strong>

                                (Gagal/Error jika
                                duplikat)

                            </span>

                        </label>

                    </div>

                </div>


                <!-- MAPPING -->

                <div class="form-group">

                    <label>
                        Pemetaan Kolom Tabel Tujuan:
                    </label>


                    <div
                        id="columns_container"
                        style="margin-top:10px;"
                    >

                        <small
                            style="
                                color:#64748b;
                            "
                        >

                            Pilih tabel tujuan di atas
                            untuk menampilkan pemetaan
                            kolom.

                        </small>

                    </div>

                </div>


                <br>


                <button
                    type="submit"
                    class="btn btn-primary"
                    onclick="return confirm('Proses salin data?')"
                >

                    Mulai Copy Data

                </button>

            </form>

        </div>


        <script>

        /*
        |--------------------------------------------------------------------------
        | DATABASE AKTIF
        |--------------------------------------------------------------------------
        */

        const ACTIVE_DB =
            <?= json_encode(
                $activeDb ?? '',
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            ) ?>;


        /*
        |--------------------------------------------------------------------------
        | PAGE LOAD
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "DOMContentLoaded",
            () => {

                const srcDbSelect =
                    document.getElementById(
                        'source_db'
                    );

                const tgtTblSelect =
                    document.getElementById(
                        'target_table'
                    );


                if (
                    srcDbSelect &&
                    srcDbSelect.value
                ) {

                    loadSourceTables();
                }


                if (
                    tgtTblSelect &&
                    tgtTblSelect.value
                ) {

                    loadColumnsMapping();
                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | LOAD SOURCE TABLES
        |--------------------------------------------------------------------------
        */

        async function loadSourceTables()
        {

            const db =
                document.getElementById(
                    'source_db'
                ).value;


            const tableSelect =
                document.getElementById(
                    'source_table'
                );


            if (!db) {

                tableSelect.innerHTML =
                    '<option value="">-- Pilih DB Asal Terlebih Dahulu --</option>';

                tableSelect.disabled = true;

                loadColumnsMapping();

                return;
            }


            tableSelect.innerHTML =
                '<option value="">-- Memuat Tabel... --</option>';

            tableSelect.disabled = true;


            try {

                const res =
                    await fetch(
                        `?api=get_tables&api_db=${encodeURIComponent(db)}`
                    );


                const tables =
                    await res.json();


                if (
                    Array.isArray(tables) &&
                    tables.length > 0
                ) {

                    tableSelect.innerHTML =
                        '<option value="">-- Pilih Tabel Asal --</option>';


                    tables.forEach(t => {

                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value = t;
                        option.textContent = t;

                        tableSelect.appendChild(
                            option
                        );

                    });


                    tableSelect.disabled = false;

                } else {

                    tableSelect.innerHTML =
                        '<option value="">(Tidak Ada Tabel / File Kosong)</option>';

                    tableSelect.disabled = true;
                }

            } catch (e) {

                console.error(
                    "Error loading tables:",
                    e
                );


                tableSelect.innerHTML =
                    '<option value="">Gagal Memuat Tabel</option>';

                tableSelect.disabled = true;
            }


            loadColumnsMapping();
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD COLUMN MAPPING
        |--------------------------------------------------------------------------
        */

        async function loadColumnsMapping()
        {

            const srcDb =
                document.getElementById(
                    'source_db'
                ).value;


            const srcTbl =
                document.getElementById(
                    'source_table'
                ).value;


            const tgtTbl =
                document.getElementById(
                    'target_table'
                ).value;


            const container =
                document.getElementById(
                    'columns_container'
                );


            if (!tgtTbl) {

                container.innerHTML =
                    '<small style="color:#64748b;">Pilih tabel tujuan untuk menampilkan pemetaan kolom.</small>';

                return;
            }


            container.innerHTML =
                '<i>Memuat pemetaan kolom...</i>';


            let srcCols = [];
            let tgtCols = [];


            try {

                /*
                 * TARGET
                 */

                const resTgt =
                    await fetch(
                        `?api=get_cols&api_db=${encodeURIComponent(ACTIVE_DB)}&api_table=${encodeURIComponent(tgtTbl)}`
                    );


                tgtCols =
                    await resTgt.json();


                /*
                 * SOURCE
                 */

                if (
                    srcDb &&
                    srcTbl
                ) {

                    const resSrc =
                        await fetch(
                            `?api=get_cols&api_db=${encodeURIComponent(srcDb)}&api_table=${encodeURIComponent(srcTbl)}`
                        );


                    srcCols =
                        await resSrc.json();
                }


            } catch (e) {

                console.error(
                    "Error fetching columns:",
                    e
                );
            }


            if (
                !Array.isArray(tgtCols) ||
                tgtCols.length === 0
            ) {

                container.innerHTML =
                    `<small style="color:#ef4444;">
                        Gagal memuat skema kolom tabel tujuan
                        (${escapeHtml(tgtTbl)}).
                    </small>`;

                return;
            }


            container.innerHTML = '';


            tgtCols.forEach(
                tCol => {

                    const colName =
                        tCol.name;


                    const isNotNull =
                        tCol.notnull == 1;


                    const isIdCol =
                        colName.toLowerCase() === 'id' ||
                        tCol.pk == 1;


                    const badge =
                        isNotNull
                        ? '<span class="badge-notnull">NOT NULL</span>'
                        : '';


                    let colOptionsHtml =
                        '<option value="">-- Abaikan (NULL) --</option>';


                    let isMatched =
                        false;


                    if (
                        Array.isArray(srcCols)
                    ) {

                        srcCols.forEach(
                            sCol => {

                                const selected =
                                    sCol.name.toLowerCase() ===
                                    colName.toLowerCase()
                                    ? 'selected'
                                    : '';


                                if (selected) {
                                    isMatched = true;
                                }


                                colOptionsHtml +=
                                    `<option
                                        value="${escapeAttr(sCol.name)}"
                                        ${selected}
                                    >
                                        Ambil dari:
                                        ${escapeHtml(sCol.name)}
                                    </option>`;
                            }
                        );
                    }


                    let defaultType =
                        'ignore';


                    if (
                        isIdCol &&
                        !isMatched
                    ) {

                        defaultType =
                            'auto_id';

                    } else if (isMatched) {

                        defaultType =
                            'col';

                    } else if (isNotNull) {

                        defaultType =
                            'custom';
                    }


                    const defaultValue =
                        isNotNull
                        ? (
                            tCol.dflt_value
                            ? tCol.dflt_value
                                .replace(/^'|'$/g, '')
                            : '1'
                        )
                        : '';


                    const safeId =
                        'col_' +
                        Math.random()
                            .toString(36)
                            .substring(2);


                    const row =
                        document.createElement(
                            'div'
                        );


                    row.className =
                        'col-mapping-row';


                    row.innerHTML = `

                        <div
                            style="width:220px;"
                        >

                            <strong>
                                ${escapeHtml(colName)}
                            </strong>

                            ${badge}

                            <div
                                style="
                                    font-size:11px;
                                    color:#64748b;
                                "
                            >

                                Tipe:
                                ${escapeHtml(
                                    tCol.type || 'ANY'
                                )}

                                ${tCol.pk
                                    ? '(PK)'
                                    : ''}

                            </div>

                        </div>


                        <select
                            name="map_type[${escapeAttr(colName)}]"
                            id="type_${safeId}"
                            style="
                                width:170px;
                                padding:4px;
                            "
                        >

                            <option
                                value="col"
                                ${defaultType === 'col'
                                    ? 'selected'
                                    : ''}
                            >
                                Dari Kolom Asal
                            </option>


                            <option
                                value="auto_id"
                                ${defaultType === 'auto_id'
                                    ? 'selected'
                                    : ''}
                            >
                                🔢 Auto-Generate ID (Urut)
                            </option>


                            <option
                                value="custom"
                                ${defaultType === 'custom'
                                    ? 'selected'
                                    : ''}
                            >
                                Nilai Tetap (Manual)
                            </option>


                            <option
                                value="ignore"
                                ${defaultType === 'ignore'
                                    ? 'selected'
                                    : ''}
                            >
                                Abaikan (NULL)
                            </option>

                        </select>


                        <select
                            name="map_col[${escapeAttr(colName)}]"
                            id="source_col_${safeId}"
                            style="
                                flex:1;
                                padding:4px;
                                ${defaultType === 'col'
                                    ? ''
                                    : 'display:none;'}
                            "
                        >

                            ${colOptionsHtml}

                        </select>


                        <input
                            type="text"
                            name="map_value[${escapeAttr(colName)}]"
                            id="source_val_${safeId}"
                            value="${escapeAttr(defaultValue)}"
                            placeholder="${isNotNull
                                ? 'Wajib diisi (NOT NULL)'
                                : 'Nilai konstan / fallback'}"
                            style="
                                flex:1;
                                padding:4px;
                                ${defaultType === 'custom'
                                    ? ''
                                    : 'display:none;'}
                            "
                        >


                        <div
                            id="source_info_${safeId}"
                            style="
                                flex:1;
                                font-size:11px;
                                color:#0284c7;
                                ${defaultType === 'auto_id'
                                    ? ''
                                    : 'display:none;'}
                            "
                        >

                            ⚡ Otomatis generate ID
                            berurutan
                            (+1 dari ID tertinggi)

                        </div>

                    `;


                    container.appendChild(row);


                    const typeSelect =
                        row.querySelector(
                            `#type_${safeId}`
                        );


                    typeSelect.addEventListener(
                        'change',
                        () => {

                            toggleInputMode(
                                safeId
                            );

                        }
                    );

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | TOGGLE MAPPING INPUT
        |--------------------------------------------------------------------------
        */

        function toggleInputMode(id)
        {

            const type =
                document.getElementById(
                    `type_${id}`
                ).value;


            const colSelect =
                document.getElementById(
                    `source_col_${id}`
                );


            const valInput =
                document.getElementById(
                    `source_val_${id}`
                );


            const infoDiv =
                document.getElementById(
                    `source_info_${id}`
                );


            colSelect.style.display =
                type === 'col'
                ? 'block'
                : 'none';


            valInput.style.display =
                type === 'custom'
                ? 'block'
                : 'none';


            infoDiv.style.display =
                type === 'auto_id'
                ? 'block'
                : 'none';
        }


        /*
        |--------------------------------------------------------------------------
        | ESCAPE HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(value)
        {

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }


        /*
        |--------------------------------------------------------------------------
        | ESCAPE ATTRIBUTE
        |--------------------------------------------------------------------------
        */

        function escapeAttr(value)
        {

            return escapeHtml(value);
        }

        </script>


    <?php endif; ?>


</div>

</body>

</html>
