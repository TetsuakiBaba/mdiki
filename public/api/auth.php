<?php
require_once __DIR__ . '/../../mdiki-src/Utils.php';
require_once __DIR__ . '/../../mdiki-src/Auth.php';

$config = require __DIR__ . '/../../mdiki-config.php';

use Mdiki\Auth;
use Mdiki\Utils;

$auth = new Auth($config);

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    $auth->logout();
    header('Location: ../index.php');
    exit;
}

Utils::jsonResponse(['error' => 'Invalid action'], 400);
