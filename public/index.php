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
            position: relative;
        }

        /* Sidebar Styles */
        #sidebar {
            width: var(--sidebar-width);
            min-width: 150px;
            max-width: 600px;
            background: var(--sidebar-bg);
            border-right: 1px solid #dadce0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            transition: transform 0.3s ease;
            z-index: 100;
        }

        #sidebar-resizer {
            width: 4px;
            cursor: col-resize;
            background: transparent;
            transition: background 0.2s;
            user-select: none;
            z-index: 10;
        }

        #sidebar-resizer:hover,
        #sidebar-resizer.resizing {
            background: var(--primary-color);
        }

        body.resizing {
            cursor: col-resize;
            user-select: none;
        }

        body.resizing iframe {
            pointer-events: none;
        }

        #sidebar.open {
            transform: translateX(0);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 90;
        }

        .sidebar-overlay.show {
            display: block;
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

        .nav-item span:not(.material-icons) {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .dir-group {
            margin-bottom: 8px;
        }

        .dir-header {
            display: flex;
            align-items: center;
            padding: 0 24px;
            height: 40px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-color);
            cursor: pointer;
            user-select: none;
            transition: background 0.2s;
        }

        .dir-header span:not(.material-icons) {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .dir-header:hover {
            background-color: #f1f3f4;
            border-radius: 0 20px 20px 0;
            margin-right: 8px;
        }

        .toggle-icon {
            margin-right: 8px;
            font-size: 18px;
            transition: transform 0.2s;
        }

        .dir-group.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .dir-group.collapsed .dir-children {
            display: none;
        }

        .dir-children {
            margin-left: 12px;
            border-left: 1px solid #dadce0;
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
            gap: 12px;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
        }

        .menu-toggle:hover {
            background-color: #f1f3f4;
        }

        .current-file-title {
            font-size: 18px;
            font-weight: 400;
            color: #3c4043;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        /* Responsive Styles */
        @media (max-width: 768px) {
            #sidebar {
                position: absolute;
                top: 0;
                left: 0;
                bottom: 0;
                transform: translateX(-100%);
                box-shadow: 4px 0 8px rgba(0, 0, 0, 0.1);
            }

            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            header {
                padding: 0 12px;
            }

            .current-file-title {
                font-size: 16px;
            }

            .header-actions span:not(.material-icons) {
                display: none;
            }

            .header-actions .nav-item {
                padding: 0 !important;
                width: 36px;
                justify-content: center;
                border-radius: 50% !important;
            }

            .header-actions .nav-item .material-icons {
                margin-right: 0;
            }
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
        <div class="sidebar-overlay" id="overlay"></div>
        <aside id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo-container">
                    <span class="material-icons logo-icon"><?= htmlspecialchars($config['site_icon'] ?? 'description') ?></span>
                    <span class="logo-text"><?= htmlspecialchars($config['site_title'] ?? 'mdiki') ?></span>
                </a>
            </div>
            <div class="nav-container">
                <?php
                function isDescendantActive($items, $activePath)
                {
                    foreach ($items as $item) {
                        if ($item['path'] === $activePath) return true;
                        if ($item['is_dir'] && isDescendantActive($item['children'], $activePath)) return true;
                    }
                    return false;
                }

                function renderMaterialMenu($items, $activePath = '', $isRoot = true)
                {
                    foreach ($items as $item) {
                        if ($item['is_dir']) {
                            // Root level folders are expanded by default
                            $isCollapsed = !$isRoot && !isDescendantActive($item['children'], $activePath);
                            $collapsedClass = $isCollapsed ? 'collapsed' : '';
                            echo '<div class="dir-group ' . $collapsedClass . '">';
                            echo '<div class="dir-header" onclick="this.parentElement.classList.toggle(\'collapsed\')" title="' . htmlspecialchars($item['name']) . '">';
                            echo '<span class="material-icons toggle-icon">expand_more</span>';
                            echo '<span>' . htmlspecialchars($item['name']) . '</span>';
                            echo '</div>';
                            echo '<div class="dir-children">';
                            renderMaterialMenu($item['children'], $activePath, false);
                            echo '</div>';
                            echo '</div>';
                        } else {
                            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                            if ($ext === 'md') {
                                $isActive = ($item['path'] === $activePath) ? 'active' : '';
                                echo '<a href="index.php?file=' . urlencode($item['path']) . '" class="nav-item ' . $isActive . '" title="' . htmlspecialchars($item['name']) . '">';
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
            <?php if (!empty($config['default_license'])): ?>
                <div class="sidebar-footer" style="padding: 16px; font-size: 11px; color: #70757a; border-top: 1px solid #dadce0; line-height: 1.4;">
                    <?= htmlspecialchars($config['default_license']) ?>
                </div>
            <?php endif; ?>
        </aside>
        <div id="sidebar-resizer"></div>

        <main id="main-content">
            <header>
                <button class="material-icons menu-toggle" id="menu-btn">menu</button>
                <div class="current-file-title">
                    <?= htmlspecialchars(basename($currentFile)) ?>
                </div>
                <div class="header-actions">
                    <a href="editor.php?file=<?= urlencode($currentFile) ?>" class="nav-item" style="height: 36px; border-radius: 18px; padding: 0 16px; background: #f1f3f4;">
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
        const menuBtn = document.getElementById('menu-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const resizer = document.getElementById('sidebar-resizer');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        menuBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Sidebar resizing
        let isResizing = false;

        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.classList.add('resizing');
            resizer.classList.add('resizing');
        });

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            const newWidth = e.clientX;
            if (newWidth > 150 && newWidth < 600) {
                sidebar.style.width = `${newWidth}px`;
                document.documentElement.style.setProperty('--sidebar-width', `${newWidth}px`);
            }
        });

        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                document.body.classList.remove('resizing');
                resizer.classList.remove('resizing');
            }
        });

        // 画面サイズが変わった時にサイドバーの状態をリセット
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        });
    </script>
</body>

</html>