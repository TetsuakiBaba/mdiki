# mdiki

A simple, lightweight mdiki system with a Finder-like UI.

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

### 3. Web Server Configuration
It is highly recommended to set the document root to the `public/` directory to prevent direct access to system files.

#### Nginx Example
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/mdiki/public;

    index editor.php;

    location / {
        try_files $uri $uri/ /editor.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }
}
```

### 4. Security Recommendations
- **HTTPS**: Always use HTTPS to protect your password and session data.
- **Password**: Change the default password in `config.php` immediately.

## Directory Structure
- `public/`: Web root.
  - `index.php`: Public view (file list and viewer).
  - `editor.php`: Main editor (password protected).
  - `mds/`: mdiki files (Markdown and images).
- `src/`: Backend logic.
- `config.php`: Configuration.

## Security
- **Auth**: Session-based authentication with a shared password.
- **CSRF**: Tokens required for all state-changing operations.
- **Path Traversal**: All file paths are sanitized and checked against the mdiki root.
- **Optimistic Locking**: Prevents overwriting concurrent changes using content hashes.
