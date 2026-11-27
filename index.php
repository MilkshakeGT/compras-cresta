<?php
declare(strict_types=1);

date_default_timezone_set('America/Guatemala');

require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/DataProcessor.php';
require_once __DIR__ . '/src/OdooConnector.php';
require_once __DIR__ . '/src/UploadHandler.php';
require_once __DIR__ . '/src/StatusConfig.php';
require_once __DIR__ . '/src/SolNotesManager.php';

// =========================================================================
// SECCIÓN 1: INICIALIZACIÓN DE SERVICIOS Y CONTROL DE ACCESO (RBAC)
// =========================================================================
$dataDir          = __DIR__ . '/data';
$excelPath        = $dataDir . '/DB.xlsx';
$cachePath        = $dataDir . '/cache.json';
$detailsCachePath = $dataDir . '/details_cache.json';
$authConfigPath   = $dataDir . '/auth.config.php';
$odooConfigPath   = $dataDir . '/odoo.config.php';

$auth = new Auth($authConfigPath);
$auth->requireAuthOrRenderLogin(__DIR__ . '/views/login.php');

$currentUser   = $auth->currentUser();
$isManager     = $auth->isManager();
$allowedBuyers = $isManager ? null : $auth->getAllowedBuyers();

$processor       = new DataProcessor($excelPath, $cachePath);
$odooConnector   = new OdooConnector($odooConfigPath, $cachePath, $detailsCachePath);
$statusConfigMgr = new StatusConfig($dataDir);

// =========================================================================
// SECCIÓN 2: ENDPOINTS AJAX PROTEGIDOS (JSON)
// =========================================================================
$action = $_GET['action'] ?? '';
if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if ($action === 'detail') {
        $type = $_GET['type'] ?? '';
        $id   = $_GET['id'] ?? '';
        echo json_encode($processor->getDocumentDetail($type, $id, $allowedBuyers, $currentUser), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'firm_lines') {
        echo json_encode($processor->getFirmOcLines($allowedBuyers), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'search') {
        $q = $_GET['q'] ?? '';
        echo json_encode($processor->searchLines($q, $allowedBuyers), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'save_sol_note') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rawBody = (string)file_get_contents('php://input');
        $jsonIn  = json_decode($rawBody, true);
        if (!is_array($jsonIn)) {
            $jsonIn = $_POST;
        }

        $solId   = trim((string)($jsonIn['id'] ?? ''));
        $rawNote = (string)($jsonIn['note'] ?? '');

        $res = $processor->saveSolicitationNote($solId, $rawNote, $currentUser ?? []);
        if (empty($res['ok'])) {
            http_response_code(403);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida']);
    exit;
}

// =========================================================================
// SECCIÓN 3: ACCIONES ADMINISTRATIVAS (POST · EXCLUSIVAS PARA MANAGER)
// =========================================================================
$uploadFeedback    = null;
$userAdminFeedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'sync_odoo_api') {
        if (!$isManager) {
            $uploadFeedback = ['ok' => false, 'message' => 'Acceso denegado: solo el Manager puede sincronizar Odoo.'];
        } else {
            try {
                $syncResult = $odooConnector->syncFromOdoo();
                $uploadFeedback = [
                    'ok'      => true,
                    'message' => $syncResult['message'],
                ];
            } catch (Throwable $e) {
                $uploadFeedback = [
                    'ok'      => false,
                    'message' => 'Error sincronizando Odoo: ' . $e->getMessage(),
                ];
            }
        }
    } elseif ($postAction === 'upload_excel_files') {
        if (!$isManager) {
            $uploadFeedback = ['ok' => false, 'message' => 'Acceso denegado: solo el Manager puede subir respaldos Excel.'];
        } else {
            $uploader = new UploadHandler($dataDir);
            $uploadFeedback = $uploader->handleUpload($_FILES);
            if (!empty($uploadFeedback['ok'])) {
                try {
                    $processor->getProcessedData(true);
                } catch (Throwable $e) {
                    $uploadFeedback['ok'] = false;
                    $uploadFeedback['message'] .= ' (Aviso: ' . $e->getMessage() . ')';
                }
            }
        }
    } elseif ($postAction === 'save_user') {
        if (!$isManager) {
            $userAdminFeedback = ['ok' => false, 'message' => 'Acceso denegado.', 'tab' => 'users'];
        } else {
            $userAdminFeedback = $auth->saveUser($_POST);
            $userAdminFeedback['tab'] = 'users';
        }
    } elseif ($postAction === 'delete_user') {
        if (!$isManager) {
            $userAdminFeedback = ['ok' => false, 'message' => 'Acceso denegado.', 'tab' => 'users'];
        } else {
            $userAdminFeedback = $auth->deleteUser((string)($_POST['username'] ?? ''));
            $userAdminFeedback['tab'] = 'users';
        }
    } elseif ($postAction === 'save_sync_config') {
        if (!$isManager) {
            $userAdminFeedback = ['ok' => false, 'message' => 'Acceso denegado.', 'tab' => 'dates'];
        } else {
            $userAdminFeedback = $odooConnector->saveSyncConfig($_POST);
            $userAdminFeedback['tab'] = 'dates';
        }
    } elseif ($postAction === 'save_status_config') {
        if (!$isManager) {
            $userAdminFeedback = ['ok' => false, 'message' => 'Acceso denegado.', 'tab' => 'statuses'];
        } else {
            $userAdminFeedback = $statusConfigMgr->saveConfig($_POST);
            $userAdminFeedback['tab'] = 'statuses';
        }
    }
}

// =========================================================================
// SECCIÓN 4: CARGA DE DATOS, FILTRADO RBAC Y VERSIONADO DE ASSETS
// =========================================================================
$forceRebuild = isset($_GET['sync']) && $_GET['sync'] === '1';

try {
    $rawPayload = $processor->getProcessedData($forceRebuild);
    $allOdooBuyers = $rawPayload['filters']['compradores'] ?? [];
    $payload = $processor->scopePayloadForUser($rawPayload, $currentUser);
} catch (Throwable $e) {
    $payload = [
        'cutoff_date'       => date('Y-m-d'),
        'filters'           => ['meses' => [], 'sociedades' => [], 'compradores' => [], 'proveedores' => [], 'solicitantes' => []],
        'oc_stats'          => [],
        'records'           => [],
        'oc_records'        => [],
        'spend_records'     => [],
        'team_avg_by_month' => ['ALL' => []],
        'user_context'      => [
            'role'           => $currentUser['role'] ?? 'buyer',
            'username'       => $currentUser['username'] ?? '',
            'display_name'   => $currentUser['display_name'] ?? '',
            'allowed_buyers' => $allowedBuyers ?? [],
        ],
    ];
    $allOdooBuyers = [];
    if ($uploadFeedback === null) {
        $uploadFeedback = ['ok' => false, 'message' => $e->getMessage()];
    }
}

$allUsersList   = $isManager ? $auth->getAllUsers() : [];
$odooConfigured = $odooConnector->isConfigured();
$odooSyncInfo   = $odooConnector->getLastSyncInfo();
$syncConfig     = $isManager ? $odooConnector->getSyncConfig() : [];
$statusConfig   = $isManager ? $statusConfigMgr->getConfig() : [];

$assetFiles = [
    __DIR__ . '/assets/css/app.css',
    __DIR__ . '/assets/js/app.js',
    __DIR__ . '/assets/js/export.js',
    __DIR__ . '/assets/js/modals.js',
    __DIR__ . '/assets/js/module_cycle.js',
    __DIR__ . '/assets/js/module_spend.js',
    __DIR__ . '/assets/js/module_supplier.js',
];
$assetVer = 1;
foreach ($assetFiles as $af) {
    if (file_exists($af)) {
        $assetVer = max($assetVer, (int)filemtime($af));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Compras y Proveedores · Grupo Cresta</title>

    <script>
        if (localStorage.getItem('cresta_theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Verdana', 'Geneva', 'Tahoma', 'sans-serif'],
                    },
                    colors: {
                        cresta: {
                            navy:  '#1B2A4A',
                            blue:  '#264073',
                            red:   '#C8102E',
                            slate: '#F4F6F9',
                            ink:   '#0F172A'
                        }
                    }
                }
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

    <link rel="stylesheet" href="assets/css/app.css?v=<?= $assetVer ?>">
</head>
<body class="bg-cresta-slate text-cresta-ink dark:bg-gray-950 dark:text-gray-100 antialiased min-h-screen flex flex-col">

    <?php require __DIR__ . '/views/header.php'; ?>

    <?php require __DIR__ . '/views/tab_cycle.php'; ?>
    <?php require __DIR__ . '/views/tab_spend.php'; ?>
    <?php require __DIR__ . '/views/tab_supplier.php'; ?>

    <?php require __DIR__ . '/views/modals.php'; ?>
    <?php if ($isManager): ?>
        <?php require __DIR__ . '/views/admin_upload.php'; ?>
        <?php require __DIR__ . '/views/admin_users.php'; ?>
    <?php endif; ?>

    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mt-6 border-t border-slate-200 dark:border-gray-800 text-xs text-slate-500 dark:text-gray-400 flex flex-col sm:flex-row justify-between gap-2 w-full">
        <span>Grupo Cresta · Gerencia de Compras y Suministros</span>
        <span>Corte al <?= htmlspecialchars((string)$payload['cutoff_date']) ?> · Todos los derechos reservados · Powered by <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-cresta-navy dark:text-indigo-400 hover:underline">mksbox.com</a></span>
    </footer>

    <script>
        const RAW_DATA = <?= json_encode($payload, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="assets/js/app.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/export.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/modals.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/module_cycle.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/module_spend.js?v=<?= $assetVer ?>"></script>
    <script src="assets/js/module_supplier.js?v=<?= $assetVer ?>"></script>

    <!-- =================================================================
         SECCIÓN 5: ARRANQUE AUTOMÁTICO GARANTIZADO DEL DASHBOARD
         ================================================================= -->
    <script>
        (function bootCrestaDashboard() {
            const start = () => {
                if (typeof initFilters === 'function') {
                    const yearSel = document.getElementById('filterYear');
                    if (yearSel && yearSel.options.length === 0) {
                        initFilters();
                    }
                }
                if (typeof renderDashboard === 'function') {
                    renderDashboard();
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start);
            } else {
                start();
            }
        })();
    </script>
</body>
</html>
