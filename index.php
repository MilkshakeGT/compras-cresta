<?php
declare(strict_types=1);

require_once __DIR__ . '/src/DataProcessor.php';

$excelPath = __DIR__ . '/data/DB.xlsx';
$cachePath = __DIR__ . '/data/cache.json';

$error = null;
$data  = null;
$startTime = microtime(true);

try {
    $processor = new DataProcessor($excelPath, $cachePath);
    $data = $processor->getProcessedData();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba ETL · Compras Grupo Cresta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: Verdana, Geneva, sans-serif; background-color: #111827; color: #F9FAFB; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-2xl w-full bg-gray-900 border border-gray-800 rounded-2xl p-8 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <span class="px-3 py-1 text-xs font-bold rounded-full" style="background-color: #5B45FF; color: #FFFFFF;">
                PASO 2 · VALIDACIÓN MOTOR ETL
            </span>
            <span class="text-xs text-gray-400">Tiempo: <?= $elapsedMs ?> ms</span>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-red-950/70 border border-red-700 rounded-xl text-red-200 text-sm">
                <strong>Error al procesar Excel:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>
            <h1 class="text-xl font-bold mb-2">DB.xlsx procesado correctamente</h1>
            <p class="text-sm text-gray-400 mb-6">
                Caché generado en <code class="text-indigo-400">data/cache.json</code> · Corte detectado: <strong><?= htmlspecialchars($data['cutoff_date']) ?></strong>
            </p>

            <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                <div class="p-4 bg-gray-800/60 rounded-xl">
                    <div class="text-gray-400 text-xs">Solicitudes Únicas</div>
                    <div class="text-2xl font-bold text-emerald-400 mt-1"><?= number_format(count($data['records'])) ?></div>
                </div>
                <div class="p-4 bg-gray-800/60 rounded-xl">
                    <div class="text-gray-400 text-xs">Meses Detectados</div>
                    <div class="text-2xl font-bold text-indigo-400 mt-1"><?= count($data['filters']['meses']) ?></div>
                </div>
                <div class="p-4 bg-gray-800/60 rounded-xl">
                    <div class="text-gray-400 text-xs">Sociedades</div>
                    <div class="text-sm font-bold text-gray-200 mt-1"><?= htmlspecialchars(implode(', ', $data['filters']['sociedades'])) ?></div>
                </div>
                <div class="p-4 bg-gray-800/60 rounded-xl">
                    <div class="text-gray-400 text-xs">Compradores Activos</div>
                    <div class="text-2xl font-bold text-gray-200 mt-1"><?= count($data['filters']['compradores']) ?></div>
                </div>
            </div>

            <div class="text-xs text-gray-400 bg-gray-800/40 p-3 rounded-lg">
                <strong>Primer registro limpio:</strong>
                <?= htmlspecialchars(json_encode($data['records'][0] ?? [], JSON_UNESCAPED_UNICODE)) ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
