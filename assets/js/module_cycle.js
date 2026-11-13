/* =========================================================================
 * MÓDULO 1: CICLO OPERATIVO Y SLA (assets/js/module_cycle.js)
 * ========================================================================= */

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
    renderChartTopSolicitantesReqs(currentRecords);
    renderMonthlyTable(entityRecords, tableYear);
}

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

function renderChartTopSolicitantesReqs(currentRecords) {
    const mapSol = {};
    currentRecords.forEach(r => {
        if (r.is_borrador) return;
        const name = r.solicitado_por || 'Sin solicitante';
        if (!mapSol[name]) mapSol[name] = { validas: 0, rechazadas: 0, total: 0 };
        mapSol[name].total += 1;
        if (r.is_rechazada) mapSol[name].rechazadas += 1;
        else if (!r.excluida) mapSol[name].validas += 1;
    });

    const top10 = Object.entries(mapSol)
        .map(([name, info]) => ({
            name,
            validas: info.validas,
            rechazadas: info.rechazadas,
            pctRech: info.total > 0 ? ((info.rechazadas / info.total) * 100).toFixed(1) : '0.0'
        }))
        .filter(x => x.validas > 0)
        .sort((a, b) => b.validas - a.validas)
        .slice(0, 10);

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 320, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
        colors: isDark ? ['#38BDF8'] : ['#264073'],
        series: [{ name: 'Solicitudes válidas', data: top10.map(s => s.validas) }],
        xaxis: {
            categories: top10.map(s => shortName(s.name)),
            labels: { style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, style: { fontSize: '9px' } },
        tooltip: {
            y: {
                formatter: (val, { dataPointIndex }) => `${val} válidas · ${top10[dataPointIndex].rechazadas} rechazadas (${top10[dataPointIndex].pctRech}%)`
            }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.topSolReqs) charts.topSolReqs.destroy();
    charts.topSolReqs = new ApexCharts(document.querySelector('#chartTopSolicitantesReqs'), options);
    charts.topSolReqs.render();
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
