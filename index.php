<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/DataProcessor.php';

// 1. Control de acceso y sesión
$auth = new Auth(__DIR__ . '/data/auth.config.php');
$auth->requireAuthOrRenderLogin(__DIR__ . '/views/login.php');

$excelPath = __DIR__ . '/data/DB.xlsx';
$cachePath = __DIR__ . '/data/cache.json';

// 2. Endpoints AJAX protegidos por sesión
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $processor = new DataProcessor($excelPath, $cachePath);

    if ($_GET['action'] === 'detail') {
        $type = (string)($_GET['type'] ?? '');
        $id   = (string)($_GET['id'] ?? '');
        echo json_encode($processor->getDocumentDetail($type, $id), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'firm_lines') {
        echo json_encode($processor->getFirmOcLines(), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 3. Carga de datos principales para el dashboard
$forceSync = isset($_GET['sync']) && $_GET['sync'] === '1';

try {
    $processor = new DataProcessor($excelPath, $cachePath);
    $payload   = $processor->getProcessedData($forceSync);
} catch (Throwable $e) {
    http_response_code(500);
    die("Error cargando datos del dashboard: " . htmlspecialchars($e->getMessage()));
}

$syncInfo    = $payload['sync_info'] ?? [];
$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
$assetVer    = '1.06.' . (file_exists(__DIR__ . '/assets/js/app.js') ? filemtime(__DIR__ . '/assets/js/app.js') : time());
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
    <!-- Librerías para exportación nativa a Excel (.xlsx) y PDF gráfico -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
