<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/DataProcessor.php';
require_once __DIR__ . '/src/UploadHandler.php';

// 1. Control de acceso y sesión RBAC
$auth = new Auth(__DIR__ . '/data/auth.config.php');
$auth->requireAuthOrRenderLogin(__DIR__ . '/views/login.php');

$currentUser   = $auth->currentUser();
$isManager     = $auth->isManager();
$allowedBuyers = $isManager ? null : $auth->getAllowedBuyers();

$dataDir   = __DIR__ . '/data';
$excelPath = $dataDir . '/DB.xlsx';
$cachePath = $dataDir . '/cache.json';

// 2. Endpoints AJAX protegidos por sesión y aislados por comprador (Row-Level Security)
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $processor = new DataProcessor($excelPath, $cachePath);

    if ($_GET['action'] === 'detail') {
        $type = (string)($_GET['type'] ?? '');
        $id   = (string)($_GET['id'] ?? '');
        echo json_encode($processor->getDocumentDetail($type, $id, $allowedBuyers), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'firm_lines') {
        echo json_encode($processor->getFirmOcLines($allowedBuyers), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'search') {
        $q = (string)($_GET['q'] ?? '');
        echo json_encode($processor->searchLines($q, $allowedBuyers), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 3. Acciones POST de Administración (Exclusivas para Manager)
$uploadHandler     = new UploadHandler($dataDir);
$uploadFeedback    = null;
$userAdminFeedback = null;
$forceRebuild      = $isManager && isset($_GET['rebuild']) && $_GET['rebuild'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['action'] ?? '');

    if ($postAction === 'upload_odoo_files') {
        if (!$isManager) {
            http_response_code(403);
            die('Acceso denegado: solo un usuario con perfil Manager puede cargar reportes de Odoo.');
        }
        $uploadFeedback = $uploadHandler->handleUploads();
        if (!empty($uploadFeedback['ok'])) {
            $forceRebuild = true;
        }
    } elseif ($postAction === 'save_user') {
        if (!$isManager) {
            http_response_code(403);
            die('Acceso denegado: solo un usuario con perfil Manager puede administrar usuarios.');
        }
        $userAdminFeedback = $auth->saveUser($_POST);
    } elseif ($postAction === 'delete_user') {
        if (!$isManager) {
            http_response_code(403);
            die('Acceso denegado: solo un usuario con perfil Manager puede eliminar usuarios.');
        }
        $userAdminFeedback = $auth->deleteUser((string)($_POST['username'] ?? ''));
    }
}

$filesStatus = $isManager ? $uploadHandler->getFilesStatus() : [];

// 4. Carga de datos principales y aplicación de aislamiento RBAC
$loadError = null;
$rawPayload = [
    'cutoff_date'   => date('Y-m-d'),
    'filters'       => ['meses' => [], 'sociedades' => [], 'compradores' => [], 'proveedores' => [], 'solicitantes' => []],
    'oc_stats'      => [],
    'records'       => [],
    'oc_records'    => [],
    'spend_records' => [],
];

try {
    $processor  = new DataProcessor($excelPath, $cachePath);
    $rawPayload = $processor->getProcessedData($forceRebuild);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
    if ($isManager && $uploadFeedback === null) {
        $uploadFeedback = ['ok' => false, 'message' => $loadError];
    }
}

$allOdooBuyers = $rawPayload['filters']['compradores'] ?? [];
$allUsersList  = $isManager ? $auth->getAllUsers() : [];
$payload       = $processor->scopePayloadForUser($rawPayload, $currentUser);
$jsonPayload   = json_encode($payload, JSON_UNESCAPED_UNICODE);

// Versionado dinámico basado en la última modificación de TODOS los assets
$assetFiles = [
    __DIR__ . '/assets/css/app.css',
    __DIR__ . '/assets/js/app.js',
    __DIR__ . '/assets/js/export.js',
    __DIR__ . '/assets/js/modals.js',
    __DIR__ . '/assets/js/module_cycle.js',
    __DIR__ . '/assets/js/module_spend.js',
    __DIR__ . '/assets/js/module_supplier.js',
];
$maxAssetMtime = 0;
foreach ($assetFiles as $af) {
    if (file_exists($af)) {
        $mt = (int)filemtime($af);
        if ($mt > $maxAssetMtime) {
            $maxAssetMtime = $mt;
        }
    }
}
$assetVer = '1.12.' . ($maxAssetMtime > 0 ? $maxAssetMtime : time());
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Compras y Proveedores · Grupo Cresta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        cresta: {
                            navy: '#1B2A4A',
                            blue: '#264073',
                            red: '#C8102E',
                            light: '#F8FAFC'
                        }
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= $assetVer ?>">
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-900 dark:bg-gray-950 dark:text-gray-100">

    <?php require __DIR__ . '/views/header.php'; ?>
    <?php require __DIR__ . '/views/tab_cycle.php'; ?>
    <?php require __DIR__ . '/views/tab_spend.php'; ?>
    <?php require __DIR__ . '/views/tab_supplier.php'; ?>

    <!-- Footer con créditos -->
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4 w-full text-center">
        <p class="text-[11px] text-slate-400 dark:text-gray-500">
            Dashboard construido por <span class="font-semibold text-slate-500 dark:text-gray-400">Elder Velásquez</span> con ayuda de
            <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-cresta-navy dark:hover:text-gray-300">mksbox.com</a>
        </p>
    </footer>

    <?php require __DIR__ . '/views/modals.php'; ?>
    <?php if ($isManager): ?>
        <?php require __DIR__ . '/views/admin_upload.php'; ?>
        <?php require __DIR__ . '/views/admin_users.php'; ?>
    <?php endif; ?>

    <!-- Inyección de datos y módulos JS -->
    <script>
        const RAW_DATA = <?= $jsonPayload ?>;
    </script>
    <script src="assets/js/app.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/export.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/modals.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/module_cycle.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/module_spend.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/module_supplier.js?v=<?= $assetVer ?>"></script>
    <script>
        initTheme();
        initFilters();
        renderDashboard();
    </script>
</body>
</html>
