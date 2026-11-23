/* ==========================================================================
   MÓDULO 1: CICLO OPERATIVO Y SLA DE COMPRAS (assets/js/module_cycle.js)
   ========================================================================== */

let chartCycleTrendInstance = null;
let chartSapBacklogInstance = null;
let chartBuyerSlaInstance   = null;

// ==========================================================================
// SECCIÓN 1: REGLA ÚNICA DE EXCLUSIÓN Y ELEGIBILIDAD DE REZAGO SIN SAP
// ==========================================================================
function isRecordExcludedFromCycle(r) {
    if (!r) return true;
    if (r.excluida || r.is_rechazada || r.is_borrador) return true;

    const st = String(r.estado_sol || '').trim().toLowerCase();
    if (st === 'rechazado' || st === 'rechazada' || st === 'rejected' || st === 'borrador' || st === 'draft') {
        return true;
    }

    const hasOcOdoo = Boolean(r.tiene_oc_odoo) || String(r.oc_odoo || '').trim() !== '';
    const hasOcSap  = Boolean(r.tiene_oc_sap) || Boolean(r.f_creacion_sap);

    // Excluir solicitudes "hecho" sin OC en Odoo ni en SAP (dualidad ERP histórica)
    if ((st === 'hecho' || st === 'done') && !hasOcOdoo && !hasOcSap) {
        return true;
    }

    return false;
}

function isRecordPendingSap(r) {
    if (typeof isValidForSapBacklog === 'function') {
        return isValidForSapBacklog(r);
    }
    if (isRecordExcludedFromCycle(r)) return false;
    const hasOcSap = Boolean(r.tiene_oc_sap) || Boolean(r.f_creacion_sap);
    return Boolean(r.aprobada) && !hasOcSap;
}

// ==========================================================================
// SECCIÓN 2: RENDERIZADO PRINCIPAL DEL MÓDULO DE CICLO OPERATIVO (SLA)
// ==========================================================================
function renderCycleModule(records) {
    const validRecords      = records.filter(r => !isRecordExcludedFromCycle(r));
    const approvedRecords   = validRecords.filter(r => Boolean(r.aprobada));
    const withSapRecords    = approvedRecords.filter(r => Boolean(r.tiene_oc_sap) && r.d_ciclo_total !== null);
    const pendingSapRecords = records.filter(isRecordPendingSap);

    // 1. KPI: Tiempo Ciclo Total (Creación PR -> SAP)
    const avgCycleTotal = withSapRecords.length
        ? (withSapRecords.reduce((acc, r) => acc + Number(r.d_ciclo_total || 0), 0) / withSapRecords.length)
        : 0;

    // 2. KPI: Gestión Comprador (Aprobación PR -> Creación OC Odoo)
    const buyerManaged = approvedRecords.filter(r => r.d_comprador !== null);
    const avgBuyerDays = buyerManaged.length
        ? (buyerManaged.reduce((acc, r) => acc + Number(r.d_comprador || 0), 0) / buyerManaged.length)
        : 0;

    // 3. KPI: Cumplimiento Meta <= 14 días (sobre solicitudes válidas aprobadas con ciclo cerrado o en curso)
    const slaEvaluated = approvedRecords.filter(r => Boolean(r.tiene_oc_sap) || Boolean(r.tiene_oc_odoo));
    const slaWithin14  = slaEvaluated.filter(r => {
        const d = r.tiene_oc_sap ? Number(r.d_ciclo_total ?? 999) : Number(r.d_to_sap ?? r.edad_dias ?? 999);
        return d <= 14;
    });
    const slaPct = slaEvaluated.length ? ((slaWithin14.length / slaEvaluated.length) * 100) : 0;

    // 4. KPI: Rezago sin SAP (idéntico al modal AUDITORÍA · REZAGO SIN SAP)
    const pendingSapCount = pendingSapRecords.length;
    const criticalSapCount = pendingSapRecords.filter(r => Number(r.d_to_sap ?? r.edad_dias ?? 0) > 30).length;

    setTextSafe('kpiCycleTotalDays', `${avgCycleTotal.toFixed(1)} d`);
    setTextSafe('kpiCycleTotalSub', `${withSapRecords.length} solicitudes cerradas en SAP`);

    setTextSafe('kpiBuyerDays', `${avgBuyerDays.toFixed(1)} d`);
    setTextSafe('kpiBuyerSub', `${buyerManaged.length} solicitudes gestionadas`);

    setTextSafe('kpiSla14Pct', `${slaPct.toFixed(1)}%`);
    setTextSafe('kpiSla14Sub', `${slaWithin14.length} de ${slaEvaluated.length} en meta (≤ 14 d)`);

    setTextSafe('kpiPendingSapCount', String(pendingSapCount));
    setTextSafe('kpiPendingSapSub', `${criticalSapCount} críticas (> 30 días) · Clic para auditar`);

    renderCycleTrendChart(validRecords);
    renderSapBacklogChart(pendingSapRecords);
    renderBuyerSlaTableAndChart(approvedRecords);
}

// ==========================================================================
// SECCIÓN 3: GRÁFICA DE TENDENCIA MENSUAL DEL CICLO (ETAPAS SLA)
// ==========================================================================
function renderCycleTrendChart(validRecords) {
    const el = document.getElementById('chartCycleTrend');
    if (!el || typeof ApexCharts === 'undefined') return;

    const byYm = {};
    validRecords.forEach(r => {
        const ym = r.ym || '';
        if (!ym) return;
        if (!byYm[ym]) {
            byYm[ym] = { aprobSol: [], comp: [], aprobSap: [], total: [] };
        }
        if (r.d_aprob_sol !== null) byYm[ym].aprobSol.push(Number(r.d_aprob_sol));
        if (r.d_comprador !== null) byYm[ym].comp.push(Number(r.d_comprador));
        if (r.d_aprob_sap !== null) byYm[ym].aprobSap.push(Number(r.d_aprob_sap));
        if (r.d_ciclo_total !== null) byYm[ym].total.push(Number(r.d_ciclo_total));
    });

    const months = Object.keys(byYm).sort();
    const avg = arr => arr.length ? Number((arr.reduce((a, b) => a + b, 0) / arr.length).toFixed(1)) : 0;

    const sAprobSol = months.map(m => avg(byYm[m].aprobSol));
    const sComp     = months.map(m => avg(byYm[m].comp));
    const sAprobSap = months.map(m => avg(byYm[m].aprobSap));
    const sTotal    = months.map(m => avg(byYm[m].total));

    const isDark = document.documentElement.classList.contains('dark');
    const options = {
        chart: { type: 'line', height: 290, toolbar: { show: false }, background: 'transparent' },
        theme: { mode: isDark ? 'dark' : 'light' },
        stroke: { width: [2, 3, 2, 3], curve: 'smooth', dashArray: [0, 0, 0, 5] },
        colors: ['#64748B', '#264073', '#0EA5E9', '#C8102E'],
        series: [
            { name: 'Aprobación PR (d)', data: sAprobSol },
            { name: 'Gestión Comprador (d)', data: sComp },
            { name: 'Odoo → SAP (d)', data: sAprobSap },
            { name: 'Ciclo Total SAP (d)', data: sTotal },
        ],
        xaxis: { categories: months },
        yaxis: { title: { text: 'Días promedio' }, min: 0 },
        legend: { position: 'top' },
        dataLabels: { enabled: false },
    };

    if (chartCycleTrendInstance) {
        chartCycleTrendInstance.destroy();
    }
    chartCycleTrendInstance = new ApexCharts(el, options);
    chartCycleTrendInstance.render();
}

// ==========================================================================
// SECCIÓN 4: GRÁFICA DE REZAGO DIARIO SIN SAP POR MES DE ORIGEN
// ==========================================================================
function renderSapBacklogChart(pendingSapRecords) {
    const el = document.getElementById('chartSapBacklog');
    if (!el || typeof ApexCharts === 'undefined') return;

    const byYm = {};
    pendingSapRecords.forEach(r => {
        const ym = r.ym || 'Sin mes';
        if (!byYm[ym]) {
            byYm[ym] = { count: 0, sumDays: 0 };
        }
        byYm[ym].count += 1;
        byYm[ym].sumDays += Number(r.d_to_sap ?? r.edad_dias ?? 0);
    });

    const months = Object.keys(byYm).sort();
    const counts = months.map(m => byYm[m].count);
    const avgDays = months.map(m => byYm[m].count ? Number((byYm[m].sumDays / byYm[m].count).toFixed(1)) : 0);

    const isDark = document.documentElement.classList.contains('dark');
    const options = {
        chart: { type: 'bar', height: 290, toolbar: { show: false }, background: 'transparent' },
        theme: { mode: isDark ? 'dark' : 'light' },
        colors: ['#1B2A4A', '#C8102E'],
        series: [
            { name: 'Solicitudes Pendientes', type: 'column', data: counts },
            { name: 'Antigüedad Promedio (d)', type: 'line', data: avgDays },
        ],
        stroke: { width: [0, 3], curve: 'smooth' },
        xaxis: { categories: months },
        yaxis: [
            { title: { text: 'Solicitudes' }, min: 0 },
            { opposite: true, title: { text: 'Días promedio' }, min: 0 },
        ],
        legend: { position: 'top' },
    };

    if (chartSapBacklogInstance) {
        chartSapBacklogInstance.destroy();
    }
    chartSapBacklogInstance = new ApexCharts(el, options);
    chartSapBacklogInstance.render();
}

// ==========================================================================
// SECCIÓN 5: DESEMPEÑO POR COMPRADOR (% OC EN ODOO <= 14 DÍAS)
// ==========================================================================
function renderBuyerSlaTableAndChart(approvedRecords) {
    const tbody = document.getElementById('buyerSlaTableBody');
    const elChart = document.getElementById('chartBuyerSla');

    const byBuyer = {};
    approvedRecords.forEach(r => {
        const b = r.comprador || 'Sin asignar';
        if (b === 'Sin asignar') return;
        if (!byBuyer[b]) {
            byBuyer[b] = { total: 0, withOc: 0, within14: 0, daysSum: 0, daysCnt: 0, pendingSap: 0 };
        }
        byBuyer[b].total += 1;
        if (r.tiene_oc_odoo) {
            byBuyer[b].withOc += 1;
        }
        if (r.d_comprador !== null) {
            const d = Number(r.d_comprador);
            byBuyer[b].daysSum += d;
            byBuyer[b].daysCnt += 1;
            if (d <= 14) {
                byBuyer[b].within14 += 1;
            }
        }
        if (isRecordPendingSap(r)) {
            byBuyer[b].pendingSap += 1;
        }
    });

    const rows = Object.entries(byBuyer).map(([buyer, st]) => {
        const avgD = st.daysCnt ? (st.daysSum / st.daysCnt) : 0;
        const pct14 = st.daysCnt ? ((st.within14 / st.daysCnt) * 100) : 0;
        return { buyer, ...st, avgD, pct14 };
    }).sort((a, b) => b.pct14 - a.pct14);

    if (tbody) {
        tbody.innerHTML = rows.length ? rows.map(row => `
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40 border-b border-slate-100 dark:border-gray-800">
                <td class="py-2.5 px-3 font-bold text-cresta-navy dark:text-white">${escapeHtml(row.buyer)}</td>
                <td class="py-2.5 px-3 text-right">${row.total}</td>
                <td class="py-2.5 px-3 text-right">${row.withOc}</td>
                <td class="py-2.5 px-3 text-right font-semibold">${row.avgD.toFixed(1)} d</td>
                <td class="py-2.5 px-3 text-right font-bold ${row.pct14 >= 70 ? 'text-emerald-600' : 'text-cresta-red'}">${row.pct14.toFixed(1)}%</td>
                <td class="py-2.5 px-3 text-right font-semibold">${row.pendingSap}</td>
            </tr>
        `).join('') : `<tr><td colspan="6" class="py-4 text-center text-slate-400">Sin datos de compradores en el período.</td></tr>`;
    }

    if (elChart && typeof ApexCharts !== 'undefined') {
        const isDark = document.documentElement.classList.contains('dark');
        const options = {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
            theme: { mode: isDark ? 'dark' : 'light' },
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
            colors: ['#264073'],
            series: [{ name: '% Gestión ≤ 14 días', data: rows.map(r => Number(r.pct14.toFixed(1))) }],
            xaxis: { categories: rows.map(r => r.buyer), max: 100 },
        };

        if (chartBuyerSlaInstance) {
            chartBuyerSlaInstance.destroy();
        }
        chartBuyerSlaInstance = new ApexCharts(elChart, options);
        chartBuyerSlaInstance.render();
    }
}

function setTextSafe(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}
