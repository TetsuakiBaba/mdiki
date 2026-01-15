<?php

namespace Mdiki;

class Auth
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->config['session_name']);
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'gc_maxlifetime' => $this->config['session_lifetime'],
                'cookie_lifetime' => $this->config['session_lifetime'],
            ]);
        }

        // タイムアウトのチェック
        if ($this->isAuthenticated()) {
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->config['session_lifetime'])) {
                $this->logout();
            } else {
                $_SESSION['last_activity'] = time();
            }
        }
    }

    public function login($password)
    {
        if ($password === $this->config['password']) {
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    public function isAuthenticated()
    {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }

    public function logout()
    {
        $_SESSION = [];
        session_destroy();
    }

    public function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            Utils::jsonResponse(['error' => 'Unauthorized'], 401);
        }
    }
}
