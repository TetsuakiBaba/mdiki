<?php
require_once __DIR__ . '/../../mdiki-src/Utils.php';
require_once __DIR__ . '/../../mdiki-src/Auth.php';
require_once __DIR__ . '/../../mdiki-src/FileManager.php';
require_once __DIR__ . '/../../mdiki-src/ShareManager.php';

$config = require __DIR__ . '/../../mdiki-config.php';

use Mdiki\Auth;
use Mdiki\Utils;
use Mdiki\FileManager;
use Mdiki\ShareManager;

$auth = new Auth($config);
$fm = new FileManager($config['mdiki_root'], $config['max_upload_size'] ?? 10);
$shares = new ShareManager($config['data_dir'] ?? (__DIR__ . '/../../.mdiki-data'), $config['edit_lock_lifetime'] ?? 120);

$rawData = file_get_contents('php://input');
$jsonData = json_decode($rawData, true) ?? [];

$action = $_REQUEST['action'] ?? $jsonData['action'] ?? '';
$editToken = $_REQUEST['edit_token'] ?? $jsonData['edit_token'] ?? '';
$sharedPath = $shares->resolveToken($editToken);
$isAuthenticated = $auth->isAuthenticated();
$isSharedEditor = $sharedPath !== null;

if (!$isAuthenticated && !$isSharedEditor) {
    Utils::jsonResponse(['error' => 'Unauthorized'], 401);
}

if ($isSharedEditor && !$isAuthenticated && !in_array($action, ['get', 'save', 'upload', 'refresh_lock', 'release_lock'], true)) {
    Utils::jsonResponse(['error' => 'This edit link can only read and save its assigned file'], 403);
}

try {
    switch ($action) {
        case 'create_edit_link':
            if (!$isAuthenticated) {
                Utils::jsonResponse(['error' => 'Unauthorized'], 401);
            }
            $data = !empty($_POST) ? $_POST : $jsonData;
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            $path = $data['path'] ?? '';
            if (pathinfo($path, PATHINFO_EXTENSION) !== 'md') {
                Utils::jsonResponse(['error' => 'Edit links can only be created for markdown files'], 400);
            }
            $fm->readFile($path);
            Utils::jsonResponse([
                'success' => true,
                'token' => $shares->getOrCreateToken($path)
            ]);
            break;

        case 'refresh_lock':
            if (!$isSharedEditor || empty($_SESSION['anonymous_edit_owner'])) {
                Utils::jsonResponse(['error' => 'Invalid edit session'], 403);
            }
            $wantsLock = filter_var($jsonData['active'] ?? $_REQUEST['active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $status = $shares->heartbeat($editToken, $_SESSION['anonymous_edit_owner'], $wantsLock);
            Utils::jsonResponse(['success' => true] + $status);
            break;

        case 'release_lock':
            if ($isSharedEditor && !empty($_SESSION['anonymous_edit_owner'])) {
                $shares->releaseLock($editToken, $_SESSION['anonymous_edit_owner']);
            }
            Utils::jsonResponse(['success' => true]);
            break;

        case 'list':
            if (!$isAuthenticated) {
                Utils::jsonResponse(['error' => 'Unauthorized'], 401);
            }
            $dir = $_GET['dir'] ?? '';
            Utils::jsonResponse($fm->listFiles($dir));
            break;

        case 'get':
            $path = $isSharedEditor ? $sharedPath : ($_GET['path'] ?? '');
            $content = $fm->readFile($path);
            Utils::jsonResponse([
                'content' => $content,
                'hash' => md5($content)
            ]);
            break;

        case 'save':
            $data = !empty($_POST) ? $_POST : $jsonData;

            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }

            $path = $isSharedEditor ? $sharedPath : ($data['path'] ?? '');
            if ($isSharedEditor) {
                if (empty($_SESSION['anonymous_edit_owner']) || !$shares->ownsLock($editToken, $_SESSION['anonymous_edit_owner'])) {
                    Utils::jsonResponse(['error' => 'This file is currently being edited by someone else.'], 409);
                }
            }
            $content = $data['content'] ?? '';
            if ($data['is_base64'] ?? false) {
                if (str_starts_with($content, 'base64:')) {
                    $content = substr($content, 7);
                }
                $content = base64_decode($content);
            }

            // Auto-update date metadata if present in .md files
            if (pathinfo($path, PATHINFO_EXTENSION) === 'md') {
                $today = date('Y-m-d');
                // Matches 'date: ...' at the start of a line
                $content = preg_replace('/^date:\s*.*$/mi', 'date: ' . $today, $content);
            }

            $oldHash = $data['old_hash'] ?? '';

            if (file_exists($fm->getFullPath($path))) {
                $currentContent = $fm->readFile($path);
                $currentHash = md5($currentContent);
                if ($oldHash && $oldHash !== $currentHash) {
                    Utils::jsonResponse(['error' => 'Conflict detected. The file has been modified by someone else.'], 409);
                }
            }

            $fm->saveFile($path, $content);
            if ($isSharedEditor) {
                $shares->refreshLock($editToken, $_SESSION['anonymous_edit_owner']);
            }
            Utils::jsonResponse([
                'success' => true,
                'hash' => md5($content),
                'content' => $content
            ]);
            break;

        case 'delete':
            if (!$isAuthenticated) {
                Utils::jsonResponse(['error' => 'Unauthorized'], 401);
            }
            $data = !empty($_POST) ? $_POST : $jsonData;
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            $fm->deleteFile($data['path']);
            Utils::jsonResponse(['success' => true]);
            break;

        case 'mkdir':
            if (!$isAuthenticated) {
                Utils::jsonResponse(['error' => 'Unauthorized'], 401);
            }
            $data = !empty($_POST) ? $_POST : $jsonData;
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            $fm->createDirectory($data['path']);
            Utils::jsonResponse(['success' => true]);
            break;

        case 'move':
            if (!$isAuthenticated) {
                Utils::jsonResponse(['error' => 'Unauthorized'], 401);
            }
            $data = !empty($_POST) ? $_POST : $jsonData;
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            if ($fm->move($data['oldPath'], $data['newPath'])) {
                Utils::jsonResponse(['success' => true]);
            } else {
                Utils::jsonResponse(['error' => 'Failed to move file'], 500);
            }
            break;

        case 'upload':
            if (!$isAuthenticated && !$isSharedEditor) {
                Utils::jsonResponse(['error' => 'Unauthorized'], 401);
            }
            if (!Utils::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            if (!isset($_FILES['image'])) {
                Utils::jsonResponse(['error' => 'No file uploaded'], 400);
            }
            if ($isSharedEditor) {
                if (empty($_SESSION['anonymous_edit_owner']) || !$shares->ownsLock($editToken, $_SESSION['anonymous_edit_owner'])) {
                    Utils::jsonResponse(['error' => 'This file is currently being edited by someone else.'], 409);
                }
                $sharedDir = dirname($sharedPath);
                $targetDir = ($sharedDir === '.' ? '' : $sharedDir . '/') . '.data';
            } else {
                $targetDir = $_POST['dir'] ?? '';
            }
            $path = $fm->uploadImage($_FILES['image'], $targetDir);
            if ($isSharedEditor) {
                $shares->refreshLock($editToken, $_SESSION['anonymous_edit_owner']);
            }
            Utils::jsonResponse(['success' => true, 'path' => $path]);
            break;

        default:
            Utils::jsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (\Exception $e) {
    Utils::jsonResponse(['error' => $e->getMessage()], 500);
}
