<?php
declare(strict_types=1);

class Auth
{
    private string $configuredPassword;
    private int $sessionTimeout;

    public function __construct(?string $configPath = null)
    {
        $path = $configPath ?? dirname(__DIR__) . '/data/auth.config.php';
        $cfg  = file_exists($path) ? require $path : [];

        $this->configuredPassword = (string)($cfg['dashboard_password'] ?? 'Cresta2026!');
        $this->sessionTimeout     = (int)($cfg['session_timeout'] ?? 28800);
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public function isAuthenticated(): bool
    {
        if (empty($_SESSION['cresta_auth'])) {
            return false;
        }

        if (isset($_SESSION['cresta_last_activity'])) {
            if ((time() - (int)$_SESSION['cresta_last_activity']) > $this->sessionTimeout) {
                $this->destroySession();
                $this->startSession();
                return false;
            }
        }

        $_SESSION['cresta_last_activity'] = time();
        return true;
    }

    public function handleLogoutIfRequested(): void
    {
        if (isset($_GET['logout']) && $_GET['logout'] === '1') {
            $this->destroySession();
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    public function attemptLoginFromPost(): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['access_password'])) {
            return '';
        }

        $submitted = (string)$_POST['access_password'];
        if ($this->configuredPassword !== '' && hash_equals($this->configuredPassword, $submitted)) {
            session_regenerate_id(true);
            $_SESSION['cresta_auth'] = true;
            $_SESSION['cresta_last_activity'] = time();
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        return 'Contraseña incorrecta. Verifica tus credenciales e intenta de nuevo.';
    }

    public function requireAuthOrRenderLogin(string $loginViewPath): void
    {
        $this->startSession();
        $this->handleLogoutIfRequested();
        $loginError = $this->attemptLoginFromPost();

        if (!$this->isAuthenticated()) {
            require $loginViewPath;
            exit;
        }
    }

    private function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
