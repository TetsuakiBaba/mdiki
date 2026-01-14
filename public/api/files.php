<?php
require_once __DIR__ . '/../../mdiki-src/Utils.php';
require_once __DIR__ . '/../../mdiki-src/Auth.php';
require_once __DIR__ . '/../../mdiki-src/FileManager.php';

$config = require __DIR__ . '/../../mdiki-config.php';

use Mdiki\Auth;
use Mdiki\Utils;
use Mdiki\FileManager;

$auth = new Auth($config);
$auth->requireAuth();

$fm = new FileManager($config['mdiki_root']);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $dir = $_GET['dir'] ?? '';
            Utils::jsonResponse($fm->listFiles($dir));
            break;

        case 'get':
            $path = $_GET['path'] ?? '';
            $content = $fm->readFile($path);
            Utils::jsonResponse([
                'content' => $content,
                'hash' => md5($content)
            ]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }

            $path = $data['path'];
            $content = $data['content'];
            if ($data['is_base64'] ?? false) {
                $content = base64_decode($content);
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
            Utils::jsonResponse(['success' => true, 'hash' => md5($content)]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            $fm->deleteFile($data['path']);
            Utils::jsonResponse(['success' => true]);
            break;

        case 'mkdir':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!Utils::verifyCSRFToken($data['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            $fm->createDirectory($data['path']);
            Utils::jsonResponse(['success' => true]);
            break;

        case 'move':
            $data = json_decode(file_get_contents('php://input'), true);
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
            if (!Utils::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                Utils::jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
            if (!isset($_FILES['image'])) {
                Utils::jsonResponse(['error' => 'No file uploaded'], 400);
            }
            $targetDir = $_POST['dir'] ?? '';
            $path = $fm->uploadImage($_FILES['image'], $targetDir);
            Utils::jsonResponse(['success' => true, 'path' => $path]);
            break;

        default:
            Utils::jsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (\Exception $e) {
    Utils::jsonResponse(['error' => $e->getMessage()], 500);
}
