<?php
require_once __DIR__ . '/../mdiki-src/Utils.php';
require_once __DIR__ . '/../mdiki-src/Auth.php';
require_once __DIR__ . '/../mdiki-src/FileManager.php';

$config = require __DIR__ . '/../mdiki-config.php';

use Mdiki\Auth;
use Mdiki\Utils;

$auth = new Auth($config);

if (!$auth->isAuthenticated()) {
    // Simple login page
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($auth->login($_POST['password'])) {
            header('Location: editor.php');
            exit;
        } else {
            $error = "Invalid password";
        }
    }
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Login - mdiki</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body {
                font-family: sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: #f0f2f5;
            }

            .login-box {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            input {
                display: block;
                width: 100%;
                margin-bottom: 1rem;
                padding: 0.5rem;
                box-sizing: border-box;
            }

            button {
                width: 100%;
                padding: 0.5rem;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
    </head>

    <body>
        <div class="login-box">
            <h2>mdiki</h2>
            <?php if (isset($error)): ?><p style="color:red"><?= $error ?></p><?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Password" required autofocus>
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
            <div class="logo">
                <span class="material-icons">description</span>
                <span>mdiki</span>
            </div>
            <div class="toolbar">
                <button id="new-file" title="New File"><span class="material-icons">note_add</span></button>
                <button id="new-folder" title="New Folder"><span class="material-icons">create_new_folder</span></button>
                <button id="upload-image" title="Upload Image"><span class="material-icons">image</span></button>
                <button id="save-file" title="Save"><span class="material-icons">save</span></button>
                <button id="copy-link" title="Copy Link"><span class="material-icons">link</span></button>
                <button id="show-cheatsheet" title="Help"><span class="material-icons">help_outline</span></button>
                <button id="logout" title="Logout"><span class="material-icons">logout</span></button>
            </div>
        </header>
        <main>
            <aside id="sidebar">
                <div id="file-tree"></div>
            </aside>
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

    <input type="file" id="image-input" style="display: none;" accept="image/*">

    <!-- Cheat Sheet Modal -->
    <div id="cheatsheet-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>MarkPaper Markdown Cheat Sheet</h2>
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
    </script>
    <script src="assets/js/app.js"></script>
</body>

</html>