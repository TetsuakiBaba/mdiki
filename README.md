# mdiki
A lightweight, file-based wiki system for Markdown documents with a Material Design UI.

## Features
- **No Accounts**: Single password access.
- **Markdown Based**: Files are stored as `.md` files in the filesystem.
- **Finder UI**: Easy file management with a tree view.
- **MarkPaper Rendering**: Beautiful academic-style rendering.
- **Secure**: Path traversal protection, CSRF tokens, and optimistic locking.

## Setup
1. Place the files on a PHP-enabled server.
2. Ensure `public/mds/` directory is writable by the web server.
3. Edit `config.php` to set your password.
4. Access `public/index.php` for public view, or `public/editor.php` for editing.

## Deployment

### 1. Server Requirements
- PHP 8.0 or higher.
- Web server (Apache, Nginx, etc.).

### 2. Directory Permissions
The `public/mds/` directory must be writable by the web server user (e.g., `www-data`).
```bash
chmod -R 775 public/mds/
chown -R www-data:www-data public/mds/
```
### 3. Security Recommendations
- **HTTPS**: Always use HTTPS to protect your password and session data.
- **Password**: Change the default password in `mdiki-config.php` immediately.

## Directory Structure
- `public/`: Web root.
  - `index.php`: Public view (file list and viewer).
  - `editor.php`: Main editor (password protected).
  - `mds/`: mdiki files (Markdown and images).
- `mdiki-src/`: Backend logic.
- `mdiki-config.php`: Configuration.

```
server-root/
├── mdiki-config.php
├── mdiki-src/
└── public/
```
## Security
- **Auth**: Session-based authentication with a shared password.
- **CSRF**: Tokens required for all state-changing operations.
- **Path Traversal**: All file paths are sanitized and checked against the mdiki root.
- **Optimistic Locking**: Prevents overwriting concurrent changes using content hashes.
