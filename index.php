<?php
declare(strict_types=1);

require_once __DIR__ . '/src/DataProcessor.php';

$excelPath = __DIR__ . '/data/DB.xlsx';
$cachePath = __DIR__ . '/data/cache.json';

try {
    $processor = new DataProcessor($excelPath, $cachePath);
    $payload = $processor->getProcessedData();
} catch (Throwable $e) {
    http_response_code(500);
    die("Error cargando datos del dashboard: " . htmlspecialchars($e->getMessage()));
}

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evolución Ciclo de Compras · Grupo Cresta</title>
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md text-white bg-cresta-navy dark:bg-cresta-red tracking-wide">
                        GRUPO CRESTA · SUPPLY CHAIN
                    </span>
                    <span class="text-xs text-slate-500 dark:text-gray-400">
                        Corte de datos: <strong class="text-slate-800 dark:text-gray-200"><?= htmlspecialchars($payload['cutoff_date']) ?></strong>
                    </span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold text-cresta-navy dark:text-white mt-1">
                    Evolución del Ciclo de Compras · Grupo Cresta
                </h1>
            </div>

            <!-- Barra de Filtros + Toggle Modo Oscuro -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <!-- Modo Periodo -->
                <div class="inline-flex rounded-lg bg-slate-100 dark:bg-gray-800 p-1 border border-slate-200 dark:border-gray-700">
                    <button id="btnModeMonth" type="button" onclick="setPeriodMode('month')"
                        class="px-3 py-1.5 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                        Mes
                    </button>
                    <button id="btnModeYTD" type="button" onclick="setPeriodMode('ytd')"
                        class="px-3 py-1.5 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        YTD (Acumulado)
                    </button>
                </div>

                <!-- Selector de Mes -->
                <select id="filterMes" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 font-medium shadow-xs">
                </select>

                <!-- Selector de Sociedad -->
                <select id="filterSociedad" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 font-medium shadow-xs">
                    <option value="ALL">Todas las Sociedades</option>
                </select>

                <!-- Selector de Comprador -->
                <select id="filterComprador" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 font-medium shadow-xs">
                    <option value="ALL">Todos los Compradores</option>
                </select>

                <!-- Toggle Modo Claro / Oscuro -->
                <button id="btnThemeToggle" type="button" onclick="toggleTheme()" title="Alternar modo claro / oscuro"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 font-bold transition">
                    <span id="themeIcon">🌙</span>
                    <span id="themeLabel" class="hidden sm:inline">Modo Oscuro</span>
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 flex-1 w-full">

        <!-- Tarjetas KPI Principales -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- KPI 1 -->
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-gray-400 font-bold uppercase">Solicitudes Válidas</span>
                    <span id="kpi1Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold">--</span>
                </div>
                <div id="kpi1Value" class="text-3xl font-bold text-cresta-navy dark:text-white mt-2">0</div>
                <p id="kpi1Sub" class="text-xs text-slate-500 dark:text-gray-400 mt-1.5">Excluye rechazadas y OC canceladas</p>
            </div>

            <!-- KPI 2 -->
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-gray-400 font-bold uppercase">SLA Comprador (≤14d Odoo)</span>
                    <span id="kpi2Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300">--</span>
                </div>
                <div class="flex items-baseline gap-2 mt-2">
                    <span id="kpi2Value" class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">0%</span>
                    <span class="text-xs text-slate-500 dark:text-gray-400">Meta: 70%</span>
                </div>
                <p id="kpi2Sub" class="text-xs text-slate-500 dark:text-gray-400 mt-1.5">Solo solicitudes con ≥14d de madurez</p>
            </div>

            <!-- KPI 3 -->
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-gray-400 font-bold uppercase">En SAP ≤14 días</span>
                    <span id="kpi3Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300">--</span>
                </div>
                <div id="kpi3Value" class="text-3xl font-bold text-cresta-blue dark:text-indigo-400 mt-2">0%</div>
                <p id="kpi3Sub" class="text-xs text-slate-500 dark:text-gray-400 mt-1.5">Solo solicitudes con ≥14d de madurez</p>
            </div>

            <!-- KPI 4 -->
            <div onclick="openRezagoModal('ALL')" title="Haz clic para auditar el detalle de solicitudes pendientes"
                class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm cursor-pointer hover:border-cresta-red dark:hover:border-amber-500/70 transition group relative">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-red dark:group-hover:text-amber-300 transition">Rezago Sin Llegar a SAP</span>
                    <span id="kpi4Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-950 text-cresta-red dark:text-red-300 border border-red-200 dark:border-red-800 font-bold">0 > 30d</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span id="kpi4Value" class="text-3xl font-bold text-cresta-red dark:text-amber-400">0</span>
                    <span class="text-[11px] text-cresta-navy dark:text-indigo-400 group-hover:underline font-bold">Auditar lista →</span>
                </div>
                <p id="kpi4Sub" class="text-xs text-slate-500 dark:text-gray-400 mt-1.5">Solicitudes aprobadas abiertas al corte</p>
            </div>
        </section>

        <!-- Fila 1 de Gráficas -->
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

        <!-- Fila 2 de Gráficas -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-7">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Desempeño por Comprador (% OC en Odoo ≤14 días)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Gestión pura del comprador (Aprobación Solicitud → Creación OC Odoo) · Meta 70%</p>
                </div>
                <div id="chartCompradores" class="w-full h-80"></div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-5">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Rezago Abierto Sin SAP por Mes de Origen</h2>
                        <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Solicitudes aprobadas pendientes de sincronizar en SAP</p>
                    </div>
                    <button type="button" onclick="openRezagoModal('GT30')"
                        class="text-[11px] px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/80 dark:hover:bg-red-900 text-cresta-red dark:text-red-200 border border-red-200 dark:border-red-800 font-bold shrink-0">
                        Ver > 30d
                    </button>
                </div>
                <div id="chartRezago" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Tabla Ejecutiva Mensual -->
        <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Indicadores Mensuales Consolidados</h2>
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

    <!-- Footer con créditos -->
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4 w-full text-center">
        <p class="text-[11px] text-slate-400 dark:text-gray-500">
            Dashboard construido por <span class="font-semibold text-slate-500 dark:text-gray-400">Elder Velásquez</span> con ayuda de
            <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-cresta-navy dark:hover:text-gray-300">mksbox.com</a>
        </p>
    </footer>

    <!-- Modal de Auditoría de Solicitudes Rezagadas -->
    <div id="rezagoModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 dark:bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-2xl max-w-5xl w-full max-h-[88vh] flex flex-col shadow-2xl">
            <div class="p-5 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">AUDITORÍA DE REZAGO</span>
                        <span id="modalSubtitle" class="text-xs text-slate-500 dark:text-gray-400"></span>
                    </div>
                    <h3 class="text-base font-bold text-cresta-navy dark:text-white mt-1">Solicitudes Aprobadas Pendientes de Llegar a SAP</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="exportRezagoCSV()"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                        Descargar CSV
                    </button>
                    <button type="button" onclick="closeRezagoModal()"
                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-300 text-xs font-bold">
                        Cerrar ✕
                    </button>
                </div>
            </div>

            <!-- Controles internos del modal -->
            <div class="px-5 py-3 bg-slate-50 dark:bg-gray-800/40 border-b border-slate-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" id="tabRezAll" onclick="setModalFilter('ALL')" class="px-3 py-1 rounded-md font-bold bg-cresta-navy text-white">Todas</button>
                    <button type="button" id="tabRezGt30" onclick="setModalFilter('GT30')" class="px-3 py-1 rounded-md font-bold bg-slate-200 dark:bg-gray-800 text-slate-700 dark:text-gray-300">Críticas (> 30 días)</button>
                    <button type="button" id="tabRezNoOc" onclick="setModalFilter('NO_OC')" class="px-3 py-1 rounded-md font-bold bg-slate-200 dark:bg-gray-800 text-slate-700 dark:text-gray-300">Sin OC en Odoo (Comprador)</button>
                    <button type="button" id="tabRezPendSap" onclick="setModalFilter('PEND_SAP')" class="px-3 py-1 rounded-md font-bold bg-slate-200 dark:bg-gray-800 text-slate-700 dark:text-gray-300">Con OC Odoo (Falta Aprob/SAP)</button>
                </div>
                <input id="modalSearch" type="text" oninput="renderModalTable()" placeholder="Buscar solicitud, comprador, OC..."
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-slate-800 dark:text-gray-100 w-60">
            </div>

            <!-- Tabla del modal -->
            <div class="overflow-auto flex-1 p-5">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0">
                        <tr>
                            <th class="py-2.5 px-3">Solicitud</th>
                            <th class="py-2.5 px-3">Fecha Creación</th>
                            <th class="py-2.5 px-3">Sociedad</th>
                            <th class="py-2.5 px-3">Comprador</th>
                            <th class="py-2.5 px-3">OC Odoo</th>
                            <th class="py-2.5 px-3">Cuello Actual</th>
                            <th class="py-2.5 px-3 text-right">Días desde Aprob.</th>
                        </tr>
                    </thead>
                    <tbody id="modalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                </table>
            </div>
        </div>
    </div>

<script>
const RAW_DATA = <?= $jsonPayload ?>;
let periodMode = 'month';
let charts = {};
let currentRezagoList = [];
let modalFilterType = 'ALL';
let isDark = false;

const MONTH_NAMES = {
    '01': 'Ene', '02': 'Feb', '03': 'Mar', '04': 'Abr',
    '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Ago',
    '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dic'
};

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
    updatePeriodButtons();
}

function updatePeriodButtons() {
    const btnM = document.getElementById('btnModeMonth');
    const btnY = document.getElementById('btnModeYTD');
    const activeCls = 'px-3 py-1.5 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-3 py-1.5 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    if (periodMode === 'month') {
        btnM.className = activeCls;
        btnY.className = inactiveCls;
    } else {
        btnY.className = activeCls;
        btnM.className = inactiveCls;
    }
}

function formatYm(ym) {
    const [y, m] = ym.split('-');
    return `${MONTH_NAMES[m] || m} ${y}`;
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

function initFilters() {
    const selMes = document.getElementById('filterMes');
    const selSoc = document.getElementById('filterSociedad');
    const selComp = document.getElementById('filterComprador');

    RAW_DATA.filters.meses.forEach(ym => {
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = formatYm(ym);
        selMes.appendChild(opt);
    });
    if (RAW_DATA.filters.meses.length > 0) {
        selMes.value = RAW_DATA.filters.meses[RAW_DATA.filters.meses.length - 1];
    }

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

function filterByPeriod(records, targetYm, mode) {
    const targetYear = targetYm.split('-')[0];
    return records.filter(r => {
        if (mode === 'month') return r.ym === targetYm;
        return r.ym.startsWith(targetYear) && r.ym <= targetYm;
    });
}

function calcKpiStats(records) {
    const validas = records.filter(r => !r.excluida);
    const aprobadas = validas.filter(r => r.aprobada);

    const compEvaluables = aprobadas.filter(r => r.edad_dias >= 14);
    const comp14 = compEvaluables.filter(r => r.tiene_oc_odoo && r.d_comprador !== null && r.d_comprador <= 14);
    const pctComp14 = compEvaluables.length ? Math.round((comp14.length / compEvaluables.length) * 100) : null;

    const sapEvaluables = aprobadas.filter(r => r.edad_dias >= 14);
    const sap14 = sapEvaluables.filter(r => r.tiene_oc_sap && r.d_to_sap !== null && r.d_to_sap <= 14);
    const pctSap14 = sapEvaluables.length ? Math.round((sap14.length / sapEvaluables.length) * 100) : null;

    return {
        totalValidas: validas.length,
        pctComp14,
        pctSap14
    };
}

function renderDashboard() {
    const selectedYm = document.getElementById('filterMes').value;
    const entityRecords = filterByEntity(RAW_DATA.records);
    const currentRecords = filterByPeriod(entityRecords, selectedYm, periodMode);
    const currentStats = calcKpiStats(currentRecords);

    const allMeses = RAW_DATA.filters.meses;
    const idxYm = allMeses.indexOf(selectedYm);
    const prevYm = idxYm > 0 ? allMeses[idxYm - 1] : null;
    const prevStats = prevYm ? calcKpiStats(filterByPeriod(entityRecords, prevYm, 'month')) : null;

    document.getElementById('kpi1Value').textContent = currentStats.totalValidas.toLocaleString();
    document.getElementById('kpi1Badge').textContent = periodMode === 'ytd' ? `YTD ${selectedYm.split('-')[0]}` : formatYm(selectedYm);

    document.getElementById('kpi2Value').textContent = currentStats.pctComp14 !== null ? `${currentStats.pctComp14}%` : 'N/D';
    if (periodMode === 'month' && prevStats && currentStats.pctComp14 !== null && prevStats.pctComp14 !== null) {
        const diff2 = currentStats.pctComp14 - prevStats.pctComp14;
        const b2 = document.getElementById('kpi2Badge');
        b2.textContent = `${diff2 >= 0 ? '+' : ''}${diff2} pts vs ${formatYm(prevYm)}`;
        b2.className = `text-[11px] px-2 py-0.5 rounded-full font-bold ${diff2 >= 0
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-800'
            : 'bg-red-50 text-cresta-red border border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800'}`;
    } else {
        document.getElementById('kpi2Badge').textContent = periodMode === 'ytd' ? 'Acumulado YTD' : 'En maduración';
    }

    document.getElementById('kpi3Value').textContent = currentStats.pctSap14 !== null ? `${currentStats.pctSap14}%` : 'N/D';
    if (periodMode === 'month' && prevStats && currentStats.pctSap14 !== null && prevStats.pctSap14 !== null) {
        const diff3 = currentStats.pctSap14 - prevStats.pctSap14;
        const b3 = document.getElementById('kpi3Badge');
        b3.textContent = `${diff3 >= 0 ? '+' : ''}${diff3} pts vs ${formatYm(prevYm)}`;
        b3.className = `text-[11px] px-2 py-0.5 rounded-full font-bold ${diff3 >= 0
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-400 dark:border-emerald-800'
            : 'bg-red-50 text-cresta-red border border-red-200 dark:bg-red-950 dark:text-red-400 dark:border-red-800'}`;
    } else {
        document.getElementById('kpi3Badge').textContent = periodMode === 'ytd' ? 'Acumulado YTD' : 'En maduración';
    }

    currentRezagoList = entityRecords
        .filter(r => r.ym <= selectedYm && !r.excluida && r.aprobada && !r.tiene_oc_sap)
        .sort((a, b) => (b.d_to_sap || 0) - (a.d_to_sap || 0));

    const upToSelected30 = currentRezagoList.filter(r => r.d_to_sap !== null && r.d_to_sap > 30);
    document.getElementById('kpi4Value').textContent = currentRezagoList.length.toLocaleString();
    document.getElementById('kpi4Badge').textContent = `${upToSelected30.length} > 30 días`;

    renderChartQuincenas(entityRecords, selectedYm);
    renderChartCiclo(entityRecords, selectedYm);
    renderChartCompradores(currentRecords);
    renderChartRezago(currentRezagoList);
    renderMonthlyTable(entityRecords);
}

function getChartThemeConfig() {
    return {
        mode: isDark ? 'dark' : 'light',
        labelColor: isDark ? '#9CA3AF' : '#475569',
        gridColor: isDark ? '#374151' : '#E2E8F0'
    };
}

function renderChartQuincenas(entityRecords, selectedYm) {
    const validas = entityRecords.filter(r => !r.excluida && r.aprobada && r.ym <= selectedYm);
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

function renderChartCiclo(entityRecords, selectedYm) {
    const meses = RAW_DATA.filters.meses.filter(m => m <= selectedYm).slice(-6);
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
    const mapComp = {};

    validas.forEach(r => {
        if (!mapComp[r.comprador]) mapComp[r.comprador] = [];
        mapComp[r.comprador].push(r);
    });

    const summary = Object.entries(mapComp)
        .map(([name, rows]) => {
            const evalRows = rows.filter(r => r.edad_dias >= 14);
            const okRows = evalRows.filter(r => r.tiene_oc_odoo && r.d_comprador <= 14);
            const pct = evalRows.length ? Math.round((okRows.length / evalRows.length) * 100) : 0;
            return { name, total: rows.length, pct };
        })
        .filter(item => item.total >= 3)
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
            categories: summary.map(s => `${s.name.split(' ').slice(0, 2).join(' ')} (${s.total})`),
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

function renderMonthlyTable(entityRecords) {
    const meses = RAW_DATA.filters.meses;
    const thead = document.getElementById('monthlyTableHead');
    const tbody = document.getElementById('monthlyTableBody');

    thead.innerHTML = `<tr>
        <th class="py-3 px-4 font-bold rounded-tl-lg">Indicador</th>
        ${meses.map((m, idx) => `<th class="py-3 px-3 text-right font-bold ${idx === meses.length - 1 ? 'rounded-tr-lg' : ''}">${formatYm(m)}</th>`).join('')}
    </tr>`;

    const metricsByMonth = meses.map(m => {
        const allM = entityRecords.filter(r => r.ym === m);
        const validas = allM.filter(r => !r.excluida);
        const aprobadas = validas.filter(r => r.aprobada);
        const rechazadas = allM.filter(r => r.is_rechazada);

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
            p7, p14, p30,
            medAprob: medAprob.toFixed(2),
            pctRech, pctCanc
        };
    });

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

// Funciones del Modal de Rezago
function openRezagoModal(filterType) {
    document.getElementById('rezagoModal').classList.remove('hidden');
    setModalFilter(filterType || 'ALL');
}

function closeRezagoModal() {
    document.getElementById('rezagoModal').classList.add('hidden');
}

function setModalFilter(type) {
    modalFilterType = type;
    const tabs = {
        'ALL': 'tabRezAll',
        'GT30': 'tabRezGt30',
        'NO_OC': 'tabRezNoOc',
        'PEND_SAP': 'tabRezPendSap'
    };
    Object.entries(tabs).forEach(([k, id]) => {
        const el = document.getElementById(id);
        el.className = k === type
            ? 'px-3 py-1 rounded-md font-bold bg-cresta-navy dark:bg-cresta-red text-white'
            : 'px-3 py-1 rounded-md font-bold bg-slate-200 dark:bg-gray-800 text-slate-700 dark:text-gray-300 hover:opacity-80';
    });
    renderModalTable();
}

function getFilteredModalRows() {
    const q = document.getElementById('modalSearch').value.trim().toLowerCase();
    return currentRezagoList.filter(r => {
        if (modalFilterType === 'GT30' && !(r.d_to_sap !== null && r.d_to_sap > 30)) return false;
        if (modalFilterType === 'NO_OC' && r.tiene_oc_odoo) return false;
        if (modalFilterType === 'PEND_SAP' && !r.tiene_oc_odoo) return false;
        if (q) {
            const hay = `${r.id} ${r.sociedad} ${r.comprador} ${r.oc_odoo} ${r.estado_oc}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
}

function renderModalTable() {
    const rows = getFilteredModalRows();
    document.getElementById('modalSubtitle').textContent = `${rows.length} solicitudes encontradas`;
    const tbody = document.getElementById('modalTableBody');

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-slate-400 dark:text-gray-400">No hay solicitudes con este filtro.</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.slice(0, 250).map(r => {
        const dias = r.d_to_sap !== null ? Math.round(r.d_to_sap) : 0;
        const cuello = !r.tiene_oc_odoo
            ? `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800">Sin OC en Odoo</span>`
            : `<span class="px-2 py-0.5 rounded bg-slate-100 text-cresta-navy border border-slate-300 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800">${r.estado_oc || 'Pendiente SAP'}</span>`;
        const diasCls = dias > 30 ? 'text-cresta-red dark:text-red-400 font-bold' : 'text-amber-700 dark:text-amber-300 font-bold';

        return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50">
            <td class="py-2 px-3 font-bold text-cresta-navy dark:text-white">${r.id}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${r.fecha}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${r.sociedad}</td>
            <td class="py-2 px-3 text-slate-700 dark:text-gray-300">${r.comprador}</td>
            <td class="py-2 px-3 text-slate-500 dark:text-gray-400">${r.oc_odoo || '—'}</td>
            <td class="py-2 px-3">${cuello}</td>
            <td class="py-2 px-3 text-right ${diasCls}">${dias} d</td>
        </tr>`;
    }).join('');
}

function exportRezagoCSV() {
    const rows = getFilteredModalRows();
    const headers = ['Solicitud', 'Fecha Creacion', 'Sociedad', 'Comprador', 'OC Odoo', 'Estado OC', 'Dias desde Aprobacion'];
    const csvLines = [headers.join(',')];

    rows.forEach(r => {
        const line = [
            `"${r.id}"`,
            `"${r.fecha}"`,
            `"${r.sociedad}"`,
            `"${r.comprador}"`,
            `"${r.oc_odoo || ''}"`,
            `"${r.tiene_oc_odoo ? (r.estado_oc || 'Pendiente SAP') : 'Sin OC en Odoo'}"`,
            Math.round(r.d_to_sap || 0)
        ];
        csvLines.push(line.join(','));
    });

    const blob = new Blob(["\uFEFF" + csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rezago_compras_cresta_${RAW_DATA.cutoff_date}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

initTheme();
initFilters();
renderDashboard();
</script>
</body>
</html>
