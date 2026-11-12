<?php
declare(strict_types=1);

$phpVersion = PHP_VERSION;
$zipEnabled = class_exists('ZipArchive');
$excelPath  = __DIR__ . '/data/DB.xlsx';
$excelFound = file_exists($excelPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras Grupo Cresta | MKS Box</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: Verdana, Geneva, sans-serif; background-color: #111827; color: #F9FAFB; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-lg w-full bg-gray-900 border border-gray-800 rounded-2xl p-8 shadow-2xl">
        <div class="flex items-center justify-between mb-6">
            <span class="px-3 py-1 text-xs font-bold rounded-full" style="background-color: #5B45FF; color: #FFFFFF;">
                MKS BOX · ANALYTICS
            </span>
            <span class="text-xs text-gray-400">compras-cresta.mksbox.com</span>
        </div>

        <h1 class="text-xl font-bold mb-2">Despliegue Git conectado</h1>
        <p class="text-sm text-gray-400 mb-6">Verificación de entorno en 20i antes de cargar el motor ETL.</p>

        <div class="space-y-3 text-sm">
            <div class="flex justify-between p-3 bg-gray-800/60 rounded-lg">
                <span class="text-gray-300">Versión PHP</span>
                <span class="font-bold text-emerald-400"><?= htmlspecialchars($phpVersion) ?></span>
            </div>
            <div class="flex justify-between p-3 bg-gray-800/60 rounded-lg">
                <span class="text-gray-300">Extensión ZipArchive (.xlsx)</span>
                <span class="font-bold <?= $zipEnabled ? 'text-emerald-400' : 'text-red-400' ?>">
                    <?= $zipEnabled ? 'Activa' : 'Inactiva (Activar en PHP Extensions)' ?>
                </span>
            </div>
            <div class="flex justify-between p-3 bg-gray-800/60 rounded-lg">
                <span class="text-gray-300">Archivo data/DB.xlsx</span>
                <span class="font-bold <?= $excelFound ? 'text-emerald-400' : 'text-amber-400' ?>">
                    <?= $excelFound ? 'Detectado' : 'Pendiente de subir' ?>
                </span>
            </div>
        </div>
    </div>
</body>
</html>
