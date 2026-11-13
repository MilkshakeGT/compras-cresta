/* =========================================================================
 * MÓDULO 3: PORTAL 360° PROVEEDOR (assets/js/module_supplier.js)
 * ========================================================================= */

function onSupplier360SelectChange() {
    renderSupplier360View();
}

async function renderSupplier360View() {
    await ensureFirmLinesLoaded();

    const selectedProv = document.getElementById('sup360Selector').value;
    const selectedYear = document.getElementById('filterYear').value;
    const selectedYm   = document.getElementById('filterMes').value;
    const selectedQ    = document.getElementById('filterQuarter').value;

    document.getElementById('sup360PeriodTag').textContent = `${getActivePeriodLabel()} · Divisa ${activeCurrency}`;
    document.getElementById('sup360Title').textContent = selectedProv === 'ALL'
        ? 'Todos los Proveedores (Consolidado OCs en Firme)'
        : selectedProv;

    const allDivSpendFirm = filterSpendRecordsBase(RAW_DATA.spend_records || [], 'ALL').filter(r => r.is_firm);
    const periodAllProvFirm = filterByActivePeriod(allDivSpendFirm);
    const grandTotalPeriod = periodAllProvFirm.reduce((s, r) => s + r.monto, 0);

    const baseProvSpend = filterSpendRecordsBase(RAW_DATA.spend_records || [], selectedProv).filter(r => r.is_firm);
    const periodProvOcs = filterByActivePeriod(baseProvSpend).sort((a, b) => b.fecha.localeCompare(a.fecha));
    cachedSup360Ocs = periodProvOcs;

    const totalMonto = periodProvOcs.reduce((s, r) => s + r.monto, 0);
    const ocsCount   = periodProvOcs.length;
    const avgTicket  = ocsCount ? totalMonto / ocsCount : 0;
    const medTicket  = ocsCount ? median(periodProvOcs.map(r => r.monto)) : 0;
    const sharePct   = grandTotalPeriod > 0 ? ((totalMonto / grandTotalPeriod) * 100).toFixed(1) : '0.0';

    const sapCount = periodProvOcs.filter(r => r.en_sap).length;
    const sapPct   = ocsCount ? Math.round((sapCount / ocsCount) * 100) : 0;

    const socSet = new Set(periodProvOcs.map(r => r.sociedad));
    const fechasSorted = periodProvOcs.map(r => r.fecha).sort();
    const firstDate = fechasSorted.length ? fechasSorted[0] : '—';
    const lastDate  = fechasSorted.length ? fechasSorted[fechasSorted.length - 1] : '—';

    const baseLines = filterSpendRecordsBase(firmLinesCache || [], selectedProv);
    const periodLines = filterByActivePeriod(baseLines);

    const prodMap = {};
    const chronoLines = [...periodLines].sort((a, b) => a.fecha.localeCompare(b.fecha));
    chronoLines.forEach(l => {
        const pName = l.producto || '(Sin descripción)';
        if (!prodMap[pName]) {
            prodMap[pName] = {
                producto: pName,
                ocs: new Set(),
                qty: 0,
                monto: 0,
                minPu: l.pu > 0 ? l.pu : Infinity,
                maxPu: l.pu > 0 ? l.pu : 0,
                lastPu: l.pu,
                lastDate: l.fecha
            };
        }
        prodMap[pName].ocs.add(l.oc_odoo);
        prodMap[pName].qty += l.cantidad;
        prodMap[pName].monto += l.total;
        if (l.pu > 0) {
            if (l.pu < prodMap[pName].minPu) prodMap[pName].minPu = l.pu;
            if (l.pu > prodMap[pName].maxPu) prodMap[pName].maxPu = l.pu;
            prodMap[pName].lastPu = l.pu;
            prodMap[pName].lastDate = l.fecha;
        }
    });

    cachedSup360Products = Object.values(prodMap)
        .map(p => ({
            producto: p.producto,
            ocsCount: p.ocs.size,
            qty: p.qty,
            monto: p.monto,
            minPu: p.minPu === Infinity ? 0 : p.minPu,
            maxPu: p.maxPu,
            avgPu: p.qty > 0 ? p.monto / p.qty : 0,
            lastPu: p.lastPu,
            pct: totalMonto > 0 ? (p.monto / totalMonto) * 100 : 0
        }))
        .sort((a, b) => b.monto - a.monto);

    let prevOcs = null;
    let prevLabel = '';
    if (periodMode === 'compare_q') {
        const yq1 = document.getElementById('filterCompareQ1').value;
        const yq2 = document.getElementById('filterCompareQ2').value;
        prevOcs = filterRecordsByYearQuarter(baseProvSpend, yq2);
        prevLabel = formatYearQuarterShort(yq2);
        renderSup360CompareQ(yq1, yq2, periodProvOcs, prevOcs);
    } else if (periodMode === 'month') {
        const allMeses = RAW_DATA.filters.meses;
        const idx = allMeses.indexOf(selectedYm);
        if (idx > 0) {
            prevOcs = baseProvSpend.filter(r => r.ym === allMeses[idx - 1]);
            prevLabel = MONTH_NAMES[allMeses[idx - 1].split('-')[1]];
        }
    } else if (periodMode === 'quarter') {
        const order = ['Q1', 'Q2', 'Q3', 'Q4'];
        const idx = order.indexOf(selectedQ);
        const prevY = idx > 0 ? selectedYear : String(Number(selectedYear) - 1);
        const prevQ = idx > 0 ? order[idx - 1] : 'Q4';
        prevOcs = filterRecordsByYearQuarter(baseProvSpend, `${prevY}-${prevQ}`);
        prevLabel = `${prevQ} ${prevY !== selectedYear ? prevY : ''}`.trim();
    }

    document.getElementById('s360Kpi1Val').textContent = formatMoney(totalMonto);
    document.getElementById('s360Kpi1Sub').textContent = selectedProv === 'ALL'
        ? `100% del gasto firme en ${activeCurrency}`
        : `Representa el ${sharePct}% del gasto total del periodo`;

    const k1Badge = document.getElementById('s360Kpi1Badge');
    if (prevOcs && prevOcs.length > 0) {
        const prevMonto = prevOcs.reduce((s, r) => s + r.monto, 0);
        const diffPct = prevMonto > 0 ? Math.round(((totalMonto - prevMonto) / prevMonto) * 100) : 0;
        k1Badge.textContent = `${diffPct >= 0 ? '+' : ''}${diffPct}% vs ${prevLabel}`;
    } else {
        k1Badge.textContent = activeCurrency;
    }

    document.getElementById('s360Kpi2Val').textContent   = formatMoney(avgTicket);
    document.getElementById('s360Kpi2Badge').textContent = `${ocsCount.toLocaleString()} OCs`;
    document.getElementById('s360Kpi2Sub').textContent   = `Mediana por orden: ${formatMoney(medTicket)}`;

    document.getElementById('s360Kpi3Val').textContent   = cachedSup360Products.length.toLocaleString();
    document.getElementById('s360Kpi3Badge').textContent = `${periodLines.length.toLocaleString()} líneas`;

    document.getElementById('s360Kpi4Val').textContent   = lastDate;
    document.getElementById('s360Kpi4Badge').textContent = `${socSet.size} Soc.`;
    document.getElementById('s360Kpi4Sub').textContent   = `Primera compra: ${firstDate}`;

    document.getElementById('s360Kpi5Val').textContent   = `${sapPct}%`;
    document.getElementById('s360Kpi5Sub').textContent   = `${sapCount} de ${ocsCount} órdenes en SAP`;

    const chartYear = periodMode === 'compare_q'
        ? document.getElementById('filterCompareQ1').value.split('-')[0]
        : selectedYear;
    const meses = RAW_DATA.filters.meses.filter(m => m.startsWith(`${chartYear}-`));

    const mMontos = meses.map(m => Number(baseProvSpend.filter(r => r.ym === m).reduce((s, r) => s + r.monto, 0).toFixed(2)));
    const mOcs    = meses.map(m => baseProvSpend.filter(r => r.ym === m).length);

    const themeCfg = getChartThemeConfig();
    const optTrend = {
        chart: { type: 'line', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        stroke: { width: [0, 3], curve: 'smooth' },
        colors: isDark ? ['#6366F1', '#34D399'] : ['#1B2A4A', '#C8102E'],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } },
        series: [
            { name: `Monto (${activeCurrency})`, type: 'column', data: mMontos },
            { name: 'Órdenes (OCs)', type: 'line', data: mOcs }
        ],
        xaxis: { categories: meses.map(formatMonthOnly), labels: { style: { fontSize: '10px', colors: themeCfg.labelColor } } },
        yaxis: [
            {
                title: { text: `Monto (${activeCurrency})`, style: { fontSize: '10px', color: themeCfg.labelColor } },
                labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
            },
            {
                opposite: true,
                title: { text: 'OCs', style: { fontSize: '10px', color: themeCfg.labelColor } },
                labels: { formatter: v => Math.round(v), style: { colors: themeCfg.labelColor } }
            }
        ],
        markers: { size: 4 },
        tooltip: {
            y: { formatter: (val, { seriesIndex }) => seriesIndex === 0 ? formatMoney(val) : `${val} OCs` }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 },
        legend: { position: 'top', fontSize: '11px' }
    };

    if (charts.sup360Trend) charts.sup360Trend.destroy();
    charts.sup360Trend = new ApexCharts(document.querySelector('#chartSup360Trend'), optTrend);
    charts.sup360Trend.render();

    const top10Prods = cachedSup360Products.slice(0, 10);
    const optProds = {
        chart: { type: 'bar', height: 310, toolbar: { show: false }, background: 'transparent', fontFamily: 'Verdana' },
        theme: { mode: themeCfg.mode },
        noData: { text: 'Sin líneas de producto en este filtro', style: { color: themeCfg.labelColor, fontSize: '12px' } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
        colors: isDark ? ['#38BDF8'] : ['#264073'],
        series: [{ name: `Monto (${activeCurrency})`, data: top10Prods.map(p => Number(p.monto.toFixed(2))) }],
        xaxis: {
            categories: top10Prods.map(p => shortProduct(p.producto)),
            labels: { formatter: v => formatMoneyCompact(v), style: { colors: themeCfg.labelColor } }
        },
        yaxis: { labels: { style: { colors: themeCfg.labelColor, fontSize: '10px' } } },
        dataLabels: { enabled: true, formatter: v => formatMoneyCompact(v), style: { fontSize: '9px' } },
        tooltip: {
            y: { formatter: (val, { dataPointIndex }) => `${formatMoney(val)} · ${top10Prods[dataPointIndex].qty.toLocaleString()} uds` }
        },
        grid: { borderColor: themeCfg.gridColor, strokeDashArray: 4 }
    };

    if (charts.sup360Products) charts.sup360Products.destroy();
    charts.sup360Products = new ApexCharts(document.querySelector('#chartSup360Products'), optProds);
    charts.sup360Products.render();

    renderSup360TablesOnly();
}

function renderSup360CompareQ(yq1, yq2, ocs1, ocs2) {
    const lbl1 = formatYearQuarterShort(yq1);
    const lbl2 = formatYearQuarterShort(yq2);
    document.getElementById('sup360CompareQTitle').textContent = `${lbl1} vs ${lbl2} (${activeCurrency})`;

    const m1 = ocs1.reduce((s, r) => s + r.monto, 0);
    const m2 = ocs2.reduce((s, r) => s + r.monto, 0);
    const t1 = ocs1.length ? m1 / ocs1.length : 0;
    const t2 = ocs2.length ? m2 / ocs2.length : 0;

    document.getElementById('sup360CompareQHead').innerHTML = `<tr>
        <th class="py-2 px-3 font-bold">Métrica del Proveedor</th>
        <th class="py-2 px-3 text-right font-bold">${lbl1}</th>
        <th class="py-2 px-3 text-right font-bold">${lbl2}</th>
        <th class="py-2 px-3 text-right font-bold">Diferencia</th>
    </tr>`;

    const diffMonto = m1 - m2;
    const diffPct = m2 > 0 ? ((diffMonto / m2) * 100).toFixed(1) + '%' : 'N/D';

    document.getElementById('sup360CompareQBody').innerHTML = `
        <tr>
            <td class="py-2 px-3 font-medium">Monto Comprado (OCs Firmes)</td>
            <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-white">${formatMoney(m1)}</td>
            <td class="py-2 px-3 text-right">${formatMoney(m2)}</td>
            <td class="py-2 px-3 text-right font-bold ${diffMonto <= 0 ? 'text-emerald-600' : 'text-amber-600'}">${diffMonto >= 0 ? '+' : ''}${formatMoney(diffMonto)} (${diffPct})</td>
        </tr>
        <tr>
            <td class="py-2 px-3 font-medium">Cantidad de OCs Firmes</td>
            <td class="py-2 px-3 text-right font-bold">${ocs1.length}</td>
            <td class="py-2 px-3 text-right">${ocs2.length}</td>
            <td class="py-2 px-3 text-right font-bold">${ocs1.length - ocs2.length >= 0 ? '+' : ''}${ocs1.length - ocs2.length} OCs</td>
        </tr>
        <tr>
            <td class="py-2 px-3 font-medium">Ticket Promedio por OC</td>
            <td class="py-2 px-3 text-right font-bold">${formatMoney(t1)}</td>
            <td class="py-2 px-3 text-right">${formatMoney(t2)}</td>
            <td class="py-2 px-3 text-right font-bold">${formatMoney(t1 - t2)}</td>
        </tr>
    `;
}

function renderSup360TablesOnly() {
    const q = (document.getElementById('sup360ProdSearch').value || '').trim().toLowerCase();
    const filteredProds = q
        ? cachedSup360Products.filter(p => p.producto.toLowerCase().includes(q))
        : cachedSup360Products;

    const prodTbody = document.getElementById('sup360ProductsTableBody');
    if (!filteredProds.length) {
        prodTbody.innerHTML = `<tr><td colspan="9" class="py-6 text-center text-slate-400">No hay productos registrados en este filtro.</td></tr>`;
    } else {
        prodTbody.innerHTML = filteredProds.slice(0, 150).map(p => `
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
                <td class="py-2 px-3 font-medium text-slate-800 dark:text-gray-200">${escapeHtml(p.producto)}</td>
                <td class="py-2 px-3 text-right text-slate-600 dark:text-gray-300">${p.ocsCount}</td>
                <td class="py-2 px-3 text-right font-semibold text-slate-700 dark:text-gray-200">${p.qty.toLocaleString('en-US', { maximumFractionDigits: 2 })}</td>
                <td class="py-2 px-3 text-right text-slate-500 dark:text-gray-400">${formatMoney(p.minPu)}</td>
                <td class="py-2 px-3 text-right font-semibold text-cresta-blue dark:text-sky-400">${formatMoney(p.avgPu)}</td>
                <td class="py-2 px-3 text-right text-slate-500 dark:text-gray-400">${formatMoney(p.maxPu)}</td>
                <td class="py-2 px-3 text-right font-bold text-emerald-700 dark:text-emerald-400">${formatMoney(p.lastPu)}</td>
                <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-white">${formatMoney(p.monto)}</td>
                <td class="py-2 px-3 text-right font-semibold text-cresta-red dark:text-amber-400">${p.pct.toFixed(1)}%</td>
            </tr>
        `).join('');
    }

    const ocsTbody = document.getElementById('sup360OcsTableBody');
    document.getElementById('sup360OcsCountLabel').textContent = `${cachedSup360Ocs.length.toLocaleString()} órdenes firmes`;

    if (!cachedSup360Ocs.length) {
        ocsTbody.innerHTML = `<tr><td colspan="8" class="py-6 text-center text-slate-400">No hay órdenes firmes en este filtro.</td></tr>`;
    } else {
        ocsTbody.innerHTML = cachedSup360Ocs.slice(0, 200).map(r => {
            const sapTag = r.en_sap
                ? `<span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800">SAP ${escapeHtml(r.oc_sap)}</span>`
                : `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800">${escapeHtml(r.estado_oc)}</span>`;

            return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.fecha)}</td>
                <td class="py-2 px-3">${renderOcLinks(r.oc_odoo)}</td>
                <td class="py-2 px-3">${renderSolLink(r.solicitud)}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.sociedad)}</td>
                <td class="py-2 px-3 font-medium text-slate-800 dark:text-gray-200">${escapeHtml(r.proveedor)}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(shortName(r.comprador))}</td>
                <td class="py-2 px-3">${sapTag}</td>
                <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${formatMoney(r.monto, r.divisa)}</td>
            </tr>`;
        }).join('');
    }
}

function exportSupplier360CSV() {
    const selectedProv = document.getElementById('sup360Selector').value;
    const provLabel = selectedProv === 'ALL' ? 'todos_proveedores' : selectedProv.replace(/[^a-zA-Z0-9]/g, '_').slice(0, 30);

    const headers = ['Proveedor', 'Producto / Servicio', 'OCs', 'Cantidad Total', 'Divisa', 'Precio Minimo', 'Precio Promedio', 'Precio Maximo', 'Ultimo Precio', 'Monto Total', '% Participacion'];
    const csvLines = [headers.join(',')];

    cachedSup360Products.forEach(p => {
        csvLines.push([
            `"${selectedProv.replace(/"/g, '""')}"`,
            `"${p.producto.replace(/"/g, '""').replace(/\n/g, ' ')}"`,
            p.ocsCount,
            p.qty.toFixed(2),
            `"${activeCurrency}"`,
            p.minPu.toFixed(4),
            p.avgPu.toFixed(4),
            p.maxPu.toFixed(4),
            p.lastPu.toFixed(4),
            p.monto.toFixed(2),
            p.pct.toFixed(2)
        ].join(','));
    });

    downloadCsvBlob(csvLines.join('\n'), `portal360_${provLabel}_${activeCurrency}_${RAW_DATA.cutoff_date}.csv`);
}
