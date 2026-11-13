/* =========================================================================
 * MÓDULO 2: GESTIÓN DE GASTO Y PRODUCTOS (assets/js/module_spend.js)
 * ========================================================================= */

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

async function renderSpendView() {
    const selectedYear = document.getElementById('filterYear').value;
    const selectedYm   = document.getElementById('filterMes').value;
    const selectedQ    = document.getElementById('filterQuarter').value;

    const baseSpend = filterSpendRecordsBase(RAW_DATA.spend_records || []);
    const currentSpendRows = filterByActivePeriod(baseSpend);
    const st = calcSpendStats(currentSpendRows);

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

    currentTop5ProvidersSet = new Set(st.provSorted.slice(0, 5).map(p => p.name));
    cachedUniverses.SPEND_FIRM   = [...st.firmList].sort((a, b) => b.monto - a.monto);
    cachedUniverses.SPEND_RFQ    = [...st.rfqList].sort((a, b) => b.monto - a.monto);
    cachedUniverses.SPEND_NO_SAP = [...st.noSapList].sort((a, b) => b.monto - a.monto);

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

    document.getElementById('spKpi2Value').textContent = formatMoney(st.avgTicket);
    document.getElementById('spKpi2Badge').textContent = `${st.firmCount.toLocaleString()} OCs`;
    document.getElementById('spKpi2Sub').textContent   = `Mediana: ${formatMoney(st.medTicket)}`;

    document.getElementById('spKpi3Value').textContent = st.provCount.toLocaleString();
    document.getElementById('spKpi3Badge').textContent = `Top 5: ${st.top5Pct}%`;
    document.getElementById('spKpi3Sub').textContent   = `Concentración Pareto en 5 mayores`;

    document.getElementById('spKpi4Value').textContent = formatMoney(st.rfqMonto);
    document.getElementById('spKpi4Badge').textContent = `${st.rfqCount} abiertas`;
    document.getElementById('spKpi4Sub').textContent   = `Cotizaciones sin convertir a OC`;

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
    renderChartTopSolicitantesMonto(st.firmList);
    renderChartTopProveedoresFreq(st.firmList);
    renderTopSuppliersTable(st);

    await ensureFirmLinesLoaded();
    renderProductsAnalyticsSection();
}

function renderProductsAnalyticsSection() {
    const allLines = firmLinesCache || [];
    const baseLines = filterSpendRecordsBase(allLines);
    const periodLines = filterByActivePeriod(baseLines);

    const mapProd = {};
    periodLines.forEach(l => {
        const p = l.producto || '(Sin descripción)';
        if (!mapProd[p]) mapProd[p] = { monto: 0, qty: 0, ocs: new Set() };
        mapProd[p].monto += l.total;
        mapProd[p].qty += l.cantidad;
        mapProd[p].ocs.add(l.oc_odoo);
    });

    const sortedProds = Object.entries(mapProd)
        .map(([name, info]) => ({ name, monto: info.monto, qty: info.qty, ocs: info.ocs.size }))
        .sort((a, b) => b.monto - a.monto);

    const selProd = document.getElementById('selectProductTrack');
    const prevVal = selProd.value;
    selProd.innerHTML = '';

    sortedProds.slice(0, 60).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.name;
        opt.textContent = `${shortProduct(p.name)} (${formatMoneyCompact(p.monto)})`;
        selProd.appendChild(opt);
    });

    if (prevVal && sortedProds.some(p => p.name === prevVal)) {
        selProd.value = prevVal;
    } else if (sortedProds.length > 0) {
        selProd.value = sortedProds[0].name;
    }

    const top10 = sortedProds.slice(0, 10);
    const themeCfg = getChartThemeConfig();
    const optTop = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
        colors: isDark ? ['#34D399'] : ['#15803D'],
        series: [{ name: `Monto (${activeCurrency})`, data: top10.map(p => Number(p.monto.toFixed(2))) }],
        xaxis: {
            categories: top10.map(p => shortProduct(p.name)),
            labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => formatMoneyCompact(v), style: { fontSize: '9px' } },
        tooltip: {
            y: {
                formatter: (val, { dataPointIndex }) => `${formatMoney(val)} · ${top10[dataPointIndex].qty.toLocaleString()} uds (${top10[dataPointIndex].ocs} OCs)`
            }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.topProdsMonto) charts.topProdsMonto.destroy();
    charts.topProdsMonto = new ApexCharts(document.querySelector('#chartTopProductsMonto'), optTop);
    charts.topProdsMonto.render();

    renderProductPriceTrend();
}

function renderProductPriceTrend() {
    const selectedProd = document.getElementById('selectProductTrack').value;
    const allLines = firmLinesCache || [];
    const baseLines = filterSpendRecordsBase(allLines).filter(l => l.producto === selectedProd);
    const periodLines = filterByActivePeriod(baseLines);

    const spendSum = periodLines.reduce((s, l) => s + l.total, 0);
    const qtySum   = periodLines.reduce((s, l) => s + l.cantidad, 0);
    const pus      = periodLines.map(l => l.pu).filter(v => v > 0);
    const avgPu    = qtySum > 0 ? (spendSum / qtySum) : (pus.length ? avg(pus) : 0);
    const minPu    = pus.length ? Math.min(...pus) : 0;
    const maxPu    = pus.length ? Math.max(...pus) : 0;

    document.getElementById('prodKpiSpend').textContent = formatMoney(spendSum);
    document.getElementById('prodKpiQty').textContent   = qtySum.toLocaleString('en-US', { maximumFractionDigits: 2 });
    document.getElementById('prodKpiAvgPu').textContent = formatMoney(avgPu);
    document.getElementById('prodKpiRange').textContent = `${formatMoneyCompact(minPu)} – ${formatMoneyCompact(maxPu)}`;

    const selectedYear = periodMode === 'compare_q'
        ? document.getElementById('filterCompareQ1').value.split('-')[0]
        : document.getElementById('filterYear').value;

    const meses = RAW_DATA.filters.meses.filter(m => m.startsWith(`${selectedYear}-`));
    document.getElementById('prodTrendChartTitle').textContent = `Precio Unitario Mensual (${activeCurrency}) · ${shortProduct(selectedProd || 'Sin selección')}`;

    const monthlyAvgPu = meses.map(m => {
        const mRows = baseLines.filter(l => l.ym === m && l.pu > 0);
        if (!mRows.length) return null;
        const mTot = mRows.reduce((s, l) => s + l.total, 0);
        const mQty = mRows.reduce((s, l) => s + l.cantidad, 0);
        return mQty > 0 ? Number((mTot / mQty).toFixed(2)) : Number(avg(mRows.map(l => l.pu)).toFixed(2));
    });

    const themeCfg = getChartThemeConfig();
    const optTrend = {
        chart: { type: 'line', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        stroke: { width: 3, curve: 'smooth' },
        colors: isDark ? ['#FBBF24'] : ['#C8102E'],
        series: [{ name: `Precio Unitario (${activeCurrency})`, data: monthlyAvgPu }],
        xaxis: { categories: meses.map(formatMonthOnly), labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
        yaxis: {
            labels: { formatter: v => v !== null ? formatMoney(v) : '', style: { colors: themeCfg.labelColor } }
        },
        markers: { size: 5 },
        dataLabels: { enabled: true, formatter: v => v !== null ? formatMoneyCompact(v) : '', style: { fontSize: '9px' } },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.prodPriceTrend) charts.prodPriceTrend.destroy();
    charts.prodPriceTrend = new ApexCharts(document.querySelector('#chartProductPriceTrend'), optTrend);
    charts.prodPriceTrend.render();
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

function renderChartTopSolicitantesMonto(firmList) {
    const mapSol = {};
    firmList.forEach(r => {
        if (!mapSol[r.solicitado_por]) mapSol[r.solicitado_por] = { monto: 0, ocs: 0 };
        mapSol[r.solicitado_por].monto += r.monto;
        mapSol[r.solicitado_por].ocs += 1;
    });

    const top10 = Object.entries(mapSol)
        .map(([name, info]) => ({ name, monto: info.monto, ocs: info.ocs }))
        .sort((a, b) => b.monto - a.monto)
        .slice(0, 10);

    const themeCfg = getChartThemeConfig();
    const options = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
        colors: isDark ? ['#A78BFA'] : ['#1B2A4A'],
        series: [{ name: `Monto (${activeCurrency})`, data: top10.map(s => Number(s.monto.toFixed(2))) }],
        xaxis: {
            categories: top10.map(s => `${shortName(s.name)} (${s.ocs})`),
            labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => formatMoneyCompact(v), style: { fontSize: '9px' } },
        tooltip: {
            y: {
                formatter: (val, { dataPointIndex }) => `${formatMoney(val)} · ${top10[dataPointIndex].ocs} OCs`
            }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.topSolMonto) charts.topSolMonto.destroy();
    charts.topSolMonto = new ApexCharts(document.querySelector('#chartTopSolicitantesMonto'), options);
    charts.topSolMonto.render();
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
            <td class="py-2.5 px-3">
                <button type="button" onclick="jumpToSupplier360('${escapeHtml(p.name)}')" title="Abrir Portal 360° de este proveedor"
                    class="font-bold text-cresta-navy dark:text-white underline decoration-dotted hover:text-cresta-red text-left">
                    ${escapeHtml(p.name)}
                </button>
            </td>
            <td class="py-2.5 px-3 text-right text-slate-700 dark:text-gray-300">${p.ocs}</td>
            <td class="py-2.5 px-3 text-right text-slate-600 dark:text-gray-400">${p.socCount}</td>
            <td class="py-2.5 px-3 text-right text-slate-600 dark:text-gray-300">${formatMoney(ticket)}</td>
            <td class="py-2.5 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${formatMoney(p.monto)}</td>
            <td class="py-2.5 px-3 text-right font-semibold text-slate-700 dark:text-gray-300">${pct.toFixed(1)}%</td>
            <td class="py-2.5 px-3 text-right font-bold text-cresta-red dark:text-emerald-400">${accumPct.toFixed(1)}%</td>
        </tr>`;
    }).join('');
}

function jumpToSupplier360(provName) {
    const sel = document.getElementById('sup360Selector');
    sel.value = provName;
    setMainTab('supplier');
}
