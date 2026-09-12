```php
<?php

/*
|--------------------------------------------------------------------------
| KIOS MAFAZA - TOOLBOX
|--------------------------------------------------------------------------
| Otomatis mendeteksi file PHP / HTML dan folder di direktori ini.
|--------------------------------------------------------------------------
*/

$baseDir = __DIR__;
$baseUrl = '/tool/';

$items = [];

// Ambil isi folder
$files = scandir($baseDir);

foreach ($files as $file) {

    // Abaikan . dan ..
    if ($file === '.' || $file === '..') {
        continue;
    }

    // Abaikan file tersembunyi
    if (str_starts_with($file, '.')) {
        continue;
    }

    // index.php adalah dashboard ini sendiri
    if ($file === 'index.php') {
        continue;
    }

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $file;

    // ========================================================
    // FOLDER
    // ========================================================
    if (is_dir($fullPath)) {

        $items[] = [
            'type' => 'folder',
            'name' => $file,
            'url'  => $baseUrl . rawurlencode($file) . '/'
        ];

        continue;
    }

    // ========================================================
    // FILE
    // ========================================================

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    // Hanya tampilkan file web
    $allowedExtensions = [
        'php',
        'html',
        'htm'
    ];

    if (!in_array($extension, $allowedExtensions, true)) {
        continue;
    }

    $items[] = [
        'type' => 'file',
        'name' => $file,
        'extension' => strtoupper($extension),
        'url' => $baseUrl . rawurlencode($file)
    ];
}

// Folder dulu, kemudian file
usort($items, function ($a, $b) {

    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'folder' ? -1 : 1;
    }

    return strnatcasecmp($a['name'], $b['name']);
});

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Kios Mafaza Tools</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #f3f4f6;
            color: #1f2937;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .header {
            background: white;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        .header h1 {
            margin: 0 0 6px;
            font-size: 25px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }

        .tools {
            display: grid;
            grid-template-columns:
                repeat(auto-fill, minmax(230px, 1fr));

            gap: 14px;
        }

        .item {
            display: flex;
            align-items: center;
            gap: 14px;

            background: white;
            padding: 16px;

            border-radius: 12px;

            text-decoration: none;
            color: inherit;

            box-shadow: 0 2px 8px rgba(0,0,0,.05);

            transition:
                transform .15s ease,
                box-shadow .15s ease;
        }

        .item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 16px rgba(0,0,0,.10);
        }

        .icon {
            width: 45px;
            height: 45px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;

            font-size: 22px;

            flex-shrink: 0;
        }

        .folder {
            background: #fef3c7;
        }

        .file {
            background: #e0f2fe;
        }

        .info {
            min-width: 0;
        }

        .name {
            font-weight: 600;
            font-size: 15px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .type {
            margin-top: 3px;
            font-size: 12px;
            color: #9ca3af;
        }

        .empty {
            background: white;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            color: #6b7280;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .tools {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>🧰 Kios Mafaza Tools</h1>

        <p>
            Daftar aplikasi dan tools yang tersedia di server
        </p>

    </div>


    <?php if (empty($items)): ?>

        <div class="empty">
            Belum ada tool yang tersedia.
        </div>

    <?php else: ?>

        <div class="tools">

            <?php foreach ($items as $item): ?>

                <a
                    class="item"
                    href="<?= htmlspecialchars($item['url']) ?>"
                >

                    <?php if ($item['type'] === 'folder'): ?>

                        <div class="icon folder">
                            📁
                        </div>

                        <div class="info">

                            <div class="name">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>

                            <div class="type">
                                Folder
                            </div>

                        </div>

                    <?php else: ?>

                        <div class="icon file">
                            📄
                        </div>

                        <div class="info">

                            <div class="name">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>

                            <div class="type">
                                <?= htmlspecialchars($item['extension']) ?>
                            </div>

                        </div>

                    <?php endif; ?>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <div class="footer">

        <?= count($items) ?> item tersedia

    </div>

</div>

</body>

</html>
```
