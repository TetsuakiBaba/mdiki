<?php

namespace Mdiki;

class Utils
{
    public static function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    public static function generateCSRFToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function sanitizePath($path)
    {
        // Basic path traversal protection
        $path = str_replace(['../', '..\\'], '', $path);
        return ltrim($path, '/');
    }
}
