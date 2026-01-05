# Project: mdiki

A simple, lightweight file-based wiki system with a Google Material Design UI.

## Status
- [x] Initialize Project
- [x] Implement Backend Logic (PHP, File-based)
- [x] Implement Frontend UI (Material Design)
- [x] Integrate MarkPaper Rendering
- [x] Implement Image Upload & Management
- [x] Separate Public View (index.php) and Editor (editor.php)

## Architecture & Guidelines
- **Backend**: PHP 8.x with `Mdiki` namespace. No database (pure filesystem).
- **Frontend**: Vanilla JavaScript, Google Material Design 3 style (Roboto, Material Icons).
- **Rendering**: Use [MarkPaper](https://github.com/TetsuakiBaba/MarkPaper) for academic-style Markdown rendering.
- **Storage**: All Markdown and image files are stored in `public/mds/`.
- **Security**:
    - Always sanitize paths to prevent traversal attacks (use `Utils::sanitizePath`).
    - Use CSRF tokens for all state-changing operations (POST/DELETE).
    - Session-based authentication for `editor.php`.
    - Optimistic locking using MD5 hashes to prevent concurrent edit conflicts.

## Directory Structure
- `public/`: Web root.
    - `index.php`: Public portal (Material Design, file list + viewer).
    - `editor.php`: Management UI (Password protected).
    - `mds/`: Data directory for `.md` and image files.
    - `view.html`: Public viewer for individual files.
    - `preview.html`: Real-time preview for the editor.
    - `assets/`: CSS, JS, and MarkPaper assets.
- `mdiki-src/`: PHP source files (`Auth`, `FileManager`, `Utils`).
- `mdiki-config.php`: Global configuration (password, root path).

## UI/UX Rules
- Use Material Icons for all UI actions.
- Maintain a clean, 2-pane layout for both public and editor views.
- Images should be previewable in a modal.
- Support Drag & Drop for file/folder management in the editor.
