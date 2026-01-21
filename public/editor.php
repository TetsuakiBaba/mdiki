<?php
require_once __DIR__ . '/../mdiki-src/Utils.php';
require_once __DIR__ . '/../mdiki-src/version.php';
require_once __DIR__ . '/../mdiki-src/Auth.php';
require_once __DIR__ . '/../mdiki-src/FileManager.php';

$config = require __DIR__ . '/../mdiki-config.php';

use Mdiki\Auth;
use Mdiki\Utils;
use Mdiki\AppInfo;

$auth = new Auth($config);

if (!$auth->isAuthenticated()) {
    // Simple login page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($auth->login($_POST['password'])) {
            $redirect = $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = "Invalid password";
        }
    }
?>
    <!DOCTYPE html>
    <html lang="ja">

    <head>
        <title>Login - <?= htmlspecialchars($config['site_title'] ?? 'mdiki') ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <style>
            :root {
                --primary-color: #1a73e8;
                --text-color: #3c4043;
                --bg-color: #f8f9fa;
                --border-color: #dadce0;
            }

            body {
                font-family: 'Roboto', sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: var(--bg-color);
                color: var(--text-color);
            }

            .login-box {
                background: white;
                padding: 40px;
                border-radius: 28px;
                box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
                width: 100%;
                max-width: 360px;
                text-align: center;
            }

            .logo {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                margin-bottom: 24px;
                color: var(--text-color);
            }

            .logo .material-icons {
                color: var(--primary-color);
                font-size: 32px;
            }

            .logo h2 {
                margin: 0;
                font-size: 24px;
                font-weight: 400;
            }

            .error-message {
                color: #d93025;
                background: #fce8e6;
                padding: 8px 12px;
                border-radius: 8px;
                margin-bottom: 16px;
                font-size: 14px;
            }

            .input-group {
                margin-bottom: 24px;
                text-align: left;
            }

            input[type="password"] {
                display: block;
                width: 100%;
                padding: 12px 16px;
                font-size: 16px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                box-sizing: border-box;
                font-family: inherit;
                transition: border-color 0.2s, box-shadow 0.2s;
            }

            input[type="password"]:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
            }

            button {
                width: 100%;
                padding: 12px;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 100px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.2s, box-shadow 0.2s;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            button:hover {
                background: #1557b0;
                box-shadow: 0 1px 3px 1px rgba(60, 64, 67, 0.15), 0 1px 2px rgba(60, 64, 67, 0.3);
            }

            button:active {
                background: #174ea6;
            }
        </style>
    </head>

    <body>
        <div class="login-box">
            <div class="logo">
                <span class="material-icons"><?= htmlspecialchars($config['site_icon'] ?? 'description') ?></span>
                <h2><?= htmlspecialchars($config['site_title'] ?? 'mdiki') ?></h2>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="text"
                    name="username"
                    autocomplete="username"
                    value="mdiki.<?= htmlspecialchars($config['site_title'] ?? 'mdiki') ?>"
                    style="display: none;">
                <div class="input-group">
                    <input type="password" name="password" placeholder="Password" required autofocus>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Main UI
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>mdiki - Editor</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div id="app">
        <header>
            <a href="index.php" class="logo" style="text-decoration: none; color: inherit;">
                <span class="material-icons"><?= htmlspecialchars($config['site_icon'] ?? 'description') ?></span>
                <span><?= htmlspecialchars($config['site_title'] ?? 'mdiki') ?></span>
            </a>
            <div class="toolbar">
                <button id="new-file" title="New File"><span class="material-icons">note_add</span></button>
                <button id="new-folder" title="New Folder"><span class="material-icons">create_new_folder</span></button>
                <button id="toggle-hidden" title="Toggle Hidden Files"><span class="material-icons">visibility_off</span></button>
                <button id="save-file" title="Save"><span class="material-icons">save</span></button>
                <button id="copy-link" title="Copy Link"><span class="material-icons">link</span></button>
                <button id="show-cheatsheet" title="About & Help"><span class="material-icons">info</span></button>
                <button id="logout" title="Logout"><span class="material-icons">logout</span></button>
            </div>
        </header>
        <main>
            <aside id="sidebar">
                <div id="file-tree"></div>
                <?php if (!empty($config['default_license'])): ?>
                    <div class="sidebar-footer" style="padding: 16px; font-size: 11px; color: #70757a; border-top: 1px solid #dadce0; line-height: 1.4;">
                        <div><?= htmlspecialchars($config['default_license']) ?></div>
                        <div style="margin-top: 4px; opacity: 0.7;">v<?= htmlspecialchars(AppInfo::VERSION) ?></div>
                    </div>
                <?php endif; ?>
            </aside>
            <div id="sidebar-resizer"></div>
            <section id="editor-container">
                <div id="editor-header">
                    <input type="text" id="file-path" readonly>
                </div>
                <div id="editor-split">
                    <textarea id="markdown-editor"></textarea>
                    <iframe id="preview-frame" src="preview.html"></iframe>
                </div>
            </section>
        </main>
    </div>

    </div>

    <!-- Cheat Sheet Modal -->
    <div id="cheatsheet-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>

            <h2>Markdown Syntax Guide</h2>
            <div class="cheat-sheet-grid">
                <div class="cheat-sheet-item">
                    <h3>Document Metadata</h3>
                    <p>Write at the beginning of the document to generate an academic-style header and custom footer.</p>
                    <pre># Title
author: Author Name
date: 2024-01-15
institution: Organization
email: email@example.com
footer: © 2024 My Wiki</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Headings</h3>
                    <p>h2 and h3 are automatically numbered. h2 is displayed in the Table of Contents (TOC).</p>
                    <pre># h1 Title
## h2 Heading (Numbered, TOC)
### h3 Heading (Numbered)
#### h4 Heading</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Alerts (GitHub-style)</h3>
                    <pre>> [!NOTE]
> Useful information.

> [!TIP]
> Helpful hints.

> [!IMPORTANT]
> Important information.

> [!WARNING]
> Warning message.

> [!CAUTION]
> Caution message.</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Footnotes</h3>
                    <pre>Add a footnote[^1] in the text.

[^1]: Footnote content. Displayed at the end of the section.</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Images</h3>
                    <p>Captions and width specification are supported.</p>
                    <pre>![Caption](image.jpg)
![](image.jpg){width="50%"}</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Lists</h3>
                    <pre>- Unordered list
  - Nested item
1. Ordered list
   1. Nested item</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Tables</h3>
                    <pre>| Header | Title |
|--------|-------|
| Cell   | Cell  |</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Code</h3>
                    <pre>`inline code`

```javascript
// code block
console.log("Hello");
```</pre>
                </div>
                <div class="cheat-sheet-item">
                    <h3>Emphasis & Others</h3>
                    <pre>**Bold**  *Italic*  ~~Strikethrough~~
--- (Horizontal Rule)
[Link](https://...)</pre>
                </div>
            </div>

            <div class="about-section" style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #dadce0;">
                <h2 style="margin-top: 0; margin-bottom: 16px;">About mdiki</h2>
                <p style="margin-bottom: 16px; line-height: 1.6;">
                    A simple, lightweight file-based wiki system with a Google Material Design UI.
                    Built to be fast, secure, and easy to use without any database setup.
                </p>

                <div style="display: grid; grid-template-columns: 100px 1fr; gap: 8px 16px; align-items: start; font-size: 14px;">
                    <strong style="color: #202124;">Version</strong>
                    <div>v<?= htmlspecialchars(AppInfo::VERSION) ?></div>

                    <strong style="color: #202124;">Project</strong>
                    <div>
                        <a href="https://github.com/TetsuakiBaba/mdiki" target="_blank" style="color: #1a73e8; text-decoration: none;">GitHub Repository</a>
                    </div>

                    <strong style="color: #202124;">Author</strong>
                    <div>
                        Tetsuaki Baba
                        <span style="color: #dadce0; margin: 0 8px;">|</span>
                        <a href="https://tetsuakibaba.jp" target="_blank" style="color: #1a73e8; text-decoration: none;">Website</a>
                    </div>

                    <strong style="color: #202124;">Rendering</strong>
                    <div>
                        Powered by <a href="https://github.com/TetsuakiBaba/MarkPaper" target="_blank" style="color: #1a73e8; text-decoration: none;">MarkPaper</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="image-modal" class="modal">
        <div class="modal-content image-modal-content">
            <span class="close-button">&times;</span>
            <div style="text-align: center;">
                <img id="modal-image" src="" alt="Preview" style="max-width: 100%; height: auto; border-radius: 4px;">
                <p id="modal-image-name" style="margin-top: 10px; font-weight: bold;"></p>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = '<?= Utils::generateCSRFToken() ?>';
        const MAX_UPLOAD_SIZE = <?= (int)($config['max_upload_size'] ?? 10) ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>

</html>