/* =========================================================================
 * MOTOR CENTRAL DE EXPORTACIÓN A EXCEL (.XLSX) Y PDF NATIVO VECTORIAL (export.js)
 * ========================================================================= */

function exportToExcel(headers, rowsMatrix, filename, sheetName = 'Reporte') {
    const cleanFilename = filename.replace(/\.csv$/i, '.xlsx');
    const finalName = cleanFilename.endsWith('.xlsx') ? cleanFilename : `${cleanFilename}.xlsx`;

    if (typeof XLSX === 'undefined') {
        alert('La librería de Excel aún se está cargando. Intenta de nuevo en un segundo.');
        return;
    }

    const aoa = [headers, ...rowsMatrix];
    const ws = XLSX.utils.aoa_to_sheet(aoa);

    const colWidths = headers.map((h, colIdx) => {
        let maxLen = String(h ?? '').length;
        rowsMatrix.forEach(row => {
            const cellVal = row[colIdx] !== null && row[colIdx] !== undefined ? String(row[colIdx]) : '';
            if (cellVal.length > maxLen) {
                maxLen = Math.min(cellVal.length, 60);
            }
        });
        return { wch: maxLen + 4 };
    });
    ws['!cols'] = colWidths;

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, sheetName.slice(0, 31));
    XLSX.writeFile(wb, finalName);
}

function getActiveFiltersSummaryList() {
    const periodTxt = getActivePeriodLabel();
    const soc = document.getElementById('filterSociedad').value;
    const comp = document.getElementById('filterComprador').value;
    const sol = document.getElementById('filterSolicitante').value;

    const items = [
        { label: 'Periodo', value: periodTxt },
        { label: 'Sociedad', value: soc === 'ALL' ? 'Todas' : soc },
        { label: 'Comprador', value: comp === 'ALL' ? 'Todos' : comp },
        { label: 'Solicitante', value: sol === 'ALL' ? 'Todos' : sol }
    ];

    if (mainTab === 'spend') {
        const prov = document.getElementById('filterProveedor').value;
        const hidePetty = document.getElementById('chkExcludePettyCash').checked;
        items.push({ label: 'Proveedor', value: prov === 'ALL' ? 'Todos' : prov });
        items.push({ label: 'Divisa', value: activeCurrency });
        if (hidePetty) items.push({ label: 'Filtro', value: 'Sin Caja Chica' });
    } else if (mainTab === 'supplier') {
        const prov360 = document.getElementById('sup360Selector').value;
        const hidePetty = document.getElementById('chkExcludePettyCash').checked;
        items.push({ label: 'Proveedor', value: prov360 === 'ALL' ? 'Todos' : prov360 });
        items.push({ label: 'Divisa', value: activeCurrency });
        if (hidePetty) items.push({ label: 'Filtro', value: 'Sin Caja Chica' });
    }

    return items;
}

// =========================================================================
// GENERADOR DE PDF EJECUTIVO EN CARTA VERTICAL (jsPDF + ApexCharts dataURI)
// =========================================================================
async function exportActiveTabPDF() {
    const JsPDFCtor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : window.jsPDF;
    if (!JsPDFCtor) {
        alert('La librería PDF no está disponible. Recarga la página e intenta de nuevo.');
        return;
    }

    const btn = document.getElementById('btnExportTabPDF');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span><span>Generando PDF...</span>';

    const wasDark = isDark;

    try {
        // Si está en modo oscuro, renderizar brevemente en modo claro para gráficas con fondo limpio
        if (wasDark) {
            isDark = false;
            applyThemeUI();
            renderDashboard();
            await new Promise(r => setTimeout(r, 450));
        }

        // Configurar documento Carta Vertical (215.9mm x 279.4mm)
        const doc = new JsPDFCtor({
            orientation: 'portrait',
            unit: 'mm',
            format: 'letter'
        });

        const pageW = doc.internal.pageSize.getWidth();   // 215.9 mm
        const pageH = doc.internal.pageSize.getHeight();  // 279.4 mm
        const margin = 10;
        const contentW = pageW - margin * 2;              // 195.9 mm
        let y = margin;

        const ensureSpace = (neededH) => {
            if (y + neededH > pageH - margin) {
                doc.addPage();
                y = margin;
            }
        };

        // Determinar título y catálogo de tarjetas/gráficas de la pestaña activa
        let reportTitle = 'Informe Ejecutivo · Ciclo Operativo de Compras (SLA)';
        let filePrefix = 'ciclo_operativo';
        let kpiCards = [];
        let chartBlocks = [];

        if (mainTab === 'cycle') {
            reportTitle = 'Informe Ejecutivo · Ciclo Operativo de Compras (SLA)';
            filePrefix = 'ciclo_operativo';
            kpiCards = [
                {
                    title: 'SOLICITUDES VÁLIDAS',
                    val: document.getElementById('kpi1Value').textContent.trim(),
                    badge: document.getElementById('kpi1Badge').textContent.trim(),
                    sub: document.getElementById('kpi1Sub').textContent.trim(),
                    color: [27, 42, 74]
                },
                {
                    title: 'GESTIÓN COMPRADOR',
                    val: `${document.getElementById('kpiTimeValue').textContent.trim()} días`,
                    badge: document.getElementById('kpiTimeBadge').textContent.trim(),
                    sub: document.getElementById('kpiTimeSub').textContent.trim(),
                    color: [38, 64, 115]
                },
                {
                    title: 'SLA COMPRADOR ≤14D',
                    val: document.getElementById('kpi2Value').textContent.trim(),
                    badge: document.getElementById('kpi2Badge').textContent.trim(),
                    sub: document.getElementById('kpi2Sub').textContent.trim(),
                    color: [21, 128, 61]
                },
                {
                    title: 'EN SAP ≤14 DÍAS',
                    val: document.getElementById('kpi3Value').textContent.trim(),
                    badge: document.getElementById('kpi3Badge').textContent.trim(),
                    sub: document.getElementById('kpi3Sub').textContent.trim(),
                    color: [38, 64, 115]
                },
                {
                    title: 'REZAGO SIN SAP',
                    val: document.getElementById('kpi4Value').textContent.trim(),
                    badge: document.getElementById('kpi4Badge').textContent.trim(),
                    sub: document.getElementById('kpi4Sub').textContent.trim(),
                    color: [200, 16, 46]
                }
            ];

            chartBlocks = [
                {
                    instance: charts.quincenas,
                    title: '% Solicitudes en SAP dentro de plazo (por Quincena)',
                    sub: 'Evaluado sobre solicitudes que cumplieron ventana de 7, 14 o 30 días',
                    cols: 1,
                    heightMm: 62
                },
                {
                    instance: charts.ciclo,
                    title: 'Desglose del Ciclo Promedio a SAP (Días)',
                    sub: 'Aprobación Solicitud vs. Gestión Comprador vs. Envío SAP',
                    cols: 2,
                    heightMm: 58
                },
                {
                    instance: charts.rezago,
                    title: 'Rezago Abierto Sin SAP por Mes de Origen',
                    sub: 'Solicitudes aprobadas pendientes de sincronizar en SAP',
                    cols: 2,
                    heightMm: 58
                },
                {
                    instance: charts.compradores,
                    title: 'Desempeño por Comprador (% OC en Odoo ≤14 días)',
                    sub: 'Gestión pura del comprador (Meta 70%)',
                    cols: 1,
                    heightMm: 64
                },
                {
                    instance: charts.aprobSol,
                    title: 'Ranking Aprobadores de Solicitud (Días Promedio)',
                    sub: 'Creación Solicitud → Aprobación',
                    cols: 2,
                    heightMm: 62
                },
                {
                    instance: charts.aprobOc,
                    title: 'Ranking Aprobadores de OC Odoo (Días Promedio)',
                    sub: 'Creación OC → Aprobación OC (Autorizador 1)',
                    cols: 2,
                    heightMm: 62
                },
                {
                    instance: charts.compTrend,
                    title: document.getElementById('chartCompTrendTitle').textContent.trim(),
                    sub: 'Días promedio de respuesta por comprador',
                    cols: 1,
                    heightMm: 64
                },
                {
                    instance: charts.topSolReqs,
                    title: 'Top 10 Solicitantes Internos (Requisiciones)',
                    sub: 'Usuarios con mayor demanda de compra',
                    cols: 1,
                    heightMm: 62
                }
            ];
        } else if (mainTab === 'spend') {
            reportTitle = `Informe Ejecutivo · Gestión de Gasto y Productos (${activeCurrency})`;
            filePrefix = `gasto_productos_${activeCurrency}`;
            kpiCards = [
                {
                    title: 'GASTO COMPROMETIDO',
                    val: document.getElementById('spKpi1Value').textContent.trim(),
                    badge: document.getElementById('spKpi1Badge').textContent.trim(),
                    sub: document.getElementById('spKpi1Sub').textContent.trim(),
                    color: [27, 42, 74]
                },
                {
                    title: 'TICKET PROMEDIO OC',
                    val: document.getElementById('spKpi2Value').textContent.trim(),
                    badge: document.getElementById('spKpi2Badge').textContent.trim(),
                    sub: document.getElementById('spKpi2Sub').textContent.trim(),
                    color: [38, 64, 115]
                },
                {
                    title: 'PROVEEDORES ACTIVOS',
                    val: document.getElementById('spKpi3Value').textContent.trim(),
                    badge: document.getElementById('spKpi3Badge').textContent.trim(),
                    sub: document.getElementById('spKpi3Sub').textContent.trim(),
                    color: [21, 128, 61]
                },
                {
                    title: 'COTIZACIONES (RFQ)',
                    val: document.getElementById('spKpi4Value').textContent.trim(),
                    badge: document.getElementById('spKpi4Badge').textContent.trim(),
                    sub: document.getElementById('spKpi4Sub').textContent.trim(),
                    color: [217, 119, 6]
                },
                {
                    title: 'INTEGRACIÓN SAP',
                    val: document.getElementById('spKpi5Value').textContent.trim(),
                    badge: document.getElementById('spKpi5Badge').textContent.trim(),
                    sub: document.getElementById('spKpi5Sub').textContent.trim(),
                    color: [200, 16, 46]
                }
            ];

            chartBlocks = [
                {
                    instance: charts.spendTrend,
                    title: document.getElementById('spChartTrendTitle').textContent.trim(),
                    sub: 'Monto total comprometido vs. cantidad de órdenes emitidas',
                    cols: 1,
                    heightMm: 62
                },
                {
                    instance: charts.spendSoc,
                    title: 'Participación del Gasto por Sociedad',
                    sub: 'Distribución porcentual por empresa del grupo',
                    cols: 2,
                    heightMm: 62
                },
                {
                    instance: charts.topCompMonto,
                    title: 'Top Compradores por Monto Gestionado',
                    sub: 'Capital adjudicado en OCs firmes',
                    cols: 2,
                    heightMm: 62
                },
                {
                    instance: charts.topProvMonto,
                    title: 'Top 10 Proveedores por Monto Comprado',
                    sub: document.getElementById('badgeParetoInfo').textContent.trim(),
                    cols: 1,
                    heightMm: 64
                },
                {
                    instance: charts.topSolMonto,
                    title: 'Top 10 Solicitantes Internos por Monto',
                    sub: 'Áreas internas que originan el mayor gasto',
                    cols: 2,
                    heightMm: 62
                },
                {
                    instance: charts.topProvFreq,
                    title: 'Top 10 Proveedores por Frecuencia (OCs)',
                    sub: 'Proveedores de mayor recurrencia operativa',
                    cols: 2,
                    heightMm: 62
                },
                {
                    instance: charts.topProdsMonto,
                    title: 'Top 10 Productos por Monto Comprado',
                    sub: 'Insumos y servicios principales en OCs firmes',
                    cols: 1,
                    heightMm: 64
                },
                {
                    instance: charts.prodPriceTrend,
                    title: document.getElementById('prodTrendChartTitle').textContent.trim(),
                    sub: 'Evolución mensual de precio unitario promedio',
                    cols: 1,
                    heightMm: 60
                }
            ];
        } else {
            const provName = document.getElementById('sup360Selector').value;
            reportTitle = provName === 'ALL'
                ? `Portal 360° Proveedores · Consolidado (${activeCurrency})`
                : `Portal 360° Proveedor · ${provName} (${activeCurrency})`;
            filePrefix = `portal360_proveedor_${activeCurrency}`;

            kpiCards = [
                {
                    title: 'COMPRAS FIRMES',
                    val: document.getElementById('s360Kpi1Val').textContent.trim(),
                    badge: document.getElementById('s360Kpi1Badge').textContent.trim(),
                    sub: document.getElementById('s360Kpi1Sub').textContent.trim(),
                    color: [27, 42, 74]
                },
                {
                    title: 'TICKET PROMEDIO OC',
                    val: document.getElementById('s360Kpi2Val').textContent.trim(),
                    badge: document.getElementById('s360Kpi2Badge').textContent.trim(),
                    sub: document.getElementById('s360Kpi2Sub').textContent.trim(),
                    color: [38, 64, 115]
                },
                {
                    title: 'ARTÍCULOS DISTINTOS',
                    val: document.getElementById('s360Kpi3Val').textContent.trim(),
                    badge: document.getElementById('s360Kpi3Badge').textContent.trim(),
                    sub: 'Productos distintos comprados',
                    color: [21, 128, 61]
                },
                {
                    title: 'ÚLTIMA COMPRA',
                    val: document.getElementById('s360Kpi4Val').textContent.trim(),
                    badge: document.getElementById('s360Kpi4Badge').textContent.trim(),
                    sub: document.getElementById('s360Kpi4Sub').textContent.trim(),
                    color: [27, 42, 74]
                },
                {
                    title: 'SINCRONIZADO SAP',
                    val: document.getElementById('s360Kpi5Val').textContent.trim(),
                    badge: document.getElementById('s360Kpi5Badge').textContent.trim(),
                    sub: document.getElementById('s360Kpi5Sub').textContent.trim(),
                    color: [200, 16, 46]
                }
            ];

            chartBlocks = [
                {
                    instance: charts.sup360Trend,
                    title: 'Tendencia Mensual de Compras al Proveedor',
                    sub: 'Monto adjudicado y cantidad de órdenes por mes',
                    cols: 1,
                    heightMm: 64
                },
                {
                    instance: charts.sup360Products,
                    title: '¿Qué le Compramos? · Top 10 Productos / Servicios',
                    sub: 'Distribución del gasto por artículo comprado al proveedor',
                    cols: 1,
                    heightMm: 66
                }
            ];
        }

        // -----------------------------------------------------------------
        // 1. DIBUJAR ENCABEZADO EJECUTIVO CORPORATIVO
        // -----------------------------------------------------------------
        doc.setFillColor(27, 42, 74); // #1B2A4A Cresta Navy
        doc.roundedRect(margin, y, contentW, 20, 2, 2, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.text('GRUPO CRESTA · SUPPLY CHAIN MANAGEMENT', margin + 4, y + 6);

        doc.setFontSize(12);
        doc.text(doc.splitTextToSize(reportTitle, contentW - 55)[0], margin + 4, y + 13.5);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.text(`Corte: ${RAW_DATA.cutoff_date}`, pageW - margin - 4, y + 7, { align: 'right' });
        doc.text(`Generado: ${new Date().toLocaleDateString('es-GT')}`, pageW - margin - 4, y + 13.5, { align: 'right' });

        y += 23;

        // Barra de filtros aplicados
        const filterText = getActiveFiltersSummaryList().map(f => `${f.label}: ${f.value}`).join('   |   ');
        doc.setFillColor(241, 245, 249);
        doc.setDrawColor(203, 213, 225);
        const filterLines = doc.splitTextToSize(filterText, contentW - 6);
        const filterBoxH = Math.max(8, filterLines.length * 4.2 + 3.5);
        doc.roundedRect(margin, y, contentW, filterBoxH, 1.5, 1.5, 'FD');

        doc.setTextColor(30, 41, 59);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(7.5);
        doc.text(filterLines, margin + 3, y + 5);
        y += filterBoxH + 5;

        // -----------------------------------------------------------------
        // 2. DIBUJAR LAS 5 TARJETAS KPI EN FILA PROPORCIONAL
        // -----------------------------------------------------------------
        const gapKpi = 2.5;
        const cardW = (contentW - gapKpi * 4) / 5;
        const cardH = 22;
        ensureSpace(cardH + 4);

        kpiCards.forEach((c, idx) => {
            const cx = margin + idx * (cardW + gapKpi);
            doc.setFillColor(255, 255, 255);
            doc.setDrawColor(203, 213, 225);
            doc.roundedRect(cx, y, cardW, cardH, 1.5, 1.5, 'FD');

            // Barra superior de acento en la tarjeta
            doc.setFillColor(c.color[0], c.color[1], c.color[2]);
            doc.rect(cx, y, cardW, 1.4, 'F');

            doc.setTextColor(100, 116, 139);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(6);
            doc.text(doc.splitTextToSize(c.title, cardW - 4)[0], cx + 2.2, y + 5.2);

            doc.setTextColor(c.color[0], c.color[1], c.color[2]);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10.5);
            doc.text(doc.splitTextToSize(c.val, cardW - 4)[0], cx + 2.2, y + 11.8);

            doc.setTextColor(71, 85, 105);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(5.8);
            doc.text(doc.splitTextToSize(c.badge, cardW - 4)[0], cx + 2.2, y + 16.2);

            doc.setTextColor(100, 116, 139);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(5.4);
            doc.text(doc.splitTextToSize(c.sub, cardW - 4)[0], cx + 2.2, y + 19.8);
        });

        y += cardH + 6;

        // -----------------------------------------------------------------
        // 3. EXPORTAR CADA GRÁFICA APEXCHARTS COMO PNG LIMPIO Y MAQUETARLA
        // -----------------------------------------------------------------
        let i = 0;
        while (i < chartBlocks.length) {
            const b1 = chartBlocks[i];

            if (b1.cols === 2 && i + 1 < chartBlocks.length && chartBlocks[i + 1].cols === 2) {
                const b2 = chartBlocks[i + 1];
                const rowH = Math.max(b1.heightMm, b2.heightMm) + 12;
                ensureSpace(rowH);

                const colW = (contentW - 4) / 2;
                await drawSingleChartCard(doc, b1, margin, y, colW, b1.heightMm);
                await drawSingleChartCard(doc, b2, margin + colW + 4, y, colW, b2.heightMm);

                y += rowH + 4;
                i += 2;
            } else {
                const rowH = b1.heightMm + 12;
                ensureSpace(rowH);

                await drawSingleChartCard(doc, b1, margin, y, contentW, b1.heightMm);
                y += rowH + 4;
                i += 1;
            }
        }

        // -----------------------------------------------------------------
        // 4. DIBUJAR TABLA CONSOLIDADA DE LA PESTAÑA ACTIVA
        // -----------------------------------------------------------------
        if (mainTab === 'cycle') {
            drawDomTableInPdf(doc, '#monthlyTableHead', '#monthlyTableBody', document.getElementById('monthlyTableTitle').textContent.trim(), margin, contentW, pageH, () => y, (newY) => { y = newY; });
        } else if (mainTab === 'spend') {
            drawDomTableInPdf(doc, '#viewSpendSection table:last-of-type thead', '#topSuppliersTableBody', 'Ranking Detallado de Proveedores (Top 15 del Periodo)', margin, contentW, pageH, () => y, (newY) => { y = newY; });
        } else {
            drawDomTableInPdf(doc, '#viewSupplierSection table:first-of-type thead', '#sup360ProductsTableBody', 'Detalle de Productos y Precios Comprados al Proveedor', margin, contentW, pageH, () => y, (newY) => { y = newY; });
        }

        // Pie de página con numeración en todas las hojas
        const totalPages = doc.internal.getNumberOfPages();
        for (let p = 1; p <= totalPages; p++) {
            doc.setPage(p);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7);
            doc.setTextColor(148, 163, 184);
            doc.text('Grupo Cresta · Supply Chain · Documento Confidencial', margin, pageH - 4);
            doc.text(`Página ${p} de ${totalPages}`, pageW - margin, pageH - 4, { align: 'right' });
        }

        doc.save(`${filePrefix}_cresta_${RAW_DATA.cutoff_date}.pdf`);
    } catch (err) {
        alert('Error generando el PDF: ' + err.message);
    } finally {
        if (wasDark) {
            isDark = true;
            applyThemeUI();
            renderDashboard();
        }
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

async function drawSingleChartCard(doc, block, x, y, width, imgHeight) {
    const totalCardH = imgHeight + 12;
    doc.setFillColor(255, 255, 255);
    doc.setDrawColor(226, 232, 240);
    doc.roundedRect(x, y, width, totalCardH, 2, 2, 'FD');

    doc.setTextColor(27, 42, 74);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.5);
    doc.text(doc.splitTextToSize(block.title, width - 6)[0], x + 3, y + 5.2);

    doc.setTextColor(100, 116, 139);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6.8);
    doc.text(doc.splitTextToSize(block.sub || '', width - 6)[0], x + 3, y + 9.2);

    if (block.instance && typeof block.instance.dataURI === 'function') {
        try {
            const uriRes = await block.instance.dataURI({ scale: 2 });
            if (uriRes && uriRes.imgURI) {
                doc.addImage(uriRes.imgURI, 'PNG', x + 2, y + 10.5, width - 4, imgHeight);
            }
        } catch (e) {
            doc.setTextColor(148, 163, 184);
            doc.text('(Gráfica sin datos en este filtro)', x + 4, y + 25);
        }
    }
}

function drawDomTableInPdf(doc, headSelector, bodySelector, tableTitle, margin, contentW, pageH, getY, setY) {
    const headEl = document.querySelector(headSelector);
    const bodyEl = document.querySelector(bodySelector);
    if (!headEl || !bodyEl) return;

    const headers = [...headEl.querySelectorAll('th')].map(th => th.textContent.trim());
    const rows = [...bodyEl.querySelectorAll('tr')]
        .map(tr => [...tr.querySelectorAll('td')].map(td => td.textContent.trim()))
        .filter(r => r.length === headers.length);

    if (!headers.length || !rows.length) return;

    let y = getY();
    if (y + 28 > pageH - margin) {
        doc.addPage();
        y = margin;
    }

    doc.setTextColor(27, 42, 74);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9.5);
    doc.text(tableTitle, margin, y + 4);
    y += 7;

    const colCount = headers.length;
    const firstColW = Math.min(62, contentW * 0.34);
    const otherColW = (contentW - firstColW) / Math.max(1, colCount - 1);

    const drawHeaderRow = (currY) => {
        doc.setFillColor(27, 42, 74);
        doc.rect(margin, currY, contentW, 6.5, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(6.2);

        headers.forEach((h, idx) => {
            const cx = idx === 0 ? margin + 2 : margin + firstColW + (idx - 1) * otherColW + otherColW - 1.5;
            const align = idx === 0 ? 'left' : 'right';
            const maxW = idx === 0 ? firstColW - 3 : otherColW - 2;
            doc.text(doc.splitTextToSize(h, maxW)[0], cx, currY + 4.4, { align });
        });
        return currY + 6.5;
    };

    y = drawHeaderRow(y);

    rows.slice(0, 35).forEach((row, rIdx) => {
        if (y + 5.8 > pageH - margin - 4) {
            doc.addPage();
            y = margin;
            y = drawHeaderRow(y);
        }

        if (rIdx % 2 === 1) {
            doc.setFillColor(248, 250, 252);
            doc.rect(margin, y, contentW, 5.6, 'F');
        }

        doc.setDrawColor(226, 232, 240);
        doc.line(margin, y + 5.6, margin + contentW, y + 5.6);

        doc.setTextColor(30, 41, 59);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(6.2);

        row.forEach((cell, idx) => {
            const cx = idx === 0 ? margin + 2 : margin + firstColW + (idx - 1) * otherColW + otherColW - 1.5;
            const align = idx === 0 ? 'left' : 'right';
            const maxW = idx === 0 ? firstColW - 3 : otherColW - 2;
            doc.text(doc.splitTextToSize(cell, maxW)[0], cx, y + 3.9, { align });
        });

        y += 5.6;
    });

    setY(y + 4);
}
