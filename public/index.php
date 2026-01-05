<?php
require_once __DIR__ . '/../mdiki-src/Utils.php';
require_once __DIR__ . '/../mdiki-src/FileManager.php';

$config = require __DIR__ . '/../mdiki-config.php';

use Mdiki\FileManager;

$fm = new FileManager($config['mdiki_root']);
$files = $fm->listFiles();
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mdiki - Public View</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a73e8;
            --sidebar-width: 280px;
            --header-height: 64px;
            --bg-color: #ffffff;
            --sidebar-bg: #f8f9fa;
            --text-color: #3c4043;
            --hover-bg: #e8f0fe;
            --active-bg: #e8f0fe;
            --active-text: #1a73e8;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Roboto', sans-serif;
            color: var(--text-color);
            overflow: hidden;
        }

        #app {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* Sidebar Styles */
        #sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid #dadce0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid transparent;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-color);
        }

        .logo-icon {
            color: var(--primary-color);
            font-size: 24px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 400;
            letter-spacing: -0.5px;
        }

        .nav-container {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0 24px;
            height: 48px;
            text-decoration: none;
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            border-radius: 0 24px 24px 0;
            margin-right: 8px;
            transition: background 0.2s;
            cursor: pointer;
        }

        .nav-item:hover {
            background-color: #f1f3f4;
        }

        .nav-item.active {
            background-color: var(--active-bg);
            color: var(--active-text);
        }

        .nav-item .material-icons {
            margin-right: 12px;
            font-size: 20px;
        }

        .dir-group {
            margin-bottom: 8px;
        }

        .dir-header {
            display: flex;
            align-items: center;
            padding: 0 24px;
            height: 40px;
            font-size: 12px;
            font-weight: 500;
            color: #70757a;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* Main Content Styles */
        #main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid #dadce0;
            background: white;
        }

        .current-file-title {
            font-size: 18px;
            font-weight: 400;
            color: #3c4043;
            flex: 1;
        }

        #viewer-container {
            flex: 1;
            position: relative;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Scrollbar Styles */
        .nav-container::-webkit-scrollbar {
            width: 8px;
        }

        .nav-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-container::-webkit-scrollbar-thumb {
            background: #dadce0;
            border-radius: 4px;
        }

        .nav-container::-webkit-scrollbar-thumb:hover {
            background: #bdc1c6;
        }
    </style>
</head>

<body>
    <div id="app">
        <aside id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo-container">
                    <span class="material-icons logo-icon">description</span>
                    <span class="logo-text">mdiki</span>
                </a>
            </div>
            <div class="nav-container">
                <?php
                function renderMaterialMenu($items, $activePath = '')
                {
                    foreach ($items as $item) {
                        if ($item['is_dir']) {
                            echo '<div class="dir-group">';
                            echo '<div class="dir-header">' . htmlspecialchars($item['name']) . '</div>';
                            renderMaterialMenu($item['children'], $activePath);
                            echo '</div>';
                        } else {
                            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                            if ($ext === 'md') {
                                $isActive = ($item['path'] === $activePath) ? 'active' : '';
                                echo '<a href="index.php?file=' . urlencode($item['path']) . '" class="nav-item ' . $isActive . '">';
                                echo '<span class="material-icons">article</span>';
                                echo '<span>' . htmlspecialchars($item['name']) . '</span>';
                                echo '</a>';
                            }
                        }
                    }
                }
                $currentFile = $_GET['file'] ?? 'index.md';
                renderMaterialMenu($files, $currentFile);
                ?>
            </div>
        </aside>

        <main id="main-content">
            <header>
                <div class="current-file-title">
                    <?= htmlspecialchars(basename($currentFile)) ?>
                </div>
                <div class="header-actions">
                    <a href="editor.php" class="nav-item" style="height: 36px; border-radius: 18px; padding: 0 16px; background: #f1f3f4;">
                        <span class="material-icons" style="font-size: 18px;">edit</span>
                        <span>Edit</span>
                    </a>
                </div>
            </header>
            <div id="viewer-container">
                <iframe name="viewer" src="view.html?file=<?= urlencode($currentFile) ?>"></iframe>
            </div>
        </main>
    </div>

    <script>
        // アクティブなアイテムへのスムーズなスクロールや、iframeの読み込み管理などが必要な場合はここに追加
    </script>
</body>

</html>