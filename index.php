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
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        body {
            font-family: Verdana, Geneva, sans-serif;
            background-color: #111827;
            color: #F3F4F6;
        }
        .card {
            background-color: #1F2937;
            border: 1px solid #374151;
            border-radius: 1rem;
        }
        .brand-accent {
            background-color: #5B45FF;
        }
        select, button {
            outline: none;
        }
    </style>
</head>
<body class="min-h-screen pb-12">

    <!-- Header Ejecutivo -->
    <header class="border-b border-gray-800 bg-gray-900/90 sticky top-0 z-30 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md text-white brand-accent tracking-wide">
                        MKS BOX · SUPPLY CHAIN
                    </span>
                    <span class="text-xs text-gray-400">
                        Corte de datos: <strong class="text-gray-200"><?= htmlspecialchars($payload['cutoff_date']) ?></strong>
                    </span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold text-white mt-1">
                    Evolución del Ciclo de Compras · Grupo Cresta
                </h1>
            </div>

            <!-- Barra de Filtros -->
            <div class="flex flex-wrap items-center gap-2.5 text-xs">
                <!-- Modo Periodo -->
                <div class="inline-flex rounded-lg bg-gray-800 p-1 border border-gray-700">
                    <button id="btnModeMonth" type="button" onclick="setPeriodMode('month')"
                        class="px-3 py-1.5 rounded-md font-bold text-white brand-accent transition">
                        Mes
                    </button>
                    <button id="btnModeYTD" type="button" onclick="setPeriodMode('ytd')"
                        class="px-3 py-1.5 rounded-md font-bold text-gray-400 hover:text-white transition">
                        YTD (Acumulado)
                    </button>
                </div>

                <!-- Selector de Mes -->
                <select id="filterMes" onchange="renderDashboard()"
                    class="bg-gray-800 border border-gray-700 text-gray-100 rounded-lg px-3 py-2 font-medium">
                </select>

                <!-- Selector de Sociedad -->
                <select id="filterSociedad" onchange="renderDashboard()"
                    class="bg-gray-800 border border-gray-700 text-gray-100 rounded-lg px-3 py-2 font-medium">
                    <option value="ALL">Todas las Sociedades</option>
                </select>

                <!-- Selector de Comprador -->
                <select id="filterComprador" onchange="renderDashboard()"
                    class="bg-gray-800 border border-gray-700 text-gray-100 rounded-lg px-3 py-2 font-medium">
                    <option value="ALL">Todos los Compradores</option>
                </select>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6">

        <!-- Tarjetas KPI Principales -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- KPI 1 -->
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400 font-bold uppercase">Solicitudes Válidas</span>
                    <span id="kpi1Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-gray-800 text-gray-300">--</span>
                </div>
                <div id="kpi1Value" class="text-3xl font-bold text-white mt-2">0</div>
                <p id="kpi1Sub" class="text-xs text-gray-400 mt-1.5">Excluye rechazadas y OC canceladas</p>
            </div>

            <!-- KPI 2 -->
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400 font-bold uppercase">SLA Comprador (≤14d Odoo)</span>
                    <span id="kpi2Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-gray-800 text-gray-300">--</span>
                </div>
                <div class="flex items-baseline gap-2 mt-2">
                    <span id="kpi2Value" class="text-3xl font-bold text-emerald-400">0%</span>
                    <span class="text-xs text-gray-400">Meta: 70%</span>
                </div>
                <p id="kpi2Sub" class="text-xs text-gray-400 mt-1.5">Aprob. Solicitud → Creación OC Odoo</p>
            </div>

            <!-- KPI 3 -->
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400 font-bold uppercase">En SAP ≤14 días</span>
                    <span id="kpi3Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-gray-800 text-gray-300">--</span>
                </div>
                <div id="kpi3Value" class="text-3xl font-bold text-indigo-400 mt-2">0%</div>
                <p id="kpi3Sub" class="text-xs text-gray-400 mt-1.5">Ciclo completo desde aprobación</p>
            </div>

            <!-- KPI 4 -->
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400 font-bold uppercase">Rezago Sin Llegar a SAP</span>
                    <span id="kpi4Badge" class="text-[11px] px-2 py-0.5 rounded-full bg-red-950 text-red-300 border border-red-800">0 > 30d</span>
                </div>
                <div id="kpi4Value" class="text-3xl font-bold text-amber-400 mt-2">0</div>
                <p id="kpi4Sub" class="text-xs text-gray-400 mt-1.5">Solicitudes aprobadas abiertas al corte</p>
            </div>
        </section>

        <!-- Fila 1 de Gráficas: Evolución Quincenal + Desglose Cuello de Botella -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="card p-5 lg:col-span-7">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-white">% Solicitudes en SAP dentro de plazo (por Quincena)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Comparación sin sesgo: excluye quincenas recientes sin madurez suficiente para ≤14d o ≤30d</p>
                </div>
                <div id="chartQuincenas" class="w-full h-72"></div>
            </div>

            <div class="card p-5 lg:col-span-5">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-white">Desglose del Ciclo Promedio a SAP (Días)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Separación de responsabilidad: Aprobadores vs. Comprador</p>
                </div>
                <div id="chartCiclo" class="w-full h-72"></div>
            </div>
        </section>

        <!-- Fila 2 de Gráficas: Desempeño por Comprador + Rezago por Mes de Creación -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="card p-5 lg:col-span-7">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-white">Desempeño por Comprador (% OC en Odoo ≤14 días)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Mide gestión pura del comprador (Aprobación Solicitud → Creación OC en Odoo) · Meta 70%</p>
                </div>
                <div id="chartCompradores" class="w-full h-80"></div>
            </div>

            <div class="card p-5 lg:col-span-5">
                <div class="mb-3">
                    <h2 class="text-sm font-bold text-white">Rezago Abierto Sin SAP por Mes de Origen</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Solicitudes aprobadas pendientes de sincronizar en SAP según mes de creación</p>
                </div>
                <div id="chartRezago" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Tabla Ejecutiva Mensual (Estilo Slide 4) -->
        <section class="card p-5 overflow-x-auto">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-white">Indicadores Mensuales Consolidados</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Histórico completo filtrado por Sociedad y Comprador seleccionados</p>
                </div>
                <span class="text-[11px] text-gray-400">* Celda resaltada indica mejor desempeño mensual</span>
            </div>
            <table class="w-full text-xs text-left border-collapse">
                <thead id="monthlyTableHead" class="bg-gray-800/80 text-gray-300 border-b border-gray-700"></thead>
                <tbody id="monthlyTableBody" class="divide-y divide-gray-800"></tbody>
            </table>
        </section>

    </main>

<script>
const RAW_DATA = <?= $jsonPayload ?>;
let periodMode = 'month'; // 'month' | 'ytd'
let charts = {};

const MONTH_NAMES = {
    '01': 'Ene', '02': 'Feb', '03': 'Mar', '04': 'Abr',
    '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Ago',
    '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dic'
};

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
    const btnM = document.getElementById('btnModeMonth');
    const btnY = document.getElementById('btnModeYTD');
    if (mode === 'month') {
        btnM.className = 'px-3 py-1.5 rounded-md font-bold text-white brand-accent transition';
        btnY.className = 'px-3 py-1.5 rounded-md font-bold text-gray-400 hover:text-white transition';
    } else {
        btnY.className = 'px-3 py-1.5 rounded-md font-bold text-white brand-accent transition';
        btnM.className = 'px-3 py-1.5 rounded-md font-bold text-gray-400 hover:text-white transition';
    }
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

    // SLA Comprador <= 14d (Odoo)
    const compEvaluables = aprobadas.filter(r => r.tiene_oc_odoo || r.edad_dias >= 14);
    const comp14 = compEvaluables.filter(r => r.tiene_oc_odoo && r.d_comprador !== null && r.d_comprador <= 14);
    const pctComp14 = compEvaluables.length ? (comp14.length / compEvaluables.length) * 100 : 0;

    // En SAP <= 14d
    const sapEvaluables = aprobadas.filter(r => r.tiene_oc_sap || r.edad_dias >= 14);
    const sap14 = sapEvaluables.filter(r => r.tiene_oc_sap && r.d_to_sap !== null && r.d_to_sap <= 14);
    const pctSap14 = sapEvaluables.length ? (sap14.length / sapEvaluables.length) * 100 : 0;

    // Rezago abierto sin SAP
    const rezago = aprobadas.filter(r => !r.tiene_oc_sap);
    const rezago30 = rezago.filter(r => r.d_to_sap !== null && r.d_to_sap > 30);

    return {
        totalValidas: validas.length,
        pctComp14: Math.round(pctComp14),
        pctSap14: Math.round(pctSap14),
        rezagoTotal: rezago.length,
        rezago30: rezago30.length
    };
}

function renderDashboard() {
    const selectedYm = document.getElementById('filterMes').value;
    const entityRecords = filterByEntity(RAW_DATA.records);
    const currentRecords = filterByPeriod(entityRecords, selectedYm, periodMode);
    const currentStats = calcKpiStats(currentRecords);

    // Mes anterior para delta en modo Mes
    const allMeses = RAW_DATA.filters.meses;
    const idxYm = allMeses.indexOf(selectedYm);
    const prevYm = idxYm > 0 ? allMeses[idxYm - 1] : null;
    const prevStats = prevYm ? calcKpiStats(filterByPeriod(entityRecords, prevYm, 'month')) : null;

    // Actualizar Tarjetas KPI
    document.getElementById('kpi1Value').textContent = currentStats.totalValidas.toLocaleString();
    document.getElementById('kpi1Badge').textContent = periodMode === 'ytd' ? `YTD ${selectedYm.split('-')[0]}` : formatYm(selectedYm);

    document.getElementById('kpi2Value').textContent = `${currentStats.pctComp14}%`;
    if (periodMode === 'month' && prevStats) {
        const diff2 = currentStats.pctComp14 - prevStats.pctComp14;
        const b2 = document.getElementById('kpi2Badge');
        b2.textContent = `${diff2 >= 0 ? '+' : ''}${diff2} pts vs ${formatYm(prevYm)}`;
        b2.className = `text-[11px] px-2 py-0.5 rounded-full font-bold ${diff2 >= 0 ? 'bg-emerald-950 text-emerald-400' : 'bg-red-950 text-red-400'}`;
    } else {
        document.getElementById('kpi2Badge').textContent = 'Acumulado YTD';
    }

    document.getElementById('kpi3Value').textContent = `${currentStats.pctSap14}%`;
    if (periodMode === 'month' && prevStats) {
        const diff3 = currentStats.pctSap14 - prevStats.pctSap14;
        const b3 = document.getElementById('kpi3Badge');
        b3.textContent = `${diff3 >= 0 ? '+' : ''}${diff3} pts vs ${formatYm(prevYm)}`;
        b3.className = `text-[11px] px-2 py-0.5 rounded-full font-bold ${diff3 >= 0 ? 'bg-emerald-950 text-emerald-400' : 'bg-red-950 text-red-400'}`;
    } else {
        document.getElementById('kpi3Badge').textContent = 'Acumulado YTD';
    }

    // Rezago acumulado total hasta el corte seleccionado
    const upToSelected = entityRecords.filter(r => r.ym <= selectedYm && !r.excluida && r.aprobada && !r.tiene_oc_sap);
    const upToSelected30 = upToSelected.filter(r => r.d_to_sap !== null && r.d_to_sap > 30);
    document.getElementById('kpi4Value').textContent = upToSelected.length.toLocaleString();
    document.getElementById('kpi4Badge').textContent = `${upToSelected30.length} > 30 días`;

    renderChartQuincenas(entityRecords, selectedYm);
    renderChartCiclo(entityRecords, selectedYm);
    renderChartCompradores(currentRecords);
    renderChartRezago(upToSelected);
    renderMonthlyTable(entityRecords);
}

function renderChartQuincenas(entityRecords, selectedYm) {
    // Tomar últimas 8 quincenas hasta el mes seleccionado
    const validas = entityRecords.filter(r => !r.excluida && r.aprobada && r.ym <= selectedYm);
    const quincenasSet = [...new Set(validas.map(r => r.quincena))].sort();
    const recentQ = quincenasSet.slice(-8);

    const s7 = [], s14 = [], s30 = [], labels = [];

    recentQ.forEach(q => {
        const rows = validas.filter(r => r.quincena === q);
        const [ym, half] = q.split('-Q');
        labels.push(`${half === '1' ? '1ª Q' : '2ª Q'} ${formatYm(ym)}`);

        const eval7 = rows.filter(r => r.tiene_oc_sap || r.edad_dias >= 7);
        const ok7 = eval7.filter(r => r.tiene_oc_sap && r.d_to_sap <= 7);
        s7.push(eval7.length ? Math.round((ok7.length / eval7.length) * 100) : null);

        const eval14 = rows.filter(r => r.tiene_oc_sap || r.edad_dias >= 14);
        const ok14 = eval14.filter(r => r.tiene_oc_sap && r.d_to_sap <= 14);
        s14.push(eval14.length ? Math.round((ok14.length / eval14.length) * 100) : null);

        const eval30 = rows.filter(r => r.tiene_oc_sap || r.edad_dias >= 30);
        const ok30 = eval30.filter(r => r.tiene_oc_sap && r.d_to_sap <= 30);
        s30.push(eval30.length >= 5 ? Math.round((ok30.length / eval30.length) * 100) : null);
    });

    const options = {
        chart: { type: 'line', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: 'dark' },
        stroke: { width: [2.5, 3.5, 2.5], curve: 'smooth' },
        colors: ['#38BDF8', '#5B45FF', '#10B981'],
        series: [
            { name: '≤ 7 días', data: s7 },
            { name: '≤ 14 días (KPI)', data: s14 },
            { name: '≤ 30 días', data: s30 }
        ],
        xaxis: { categories: labels, labels: { style: { fontSize: '10px', colors: '#9CA3AF' } } },
        yaxis: { min: 0, max: 100, labels: { formatter: v => v + '%', style: { colors: '#9CA3AF' } } },
        markers: { size: 4 },
        dataLabels: { enabled: true, formatter: v => v !== null ? v + '%' : '', style: { fontSize: '9px' } },
        grid: { borderColor: '#374151', strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '11px' }
    };

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

    const options = {
        chart: { type: 'bar', stacked: true, height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: 'dark' },
        colors: ['#6B7280', '#5B45FF', '#F59E0B'],
        series: [
            { name: '1. Aprob. Solicitud', data: dAprobSol },
            { name: '2. Gestión Comprador (Odoo)', data: dComprador },
            { name: '3. Aprob. OC y Envío SAP', data: dAprobSap }
        ],
        xaxis: { categories: labels, labels: { style: { fontSize: '10px', colors: '#9CA3AF' } } },
        yaxis: { title: { text: 'Días promedio', style: { fontSize: '10px', color: '#9CA3AF' } } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        dataLabels: { enabled: true, formatter: v => v > 0.5 ? v : '', style: { fontSize: '9px' } },
        grid: { borderColor: '#374151', strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '10px' }
    };

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
            const evalRows = rows.filter(r => r.tiene_oc_odoo || r.edad_dias >= 14);
            const okRows = evalRows.filter(r => r.tiene_oc_odoo && r.d_comprador <= 14);
            const pct = evalRows.length ? Math.round((okRows.length / evalRows.length) * 100) : 0;
            return { name, total: rows.length, pct };
        })
        .filter(item => item.total >= 3)
        .sort((a, b) => b.pct - a.pct);

    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: 'dark' },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 5,
                barHeight: '60%',
                distributed: true
            }
        },
        colors: summary.map(s => s.pct >= 70 ? '#10B981' : (s.pct >= 55 ? '#5B45FF' : '#EF4444')),
        series: [{ name: '% SLA ≤14 días', data: summary.map(s => s.pct) }],
        xaxis: {
            categories: summary.map(s => `${s.name.split(' ').slice(0, 2).join(' ')} (${s.total})`),
            min: 0,
            max: 100,
            labels: { formatter: v => v + '%' }
        },
        annotations: {
            xaxis: [{
                x: 70,
                borderColor: '#F59E0B',
                label: { text: 'Meta 70%', style: { color: '#111827', background: '#F59E0B', fontSize: '10px' } }
            }]
        },
        dataLabels: { enabled: true, formatter: v => v + '%', style: { fontSize: '10px' } },
        legend: { show: false },
        grid: { borderColor: '#374151', strokeDashArray: 4 }
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
    const options = {
        chart: { type: 'bar', stacked: true, height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: 'dark' },
        colors: ['#F59E0B', '#EF4444'],
        series: [
            { name: '≤ 30 días abiertas', data: meses.map(m => byMonth[m].le30) },
            { name: '> 30 días (Crítico)', data: meses.map(m => byMonth[m].gt30) }
        ],
        xaxis: { categories: meses.map(formatYm), labels: { style: { fontSize: '10px', colors: '#9CA3AF' } } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        dataLabels: { enabled: true, formatter: v => v > 0 ? v : '', style: { fontSize: '9px' } },
        grid: { borderColor: '#374151', strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '10px' }
    };

    if (charts.rezago) charts.rezago.destroy();
    charts.rezago = new ApexCharts(document.querySelector('#chartRezago'), options);
    charts.rezago.render();
}

function renderMonthlyTable(entityRecords) {
    const meses = RAW_DATA.filters.meses;
    const thead = document.getElementById('monthlyTableHead');
    const tbody = document.getElementById('monthlyTableBody');

    thead.innerHTML = `<tr>
        <th class="py-3 px-4 font-bold">Indicador</th>
        ${meses.map(m => `<th class="py-3 px-3 text-right font-bold">${formatYm(m)}</th>`).join('')}
    </tr>`;

    const metricsByMonth = meses.map(m => {
        const allM = entityRecords.filter(r => r.ym === m);
        const validas = allM.filter(r => !r.excluida);
        const aprobadas = validas.filter(r => r.aprobada);
        const rechazadas = allM.filter(r => r.is_rechazada);

        const e7 = aprobadas.filter(r => r.tiene_oc_sap || r.edad_dias >= 7);
        const p7 = e7.length ? Math.round((e7.filter(r => r.tiene_oc_sap && r.d_to_sap <= 7).length / e7.length) * 100) : 0;

        const e14 = aprobadas.filter(r => r.tiene_oc_sap || r.edad_dias >= 14);
        const p14 = e14.length ? Math.round((e14.filter(r => r.tiene_oc_sap && r.d_to_sap <= 14).length / e14.length) * 100) : 0;

        const e30 = aprobadas.filter(r => r.tiene_oc_sap || r.edad_dias >= 30);
        const p30 = e30.length ? Math.round((e30.filter(r => r.tiene_oc_sap && r.d_to_sap <= 30).length / e30.length) * 100) : 0;

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

    const maxP7 = Math.max(...metricsByMonth.map(x => x.p7));
    const maxP14 = Math.max(...metricsByMonth.map(x => x.p14));
    const maxP30 = Math.max(...metricsByMonth.map(x => x.p30));

    const rowsHtml = [
        {
            label: 'Solicitudes válidas creadas',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right font-bold text-white">${x.creadas}</td>`)
        },
        {
            label: 'Enviadas a SAP en ≤ 7 días',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right ${x.p7 === maxP7 ? 'text-emerald-400 font-bold' : 'text-gray-300'}">${x.p7}%</td>`)
        },
        {
            label: 'Enviadas a SAP en ≤ 14 días',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right ${x.p14 === maxP14 ? 'text-emerald-400 font-bold' : 'text-gray-300'}">${x.p14}%</td>`)
        },
        {
            label: 'Enviadas a SAP en ≤ 30 días',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right ${x.p30 === maxP30 ? 'text-emerald-400 font-bold' : 'text-gray-300'}">${x.p30}%</td>`)
        },
        {
            label: 'Mediana días aprobar solicitud',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right text-gray-300">${x.medAprob}</td>`)
        },
        {
            label: '% Solicitudes rechazadas',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right text-gray-400">${x.pctRech}%</td>`)
        },
        {
            label: '% Órdenes de compra canceladas',
            vals: metricsByMonth.map(x => `<td class="py-2.5 px-3 text-right text-gray-400">${x.pctCanc}%</td>`)
        }
    ];

    tbody.innerHTML = rowsHtml.map(r => `<tr class="hover:bg-gray-800/40"><td class="py-2.5 px-4 text-gray-200 font-medium">${r.label}</td>${r.vals.join('')}</tr>`).join('');
}

initFilters();
renderDashboard();
</script>
</body>
</html>
