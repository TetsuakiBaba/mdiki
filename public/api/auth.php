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

if ($action === 'check') {
    if ($auth->isAuthenticated()) {
        Utils::jsonResponse(['authenticated' => true]);
    } else {
        Utils::jsonResponse(['authenticated' => false], 401);
    }
}

if ($action === 'extend') {
    if ($auth->isAuthenticated()) {
        // Session is already extended by the class constructor (session_start)
        Utils::jsonResponse(['success' => true]);
    } else {
        Utils::jsonResponse(['success' => false], 401);
    }
}

Utils::jsonResponse(['error' => 'Invalid action'], 400);
