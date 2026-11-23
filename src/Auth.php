<?php
declare(strict_types=1);

class Auth
{
    private string $legacyConfigPath;
    private string $usersFilePath;
    private int $sessionTimeout;

    public function __construct(string $legacyConfigPath)
    {
        $this->legacyConfigPath = $legacyConfigPath;
        $dataDir = dirname($legacyConfigPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->usersFilePath  = $dataDir . '/users.json';
        $this->sessionTimeout = 28800; // 8 horas por defecto

        if (file_exists($this->legacyConfigPath)) {
            $cfg = require $this->legacyConfigPath;
            if (is_array($cfg) && isset($cfg['session_timeout'])) {
                $this->sessionTimeout = (int)$cfg['session_timeout'];
            }
        }

        $this->ensureUsersStoreInitialized();
    }

    // =========================================================================
    // INICIALIZACIÓN Y PERSISTENCIA DE USUARIOS (data/users.json)
    // =========================================================================
    private function ensureUsersStoreInitialized(): void
    {
        if (file_exists($this->usersFilePath)) {
            return;
        }

        // Migrar contraseña existente de auth.config.php al usuario manager inicial
        $initialPass = 'Cresta2026!';
        if (file_exists($this->legacyConfigPath)) {
            $cfg = require $this->legacyConfigPath;
            if (is_array($cfg) && !empty($cfg['dashboard_password'])) {
                $initialPass = (string)$cfg['dashboard_password'];
            }
        }

        $defaultUsers = [
            'evelasquez' => [
                'username'     => 'evelasquez',
                'display_name' => 'Elder Velásquez',
                'password_hash'=> password_hash($initialPass, PASSWORD_DEFAULT),
                'role'         => 'manager', // 'manager' | 'buyer'
                'odoo_buyers'  => [],
                'active'       => true,
                'created_at'   => date('Y-m-d H:i:s'),
                'last_login'   => null,
            ]
        ];

        $this->writeUsersStore($defaultUsers);
    }

    public function getAllUsers(): array
    {
        $this->ensureUsersStoreInitialized();
        $raw = file_get_contents($this->usersFilePath);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }

    private function writeUsersStore(array $users): bool
    {
        $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->usersFilePath, $json, LOCK_EX) !== false;
    }

    public function normalizeUsername(string $username): string
    {
        $clean = mb_strtolower(trim($username));
        return preg_replace('/[^a-z0-9._-]/', '', $clean) ?? '';
    }

    // =========================================================================
    // GESTIÓN DE SESIÓN SEGURA
    // =========================================================================
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') == 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public function isAuthenticated(): bool
    {
        $this->startSession();
        if (empty($_SESSION['cresta_auth']) || empty($_SESSION['cresta_user']['username'])) {
            return false;
        }

        $lastActivity = (int)($_SESSION['cresta_last_activity'] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > $this->sessionTimeout) {
            $this->logout();
            return false;
        }

        // Sincronizar permisos en vivo desde users.json por si un manager cambió roles o compradores
        $uname = (string)$_SESSION['cresta_user']['username'];
        $users = $this->getAllUsers();
        if (!isset($users[$uname]) || empty($users[$uname]['active'])) {
            $this->logout();
            return false;
        }

        $_SESSION['cresta_user'] = [
            'username'     => $users[$uname]['username'],
            'display_name' => $users[$uname]['display_name'],
            'role'         => $users[$uname]['role'],
            'odoo_buyers'  => array_values((array)($users[$uname]['odoo_buyers'] ?? [])),
        ];
        $_SESSION['cresta_last_activity'] = time();

        return true;
    }

    public function currentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $_SESSION['cresta_user'] ?? null;
    }

    public function isManager(): bool
    {
        $u = $this->currentUser();
        return $u !== null && ($u['role'] ?? '') === 'manager';
    }

    public function getAllowedBuyers(): array
    {
        $u = $this->currentUser();
        if ($u === null || ($u['role'] ?? '') === 'manager') {
            return [];
        }
        return array_values(array_filter(array_map('strval', (array)($u['odoo_buyers'] ?? []))));
    }

    public function attemptLoginFromPost(): ?string
    {
        $this->startSession();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login_username'], $_POST['login_password'])) {
            return null;
        }

        $username = $this->normalizeUsername((string)$_POST['login_username']);
        $password = (string)$_POST['login_password'];

        if ($username === '' || $password === '') {
            return 'Ingresa tu usuario y contraseña.';
        }

        $users = $this->getAllUsers();
        if (!isset($users[$username])) {
            return 'Usuario o contraseña incorrectos.';
        }

        $user = $users[$username];
        if (empty($user['active'])) {
            return 'Este usuario se encuentra desactivado. Contacta al administrador.';
        }

        if (!password_verify($password, (string)($user['password_hash'] ?? ''))) {
            return 'Usuario o contraseña incorrectos.';
        }

        session_regenerate_id(true);
        $_SESSION['cresta_auth']          = true;
        $_SESSION['cresta_last_activity'] = time();
        $_SESSION['cresta_user']          = [
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'role'         => $user['role'],
            'odoo_buyers'  => array_values((array)($user['odoo_buyers'] ?? [])),
        ];

        // Registrar último acceso
        $users[$username]['last_login'] = date('Y-m-d H:i:s');
        $this->writeUsersStore($users);

        return null;
    }

    public function logout(): void
    {
        $this->startSession();
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

    public function requireAuthOrRenderLogin(string $loginViewPath): void
    {
        if (isset($_GET['logout']) && $_GET['logout'] === '1') {
            $this->logout();
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        $loginError = $this->attemptLoginFromPost();

        if ($this->isAuthenticated()) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
            return;
        }

        http_response_code(401);
        require $loginViewPath;
        exit;
    }

    // =========================================================================
    // CRUD DE USUARIOS PARA EL MODAL DE ADMINISTRACIÓN (SOLO MANAGER)
    // =========================================================================
    public function saveUser(array $input): array
    {
        if (!$this->isManager()) {
            return ['ok' => false, 'message' => 'Acceso denegado: se requiere perfil Manager.'];
        }

        $mode        = trim((string)($input['user_mode'] ?? 'create')); // 'create' | 'edit'
        $username    = $this->normalizeUsername((string)($input['username'] ?? ''));
        $displayName = trim((string)($input['display_name'] ?? ''));
        $role        = trim((string)($input['role'] ?? 'buyer'));
        $password    = (string)($input['password'] ?? '');
        $active      = !empty($input['active']);
        $rawBuyers   = is_array($input['odoo_buyers'] ?? null) ? $input['odoo_buyers'] : [];
        $odooBuyers  = array_values(array_unique(array_filter(array_map('trim', $rawBuyers), fn($v) => $v !== '')));

        if ($username === '' || strlen($username) < 3) {
            return ['ok' => false, 'message' => 'El nombre de usuario debe tener al menos 3 caracteres (letras, números, punto o guion).'];
        }
        if ($displayName === '') {
            return ['ok' => false, 'message' => 'Ingresa el nombre visible del usuario.'];
        }
        if (!in_array($role, ['manager', 'buyer'], true)) {
            $role = 'buyer';
        }
        if ($role === 'buyer' && empty($odooBuyers)) {
            return ['ok' => false, 'message' => 'Debes asignar al menos un comprador de Odoo para un usuario con perfil Comprador.'];
        }

        $users = $this->getAllUsers();

        if ($mode === 'create') {
            if (isset($users[$username])) {
                return ['ok' => false, 'message' => "El usuario '{$username}' ya existe."];
            }
            if (strlen($password) < 6) {
                return ['ok' => false, 'message' => 'La contraseña inicial debe tener al menos 6 caracteres.'];
            }

            $users[$username] = [
                'username'      => $username,
                'display_name'  => $displayName,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role'          => $role,
                'odoo_buyers'   => $role === 'manager' ? [] : $odooBuyers,
                'active'        => $active,
                'created_at'    => date('Y-m-d H:i:s'),
                'last_login'    => null,
            ];

            $this->writeUsersStore($users);
            return ['ok' => true, 'message' => "Usuario '{$username}' creado correctamente."];
        }

        // Modo edición
        if (!isset($users[$username])) {
            return ['ok' => false, 'message' => "No se encontró el usuario '{$username}'."];
        }

        $current = $this->currentUser();
        if ($current && $current['username'] === $username) {
            if (!$active) {
                return ['ok' => false, 'message' => 'No puedes desactivar tu propio usuario activo.'];
            }
            if ($role !== 'manager') {
                return ['ok' => false, 'message' => 'No puedes quitarte el rol Manager a ti mismo.'];
            }
        }

        $users[$username]['display_name'] = $displayName;
        $users[$username]['role']         = $role;
        $users[$username]['odoo_buyers']  = $role === 'manager' ? [] : $odooBuyers;
        $users[$username]['active']       = $active;

        if ($password !== '') {
            if (strlen($password) < 6) {
                return ['ok' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
            }
            $users[$username]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->writeUsersStore($users);
        return ['ok' => true, 'message' => "Permisos y datos de '{$username}' actualizados."];
    }

    public function deleteUser(string $rawUsername): array
    {
        if (!$this->isManager()) {
            return ['ok' => false, 'message' => 'Acceso denegado.'];
        }

        $username = $this->normalizeUsername($rawUsername);
        $current  = $this->currentUser();
        if ($current && $current['username'] === $username) {
            return ['ok' => false, 'message' => 'No puedes eliminar tu propio usuario.'];
        }

        $users = $this->getAllUsers();
        if (!isset($users[$username])) {
            return ['ok' => false, 'message' => 'Usuario no encontrado.'];
        }

        unset($users[$username]);
        $this->writeUsersStore($users);
        return ['ok' => true, 'message' => "Usuario '{$username}' eliminado."];
    }
}
