<?php
declare(strict_types=1);

require_once __DIR__ . '/src/DataProcessor.php';

$excelPath = __DIR__ . '/data/DB.xlsx';
$cachePath = __DIR__ . '/data/cache.json';
$forceSync = isset($_GET['sync']) && $_GET['sync'] === '1';

try {
    $processor = new DataProcessor($excelPath, $cachePath);
    $payload = $processor->getProcessedData($forceSync);
} catch (Throwable $e) {
    http_response_code(500);
    die("Error cargando datos del dashboard: " . htmlspecialchars($e->getMessage()));
}

$syncInfo = $payload['sync_info'] ?? [];
$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
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
    <style>
        body {
            font-family: Verdana, Geneva, sans-serif;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        select, button, input {
            outline: none;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-900 dark:bg-gray-950 dark:text-gray-100">

    <!-- Header Ejecutivo -->
    <header class="border-b border-slate-200 dark:border-gray-800 bg-white/95 dark:bg-gray-900/95 sticky top-0 z-30 backdrop-blur shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 space-y-3">
            <!-- Fila Superior: Marca + Navegación de Módulos + Acciones Globales -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md text-white bg-cresta-navy dark:bg-cresta-red tracking-wide">
                            GRUPO CRESTA · SUPPLY CHAIN
                        </span>
                        <span class="text-xs text-slate-500 dark:text-gray-400">
                            Corte: <strong class="text-slate-800 dark:text-gray-200"><?= htmlspecialchars($payload['cutoff_date']) ?></strong>
                        </span>
                        <?php if ($forceSync && !empty($syncInfo['message'])): ?>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800 font-semibold">
                                <?= htmlspecialchars($syncInfo['message']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1 id="mainHeaderTitle" class="text-lg sm:text-xl font-bold text-cresta-navy dark:text-white mt-1">
                        Evolución del Ciclo de Compras · Grupo Cresta
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <!-- Selector Principal de Módulo -->
                    <div class="inline-flex rounded-xl bg-slate-100 dark:bg-gray-800 p-1 border border-slate-200 dark:border-gray-700">
                        <button id="btnTabCycle" type="button" onclick="setMainTab('cycle')"
                            class="px-3 py-1.5 rounded-lg font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                            Ciclo Operativo (SLA)
                        </button>
                        <button id="btnTabSpend" type="button" onclick="setMainTab('spend')"
                            class="px-3 py-1.5 rounded-lg font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                            Gasto y Proveedores
                        </button>
                    </div>

                    <!-- Botón Sincronizar OneDrive -->
                    <a href="?sync=1" title="Forzar actualización desde OneDrive"
                        class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-white hover:bg-slate-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-cresta-navy dark:text-gray-200 font-bold transition">
                        <span>↻</span>
                        <span class="hidden sm:inline">Sincronizar</span>
                    </a>

                    <!-- Toggle Modo Claro / Oscuro -->
                    <button id="btnThemeToggle" type="button" onclick="toggleTheme()" title="Alternar modo claro / oscuro"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 font-bold transition">
                        <span id="themeIcon">🌙</span>
                        <span id="themeLabel" class="hidden sm:inline">Modo Oscuro</span>
                    </button>
                </div>
            </div>

            <!-- Fila Inferior del Header: Barra Unificada de Filtros Temporales y Dimensionales -->
            <div class="pt-2.5 border-t border-slate-100 dark:border-gray-800 flex flex-wrap items-center gap-2 text-xs">
                <!-- Selector de Año -->
                <select id="filterYear" onchange="onYearChange()" title="Seleccionar Año"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-cresta-navy dark:text-white rounded-lg px-2.5 py-1.5 font-bold shadow-xs">
                </select>

                <!-- Modo Periodo: Mes | Trimestre (Q) | Comparar Q | YTD -->
                <div class="inline-flex rounded-lg bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700">
                    <button id="btnModeMonth" type="button" onclick="setPeriodMode('month')"
                        class="px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                        Mes
                    </button>
                    <button id="btnModeQuarter" type="button" onclick="setPeriodMode('quarter')"
                        class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        Trimestre (Q)
                    </button>
                    <button id="btnModeCompareQ" type="button" onclick="setPeriodMode('compare_q')"
                        class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        Comparar Q
                    </button>
                    <button id="btnModeYTD" type="button" onclick="setPeriodMode('ytd')"
                        class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        YTD
                    </button>
                </div>

                <!-- Selector de Mes -->
                <select id="filterMes" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                </select>

                <!-- Selector de Trimestre -->
                <select id="filterQuarter" onchange="renderDashboard()"
                    class="hidden bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                </select>

                <!-- Selectores Duales para Comparar Q -->
                <div id="compareQControls" class="hidden inline-flex items-center gap-1.5">
                    <select id="filterCompareQ1" onchange="renderDashboard()" title="Trimestre Principal (Base)"
                        class="bg-white dark:bg-gray-800 border border-cresta-navy dark:border-indigo-500 text-cresta-navy dark:text-white rounded-lg px-2.5 py-1.5 font-bold shadow-xs">
                    </select>
                    <span class="text-slate-400 font-bold">vs</span>
                    <select id="filterCompareQ2" onchange="renderDashboard()" title="Trimestre de Referencia"
                        class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                    </select>
                </div>

                <!-- Selector de Sociedad -->
                <select id="filterSociedad" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                    <option value="ALL">Todas las Sociedades</option>
                </select>

                <!-- Selector de Comprador -->
                <select id="filterComprador" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                    <option value="ALL">Todos los Compradores</option>
                </select>

                <!-- Controles exclusivos de la pestaña Gasto y Proveedores -->
                <div id="spendOnlyFilters" class="hidden flex flex-wrap items-center gap-2">
                    <!-- Selector de Proveedor -->
                    <select id="filterProveedor" onchange="renderDashboard()"
                        class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs max-w-[230px]">
                        <option value="ALL">Todos los Proveedores</option>
                    </select>

                    <!-- Switch de Divisa GTQ / USD -->
                    <div class="inline-flex rounded-lg bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700">
                        <button id="btnCurrGTQ" type="button" onclick="setCurrency('GTQ')"
                            class="px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                            GTQ (Q)
                        </button>
                        <button id="btnCurrUSD" type="button" onclick="setCurrency('USD')"
                            class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                            USD ($)
                        </button>
                    </div>

                    <!-- Checkbox Excluir Caja Chica / Reembolsos -->
                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 cursor-pointer select-none">
                        <input id="chkExcludePettyCash" type="checkbox" onchange="renderDashboard()" class="rounded text-cresta-navy">
                        <span class="text-[11px] font-medium text-slate-700 dark:text-gray-300">Ocultar Caja Chica</span>
                    </label>
                </div>
            </div>
        </div>
    </header>

    <!-- ===================================================================== -->
    <!-- MÓDULO 1: CICLO OPERATIVO Y SLA                                       -->
    <!-- ===================================================================== -->
    <main id="viewCycleSection" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 flex-1 w-full">

        <!-- Tarjetas KPI Principales (5 tarjetas auditables al hacer clic) -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- KPI 1 -->
            <div onclick="openAuditModal('VALIDAS', 'ALL')" title="Haz clic para auditar el listado de solicitudes válidas"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-navy dark:hover:border-indigo-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-navy dark:group-hover:text-white transition">Solicitudes Válidas</span>
                        <span id="kpi1Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">--</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <span id="kpi1Value" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-white">0</span>
                        <span class="text-[10px] text-cresta-navy dark:text-indigo-400 group-hover:underline font-bold">Auditar →</span>
                    </div>
                </div>
                <p id="kpi1Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Excluye borrador, rechazadas y OC canceladas</p>
            </div>

            <!-- KPI 2: Tiempo Gestión Comprador -->
            <div onclick="openAuditModal('TIEMPO_COMP', 'ALL')" title="Haz clic para auditar los tiempos de gestión por solicitud"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-blue dark:hover:border-sky-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-blue dark:group-hover:text-sky-300 transition">Gestión Comprador</span>
                        <span id="kpiTimeBadge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">--</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <div class="flex items-baseline gap-1">
                            <span id="kpiTimeValue" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-sky-400">0.0</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-gray-400">días</span>
                        </div>
                        <span class="text-[10px] text-cresta-blue dark:text-sky-400 group-hover:underline font-bold">Auditar →</span>
                    </div>
                </div>
                <p id="kpiTimeSub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Mediana: 0.0 d · Promedio a OC</p>
            </div>

            <!-- KPI 3: SLA Comprador <= 14d -->
            <div onclick="openAuditModal('SLA_COMP', 'ALL')" title="Haz clic para auditar tiempos de gestión por comprador"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-emerald-600 dark:hover:border-emerald-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">SLA Comprador ≤14d</span>
                        <span id="kpi2Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">--</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <div class="flex items-baseline gap-1.5">
                            <span id="kpi2Value" class="text-2xl xl:text-3xl font-bold text-emerald-600 dark:text-emerald-400">0%</span>
                            <span class="text-[10px] text-slate-500 dark:text-gray-400">Meta 70%</span>
                        </div>
                        <span class="text-[10px] text-emerald-700 dark:text-emerald-400 group-hover:underline font-bold">Auditar →</span>
                    </div>
                </div>
                <p id="kpi2Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Solicitudes maduras ≥14d (Odoo)</p>
            </div>

            <!-- KPI 4: En SAP <= 14d -->
            <div onclick="openAuditModal('SAP_14', 'ALL')" title="Haz clic para auditar tiempos de ciclo hasta SAP"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-blue dark:hover:border-indigo-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-blue dark:group-hover:text-indigo-300 transition">En SAP ≤14 días</span>
                        <span id="kpi3Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">--</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <span id="kpi3Value" class="text-2xl xl:text-3xl font-bold text-cresta-blue dark:text-indigo-400">0%</span>
                        <span class="text-[10px] text-cresta-blue dark:text-indigo-400 group-hover:underline font-bold">Auditar →</span>
                    </div>
                </div>
                <p id="kpi3Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Ciclo desde aprobación (≥14d)</p>
            </div>

            <!-- KPI 5: Rezago Sin Llegar a SAP -->
            <div onclick="openAuditModal('REZAGO', 'ALL')" title="Haz clic para auditar el detalle de solicitudes pendientes"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-red dark:hover:border-amber-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-red dark:group-hover:text-amber-300 transition">Rezago Sin SAP</span>
                        <span id="kpi4Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-950 text-cresta-red dark:text-red-300 border border-red-200 dark:border-red-800 font-bold shrink-0">0 > 30d</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <span id="kpi4Value" class="text-2xl xl:text-3xl font-bold text-cresta-red dark:text-amber-400">0</span>
                        <span class="text-[10px] text-cresta-red dark:text-amber-400 group-hover:underline font-bold">Auditar →</span>
                    </div>
                </div>
                <p id="kpi4Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Aprobadas abiertas al corte</p>
            </div>
        </section>

        <!-- Panel Exclusivo de Comparación Trimestral (Ciclo) -->
        <section id="quarterComparisonSection" class="hidden bg-white dark:bg-gray-900 border-2 border-cresta-navy/20 dark:border-indigo-500/30 rounded-2xl p-5 shadow-sm">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">COMPARATIVA TRIMESTRAL</span>
                        <h2 id="compareQTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Comparación Q vs Q</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Contrastando tiempos promedio (días) y cumplimiento de SLA entre los dos trimestres seleccionados</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                <div class="lg:col-span-6">
                    <div id="chartCompareQ" class="w-full h-72"></div>
                </div>
                <div class="lg:col-span-6 overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead id="compareQTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200"></thead>
                        <tbody id="compareQTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Fila 1 de Gráficas (Ciclo) -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-7">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">% Solicitudes en SAP dentro de plazo (por Quincena)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Evaluado estrictamente sobre solicitudes que ya cumplieron la ventana de 7, 14 o 30 días</p>
                </div>
                <div id="chartQuincenas" class="w-full h-72"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-5">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Desglose del Ciclo Promedio a SAP (Días)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Separación de responsabilidad: Aprobadores vs. Comprador</p>
                </div>
                <div id="chartCiclo" class="w-full h-72"></div>
            </div>
        </section>

        <!-- Fila 2 de Gráficas (Ciclo) -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-7">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Desempeño por Comprador (% OC en Odoo ≤14 días)</h2>
                        <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Gestión pura del comprador (Aprobación Solicitud → Creación OC Odoo) · Meta 70%</p>
                    </div>
                    <span id="badgeCompradoresPartial" class="hidden text-[10px] px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800 font-bold shrink-0">
                        Avance parcial (periodo en curso)
                    </span>
                </div>
                <div id="chartCompradores" class="w-full h-80"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-5">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Rezago Abierto Sin SAP por Mes de Origen</h2>
                        <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Solicitudes aprobadas pendientes de sincronizar en SAP</p>
                    </div>
                    <button type="button" onclick="openAuditModal('REZAGO', 'GT30')"
                        class="text-[11px] px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/80 dark:hover:bg-red-900 text-cresta-red dark:text-red-200 border border-red-200 dark:border-red-800 font-bold shrink-0">
                        Ver > 30d
                    </button>
                </div>
                <div id="chartRezago" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Fila 3: Ranking de Aprobadores -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Ranking Aprobadores de Solicitud (Días Promedio)</h2>
                        <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Creación Solicitud → Aprobación · Activos con ≥5 aprobaciones (más ágil arriba)</p>
                    </div>
                    <span id="badgeAprobSolAvg" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                        Prom: 0.0 d
                    </span>
                </div>
                <div id="chartAprobadoresSol" class="w-full h-80"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Ranking Aprobadores de OC Odoo (Días Promedio)</h2>
                        <p id="subAprobOc" class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Creación OC → Aprobación OC (Autorizador 1) · Ordenado de más ágil a más lento</p>
                    </div>
                    <span id="badgeAprobOcAvg" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                        Prom: 0.0 d
                    </span>
                </div>
                <div id="chartAprobadoresOc" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Fila 4: Evolución Temporal de Días de Gestión del Comprador -->
        <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
            <div class="mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 id="chartCompTrendTitle" class="text-sm font-bold text-cresta-navy dark:text-white">
                        Evolución Mensual: Días Promedio de Gestión por Comprador
                    </h2>
                    <p id="chartCompTrendSub" class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">
                        Comparativa mensual de velocidad de respuesta (Aprobación Solicitud → OC en Odoo) · Menos días = mayor agilidad
                    </p>
                </div>
                <span id="chartCompTrendBadge" class="text-[11px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                    Vista Comparativa Equipo
                </span>
            </div>
            <div id="chartCompradorTrend" class="w-full h-80"></div>
        </section>

        <!-- Tabla Ejecutiva Mensual -->
        <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 id="monthlyTableTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Indicadores Mensuales Consolidados</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Porcentajes calculados exclusivamente sobre solicitudes maduras (sin sesgo de corte reciente)</p>
                </div>
                <span class="text-[11px] text-slate-500 dark:text-gray-400">* Celda resaltada indica mejor desempeño mensual</span>
            </div>
            <table class="w-full text-xs text-left border-collapse">
                <thead id="monthlyTableHead" class="bg-cresta-navy text-white dark:bg-gray-800 dark:text-gray-200"></thead>
                <tbody id="monthlyTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </section>

    </main>

    <!-- ===================================================================== -->
    <!-- MÓDULO 2: GESTIÓN DE GASTO, COMPRADORES Y PROVEEDORES                 -->
    <!-- ===================================================================== -->
    <main id="viewSpendSection" class="hidden max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 flex-1 w-full">

        <!-- 5 Tarjetas KPI Ejecutivas de Gasto (Todas auditables al hacer clic) -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Spend KPI 1: Gasto Comprometido Total -->
            <div onclick="openAuditModal('SPEND_FIRM', 'ALL')" title="Haz clic para auditar el detalle de OCs firmes"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-navy dark:hover:border-indigo-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-navy dark:group-hover:text-white transition">Gasto Comprometido</span>
                        <span id="spKpi1Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">--</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2 gap-1">
                        <span id="spKpi1Value" class="text-xl xl:text-2xl font-bold text-cresta-navy dark:text-white truncate">Q 0</span>
                        <span class="text-[10px] text-cresta-navy dark:text-indigo-400 group-hover:underline font-bold shrink-0">Auditar →</span>
                    </div>
                </div>
                <p id="spKpi1Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">OCs firmes en divisa seleccionada</p>
            </div>

            <!-- Spend KPI 2: Órdenes Emitidas y Ticket Promedio -->
            <div onclick="openAuditModal('SPEND_FIRM', 'ALL')" title="Haz clic para auditar tickets de órdenes de compra"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-blue dark:hover:border-sky-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-blue dark:group-hover:text-sky-300 transition">OCs Firmes / Ticket</span>
                        <span id="spKpi2Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">0 OCs</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2 gap-1">
                        <span id="spKpi2Value" class="text-xl xl:text-2xl font-bold text-cresta-blue dark:text-sky-400 truncate">Q 0</span>
                        <span class="text-[10px] text-cresta-blue dark:text-sky-400 group-hover:underline font-bold shrink-0">Auditar →</span>
                    </div>
                </div>
                <p id="spKpi2Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Ticket promedio · Mediana: Q 0</p>
            </div>

            <!-- Spend KPI 3: Proveedores Activos y Concentración Top 5 -->
            <div onclick="openAuditModal('SPEND_FIRM', 'TOP5_PROV')" title="Haz clic para auditar OCs de los 5 proveedores principales"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-emerald-600 dark:hover:border-emerald-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">Proveedores Activos</span>
                        <span id="spKpi3Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">Top 5: 0%</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <span id="spKpi3Value" class="text-2xl xl:text-3xl font-bold text-emerald-600 dark:text-emerald-400">0</span>
                        <span class="text-[10px] text-emerald-700 dark:text-emerald-400 group-hover:underline font-bold">Top 5 →</span>
                    </div>
                </div>
                <p id="spKpi3Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Concentración Pareto en Top 5</p>
            </div>

            <!-- Spend KPI 4: Cotizaciones Pendientes (RFQ) -->
            <div onclick="openAuditModal('SPEND_RFQ', 'ALL')" title="Haz clic para auditar solicitudes de cotización sin convertir"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-amber-600 dark:hover:border-amber-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Cotizaciones (RFQ)</span>
                        <span id="spKpi4Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 font-bold shrink-0">0 abiertas</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2 gap-1">
                        <span id="spKpi4Value" class="text-xl xl:text-2xl font-bold text-amber-600 dark:text-amber-400 truncate">Q 0</span>
                        <span class="text-[10px] text-amber-700 dark:text-amber-400 group-hover:underline font-bold shrink-0">Auditar →</span>
                    </div>
                </div>
                <p id="spKpi4Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">En cotización sin orden firme</p>
            </div>

            <!-- Spend KPI 5: Sincronización SAP & Cancelaciones -->
            <div onclick="openAuditModal('SPEND_NO_SAP', 'ALL')" title="Haz clic para auditar OCs firmes pendientes de SAP"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-red dark:hover:border-red-500/70 transition group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-red dark:group-hover:text-red-300 transition">Integración SAP</span>
                        <span id="spKpi5Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-950 text-cresta-red dark:text-red-300 border border-red-200 dark:border-red-800 font-bold shrink-0">Canc: 0%</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-2">
                        <span id="spKpi5Value" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-indigo-400">0%</span>
                        <span class="text-[10px] text-cresta-red dark:text-red-400 group-hover:underline font-bold">Sin SAP →</span>
                    </div>
                </div>
                <p id="spKpi5Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">OCs firmes sincronizadas en SAP</p>
            </div>
        </section>

        <!-- Panel Exclusivo de Comparación Trimestral de Gasto (visible en modo Comparar Q) -->
        <section id="spendCompareQSection" class="hidden bg-white dark:bg-gray-900 border-2 border-cresta-navy/20 dark:border-indigo-500/30 rounded-2xl p-5 shadow-sm">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">COMPARATIVA DE GASTO Q vs Q</span>
                        <h2 id="spendCompareQTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Comparación Trimestral</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Contraste de monto comprometido, cantidad de OCs, ticket promedio y proveedores entre trimestres</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead id="spendCompareQHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200"></thead>
                    <tbody id="spendCompareQBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                </table>
            </div>
        </section>

        <!-- Fila 1 (Gasto): Evolución Mensual del Gasto + Distribución por Sociedad -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-8">
                <div class="mb-3">
                    <h2 id="spChartTrendTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Evolución Mensual del Gasto Comprometido y OCs</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Barras = Monto total comprometido · Línea = Cantidad de órdenes de compra emitidas</p>
                </div>
                <div id="chartSpendTrend" class="w-full h-80"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-4">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Participación del Gasto por Sociedad</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Distribución por empresa del grupo en el periodo</p>
                </div>
                <div id="chartSpendSociedades" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Fila 2 (Gasto): Top 10 Proveedores por Monto + Top Compradores por Monto y Volumen -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top 10 Proveedores por Monto Comprado</h2>
                        <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Principales socios comerciales por valor económico en el periodo</p>
                    </div>
                    <span id="badgeParetoInfo" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                        Pareto 80%
                    </span>
                </div>
                <div id="chartTopProveedoresMonto" class="w-full h-80"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top Compradores por Monto Gestionado</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Capital adjudicado en OCs firmes y cantidad de órdenes emitidas</p>
                </div>
                <div id="chartTopCompradoresMonto" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Fila 3 (Gasto): Top 10 Proveedores por Frecuencia + Embudo del Proceso de Compra -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top 10 Proveedores por Frecuencia (Cantidad de OCs)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Proveedores de alta recurrencia operativa (candidatos a contratos marco)</p>
                </div>
                <div id="chartTopProveedoresFreq" class="w-full h-80"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Embudo de Conversión del Proceso de Compras</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Trazabilidad desde Solicitud Aprobada hasta Orden Sincronizada en SAP</p>
                </div>
                <div id="chartProcurementFunnel" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Tabla Ejecutiva Top 15 Proveedores Detallada -->
        <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Ranking Detallado de Proveedores (Top 15 del Periodo)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Incluye monto total, participación %, peso Pareto acumulado, ticket promedio y sociedades atendidas</p>
                </div>
                <button type="button" onclick="openAuditModal('SPEND_FIRM', 'ALL')"
                    class="text-xs px-3 py-1.5 rounded-lg bg-cresta-navy dark:bg-cresta-red text-white font-bold hover:opacity-90 transition shrink-0">
                    Auditar todas las OCs →
                </button>
            </div>
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-cresta-navy text-white dark:bg-gray-800 dark:text-gray-200">
                    <tr>
                        <th class="py-3 px-3 font-bold rounded-tl-lg">#</th>
                        <th class="py-3 px-3 font-bold">Proveedor</th>
                        <th class="py-3 px-3 text-right font-bold">OCs</th>
                        <th class="py-3 px-3 text-right font-bold">Sociedades</th>
                        <th class="py-3 px-3 text-right font-bold">Ticket Prom.</th>
                        <th class="py-3 px-3 text-right font-bold">Monto Total</th>
                        <th class="py-3 px-3 text-right font-bold">% Part.</th>
                        <th class="py-3 px-3 text-right font-bold rounded-tr-lg">% Acum.</th>
                    </tr>
                </thead>
                <tbody id="topSuppliersTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </section>

    </main>

    <!-- Footer con créditos -->
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4 w-full text-center">
        <p class="text-[11px] text-slate-400 dark:text-gray-500">
            Dashboard construido por <span class="font-semibold text-slate-500 dark:text-gray-400">Elder Velásquez</span> con ayuda de
            <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-cresta-navy dark:hover:text-gray-300">mksbox.com</a>
        </p>
    </footer>

    <!-- Modal Universal de Auditoría (Ciclo + Gasto) -->
    <div id="auditModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 dark:bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-2xl max-w-6xl w-full max-h-[88vh] flex flex-col shadow-2xl">
            <div class="p-5 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span id="modalBadge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">AUDITORÍA</span>
                        <span id="modalSubtitle" class="text-xs text-slate-500 dark:text-gray-400"></span>
                    </div>
                    <h3 id="modalTitle" class="text-base font-bold text-cresta-navy dark:text-white mt-1">Detalle de Registros</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="exportAuditCSV()"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                        Descargar CSV
                    </button>
                    <button type="button" onclick="closeAuditModal()"
                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-300 text-xs font-bold">
                        Cerrar ✕
                    </button>
                </div>
            </div>

            <!-- Controles internos dinámicos del modal -->
            <div class="px-5 py-3 bg-slate-50 dark:bg-gray-800/40 border-b border-slate-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div id="modalTabsContainer" class="flex flex-wrap gap-1.5"></div>
                <input id="modalSearch" type="text" oninput="renderModalTable()" placeholder="Buscar solicitud, proveedor, comprador, OC..."
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-slate-800 dark:text-gray-100 w-64">
            </div>

            <!-- Tabla del modal -->
            <div class="overflow-auto flex-1 p-5">
                <table class="w-full text-xs text-left border-collapse">
                    <thead id="modalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0"></thead>
                    <tbody id="modalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                </table>
            </div>
        </div>
    </div>

<script>
const RAW_DATA = <?= $jsonPayload ?>;
let mainTab = 'cycle'; // 'cycle' | 'spend'
let periodMode = 'month'; // 'month' | 'quarter' | 'compare_q' | 'ytd'
let activeCurrency = 'GTQ'; // 'GTQ' | 'USD'
let charts = {};
let isDark = false;
let currentTop5ProvidersSet = new Set();

if (window.location.search.includes('sync=1')) {
    window.history.replaceState({}, document.title, window.location.pathname);
}

let activeModalCategory = 'REZAGO';
let activeModalSubFilter = 'ALL';
let cachedUniverses = {
    VALIDAS: [],
    TIEMPO_COMP: [],
    SLA_COMP: [],
    SAP_14: [],
    REZAGO: [],
    SPEND_FIRM: [],
    SPEND_RFQ: [],
    SPEND_NO_SAP: []
};

const MODAL_CONFIG = {
    VALIDAS: {
        mode: 'cycle',
        badge: 'AUDITORÍA · SOLICITUDES VÁLIDAS',
        title: 'Solicitudes Válidas del Periodo Seleccionado',
        daysField: 'd_ciclo_total',
        daysLabel: 'Ciclo Total (días)',
        tabs: [
            { id: 'ALL', label: 'Todas' },
            { id: 'IN_SAP', label: 'Completadas en SAP' },
            { id: 'IN_ODOO', label: 'Con OC Odoo (Sin SAP)' },
            { id: 'NO_OC', label: 'Sin OC en Odoo' }
        ]
    },
    TIEMPO_COMP: {
        mode: 'cycle',
        badge: 'AUDITORÍA · GESTIÓN COMPRADOR (DÍAS)',
        title: 'Tiempos de Gestión del Comprador (Aprobación Solicitud → Creación OC Odoo)',
        daysField: 'd_comprador',
        daysLabel: 'Días Gestión OC',
        tabs: [
            { id: 'ALL', label: 'Todas con OC Odoo' },
            { id: 'FAST', label: 'Ágiles (≤ 7 días)' },
            { id: 'MED', label: 'Moderadas (8 a 14 días)' },
            { id: 'SLOW', label: 'Lentas (> 14 días)' }
        ]
    },
    SLA_COMP: {
        mode: 'cycle',
        badge: 'AUDITORÍA · SLA COMPRADOR',
        title: 'Evaluación SLA Comprador (Aprobación Solicitud → Creación OC Odoo)',
        daysField: 'd_comprador',
        daysLabel: 'Días Comprador',
        tabs: [
            { id: 'ALL', label: 'Todas las Evaluables' },
            { id: 'OK_14', label: 'Cumplieron (≤ 14 días)' },
            { id: 'LATE_14', label: 'Fuera de SLA (> 14d / Sin OC)' }
        ]
    },
    SAP_14: {
        mode: 'cycle',
        badge: 'AUDITORÍA · EN SAP ≤14 DÍAS',
        title: 'Evaluación Ciclo a SAP (Aprobación Solicitud → Creación en SAP)',
        daysField: 'd_to_sap',
        daysLabel: 'Días hasta SAP',
        tabs: [
            { id: 'ALL', label: 'Todas las Evaluables' },
            { id: 'OK_14', label: 'En SAP ≤ 14 días' },
            { id: 'LATE_14', label: 'Fuera de Plazo (> 14d / Sin SAP)' }
        ]
    },
    REZAGO: {
        mode: 'cycle',
        badge: 'AUDITORÍA · REZAGO SIN SAP',
        title: 'Solicitudes Aprobadas Pendientes de Llegar a SAP',
        daysField: 'd_to_sap',
        daysLabel: 'Días desde Aprob.',
        tabs: [
            { id: 'ALL', label: 'Todas' },
            { id: 'GT30', label: 'Críticas (> 30 días)' },
            { id: 'NO_OC', label: 'Sin OC en Odoo (Comprador)' },
            { id: 'PEND_SAP', label: 'Con OC Odoo (Falta Aprob/SAP)' }
        ]
    },
    SPEND_FIRM: {
        mode: 'spend',
        badge: 'AUDITORÍA · ÓRDENES DE COMPRA FIRMES',
        title: 'Detalle de Órdenes de Compra Firmes (Monto y Proveedor)',
        tabs: [
            { id: 'ALL', label: 'Todas las OCs Firmes' },
            { id: 'TOP5_PROV', label: 'Solo Top 5 Proveedores' },
            { id: 'IN_SAP', label: 'Con OC SAP' },
            { id: 'NO_SAP', label: 'Pendientes de SAP' }
        ]
    },
    SPEND_RFQ: {
        mode: 'spend',
        badge: 'AUDITORÍA · COTIZACIONES ABIERTAS (RFQ)',
        title: 'Solicitudes de Cotización Pendientes de Conversión a OC',
        tabs: [
            { id: 'ALL', label: 'Todas las Cotizaciones' }
        ]
    },
    SPEND_NO_SAP: {
        mode: 'spend',
        badge: 'AUDITORÍA · OCs FIRMES SIN SINCRONIZAR EN SAP',
        title: 'Órdenes de Compra Firmes Pendientes de Integración SAP',
        tabs: [
            { id: 'ALL', label: 'Todas sin SAP' }
        ]
    }
};

const MONTH_NAMES = {
    '01': 'Ene', '02': 'Feb', '03': 'Mar', '04': 'Abr',
    '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Ago',
    '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dic'
};

const QUARTER_DEFS = [
    { id: 'Q1', label: 'Q1 (Ene–Mar)', short: 'Q1', months: ['01', '02', '03'] },
    { id: 'Q2', label: 'Q2 (Abr–Jun)', short: 'Q2', months: ['04', '05', '06'] },
    { id: 'Q3', label: 'Q3 (Jul–Sep)', short: 'Q3', months: ['07', '08', '09'] },
    { id: 'Q4', label: 'Q4 (Oct–Dic)', short: 'Q4', months: ['10', '11', '12'] }
];

function getQuarterFromYm(ym) {
    const m = ym.split('-')[1];
    const found = QUARTER_DEFS.find(q => q.months.includes(m));
    return found ? found.id : 'Q1';
}

function formatMoney(val, curr = activeCurrency) {
    if (val === null || val === undefined || isNaN(val)) return curr === 'USD' ? '$ 0.00' : 'Q 0.00';
    const prefix = curr === 'USD' ? '$ ' : 'Q ';
    return prefix + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatMoneyCompact(val, curr = activeCurrency) {
    const prefix = curr === 'USD' ? '$' : 'Q';
    if (Math.abs(val) >= 1000000) return `${prefix}${(val / 1000000).toFixed(2)}M`;
    if (Math.abs(val) >= 1000) return `${prefix}${(val / 1000).toFixed(1)}k`;
    return `${prefix}${val.toFixed(0)}`;
}

function initTheme() {
    const saved = localStorage.getItem('cresta_dashboard_theme');
    isDark = (saved === 'dark');
    applyThemeUI();
}

function toggleTheme() {
    isDark = !isDark;
    localStorage.setItem('cresta_dashboard_theme', isDark ? 'dark' : 'light');
    applyThemeUI();
    renderDashboard();
}

function applyThemeUI() {
    const htmlEl = document.documentElement;
    const iconEl = document.getElementById('themeIcon');
    const labelEl = document.getElementById('themeLabel');

    if (isDark) {
        htmlEl.classList.add('dark');
        iconEl.textContent = '☀️';
        labelEl.textContent = 'Modo Claro';
    } else {
        htmlEl.classList.remove('dark');
        iconEl.textContent = '🌙';
        labelEl.textContent = 'Modo Oscuro';
    }
    updateMainTabButtons();
    updatePeriodButtons();
    updateCurrencyButtons();
}

function setMainTab(tab) {
    mainTab = tab;
    updateMainTabButtons();
    renderDashboard();
}

function updateMainTabButtons() {
    const btnCycle = document.getElementById('btnTabCycle');
    const btnSpend = document.getElementById('btnTabSpend');
    const secCycle = document.getElementById('viewCycleSection');
    const secSpend = document.getElementById('viewSpendSection');
    const spendFilters = document.getElementById('spendOnlyFilters');
    const titleEl = document.getElementById('mainHeaderTitle');

    const activeCls = 'px-3 py-1.5 rounded-lg font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-3 py-1.5 rounded-lg font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    if (mainTab === 'cycle') {
        btnCycle.className = activeCls;
        btnSpend.className = inactiveCls;
        secCycle.classList.remove('hidden');
        secSpend.classList.add('hidden');
        spendFilters.classList.add('hidden');
        titleEl.textContent = 'Evolución del Ciclo de Compras · Grupo Cresta';
    } else {
        btnSpend.className = activeCls;
        btnCycle.className = inactiveCls;
        secSpend.classList.remove('hidden');
        secCycle.classList.add('hidden');
        spendFilters.classList.remove('hidden');
        titleEl.textContent = 'Gestión de Gasto, Compradores y Proveedores · Grupo Cresta';
    }
}

function setCurrency(curr) {
    activeCurrency = curr;
    updateCurrencyButtons();
    renderDashboard();
}

function updateCurrencyButtons() {
    const btnG = document.getElementById('btnCurrGTQ');
    const btnU = document.getElementById('btnCurrUSD');
    const activeCls = 'px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    btnG.className = activeCurrency === 'GTQ' ? activeCls : inactiveCls;
    btnU.className = activeCurrency === 'USD' ? activeCls : inactiveCls;
}

function updatePeriodButtons() {
    const btnM = document.getElementById('btnModeMonth');
    const btnQ = document.getElementById('btnModeQuarter');
    const btnCQ = document.getElementById('btnModeCompareQ');
    const btnY = document.getElementById('btnModeYTD');

    const selYear = document.getElementById('filterYear');
    const selMes = document.getElementById('filterMes');
    const selQ = document.getElementById('filterQuarter');
    const compQBox = document.getElementById('compareQControls');
    const compQSec = document.getElementById('quarterComparisonSection');
    const spendCompQSec = document.getElementById('spendCompareQSection');

    const activeCls = 'px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    btnM.className = periodMode === 'month' ? activeCls : inactiveCls;
    btnQ.className = periodMode === 'quarter' ? activeCls : inactiveCls;
    btnCQ.className = periodMode === 'compare_q' ? activeCls : inactiveCls;
    btnY.className = periodMode === 'ytd' ? activeCls : inactiveCls;

    selYear.classList.add('hidden');
    selMes.classList.add('hidden');
    selQ.classList.add('hidden');
    compQBox.classList.add('hidden');
    compQSec.classList.add('hidden');
    spendCompQSec.classList.add('hidden');

    if (periodMode === 'compare_q') {
        compQBox.classList.remove('hidden');
        compQSec.classList.remove('hidden');
        spendCompQSec.classList.remove('hidden');
    } else if (periodMode === 'quarter') {
        selYear.classList.remove('hidden');
        selQ.classList.remove('hidden');
    } else {
        selYear.classList.remove('hidden');
        selMes.classList.remove('hidden');
    }
}

function formatYm(ym) {
    const [y, m] = ym.split('-');
    return `${MONTH_NAMES[m] || m} ${y}`;
}

function formatMonthOnly(ym) {
    const m = ym.split('-')[1];
    return MONTH_NAMES[m] || m;
}

function formatYearQuarter(yq) {
    const [y, qId] = yq.split('-');
    const qObj = QUARTER_DEFS.find(q => q.id === qId);
    return qObj ? `${qObj.short} ${y} (${qObj.label.split(' ')[1]})` : yq;
}

function formatYearQuarterShort(yq) {
    const [y, qId] = yq.split('-');
    return `${qId} ${y}`;
}

function shortName(fullName) {
    return fullName.split(' ').slice(0, 2).join(' ');
}

function shortSupplier(name) {
    return name.length > 26 ? name.slice(0, 25) + '…' : name;
}

function median(arr) {
    if (!arr.length) return 0;
    const s = [...arr].sort((a, b) => a - b);
    const mid = Math.floor(s.length / 2);
    return s.length % 2 !== 0 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
}

function avg(arr) {
    if (!arr.length) return 0;
    return arr.reduce((a, b) => a + b, 0) / arr.length;
}

function populateYearPeriodOptions(year) {
    const selMes = document.getElementById('filterMes');
    const selQ = document.getElementById('filterQuarter');

    const yearMeses = RAW_DATA.filters.meses.filter(ym => ym.startsWith(`${year}-`));

    selMes.innerHTML = '';
    yearMeses.forEach(ym => {
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = formatYm(ym);
        selMes.appendChild(opt);
    });
    if (yearMeses.length > 0) {
        selMes.value = yearMeses[yearMeses.length - 1];
    }

    const availableQs = new Set(yearMeses.map(getQuarterFromYm));
    selQ.innerHTML = '';
    QUARTER_DEFS.forEach(q => {
        if (availableQs.has(q.id)) {
            const opt = document.createElement('option');
            opt.value = q.id;
            opt.textContent = `${q.label} ${year}`;
            selQ.appendChild(opt);
        }
    });
    if (selQ.options.length > 0) {
        selQ.value = selQ.options[selQ.options.length - 1].value;
    }
}

function populateAllCompareQuarters() {
    const selCQ1 = document.getElementById('filterCompareQ1');
    const selCQ2 = document.getElementById('filterCompareQ2');

    const yqSet = new Set(RAW_DATA.filters.meses.map(ym => `${ym.split('-')[0]}-${getQuarterFromYm(ym)}`));
    const allYQs = [...yqSet].sort();

    selCQ1.innerHTML = '';
    selCQ2.innerHTML = '';

    allYQs.forEach(yq => {
        const o1 = document.createElement('option');
        o1.value = yq;
        o1.textContent = formatYearQuarter(yq);
        selCQ1.appendChild(o1);

        const o2 = document.createElement('option');
        o2.value = yq;
        o2.textContent = formatYearQuarter(yq);
        selCQ2.appendChild(o2);
    });

    if (allYQs.length >= 2) {
        selCQ1.value = allYQs[allYQs.length - 1];
        selCQ2.value = allYQs[allYQs.length - 2];
    } else if (allYQs.length === 1) {
        selCQ1.value = allYQs[0];
        selCQ2.value = allYQs[0];
    }
}

function initFilters() {
    const selYear = document.getElementById('filterYear');
    const selSoc = document.getElementById('filterSociedad');
    const selComp = document.getElementById('filterComprador');
    const selProv = document.getElementById('filterProveedor');

    const years = [...new Set(RAW_DATA.filters.meses.map(ym => ym.split('-')[0]))].sort();
    years.forEach(y => {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = `Año ${y}`;
        selYear.appendChild(opt);
    });
    const latestYear = years.length > 0 ? years[years.length - 1] : String(new Date().getFullYear());
    selYear.value = latestYear;

    populateYearPeriodOptions(latestYear);
    populateAllCompareQuarters();

    RAW_DATA.filters.sociedades.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        selSoc.appendChild(opt);
    });

    RAW_DATA.filters.compradores.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        selComp.appendChild(opt);
    });

    (RAW_DATA.filters.proveedores || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p;
        opt.textContent = p;
        selProv.appendChild(opt);
    });
}

function onYearChange() {
    const year = document.getElementById('filterYear').value;
    populateYearPeriodOptions(year);
    renderDashboard();
}

function setPeriodMode(mode) {
    periodMode = mode;
    updatePeriodButtons();
    renderDashboard();
}

function filterByEntity(records) {
    const soc = document.getElementById('filterSociedad').value;
    const comp = document.getElementById('filterComprador').value;
    return records.filter(r => {
        if (soc !== 'ALL' && r.sociedad !== soc) return false;
        if (comp !== 'ALL' && r.comprador !== comp) return false;
        return true;
    });
}

function filterSpendRecordsBase(records) {
    const soc = document.getElementById('filterSociedad').value;
    const comp = document.getElementById('filterComprador').value;
    const prov = document.getElementById('filterProveedor').value;
    const hidePetty = document.getElementById('chkExcludePettyCash').checked;

    return records.filter(r => {
        if (r.divisa !== activeCurrency) return false;
        if (soc !== 'ALL' && r.sociedad !== soc) return false;
        if (comp !== 'ALL' && r.comprador !== comp) return false;
        if (prov !== 'ALL' && r.proveedor !== prov) return false;
        if (hidePetty && r.is_caja_chica) return false;
        return true;
    });
}

function filterBySociedadOnly(records) {
    const soc = document.getElementById('filterSociedad').value;
    return records.filter(r => soc === 'ALL' || r.sociedad === soc);
}

function filterRecordsByYearQuarter(records, yq) {
    const [year, qId] = yq.split('-');
    const qDef = QUARTER_DEFS.find(q => q.id === qId) || QUARTER_DEFS[0];
    return records.filter(r => r.ym.startsWith(`${year}-`) && qDef.months.includes(r.ym.split('-')[1]));
}

function getMaxYmForYearQuarter(yq) {
    const [year, qId] = yq.split('-');
    const qDef = QUARTER_DEFS.find(q => q.id === qId) || QUARTER_DEFS[0];
    const qMeses = RAW_DATA.filters.meses.filter(ym => ym.startsWith(`${year}-`) && qDef.months.includes(ym.split('-')[1]));
    return qMeses.length ? qMeses[qMeses.length - 1] : `${year}-${qDef.months[2]}`;
}

function getMaxYmForCurrentSelection() {
    const year = document.getElementById('filterYear').value;
    if (periodMode === 'compare_q') {
        return getMaxYmForYearQuarter(document.getElementById('filterCompareQ1').value);
    }
    if (periodMode === 'quarter') {
        return getMaxYmForYearQuarter(`${year}-${document.getElementById('filterQuarter').value}`);
    }
    return document.getElementById('filterMes').value;
}

function filterByActivePeriod(records) {
    const year = document.getElementById('filterYear').value;
    if (periodMode === 'compare_q') {
        return filterRecordsByYearQuarter(records, document.getElementById('filterCompareQ1').value);
    }
    if (periodMode === 'month') {
        const targetYm = document.getElementById('filterMes').value;
        return records.filter(r => r.ym === targetYm);
    }
    if (periodMode === 'quarter') {
        return filterRecordsByYearQuarter(records, `${year}-${document.getElementById('filterQuarter').value}`);
    }
    const targetYm = document.getElementById('filterMes').value;
    return records.filter(r => r.ym.startsWith(`${year}-`) && r.ym <= targetYm);
}

function getPreviousQuarterRecords(entityRecords, year, qId) {
    const order = ['Q1', 'Q2', 'Q3', 'Q4'];
    const idx = order.indexOf(qId);
    let prevYear = year;
    let prevQId = null;

    if (idx > 0) {
        prevQId = order[idx - 1];
    } else {
        prevYear = String(Number(year) - 1);
        prevQId = 'Q4';
    }

    const prevYq = `${prevYear}-${prevQId}`;
    const prevRows = filterRecordsByYearQuarter(entityRecords, prevYq);
    if (!prevRows.length) return null;

    return {
        label: `${prevQId} ${prevYear !== year ? prevYear : ''}`.trim(),
        rows: prevRows,
        stats: calcKpiStats(prevRows)
    };
}

function calcKpiStats(records) {
    const validas = records.filter(r => !r.excluida);
    const aprobadas = validas.filter(r => r.aprobada);

    const conOcOdoo = aprobadas.filter(r => r.tiene_oc_odoo && r.d_comprador !== null);
    const diasCompArr = conOcOdoo.map(r => r.d_comprador);
    const avgDiasComp = diasCompArr.length ? avg(diasCompArr) : null;
    const medDiasComp = diasCompArr.length ? median(diasCompArr) : null;

    const aprobSolArr = validas.map(r => r.d_aprob_sol).filter(v => v !== null);
    const avgDiasAprobSol = aprobSolArr.length ? avg(aprobSolArr) : null;

    const matureComp = aprobadas.filter(r => r.edad_dias >= 14);
    const isCompPartial = (matureComp.length === 0 && conOcOdoo.length > 0);
    const compEvaluables = matureComp.length > 0 ? matureComp : conOcOdoo;
    const comp14 = compEvaluables.filter(r => r.tiene_oc_odoo && r.d_comprador !== null && r.d_comprador <= 14);
    const pctComp14 = compEvaluables.length ? Math.round((comp14.length / compEvaluables.length) * 100) : null;

    const conOcSap = aprobadas.filter(r => r.tiene_oc_sap && r.d_to_sap !== null);
    const matureSap = aprobadas.filter(r => r.edad_dias >= 14);
    const isSapPartial = (matureSap.length === 0 && conOcSap.length > 0);
    const sapEvaluables = matureSap.length > 0 ? matureSap : conOcSap;
    const sap14 = sapEvaluables.filter(r => r.tiene_oc_sap && r.d_to_sap !== null && r.d_to_sap <= 14);
    const pctSap14 = sapEvaluables.length ? Math.round((sap14.length / sapEvaluables.length) * 100) : null;

    return {
        validasList: validas,
        conOcOdooList: conOcOdoo,
        compEvaluablesList: compEvaluables,
        sapEvaluablesList: sapEvaluables,
        isCompPartial,
        isSapPartial,
        totalValidas: validas.length,
        avgDiasComp,
        medDiasComp,
        avgDiasAprobSol,
        pctComp14,
        pctSap14
    };
}

function renderDashboard() {
    if (mainTab === 'cycle') {
        renderCycleView();
    } else {
        renderSpendView();
    }
}

// =====================================================================
// RENDERIZACIÓN MÓDULO 1: CICLO OPERATIVO Y SLA
// =====================================================================
function renderCycleView() {
    const selectedYear = document.getElementById('filterYear').value;
    const selectedYm = document.getElementById('filterMes').value;
    const selectedQ = document.getElementById('filterQuarter').value;
    const selectedComp = document.getElementById('filterComprador').value;

    const entityRecords = filterByEntity(RAW_DATA.records);
    const currentRecords = filterByActivePeriod(entityRecords);
    const currentStats = calcKpiStats(currentRecords);
    const maxYm = getMaxYmForCurrentSelection();

    let prevCompData = null;
    if (periodMode === 'compare_q') {
        const yq1 = document.getElementById('filterCompareQ1').value;
        const yq2 = document.getElementById('filterCompareQ2').value;
        const rowsQ2 = filterRecordsByYearQuarter(entityRecords, yq2);
        const statsQ2 = calcKpiStats(rowsQ2);
        prevCompData = {
            label: formatYearQuarterShort(yq2),
            stats: statsQ2
        };
        renderQuarterComparisonPanel(yq1, yq2, currentStats, statsQ2);
    } else if (periodMode === 'month') {
        const allMeses = RAW_DATA.filters.meses;
        const idxYm = allMeses.indexOf(selectedYm);
        if (idxYm > 0) {
            const prevYm = allMeses[idxYm - 1];
            prevCompData = {
                label: MONTH_NAMES[prevYm.split('-')[1]],
                stats: calcKpiStats(entityRecords.filter(r => r.ym === prevYm))
            };
        }
    } else if (periodMode === 'quarter') {
        prevCompData = getPreviousQuarterRecords(entityRecords, selectedYear, selectedQ);
    }

    document.getElementById('kpi1Value').textContent = currentStats.totalValidas.toLocaleString();
    if (periodMode === 'compare_q') {
        const yq1 = document.getElementById('filterCompareQ1').value;
        const diffVol = currentStats.totalValidas - prevCompData.stats.totalValidas;
        document.getElementById('kpi1Badge').textContent = `${formatYearQuarterShort(yq1)} (${diffVol >= 0 ? '+' : ''}${diffVol})`;
    } else if (periodMode === 'ytd') {
        document.getElementById('kpi1Badge').textContent = `YTD ${selectedYear}`;
    } else if (periodMode === 'quarter') {
        document.getElementById('kpi1Badge').textContent = `${selectedQ} ${selectedYear}`;
    } else {
        document.getElementById('kpi1Badge').textContent = formatYm(selectedYm);
    }

    document.getElementById('kpiTimeValue').textContent = currentStats.avgDiasComp !== null ? currentStats.avgDiasComp.toFixed(1) : 'N/D';
    document.getElementById('kpiTimeSub').textContent = currentStats.medDiasComp !== null
        ? `Mediana: ${currentStats.medDiasComp.toFixed(1)} d · ${currentStats.conOcOdooList.length} OC`
        : 'Sin OC generadas aún';

    const bTime = document.getElementById('kpiTimeBadge');
    if (prevCompData && currentStats.avgDiasComp !== null && prevCompData.stats.avgDiasComp !== null) {
        const diffDays = Number((currentStats.avgDiasComp - prevCompData.stats.avgDiasComp).toFixed(1));
        const improved = diffDays <= 0;
        bTime.textContent = `${diffDays > 0 ? '+' : ''}${diffDays.toFixed(1)}d vs ${prevCompData.label}`;
        bTime.className = `text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 ${improved
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-800'
            : 'bg-red-50 text-cresta-red border border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800'}`;
    } else {
        bTime.textContent = periodMode === 'ytd' ? `YTD ${selectedYear}` : 'Base';
        bTime.className = 'text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0';
    }

    document.getElementById('kpi2Value').textContent = currentStats.pctComp14 !== null ? `${currentStats.pctComp14}%` : 'N/D';
    const b2 = document.getElementById('kpi2Badge');
    const sub2 = document.getElementById('kpi2Sub');
    if (currentStats.isCompPartial) {
        b2.textContent = 'Avance parcial';
        b2.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800';
        sub2.textContent = `Sobre ${currentStats.compEvaluablesList.length} OC generadas (en curso)`;
    } else if (prevCompData && currentStats.pctComp14 !== null && prevCompData.stats.pctComp14 !== null && !prevCompData.stats.isCompPartial) {
        const diff2 = currentStats.pctComp14 - prevCompData.stats.pctComp14;
        b2.textContent = `${diff2 >= 0 ? '+' : ''}${diff2} pts vs ${prevCompData.label}`;
        b2.className = `text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 ${diff2 >= 0
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-800'
            : 'bg-red-50 text-cresta-red border border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800'}`;
        sub2.textContent = 'Solicitudes maduras ≥14d (Odoo)';
    } else {
        b2.textContent = periodMode === 'ytd' ? `YTD ${selectedYear}` : 'Madurez';
        b2.className = 'text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0';
        sub2.textContent = 'Solicitudes maduras ≥14d (Odoo)';
    }

    document.getElementById('kpi3Value').textContent = currentStats.pctSap14 !== null ? `${currentStats.pctSap14}%` : 'N/D';
    const b3 = document.getElementById('kpi3Badge');
    const sub3 = document.getElementById('kpi3Sub');
    if (currentStats.isSapPartial) {
        b3.textContent = 'Avance parcial';
        b3.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800';
        sub3.textContent = `Sobre ${currentStats.sapEvaluablesList.length} cerradas en SAP (en curso)`;
    } else if (prevCompData && currentStats.pctSap14 !== null && prevCompData.stats.pctSap14 !== null && !prevCompData.stats.isSapPartial) {
        const diff3 = currentStats.pctSap14 - prevCompData.stats.pctSap14;
        b3.textContent = `${diff3 >= 0 ? '+' : ''}${diff3} pts vs ${prevCompData.label}`;
        b3.className = `text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 ${diff3 >= 0
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-800'
            : 'bg-red-50 text-cresta-red border border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800'}`;
        sub3.textContent = 'Ciclo desde aprobación (≥14d)';
    } else {
        b3.textContent = periodMode === 'ytd' ? `YTD ${selectedYear}` : 'Madurez';
        b3.className = 'text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0';
        sub3.textContent = 'Ciclo desde aprobación (≥14d)';
    }

    const rezagoList = entityRecords
        .filter(r => r.ym <= maxYm && !r.excluida && r.aprobada && !r.tiene_oc_sap)
        .sort((a, b) => (b.d_to_sap || 0) - (a.d_to_sap || 0));

    cachedUniverses.VALIDAS = [...currentStats.validasList].sort((a, b) => b.fecha.localeCompare(a.fecha));
    cachedUniverses.TIEMPO_COMP = [...currentStats.conOcOdooList].sort((a, b) => (b.d_comprador || 0) - (a.d_comprador || 0));
    cachedUniverses.SLA_COMP = [...currentStats.compEvaluablesList].sort((a, b) => (b.d_comprador ?? 9999) - (a.d_comprador ?? 9999));
    cachedUniverses.SAP_14 = [...currentStats.sapEvaluablesList].sort((a, b) => (b.d_to_sap ?? 9999) - (a.d_to_sap ?? 9999));
    cachedUniverses.REZAGO = rezagoList;

    const upToSelected30 = rezagoList.filter(r => r.d_to_sap !== null && r.d_to_sap > 30);
    document.getElementById('kpi4Value').textContent = rezagoList.length.toLocaleString();
    document.getElementById('kpi4Badge').textContent = `${upToSelected30.length} > 30d`;

    const tableYear = periodMode === 'compare_q'
        ? document.getElementById('filterCompareQ1').value.split('-')[0]
        : selectedYear;

    renderChartQuincenas(entityRecords, maxYm);
    renderChartCiclo(entityRecords, maxYm);
    renderChartCompradores(currentRecords);
    renderChartRezago(rezagoList);
    renderChartAprobadoresSol(currentRecords);
    renderChartAprobadoresOc();
    renderChartCompradorTrend(maxYm, selectedComp, tableYear);
    renderMonthlyTable(entityRecords, tableYear);
}

// =====================================================================
// RENDERIZACIÓN MÓDULO 2: GESTIÓN DE GASTO Y PROVEEDORES
// =====================================================================
function calcSpendStats(rows) {
    const firm = rows.filter(r => r.is_firm);
    const rfq  = rows.filter(r => r.is_rfq);
    const canc = rows.filter(r => r.is_cancelada);
    const noSap = firm.filter(r => !r.en_sap);

    const totalMonto = firm.reduce((s, r) => s + r.monto, 0);
    const avgTicket  = firm.length ? totalMonto / firm.length : 0;
    const medTicket  = firm.length ? median(firm.map(r => r.monto)) : 0;

    const provMap = {};
    firm.forEach(r => {
        if (!provMap[r.proveedor]) provMap[r.proveedor] = { monto: 0, ocs: 0, socs: new Set() };
        provMap[r.proveedor].monto += r.monto;
        provMap[r.proveedor].ocs += 1;
        provMap[r.proveedor].socs.add(r.sociedad);
    });

    const provSorted = Object.entries(provMap)
        .map(([name, info]) => ({ name, monto: info.monto, ocs: info.ocs, socCount: info.socs.size }))
        .sort((a, b) => b.monto - a.monto);

    const top5Monto = provSorted.slice(0, 5).reduce((s, p) => s + p.monto, 0);
    const top5Pct = totalMonto > 0 ? Math.round((top5Monto / totalMonto) * 100) : 0;

    const rfqMonto = rfq.reduce((s, r) => s + r.monto, 0);
    const sapPct = firm.length ? Math.round(((firm.length - noSap.length) / firm.length) * 100) : 0;
    const totalAllOcs = firm.length + rfq.length + canc.length;
    const cancPct = totalAllOcs > 0 ? ((canc.length / totalAllOcs) * 100).toFixed(1) : '0.0';

    return {
        firmList: firm,
        rfqList: rfq,
        noSapList: noSap,
        totalMonto,
        firmCount: firm.length,
        avgTicket,
        medTicket,
        provCount: provSorted.length,
        provSorted,
        top5Pct,
        rfqCount: rfq.length,
        rfqMonto,
        sapPct,
        cancPct
    };
}

function renderSpendView() {
    const selectedYear = document.getElementById('filterYear').value;
    const selectedYm   = document.getElementById('filterMes').value;
    const selectedQ    = document.getElementById('filterQuarter').value;

    const baseSpend = filterSpendRecordsBase(RAW_DATA.spend_records || []);
    const currentSpendRows = filterByActivePeriod(baseSpend);
    const st = calcSpendStats(currentSpendRows);

    // Calcular periodo comparativo anterior
    let prevLabel = null;
    let prevStats = null;

    if (periodMode === 'compare_q') {
        const yq1 = document.getElementById('filterCompareQ1').value;
        const yq2 = document.getElementById('filterCompareQ2').value;
        const rowsQ2 = filterRecordsByYearQuarter(baseSpend, yq2);
        prevStats = calcSpendStats(rowsQ2);
        prevLabel = formatYearQuarterShort(yq2);
        renderSpendCompareQPanel(yq1, yq2, st, prevStats);
    } else if (periodMode === 'month') {
        const allMeses = RAW_DATA.filters.meses;
        const idxYm = allMeses.indexOf(selectedYm);
        if (idxYm > 0) {
            const prevYm = allMeses[idxYm - 1];
            prevStats = calcSpendStats(baseSpend.filter(r => r.ym === prevYm));
            prevLabel = MONTH_NAMES[prevYm.split('-')[1]];
        }
    } else if (periodMode === 'quarter') {
        const order = ['Q1', 'Q2', 'Q3', 'Q4'];
        const idx = order.indexOf(selectedQ);
        const prevYear = idx > 0 ? selectedYear : String(Number(selectedYear) - 1);
        const prevQId  = idx > 0 ? order[idx - 1] : 'Q4';
        const prevRows = filterRecordsByYearQuarter(baseSpend, `${prevYear}-${prevQId}`);
        if (prevRows.length) {
            prevStats = calcSpendStats(prevRows);
            prevLabel = `${prevQId} ${prevYear !== selectedYear ? prevYear : ''}`.trim();
        }
    }

    // Guardar universos auditables para modal de Gasto
    currentTop5ProvidersSet = new Set(st.provSorted.slice(0, 5).map(p => p.name));
    cachedUniverses.SPEND_FIRM   = [...st.firmList].sort((a, b) => b.monto - a.monto);
    cachedUniverses.SPEND_RFQ    = [...st.rfqList].sort((a, b) => b.monto - a.monto);
    cachedUniverses.SPEND_NO_SAP = [...st.noSapList].sort((a, b) => b.monto - a.monto);

    // Tarjeta 1: Gasto Comprometido Total
    document.getElementById('spKpi1Value').textContent = formatMoney(st.totalMonto);
    document.getElementById('spKpi1Sub').textContent   = `${st.firmCount.toLocaleString()} OCs firmes en ${activeCurrency}`;
    const b1 = document.getElementById('spKpi1Badge');
    if (prevStats && prevStats.totalMonto > 0) {
        const pctDiff = Math.round(((st.totalMonto - prevStats.totalMonto) / prevStats.totalMonto) * 100);
        b1.textContent = `${pctDiff >= 0 ? '+' : ''}${pctDiff}% vs ${prevLabel}`;
        b1.className = 'text-[10px] px-2 py-0.5 rounded-full font-bold shrink-0 bg-slate-100 dark:bg-gray-800 text-cresta-navy dark:text-indigo-300';
    } else {
        b1.textContent = periodMode === 'ytd' ? `YTD ${selectedYear}` : activeCurrency;
        b1.className = 'text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0';
    }

    // Tarjeta 2: Ticket Promedio y OCs Emitidas
    document.getElementById('spKpi2Value').textContent = formatMoney(st.avgTicket);
    document.getElementById('spKpi2Badge').textContent = `${st.firmCount.toLocaleString()} OCs`;
    document.getElementById('spKpi2Sub').textContent   = `Mediana: ${formatMoney(st.medTicket)}`;

    // Tarjeta 3: Proveedores Activos y Top 5
    document.getElementById('spKpi3Value').textContent = st.provCount.toLocaleString();
    document.getElementById('spKpi3Badge').textContent = `Top 5: ${st.top5Pct}%`;
    document.getElementById('spKpi3Sub').textContent   = `Concentración Pareto en 5 mayores`;

    // Tarjeta 4: Cotizaciones Abiertas (RFQ)
    document.getElementById('spKpi4Value').textContent = formatMoney(st.rfqMonto);
    document.getElementById('spKpi4Badge').textContent = `${st.rfqCount} abiertas`;
    document.getElementById('spKpi4Sub').textContent   = `Cotizaciones sin convertir a OC`;

    // Tarjeta 5: Integración SAP & Cancelaciones
    document.getElementById('spKpi5Value').textContent = `${st.sapPct}%`;
    document.getElementById('spKpi5Badge').textContent = `Canc: ${st.cancPct}%`;
    document.getElementById('spKpi5Sub').textContent   = `${st.noSapList.length} OCs firmes pendientes SAP`;

    const trendYear = periodMode === 'compare_q'
        ? document.getElementById('filterCompareQ1').value.split('-')[0]
        : selectedYear;

    renderChartSpendTrend(baseSpend, trendYear);
    renderChartSpendSociedades(st.firmList);
    renderChartTopProveedoresMonto(st);
    renderChartTopCompradoresMonto(st.firmList);
    renderChartTopProveedoresFreq(st.firmList);
    renderChartProcurementFunnel();
    renderTopSuppliersTable(st);
}

function renderSpendCompareQPanel(yq1, yq2, st1, st2) {
    const lbl1 = formatYearQuarterShort(yq1);
    const lbl2 = formatYearQuarterShort(yq2);
    document.getElementById('spendCompareQTitle').textContent = `${lbl1} vs ${lbl2} (${activeCurrency})`;

    document.getElementById('spendCompareQHead').innerHTML = `<tr>
        <th class="py-2.5 px-3 font-bold rounded-tl-lg">Indicador de Gasto y Compras</th>
        <th class="py-2.5 px-3 text-right font-bold">${lbl1}</th>
        <th class="py-2.5 px-3 text-right font-bold">${lbl2}</th>
        <th class="py-2.5 px-3 text-right font-bold rounded-tr-lg">Variación</th>
    </tr>`;

    const pctVar = (v1, v2) => {
        if (!v2) return `<span class="text-slate-400">N/D</span>`;
        const d = ((v1 - v2) / v2) * 100;
        const cls = d <= 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-amber-700 dark:text-amber-300 font-bold';
        return `<span class="${cls}">${d > 0 ? '+' : ''}${d.toFixed(1)}%</span>`;
    };

    const rows = [
        {
            name: 'Gasto Comprometido Total',
            v1: formatMoney(st1.totalMonto),
            v2: formatMoney(st2.totalMonto),
            delta: pctVar(st1.totalMonto, st2.totalMonto)
        },
        {
            name: 'Órdenes de Compra Firmes',
            v1: st1.firmCount.toLocaleString(),
            v2: st2.firmCount.toLocaleString(),
            delta: `<span class="font-bold text-slate-600 dark:text-gray-300">${st1.firmCount - st2.firmCount >= 0 ? '+' : ''}${st1.firmCount - st2.firmCount} OCs</span>`
        },
        {
            name: 'Ticket Promedio por OC',
            v1: formatMoney(st1.avgTicket),
            v2: formatMoney(st2.avgTicket),
            delta: pctVar(st1.avgTicket, st2.avgTicket)
        },
        {
            name: 'Proveedores Activos',
            v1: st1.provCount.toLocaleString(),
            v2: st2.provCount.toLocaleString(),
            delta: `<span class="font-bold text-slate-600 dark:text-gray-300">${st1.provCount - st2.provCount >= 0 ? '+' : ''}${st1.provCount - st2.provCount}</span>`
        },
        {
            name: 'Concentración Top 5 Proveedores',
            v1: `${st1.top5Pct}%`,
            v2: `${st2.top5Pct}%`,
            delta: `<span class="font-bold text-slate-600 dark:text-gray-300">${st1.top5Pct - st2.top5Pct >= 0 ? '+' : ''}${st1.top5Pct - st2.top5Pct} pts</span>`
        }
    ];

    document.getElementById('spendCompareQBody').innerHTML = rows.map(r => `
        <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
            <td class="py-2 px-3 font-medium text-slate-700 dark:text-gray-200">${r.name}</td>
            <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-white">${r.v1}</td>
            <td class="py-2 px-3 text-right text-slate-600 dark:text-gray-300">${r.v2}</td>
            <td class="py-2 px-3 text-right">${r.delta}</td>
        </tr>
    `).join('');
}

function renderChartSpendTrend(baseSpend, year) {
    const meses = RAW_DATA.filters.meses.filter(m => m.startsWith(`${year}-`));
    document.getElementById('spChartTrendTitle').textContent = `Evolución Mensual del Gasto Comprometido (${activeCurrency}) · ${year}`;

    const montos = [];
    const ocsCount = [];

    meses.forEach(m => {
        const mFirm = baseSpend.filter(r => r.ym === m && r.is_firm);
        montos.push(Number(mFirm.reduce((s, r) => s + r.monto, 0).toFixed(2)));
        ocsCount.push(mFirm.length);
    });

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'line', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        stroke: { width: [0, 3], curve: 'smooth' },
        colors: isDark ? ['#6366F1', '#F59E0B'] : ['#1B2A4A', '#C8102E'],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
        series: [
            { name: `Gasto (${activeCurrency})`, type: 'column', data: montos },
            { name: 'Cantidad de OCs', type: 'line', data: ocsCount }
        ],
        xaxis: { categories: meses.map(formatMonthOnly), labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
        yaxis: [
            {
                title: { text: `Monto (${activeCurrency})`, style: { fontSize: '10px', color: themeCfg.labelColor } },
                labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
            },
            {
                opposite: true,
                title: { text: 'OCs emitidas', style: { fontSize: '10px', color: themeCfg.labelColor } },
                labels: { formatter: v => Math.round(v), style: { colors: themeCfg.labelColor } }
            }
        ],
        markers: { size: 4 },
        tooltip: {
            y: {
                formatter: (val, { seriesIndex }) => seriesIndex === 0 ? formatMoney(val) : `${val} OCs`
            }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '11px' }
    };

    if (charts.spendTrend) charts.spendTrend.destroy();
    charts.spendTrend = new ApexCharts(document.querySelector('#chartSpendTrend'), options);
    charts.spendTrend.render();
}

function renderChartSpendSociedades(firmList) {
    const mapSoc = {};
    firmList.forEach(r => {
        mapSoc[r.sociedad] = (mapSoc[r.sociedad] || 0) + r.monto;
    });

    const sorted = Object.entries(mapSoc).sort((a, b) => b[1] - a[1]);
    const themeCfg = getChartThemeConfig();

    const options = {
        chart: { type: 'donut', height: 310, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        colors: ['#1B2A4A', '#C8102E', '#2563EB', '#15803D', '#D97706', '#7C3AED', '#0D9488', '#64748B'],
        series: sorted.map(s => Number(s[1].toFixed(2))),
        labels: sorted.map(s => s[0]),
        dataLabels: { enabled: true, formatter: val => val >= 4 ? val.toFixed(1) + '%' : '' },
        tooltip: { y: { formatter: val => formatMoney(val) } },
        legend: { position: 'bottom', fontSize: '10px' }
    };

    if (charts.spendSoc) charts.spendSoc.destroy();
    charts.spendSoc = new ApexCharts(document.querySelector('#chartSpendSociedades'), options);
    charts.spendSoc.render();
}

function renderChartTopProveedoresMonto(st) {
    const top10 = st.provSorted.slice(0, 10);
    const top10Monto = top10.reduce((s, p) => s + p.monto, 0);
    const pctTop10 = st.totalMonto > 0 ? Math.round((top10Monto / st.totalMonto) * 100) : 0;
    document.getElementById('badgeParetoInfo').textContent = `Top 10 = ${pctTop10}% del gasto`;

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 320, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '65%' } },
        colors: isDark ? ['#6366F1'] : ['#1B2A4A'],
        series: [{ name: `Monto (${activeCurrency})`, data: top10.map(p => Number(p.monto.toFixed(2))) }],
        xaxis: {
            categories: top10.map(p => `${shortSupplier(p.name)} (${p.ocs})`),
            labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => formatMoneyCompact(v), style: { fontSize: '9px' } },
        tooltip: { y: { formatter: val => formatMoney(val) } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.topProvMonto) charts.topProvMonto.destroy();
    charts.topProvMonto = new ApexCharts(document.querySelector('#chartTopProveedoresMonto'), options);
    charts.topProvMonto.render();
}

function renderChartTopCompradoresMonto(firmList) {
    const mapComp = {};
    firmList.forEach(r => {
        if (!mapComp[r.comprador]) mapComp[r.comprador] = { monto: 0, ocs: 0 };
        mapComp[r.comprador].monto += r.monto;
        mapComp[r.comprador].ocs += 1;
    });

    const sorted = Object.entries(mapComp)
        .map(([name, info]) => ({ name, monto: info.monto, ocs: info.ocs }))
        .sort((a, b) => b.monto - a.monto);

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 320, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
        colors: isDark ? ['#10B981'] : ['#C8102E'],
        series: [{ name: `Monto (${activeCurrency})`, data: sorted.map(c => Number(c.monto.toFixed(2))) }],
        xaxis: {
            categories: sorted.map(c => `${shortName(c.name)} (${c.ocs} OCs)`),
            labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => formatMoneyCompact(v), style: { fontSize: '9px' } },
        tooltip: { y: { formatter: val => formatMoney(val) } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.topCompMonto) charts.topCompMonto.destroy();
    charts.topCompMonto = new ApexCharts(document.querySelector('#chartTopCompradoresMonto'), options);
    charts.topCompMonto.render();
}

function renderChartTopProveedoresFreq(firmList) {
    const mapProv = {};
    firmList.forEach(r => {
        if (!mapProv[r.proveedor]) mapProv[r.proveedor] = { ocs: 0, monto: 0 };
        mapProv[r.proveedor].ocs += 1;
        mapProv[r.proveedor].monto += r.monto;
    });

    const top10 = Object.entries(mapProv)
        .map(([name, info]) => ({ name, ocs: info.ocs, monto: info.monto }))
        .sort((a, b) => b.ocs - a.ocs)
        .slice(0, 10);

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
        colors: isDark ? ['#38BDF8'] : ['#264073'],
        series: [{ name: 'OCs Emitidas', data: top10.map(p => p.ocs) }],
        xaxis: {
            categories: top10.map(p => shortSupplier(p.name)),
            labels: { style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => `${v} OCs`, style: { fontSize: '9px' } },
        tooltip: {
            y: {
                formatter: (val, { dataPointIndex }) => `${val} OCs · ${formatMoney(top10[dataPointIndex].monto)}`
            }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.topProvFreq) charts.topProvFreq.destroy();
    charts.topProvFreq = new ApexCharts(document.querySelector('#chartTopProveedoresFreq'), options);
    charts.topProvFreq.render();
}

function renderChartProcurementFunnel() {
    // Embudo del proceso de compra sobre el universo filtrado
    const entityReqs = filterByActivePeriod(filterByEntity(RAW_DATA.records));
    const validReqs = entityReqs.filter(r => !r.excluida && r.aprobada);

    const baseSpend = filterSpendRecordsBase(RAW_DATA.spend_records || []);
    const periodSpend = filterByActivePeriod(baseSpend);
    const totalOcsOdoo = periodSpend.filter(r => !r.is_cancelada).length;
    const firmOcs = periodSpend.filter(r => r.is_firm).length;
    const sapOcs = periodSpend.filter(r => r.is_firm && r.en_sap).length;

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 5,
                barHeight: '55%',
                distributed: true
            }
        },
        colors: ['#64748B', '#264073', '#1B2A4A', '#15803D'],
        series: [{
            name: 'Documentos',
            data: [validReqs.length, totalOcsOdoo, firmOcs, sapOcs]
        }],
        xaxis: {
            categories: [
                '1. Solicitudes Aprobadas',
                `2. Cotizaciones + OCs (${activeCurrency})`,
                `3. OCs Firmes (${activeCurrency})`,
                `4. Sincronizadas en SAP (${activeCurrency})`
            ],
            labels: { style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, style: { fontSize: '10px' } },
        legend: { show: false },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.funnel) charts.funnel.destroy();
    charts.funnel = new ApexCharts(document.querySelector('#chartProcurementFunnel'), options);
    charts.funnel.render();
}

function renderTopSuppliersTable(st) {
    const tbody = document.getElementById('topSuppliersTableBody');
    const top15 = st.provSorted.slice(0, 15);

    if (!top15.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-6 text-center text-slate-400">No hay órdenes firmes en esta divisa/periodo.</td></tr>`;
        return;
    }

    let accumPct = 0;
    tbody.innerHTML = top15.map((p, idx) => {
        const pct = st.totalMonto > 0 ? (p.monto / st.totalMonto) * 100 : 0;
        accumPct += pct;
        const ticket = p.ocs > 0 ? p.monto / p.ocs : 0;

        return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
            <td class="py-2.5 px-3 font-bold text-slate-400">${idx + 1}</td>
            <td class="py-2.5 px-3 font-bold text-cresta-navy dark:text-white">${p.name}</td>
            <td class="py-2.5 px-3 text-right text-slate-700 dark:text-gray-300">${p.ocs}</td>
            <td class="py-2.5 px-3 text-right text-slate-600 dark:text-gray-400">${p.socCount}</td>
            <td class="py-2.5 px-3 text-right text-slate-600 dark:text-gray-300">${formatMoney(ticket)}</td>
            <td class="py-2.5 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${formatMoney(p.monto)}</td>
            <td class="py-2.5 px-3 text-right font-semibold text-slate-700 dark:text-gray-300">${pct.toFixed(1)}%</td>
            <td class="py-2.5 px-3 text-right font-bold text-cresta-red dark:text-emerald-400">${accumPct.toFixed(1)}%</td>
        </tr>`;
    }).join('');
}

// =====================================================================
// GRÁFICAS DEL MÓDULO 1 (CICLO OPERATIVO)
// =====================================================================
function renderQuarterComparisonPanel(yq1, yq2, stats1, stats2) {
    const lbl1 = formatYearQuarterShort(yq1);
    const lbl2 = formatYearQuarterShort(yq2);
    document.getElementById('compareQTitle').textContent = `${lbl1} vs ${lbl2}`;

    const entityOc = filterByEntity(RAW_DATA.oc_records || []);
    const oc1 = filterRecordsByYearQuarter(entityOc, yq1).map(r => r.d_aprob_oc);
    const oc2 = filterRecordsByYearQuarter(entityOc, yq2).map(r => r.d_aprob_oc);
    const avgOc1 = oc1.length ? Number(avg(oc1).toFixed(1)) : 0;
    const avgOc2 = oc2.length ? Number(avg(oc2).toFixed(1)) : 0;

    const dSol1 = stats1.avgDiasAprobSol !== null ? Number(stats1.avgDiasAprobSol.toFixed(1)) : 0;
    const dSol2 = stats2.avgDiasAprobSol !== null ? Number(stats2.avgDiasAprobSol.toFixed(1)) : 0;

    const dComp1 = stats1.avgDiasComp !== null ? Number(stats1.avgDiasComp.toFixed(1)) : 0;
    const dComp2 = stats2.avgDiasComp !== null ? Number(stats2.avgDiasComp.toFixed(1)) : 0;

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 270, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        colors: isDark ? ['#6366F1', '#9CA3AF'] : ['#1B2A4A', '#94A3B8'],
        plotOptions: { bar: { horizontal: false, columnWidth: '52%', borderRadius: 4 } },
        series: [
            { name: `${lbl1} (Base)`, data: [dSol1, dComp1, avgOc1] },
            { name: `${lbl2} (Comparar)`, data: [dSol2, dComp2, avgOc2] }
        ],
        xaxis: {
            categories: ['Aprob. Solicitud', 'Gestión Comprador', 'Aprob. OC (Aut. 1)'],
            labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } }
        },
        yaxis: {
            title: { text: 'Días promedio', style: { fontSize: '10px', color: themeCfg.labelColor } },
            labels: { formatter: v => v.toFixed(1) + ' d', style: { colors: themeCfg.labelColor } }
        },
        dataLabels: { enabled: true, formatter: v => v.toFixed(1) + 'd', style: { fontSize: '9px' } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '11px' }
    };

    if (charts.compareQ) charts.compareQ.destroy();
    charts.compareQ = new ApexCharts(document.querySelector('#chartCompareQ'), options);
    charts.compareQ.render();

    document.getElementById('compareQTableHead').innerHTML = `<tr>
        <th class="py-2.5 px-3 font-bold rounded-tl-lg">Métrica</th>
        <th class="py-2.5 px-3 text-right font-bold">${lbl1}</th>
        <th class="py-2.5 px-3 text-right font-bold">${lbl2}</th>
        <th class="py-2.5 px-3 text-right font-bold rounded-tr-lg">Variación</th>
    </tr>`;

    const fmtDelta = (v1, v2, unit, lowerIsBetter) => {
        if (v1 === null || v2 === null) return `<span class="text-slate-400">N/D</span>`;
        const diff = Number((v1 - v2).toFixed(1));
        if (diff === 0) return `<span class="text-slate-500 font-bold">0${unit}</span>`;
        const good = lowerIsBetter ? (diff < 0) : (diff > 0);
        const cls = good ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-cresta-red dark:text-red-400 font-bold';
        return `<span class="${cls}">${diff > 0 ? '+' : ''}${diff}${unit}</span>`;
    };

    const rows = [
        {
            name: 'Solicitudes válidas',
            v1: stats1.totalValidas.toLocaleString(),
            v2: stats2.totalValidas.toLocaleString(),
            delta: `<span class="font-bold text-slate-600 dark:text-gray-300">${stats1.totalValidas - stats2.totalValidas >= 0 ? '+' : ''}${stats1.totalValidas - stats2.totalValidas}</span>`
        },
        {
            name: 'Días prom. gestión comprador',
            v1: stats1.avgDiasComp !== null ? `${dComp1.toFixed(1)} d` : 'N/D',
            v2: stats2.avgDiasComp !== null ? `${dComp2.toFixed(1)} d` : 'N/D',
            delta: fmtDelta(stats1.avgDiasComp, stats2.avgDiasComp, ' d', true)
        },
        {
            name: 'Mediana gestión comprador',
            v1: stats1.medDiasComp !== null ? `${stats1.medDiasComp.toFixed(1)} d` : 'N/D',
            v2: stats2.medDiasComp !== null ? `${stats2.medDiasComp.toFixed(1)} d` : 'N/D',
            delta: fmtDelta(stats1.medDiasComp, stats2.medDiasComp, ' d', true)
        },
        {
            name: 'SLA Comprador ≤14 días',
            v1: stats1.pctComp14 !== null ? `${stats1.pctComp14}%` : 'N/D',
            v2: stats2.pctComp14 !== null ? `${stats2.pctComp14}%` : 'N/D',
            delta: fmtDelta(stats1.pctComp14, stats2.pctComp14, ' pts', false)
        },
        {
            name: 'Enviadas a SAP ≤14 días',
            v1: stats1.pctSap14 !== null ? `${stats1.pctSap14}%` : 'N/D',
            v2: stats2.pctSap14 !== null ? `${stats2.pctSap14}%` : 'N/D',
            delta: fmtDelta(stats1.pctSap14, stats2.pctSap14, ' pts', false)
        },
        {
            name: 'Días prom. aprobación OC',
            v1: `${avgOc1.toFixed(1)} d`,
            v2: `${avgOc2.toFixed(1)} d`,
            delta: fmtDelta(avgOc1, avgOc2, ' d', true)
        }
    ];

    document.getElementById('compareQTableBody').innerHTML = rows.map(r => `
        <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
            <td class="py-2 px-3 font-medium text-slate-700 dark:text-gray-200">${r.name}</td>
            <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-white">${r.v1}</td>
            <td class="py-2 px-3 text-right text-slate-600 dark:text-gray-300">${r.v2}</td>
            <td class="py-2 px-3 text-right">${r.delta}</td>
        </tr>
    `).join('');
}

function getChartThemeConfig() {
    return {
        mode: isDark ? 'dark' : 'light',
        labelColor: isDark ? '#9CA3AF' : '#475569',
        gridColor: isDark ? '#374151' : '#E2E8F0'
    };
}

function renderChartQuincenas(entityRecords, maxYm) {
    const validas = entityRecords.filter(r => !r.excluida && r.aprobada && r.ym <= maxYm);
    const quincenasSet = [...new Set(validas.map(r => r.quincena))].sort();
    const recentQ = quincenasSet.slice(-8);

    const s7 = [], s14 = [], s30 = [], labels = [];

    recentQ.forEach(q => {
        const rows = validas.filter(r => r.quincena === q);
        const [ym, half] = q.split('-Q');
        labels.push(`${half === '1' ? '1ª Q' : '2ª Q'} ${formatYm(ym)}`);

        const eval7 = rows.filter(r => r.edad_dias >= 7);
        const ok7 = eval7.filter(r => r.tiene_oc_sap && r.d_to_sap <= 7);
        s7.push(eval7.length >= 10 ? Math.round((ok7.length / eval7.length) * 100) : null);

        const eval14 = rows.filter(r => r.edad_dias >= 14);
        const ok14 = eval14.filter(r => r.tiene_oc_sap && r.d_to_sap <= 14);
        s14.push(eval14.length >= 10 ? Math.round((ok14.length / eval14.length) * 100) : null);

        const eval30 = rows.filter(r => r.edad_dias >= 30);
        const ok30 = eval30.filter(r => r.tiene_oc_sap && r.d_to_sap <= 30);
        s30.push(eval30.length >= 10 ? Math.round((ok30.length / eval30.length) * 100) : null);
    });

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'line', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        stroke: { width: [2.5, 3.5, 2.5], curve: 'smooth' },
        colors: ['#64748B', '#1B2A4A', '#C8102E'],
        series: [
            { name: '≤ 7 días', data: s7 },
            { name: '≤ 14 días (KPI)', data: s14 },
            { name: '≤ 30 días', data: s30 }
        ],
        xaxis: { categories: labels, labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
        yaxis: { min: 0, max: 100, labels: { formatter: v => v !== null ? v + '%' : '', style: { colors: themeCfg.labelColor } } },
        markers: { size: 4 },
        dataLabels: { enabled: true, formatter: v => v !== null ? v + '%' : '', style: { fontSize: '9px' } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '11px' }
    };

    if (isDark) {
        options.colors = ['#38BDF8', '#818CF8', '#F87171'];
    }

    if (charts.quincenas) charts.quincenas.destroy();
    charts.quincenas = new ApexCharts(document.querySelector('#chartQuincenas'), options);
    charts.quincenas.render();
}

function renderChartCiclo(entityRecords, maxYm) {
    const meses = RAW_DATA.filters.meses.filter(m => m <= maxYm).slice(-6);
    const labels = meses.map(formatYm);
    const dAprobSol = [], dComprador = [], dAprobSap = [];

    meses.forEach(m => {
        const cerradas = entityRecords.filter(r => r.ym === m && !r.excluida && r.tiene_oc_sap);
        dAprobSol.push(Number(avg(cerradas.map(r => r.d_aprob_sol || 0)).toFixed(1)));
        dComprador.push(Number(avg(cerradas.map(r => r.d_comprador || 0)).toFixed(1)));
        dAprobSap.push(Number(avg(cerradas.map(r => r.d_aprob_sap || 0)).toFixed(1)));
    });

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', stacked: true, height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        colors: ['#94A3B8', '#1B2A4A', '#C8102E'],
        series: [
            { name: '1. Aprob. Solicitud', data: dAprobSol },
            { name: '2. Gestión Comprador (Odoo)', data: dComprador },
            { name: '3. Aprob. OC y Envío SAP', data: dAprobSap }
        ],
        xaxis: { categories: labels, labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
        yaxis: { title: { text: 'Días promedio', style: { fontSize: '10px', color: themeCfg.labelColor } }, labels: { style: { colors: themeCfg.labelColor } } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        dataLabels: { enabled: true, formatter: v => v > 0.5 ? v : '', style: { fontSize: '9px' } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '10px' }
    };

    if (isDark) {
        options.colors = ['#6B7280', '#6366F1', '#F59E0B'];
    }

    if (charts.ciclo) charts.ciclo.destroy();
    charts.ciclo = new ApexCharts(document.querySelector('#chartCiclo'), options);
    charts.ciclo.render();
}

function renderChartCompradores(currentRecords) {
    const validas = currentRecords.filter(r => !r.excluida && r.aprobada);
    const hasMatureAny = validas.some(r => r.edad_dias >= 14);
    const badgePartial = document.getElementById('badgeCompradoresPartial');

    if (!hasMatureAny && validas.length > 0) {
        badgePartial.classList.remove('hidden');
    } else {
        badgePartial.classList.add('hidden');
    }

    const mapComp = {};
    validas.forEach(r => {
        if (!mapComp[r.comprador]) mapComp[r.comprador] = [];
        mapComp[r.comprador].push(r);
    });

    const summary = Object.entries(mapComp)
        .map(([name, rows]) => {
            const matureRows = rows.filter(r => r.edad_dias >= 14);
            const evalRows = matureRows.length > 0
                ? matureRows
                : rows.filter(r => r.tiene_oc_odoo && r.d_comprador !== null);

            const okRows = evalRows.filter(r => r.tiene_oc_odoo && r.d_comprador <= 14);
            const pct = evalRows.length ? Math.round((okRows.length / evalRows.length) * 100) : 0;
            return { name, total: rows.length, evalCount: evalRows.length, pct };
        })
        .filter(item => item.total >= 3 && item.evalCount > 0)
        .sort((a, b) => b.pct - a.pct);

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 5,
                barHeight: '60%',
                distributed: true
            }
        },
        colors: summary.map(s => s.pct >= 70 ? '#15803D' : (s.pct >= 55 ? '#1B2A4A' : '#C8102E')),
        series: [{ name: '% SLA ≤14 días', data: summary.map(s => s.pct) }],
        xaxis: {
            categories: summary.map(s => `${shortName(s.name)} (${s.total})`),
            min: 0,
            max: 100,
            labels: { formatter: v => v + '%', style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        annotations: {
            xaxis: [{
                x: 70,
                borderColor: '#C8102E',
                strokeDashArray: 4,
                label: { text: 'Meta 70%', style: { color: '#FFFFFF', background: '#C8102E', fontSize: '10px' } }
            }]
        },
        dataLabels: { enabled: true, formatter: v => v + '%', style: { fontSize: '10px' } },
        legend: { show: false },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.compradores) charts.compradores.destroy();
    charts.compradores = new ApexCharts(document.querySelector('#chartCompradores'), options);
    charts.compradores.render();
}

function renderChartRezago(rezagoRecords) {
    const byMonth = {};
    rezagoRecords.forEach(r => {
        if (!byMonth[r.ym]) byMonth[r.ym] = { le30: 0, gt30: 0 };
        if (r.d_to_sap !== null && r.d_to_sap > 30) {
            byMonth[r.ym].gt30++;
        } else {
            byMonth[r.ym].le30++;
        }
    });

    const meses = Object.keys(byMonth).sort();
    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', stacked: true, height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        colors: ['#1B2A4A', '#C8102E'],
        series: [
            { name: '≤ 30 días abiertas', data: meses.map(m => byMonth[m].le30) },
            { name: '> 30 días (Crítico)', data: meses.map(m => byMonth[m].gt30) }
        ],
        xaxis: { categories: meses.map(formatYm), labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
        yaxis: { labels: { style: { colors: themeCfg.labelColor } } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        dataLabels: { enabled: true, formatter: v => v > 0 ? v : '', style: { fontSize: '9px' } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '10px' }
    };

    if (isDark) {
        options.colors = ['#F59E0B', '#EF4444'];
    }

    if (charts.rezago) charts.rezago.destroy();
    charts.rezago = new ApexCharts(document.querySelector('#chartRezago'), options);
    charts.rezago.render();
}

function renderChartAprobadoresSol(currentRecords) {
    const validas = currentRecords.filter(r =>
        !r.excluida &&
        r.aprobada &&
        r.d_aprob_sol !== null &&
        !r.aprob_sol_excluido
    );

    const mapAprob = {};
    validas.forEach(r => {
        if (!mapAprob[r.autorizador_sol]) mapAprob[r.autorizador_sol] = [];
        mapAprob[r.autorizador_sol].push(r.d_aprob_sol);
    });

    const minSample = periodMode === 'month' ? 3 : 5;
    const summary = Object.entries(mapAprob)
        .map(([name, days]) => ({
            name,
            count: days.length,
            avgDays: Number(avg(days).toFixed(2))
        }))
        .filter(item => item.count >= minSample)
        .sort((a, b) => a.avgDays - b.avgDays);

    const activeDays = summary.flatMap(s => mapAprob[s.name]);
    const overallAvg = activeDays.length ? avg(activeDays) : 0;
    document.getElementById('badgeAprobSolAvg').textContent = `Prom: ${overallAvg.toFixed(1)} d`;

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 5,
                barHeight: '60%',
                distributed: true
            }
        },
        colors: summary.map(s => s.avgDays <= 2 ? '#15803D' : (s.avgDays <= 5 ? '#1B2A4A' : '#C8102E')),
        series: [{ name: 'Días promedio aprobación', data: summary.map(s => s.avgDays) }],
        xaxis: {
            categories: summary.map(s => `${shortName(s.name)} (${s.count})`),
            labels: { formatter: v => v.toFixed(1) + ' d', style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => v.toFixed(1) + ' d', style: { fontSize: '10px' } },
        legend: { show: false },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.aprobSol) charts.aprobSol.destroy();
    charts.aprobSol = new ApexCharts(document.querySelector('#chartAprobadoresSol'), options);
    charts.aprobSol.render();
}

function renderChartAprobadoresOc() {
    const ocList = filterByActivePeriod(filterByEntity(RAW_DATA.oc_records || []))
        .filter(r => r.d_aprob_oc !== null && !r.aprob_oc_excluido);

    const mapAprob = {};
    ocList.forEach(r => {
        if (!mapAprob[r.aprobador_oc]) mapAprob[r.aprobador_oc] = [];
        mapAprob[r.aprobador_oc].push(r.d_aprob_oc);
    });

    const minSample = periodMode === 'month' ? 2 : 5;
    const summary = Object.entries(mapAprob)
        .map(([name, days]) => ({
            name,
            count: days.length,
            avgDays: Number(avg(days).toFixed(2))
        }))
        .filter(item => item.count >= minSample)
        .sort((a, b) => a.avgDays - b.avgDays);

    const activeDays = summary.flatMap(s => mapAprob[s.name]);
    const overallAvg = activeDays.length ? avg(activeDays) : 0;
    document.getElementById('badgeAprobOcAvg').textContent = `Prom: ${overallAvg.toFixed(1)} d`;

    const subEl = document.getElementById('subAprobOc');
    if (summary.length === 0) {
        subEl.textContent = 'Sin OCs con Autorizador 1 registrado en este periodo';
    } else {
        subEl.textContent = 'Creación OC → Aprobación OC (Autorizador 1) · Ordenado de más ágil a más lento';
    }

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        noData: {
            text: 'Sin datos de Autorizador 1 en este filtro',
            style: { color: themeCfg.labelColor, fontSize: '12px', fontFamily: 'Verdana' }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 5,
                barHeight: '60%',
                distributed: true
            }
        },
        colors: summary.map(s => s.avgDays <= 2 ? '#15803D' : (s.avgDays <= 5 ? '#264073' : '#C8102E')),
        series: [{ name: 'Días promedio aprobación OC', data: summary.map(s => s.avgDays) }],
        xaxis: {
            categories: summary.map(s => `${shortName(s.name)} (${s.count})`),
            labels: { formatter: v => v.toFixed(1) + ' d', style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => v.toFixed(1) + ' d', style: { fontSize: '10px' } },
        legend: { show: false },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.aprobOc) charts.aprobOc.destroy();
    charts.aprobOc = new ApexCharts(document.querySelector('#chartAprobadoresOc'), options);
    charts.aprobOc.render();
}

function renderChartCompradorTrend(maxYm, selectedComp, selectedYear) {
    const socRecords = filterBySociedadOnly(RAW_DATA.records)
        .filter(r => !r.excluida && r.aprobada && r.tiene_oc_odoo && r.d_comprador !== null);

    const allMesesUpTo = RAW_DATA.filters.meses.filter(m => m <= maxYm);
    const meses = (periodMode === 'ytd' || periodMode === 'quarter' || periodMode === 'compare_q')
        ? allMesesUpTo.filter(m => m.startsWith(`${selectedYear}-`))
        : allMesesUpTo.slice(-6);

    const labels = meses.map(formatYm);
    const themeCfg = getChartThemeConfig();

    const titleEl = document.getElementById('chartCompTrendTitle');
    const subEl = document.getElementById('chartCompTrendSub');
    const badgeEl = document.getElementById('chartCompTrendBadge');

    if (charts.compTrend) charts.compTrend.destroy();

    if (selectedComp !== 'ALL') {
        titleEl.textContent = `Evolución Mensual de Gestión: ${selectedComp}`;
        subEl.textContent = 'Barras = Días promedio a OC en Odoo · Línea = Mediana (sin sesgo de casos atípicos)';
        badgeEl.textContent = 'Progreso Individual';

        const compRows = socRecords.filter(r => r.comprador === selectedComp);
        const avgSeries = [];
        const medSeries = [];
        const teamAvgSeries = [];

        meses.forEach(m => {
            const mComp = compRows.filter(r => r.ym === m).map(r => r.d_comprador);
            const mTeam = socRecords.filter(r => r.ym === m).map(r => r.d_comprador);

            avgSeries.push(mComp.length ? Number(avg(mComp).toFixed(1)) : null);
            medSeries.push(mComp.length ? Number(median(mComp).toFixed(1)) : null);
            teamAvgSeries.push(mTeam.length ? Number(avg(mTeam).toFixed(1)) : null);
        });

        const options = {
            chart: { type: 'line', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
            theme: { mode: themeCfg.mode },
            stroke: { width: [0, 3, 2], curve: 'smooth', dashArray: [0, 0, 5] },
            colors: isDark ? ['#6366F1', '#10B981', '#9CA3AF'] : ['#1B2A4A', '#C8102E', '#64748B'],
            plotOptions: { bar: { borderRadius: 5, columnWidth: '45%' } },
            series: [
                { name: 'Promedio Comprador (días)', type: 'column', data: avgSeries },
                { name: 'Mediana Comprador (días)', type: 'line', data: medSeries },
                { name: 'Promedio Equipo (Ref.)', type: 'line', data: teamAvgSeries }
            ],
            xaxis: { categories: labels, labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
            yaxis: {
                min: 0,
                title: { text: 'Días de gestión', style: { fontSize: '10px', color: themeCfg.labelColor } },
                labels: { formatter: v => v !== null ? v.toFixed(1) + ' d' : '', style: { colors: themeCfg.labelColor } }
            },
            markers: { size: 4 },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0, 1],
                formatter: v => v !== null ? v.toFixed(1) : '',
                style: { fontSize: '9px' }
            },
            grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
            legend: { position: 'top', fontSize: '11px' }
        };

        charts.compTrend = new ApexCharts(document.querySelector('#chartCompradorTrend'), options);
        charts.compTrend.render();
    } else {
        titleEl.textContent = 'Evolución Mensual: Días Promedio de Gestión por Comprador';
        subEl.textContent = 'Comparativa mensual de velocidad (Aprobación Solicitud → OC en Odoo) · Menos días = mayor agilidad';
        badgeEl.textContent = 'Comparativa Equipo';

        const compCounts = {};
        socRecords.filter(r => meses.includes(r.ym)).forEach(r => {
            compCounts[r.comprador] = (compCounts[r.comprador] || 0) + 1;
        });

        const activeComps = Object.keys(compCounts)
            .filter(c => compCounts[c] >= 10)
            .sort((a, b) => compCounts[b] - compCounts[a]);

        const palette = isDark
            ? ['#38BDF8', '#F87171', '#34D399', '#FBBF24', '#A78BFA', '#F472B6', '#FB923C']
            : ['#1B2A4A', '#C8102E', '#15803D', '#D97706', '#2563EB', '#7C3AED', '#0D9488'];

        const series = activeComps.map(compName => {
            const data = meses.map(m => {
                const vals = socRecords
                    .filter(r => r.comprador === compName && r.ym === m)
                    .map(r => r.d_comprador);
                return vals.length >= 3 ? Number(avg(vals).toFixed(1)) : null;
            });
            return { name: shortName(compName), data };
        });

        const teamData = meses.map(m => {
            const vals = socRecords.filter(r => r.ym === m).map(r => r.d_comprador);
            return vals.length ? Number(avg(vals).toFixed(1)) : null;
        });
        series.push({ name: 'Promedio Equipo', data: teamData });

        const colors = [...palette.slice(0, activeComps.length), isDark ? '#E5E7EB' : '#64748B'];
        const widths = [...activeComps.map(() => 2.5), 3];
        const dashes = [...activeComps.map(() => 0), 6];

        const options = {
            chart: { type: 'line', height: 320, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
            theme: { mode: themeCfg.mode },
            stroke: { width: widths, curve: 'smooth', dashArray: dashes },
            colors: colors,
            series: series,
            xaxis: { categories: labels, labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
            yaxis: {
                min: 0,
                title: { text: 'Días promedio a OC', style: { fontSize: '10px', color: themeCfg.labelColor } },
                labels: { formatter: v => v !== null ? v.toFixed(1) + ' d' : '', style: { colors: themeCfg.labelColor } }
            },
            markers: { size: 4 },
            tooltip: {
                y: { formatter: v => v !== null ? `${v.toFixed(1)} días` : 'Sin muestra suficiente' }
            },
            grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
            legend: { position: 'top', fontSize: '11px' }
        };

        charts.compTrend = new ApexCharts(document.querySelector('#chartCompradorTrend'), options);
        charts.compTrend.render();
    }
}

function renderMonthlyTable(entityRecords, selectedYear) {
    const meses = RAW_DATA.filters.meses.filter(m => m.startsWith(`${selectedYear}-`));
    const thead = document.getElementById('monthlyTableHead');
    const tbody = document.getElementById('monthlyTableBody');
    document.getElementById('monthlyTableTitle').textContent = `Indicadores Mensuales Consolidados · Año ${selectedYear}`;

    thead.innerHTML = `<tr>
        <th class="py-3 px-4 font-bold rounded-tl-lg">Indicador</th>
        ${meses.map((m, idx) => `<th class="py-3 px-3 text-right font-bold ${idx === meses.length - 1 ? 'rounded-tr-lg' : ''}">${formatMonthOnly(m)} ${selectedYear}</th>`).join('')}
    </tr>`;

    const metricsByMonth = meses.map(m => {
        const allM = entityRecords.filter(r => r.ym === m);
        const validas = allM.filter(r => !r.excluida);
        const aprobadas = validas.filter(r => r.aprobada);
        const rechazadas = allM.filter(r => r.is_rechazada);

        const conOc = aprobadas.filter(r => r.tiene_oc_odoo && r.d_comprador !== null);
        const avgComp = conOc.length ? Number(avg(conOc.map(r => r.d_comprador)).toFixed(1)) : null;

        const e7 = aprobadas.filter(r => r.edad_dias >= 7);
        const p7 = e7.length >= 10 ? Math.round((e7.filter(r => r.tiene_oc_sap && r.d_to_sap <= 7).length / e7.length) * 100) : null;

        const e14 = aprobadas.filter(r => r.edad_dias >= 14);
        const p14 = e14.length >= 10 ? Math.round((e14.filter(r => r.tiene_oc_sap && r.d_to_sap <= 14).length / e14.length) * 100) : null;

        const e30 = aprobadas.filter(r => r.edad_dias >= 30);
        const p30 = e30.length >= 10 ? Math.round((e30.filter(r => r.tiene_oc_sap && r.d_to_sap <= 30).length / e30.length) * 100) : null;

        const medAprob = median(validas.map(r => r.d_aprob_sol).filter(v => v !== null));
        const pctRech = allM.length ? ((rechazadas.length / allM.length) * 100).toFixed(1) : '0.0';

        const ocStat = RAW_DATA.oc_stats[m] || { total: 0, canceladas: 0 };
        const pctCanc = ocStat.total ? ((ocStat.canceladas / ocStat.total) * 100).toFixed(1) : '0.0';

        return {
            creadas: validas.length,
            avgComp,
            p7, p14, p30,
            medAprob: medAprob.toFixed(2),
            pctRech, pctCanc
        };
    });

    const minAvgComp = Math.min(...metricsByMonth.map(x => x.avgComp ?? 9999));
    const maxP7 = Math.max(...metricsByMonth.map(x => x.p7 ?? -1));
    const maxP14 = Math.max(...metricsByMonth.map(x => x.p14 ?? -1));
    const maxP30 = Math.max(...metricsByMonth.map(x => x.p30 ?? -1));

    const fmtCell = (val, maxVal) => {
        if (val === null) return `<td class="py-2.5 px-3 text-right text-slate-400 dark:text-gray-500 italic">En curso</td>`;
        const cls = val === maxVal
            ? 'text-cresta-red dark:text-emerald-400 font-bold bg-red-50/60 dark:bg-emerald-950/30'
            : 'text-slate-700 dark:text-gray-300';
        return `<td class="py-2.5 px-3 text-right ${cls}">${val}%</td>`;
    };

    const rowsHtml = [
        {
            label: 'Solicitudes válidas creadas',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right font-bold text-cresta-navy dark:text-white">${x.creadas}</td>`)
        },
        {
            label: 'Promedio días gestión comprador (Odoo)',
            vals: metricsByMonth.map(x => {
                if (x.avgComp === null) return `<td class="py-2.5 px-3 text-right text-slate-400">N/D</td>`;
                const cls = x.avgComp === minAvgComp
                    ? 'text-cresta-red dark:text-emerald-400 font-bold bg-red-50/60 dark:bg-emerald-950/30'
                    : 'text-cresta-blue dark:text-sky-400 font-semibold';
                return `<td class="py-2.5 px-3 text-right ${cls}">${x.avgComp.toFixed(1)} d</td>`;
            })
        },
        {
            label: 'Enviadas a SAP en ≤ 7 días',
            vals: metricsByMonth.map(x => fmtCell(x.p7, maxP7))
        },
        {
            label: 'Enviadas a SAP en ≤ 14 días',
            vals: metricsByMonth.map(x => fmtCell(x.p14, maxP14))
        },
        {
            label: 'Enviadas a SAP en ≤ 30 días',
            vals: metricsByMonth.map(x => fmtCell(x.p30, maxP30))
        },
        {
            label: 'Mediana días aprobar solicitud',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right text-slate-700 dark:text-gray-300">${x.medAprob}</td>`)
        },
        {
            label: '% Solicitudes rechazadas',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right text-slate-500 dark:text-gray-400">${x.pctRech}%</td>`)
        },
        {
            label: '% Órdenes de compra canceladas',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right text-slate-500 dark:text-gray-400">${x.pctCanc}%</td>`)
        }
    ];

    tbody.innerHTML = rowsHtml.map(r => `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40"><td class="py-2.5 px-4 text-slate-800 dark:text-gray-200 font-medium">${r.label}</td>${r.vals.join('')}</tr>`).join('');
}

// =====================================================================
// MODAL UNIVERSAL DE AUDITORÍA (SOPORTA CICLO Y GASTO)
// =====================================================================
function openAuditModal(category, defaultSubFilter) {
    activeModalCategory = category || 'REZAGO';
    activeModalSubFilter = defaultSubFilter || 'ALL';
    document.getElementById('modalSearch').value = '';

    const cfg = MODAL_CONFIG[activeModalCategory];
    document.getElementById('modalBadge').textContent = cfg.badge;
    document.getElementById('modalTitle').textContent = cfg.title;

    if (cfg.mode === 'spend') {
        document.getElementById('modalTableHead').innerHTML = `<tr>
            <th class="py-2.5 px-3">OC Odoo</th>
            <th class="py-2.5 px-3">Fecha OC</th>
            <th class="py-2.5 px-3">Sociedad</th>
            <th class="py-2.5 px-3">Proveedor</th>
            <th class="py-2.5 px-3">Comprador</th>
            <th class="py-2.5 px-3">Estado / SAP</th>
            <th class="py-2.5 px-3 text-right">Monto (${activeCurrency})</th>
        </tr>`;
    } else {
        document.getElementById('modalTableHead').innerHTML = `<tr>
            <th class="py-2.5 px-3">Solicitud</th>
            <th class="py-2.5 px-3">Fecha Creación</th>
            <th class="py-2.5 px-3">Sociedad</th>
            <th class="py-2.5 px-3">Comprador</th>
            <th class="py-2.5 px-3">OC Odoo</th>
            <th class="py-2.5 px-3">Estatus / Etapas</th>
            <th class="py-2.5 px-3 text-right">${cfg.daysLabel}</th>
        </tr>`;
    }

    renderModalTabs();
    document.getElementById('auditModal').classList.remove('hidden');
    renderModalTable();
}

function closeAuditModal() {
    document.getElementById('auditModal').classList.add('hidden');
}

function setModalSubFilter(subFilter) {
    activeModalSubFilter = subFilter;
    renderModalTabs();
    renderModalTable();
}

function renderModalTabs() {
    const cfg = MODAL_CONFIG[activeModalCategory];
    const container = document.getElementById('modalTabsContainer');
    container.innerHTML = cfg.tabs.map(t => {
        const isActive = (t.id === activeModalSubFilter);
        const cls = isActive
            ? 'px-3 py-1 rounded-md font-bold bg-cresta-navy dark:bg-cresta-red text-white'
            : 'px-3 py-1 rounded-md font-bold bg-slate-200 dark:bg-gray-800 text-slate-700 dark:text-gray-300 hover:opacity-80';
        return `<button type="button" onclick="setModalSubFilter('${t.id}')" class="${cls}">${t.label}</button>`;
    }).join('');
}

function getFilteredModalRows() {
    const baseList = cachedUniverses[activeModalCategory] || [];
    const q = document.getElementById('modalSearch').value.trim().toLowerCase();
    const cfg = MODAL_CONFIG[activeModalCategory];

    return baseList.filter(r => {
        if (cfg.mode === 'spend') {
            if (activeModalCategory === 'SPEND_FIRM') {
                if (activeModalSubFilter === 'TOP5_PROV' && !currentTop5ProvidersSet.has(r.proveedor)) return false;
                if (activeModalSubFilter === 'IN_SAP' && !r.en_sap) return false;
                if (activeModalSubFilter === 'NO_SAP' && r.en_sap) return false;
            }
            if (q) {
                const hay = `${r.oc_odoo} ${r.oc_sap} ${r.solicitud} ${r.sociedad} ${r.proveedor} ${r.comprador} ${r.estado_oc}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        }

        if (activeModalCategory === 'VALIDAS') {
            if (activeModalSubFilter === 'IN_SAP' && !r.tiene_oc_sap) return false;
            if (activeModalSubFilter === 'IN_ODOO' && !(r.tiene_oc_odoo && !r.tiene_oc_sap)) return false;
            if (activeModalSubFilter === 'NO_OC' && r.tiene_oc_odoo) return false;
        } else if (activeModalCategory === 'TIEMPO_COMP') {
            const d = r.d_comprador ?? 0;
            if (activeModalSubFilter === 'FAST' && d > 7) return false;
            if (activeModalSubFilter === 'MED' && (d <= 7 || d > 14)) return false;
            if (activeModalSubFilter === 'SLOW' && d <= 14) return false;
        } else if (activeModalCategory === 'SLA_COMP') {
            const ok = r.tiene_oc_odoo && r.d_comprador !== null && r.d_comprador <= 14;
            if (activeModalSubFilter === 'OK_14' && !ok) return false;
            if (activeModalSubFilter === 'LATE_14' && ok) return false;
        } else if (activeModalCategory === 'SAP_14') {
            const ok = r.tiene_oc_sap && r.d_to_sap !== null && r.d_to_sap <= 14;
            if (activeModalSubFilter === 'OK_14' && !ok) return false;
            if (activeModalSubFilter === 'LATE_14' && ok) return false;
        } else if (activeModalCategory === 'REZAGO') {
            if (activeModalSubFilter === 'GT30' && !(r.d_to_sap !== null && r.d_to_sap > 30)) return false;
            if (activeModalSubFilter === 'NO_OC' && r.tiene_oc_odoo) return false;
            if (activeModalSubFilter === 'PEND_SAP' && !r.tiene_oc_odoo) return false;
        }

        if (q) {
            const hay = `${r.id} ${r.sociedad} ${r.comprador} ${r.oc_odoo} ${r.estado_oc} ${r.estado_sol}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
}

function getRowStatusBadge(r) {
    if (r.tiene_oc_sap) {
        return `<span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800">En SAP</span>`;
    }
    if (r.tiene_oc_odoo) {
        return `<span class="px-2 py-0.5 rounded bg-slate-100 text-cresta-navy border border-slate-300 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800">${r.estado_oc || 'OC Odoo (Sin SAP)'}</span>`;
    }
    if (!r.aprobada) {
        return `<span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">${r.estado_sol || 'Sin aprobar'}</span>`;
    }
    return `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800">Sin OC en Odoo</span>`;
}

function renderModalTable() {
    const cfg = MODAL_CONFIG[activeModalCategory];
    const rows = getFilteredModalRows();
    const tbody = document.getElementById('modalTableBody');

    if (cfg.mode === 'spend') {
        const sumMonto = rows.reduce((s, r) => s + r.monto, 0);
        document.getElementById('modalSubtitle').textContent = `${rows.length.toLocaleString()} OCs · Total: ${formatMoney(sumMonto)}`;

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-slate-400 dark:text-gray-400">No hay órdenes de compra con este filtro.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.slice(0, 300).map(r => {
            const sapBadge = r.en_sap
                ? `<span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800">SAP ${r.oc_sap}</span>`
                : `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800">${r.estado_oc} (Sin SAP)</span>`;

            return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50">
                <td class="py-2 px-3 font-bold text-cresta-navy dark:text-white">${r.oc_odoo} <span class="text-[10px] text-slate-400 font-normal">(${r.solicitud})</span></td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${r.fecha}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${r.sociedad}</td>
                <td class="py-2 px-3 font-medium text-slate-800 dark:text-gray-200">${r.proveedor}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${shortName(r.comprador)}</td>
                <td class="py-2 px-3">${sapBadge}</td>
                <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${formatMoney(r.monto, r.divisa)}</td>
            </tr>`;
        }).join('');
        return;
    }

    document.getElementById('modalSubtitle').textContent = `${rows.length.toLocaleString()} solicitudes encontradas`;

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-slate-400 dark:text-gray-400">No hay solicitudes con este filtro.</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.slice(0, 300).map(r => {
        const rawDays = r[cfg.daysField];
        const diasTxt = rawDays !== null && rawDays !== undefined ? `${rawDays.toFixed(1)} d` : '—';

        let diasCls = 'text-slate-700 dark:text-gray-300 font-bold';
        if (rawDays !== null && rawDays !== undefined) {
            if (rawDays <= 14) diasCls = 'text-emerald-700 dark:text-emerald-400 font-bold';
            else if (rawDays <= 30) diasCls = 'text-amber-700 dark:text-amber-300 font-bold';
            else diasCls = 'text-cresta-red dark:text-red-400 font-bold';
        }

        return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50">
            <td class="py-2 px-3 font-bold text-cresta-navy dark:text-white">${r.id}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${r.fecha}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${r.sociedad}</td>
            <td class="py-2 px-3 text-slate-700 dark:text-gray-300">${r.comprador}</td>
            <td class="py-2 px-3 text-slate-500 dark:text-gray-400">${r.oc_odoo || '—'}</td>
            <td class="py-2 px-3">${getRowStatusBadge(r)}</td>
            <td class="py-2 px-3 text-right ${diasCls}">${diasTxt}</td>
        </tr>`;
    }).join('');
}

function exportAuditCSV() {
    const cfg = MODAL_CONFIG[activeModalCategory];
    const rows = getFilteredModalRows();

    if (cfg.mode === 'spend') {
        const headers = ['OC Odoo', 'OC SAP', 'Solicitud', 'Fecha OC', 'Sociedad', 'Proveedor', 'Comprador', 'Estado OC', 'Divisa', 'Total OC'];
        const csvLines = [headers.join(',')];

        rows.forEach(r => {
            const line = [
                `"${r.oc_odoo}"`,
                `"${r.oc_sap || ''}"`,
                `"${r.solicitud}"`,
                `"${r.fecha}"`,
                `"${r.sociedad}"`,
                `"${r.proveedor.replace(/"/g, '""')}"`,
                `"${r.comprador}"`,
                `"${r.estado_oc}"`,
                `"${r.divisa}"`,
                r.monto.toFixed(2)
            ];
            csvLines.push(line.join(','));
        });

        const blob = new Blob(["\uFEFF" + csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `auditoria_${activeModalCategory.toLowerCase()}_${activeCurrency}_${RAW_DATA.cutoff_date}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        return;
    }

    const headers = ['Solicitud', 'Fecha Creacion', 'Sociedad', 'Comprador', 'OC Odoo', 'Estatus', cfg.daysLabel];
    const csvLines = [headers.join(',')];

    rows.forEach(r => {
        const statusTxt = r.tiene_oc_sap
            ? 'En SAP'
            : (r.tiene_oc_odoo ? (r.estado_oc || 'OC Odoo sin SAP') : (!r.aprobada ? (r.estado_sol || 'Sin aprobar') : 'Sin OC en Odoo'));
        const rawDays = r[cfg.daysField];
        const daysVal = rawDays !== null && rawDays !== undefined ? rawDays.toFixed(1) : '';

        const line = [
            `"${r.id}"`,
            `"${r.fecha}"`,
            `"${r.sociedad}"`,
            `"${r.comprador}"`,
            `"${r.oc_odoo || ''}"`,
            `"${statusTxt}"`,
            daysVal
        ];
        csvLines.push(line.join(','));
    });

    const blob = new Blob(["\uFEFF" + csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `auditoria_${activeModalCategory.toLowerCase()}_cresta_${RAW_DATA.cutoff_date}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

initTheme();
initFilters();
renderDashboard();
</script>
</body>
</html>
