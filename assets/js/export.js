/* =========================================================================
 * MOTOR CENTRAL DE EXPORTACIÓN A EXCEL (.XLSX) Y PDF VERTICAL (export.js)
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
        { label: 'Sociedad', value: soc === 'ALL' ? 'Todas las Sociedades' : soc },
        { label: 'Comprador', value: comp === 'ALL' ? 'Todos los Compradores' : comp },
        { label: 'Solicitante', value: sol === 'ALL' ? 'Todos los Solicitantes' : sol }
    ];

    if (mainTab === 'spend') {
        const prov = document.getElementById('filterProveedor').value;
        const hidePetty = document.getElementById('chkExcludePettyCash').checked;
        items.push({ label: 'Proveedor', value: prov === 'ALL' ? 'Todos los Proveedores' : prov });
        items.push({ label: 'Divisa', value: activeCurrency });
        if (hidePetty) items.push({ label: 'Filtro', value: 'Sin Caja Chica' });
    } else if (mainTab === 'supplier') {
        const prov360 = document.getElementById('sup360Selector').value;
        const hidePetty = document.getElementById('chkExcludePettyCash').checked;
        items.push({ label: 'Proveedor Auditado', value: prov360 === 'ALL' ? 'Todos los Proveedores' : prov360 });
        items.push({ label: 'Divisa', value: activeCurrency });
        if (hidePetty) items.push({ label: 'Filtro', value: 'Sin Caja Chica' });
    }

    return items;
}

async function exportActiveTabPDF() {
    if (typeof html2pdf === 'undefined') {
        alert('La librería de PDF aún se está cargando. Intenta de nuevo en un segundo.');
        return;
    }

    const btn = document.getElementById('btnExportTabPDF');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span><span>Generando PDF...</span>';

    let targetId = 'viewCycleSection';
    let reportTitle = 'Informe Ejecutivo · Ciclo Operativo de Compras (SLA)';
    let filePrefix = 'ciclo_operativo';

    if (mainTab === 'spend') {
        targetId = 'viewSpendSection';
        reportTitle = `Informe Ejecutivo · Gestión de Gasto y Productos (${activeCurrency})`;
        filePrefix = `gasto_productos_${activeCurrency}`;
    } else if (mainTab === 'supplier') {
        targetId = 'viewSupplierSection';
        const provName = document.getElementById('sup360Selector').value;
        reportTitle = provName === 'ALL'
            ? `Portal 360° Proveedores · Consolidado (${activeCurrency})`
            : `Portal 360° Proveedor · ${provName} (${activeCurrency})`;
        filePrefix = `portal360_proveedor_${activeCurrency}`;
    }

    const sourceEl = document.getElementById(targetId);
    const wasDark = isDark;
    let printHost = null;

    try {
        // 1. Forzar modo claro temporalmente para colores limpios en hoja blanca
        if (wasDark) {
            isDark = false;
            applyThemeUI();
            renderDashboard();
            await new Promise(r => setTimeout(r, 400));
        }

        // 2. Ancho exacto Carta Vertical (780px equivale a 8.5in con márgenes de 8mm)
        const PDF_WIDTH_PX = 780;

        // Contenedor fijo en (0,0) fuera de vista del usuario para evitar cualquier recorte de scrollX
        printHost = document.createElement('div');
        printHost.style.position = 'fixed';
        printHost.style.left = '0';
        printHost.style.top = '0';
        printHost.style.width = `${PDF_WIDTH_PX}px`;
        printHost.style.backgroundColor = '#FFFFFF';
        printHost.style.zIndex = '-9999';
        printHost.style.pointerEvents = 'none';

        const wrapper = document.createElement('div');
        wrapper.style.width = `${PDF_WIDTH_PX}px`;
        wrapper.style.boxSizing = 'border-box';
        wrapper.style.backgroundColor = '#FFFFFF';
        wrapper.style.color = '#0F172A';
        wrapper.style.padding = '12px';
        wrapper.style.fontFamily = 'Verdana, Geneva, sans-serif';

        // Encabezado ejecutivo corporativo
        const filtersHtml = getActiveFiltersSummaryList()
            .map(f => `<span style="display:inline-block; background:#F1F5F9; border:1px solid #CBD5E1; border-radius:4px; padding:3px 7px; margin:2px 4px 2px 0; font-size:9px; color:#1E293B;"><strong>${escapeHtml(f.label)}:</strong> ${escapeHtml(f.value)}</span>`)
            .join('');

        const headerDiv = document.createElement('div');
        headerDiv.style.borderBottom = '2px solid #1B2A4A';
        headerDiv.style.paddingBottom = '10px';
        headerDiv.style.marginBottom = '14px';
        headerDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                <div>
                    <span style="background:#1B2A4A; color:#FFFFFF; font-size:9px; font-weight:bold; padding:3px 8px; border-radius:4px; letter-spacing:0.5px;">GRUPO CRESTA · SUPPLY CHAIN</span>
                    <h1 style="font-size:15px; font-weight:bold; color:#1B2A4A; margin:6px 0 2px 0;">${escapeHtml(reportTitle)}</h1>
                </div>
                <div style="text-align:right; font-size:9px; color:#475569; flex-shrink:0;">
                    <div><strong>Corte de datos:</strong> ${escapeHtml(RAW_DATA.cutoff_date)}</div>
                    <div><strong>Generado:</strong> ${new Date().toLocaleString('es-GT')}</div>
                </div>
            </div>
            <div style="margin-top:6px;">${filtersHtml}</div>
        `;
        wrapper.appendChild(headerDiv);

        // 3. Clonar la vista activa y adaptar su layout a Carta Vertical (1 columna por gráfica)
        const clone = sourceEl.cloneNode(true);
        clone.classList.remove('hidden');
        clone.style.width = '100%';
        clone.style.maxWidth = '100%';
        clone.style.padding = '0';
        clone.style.margin = '0';

        // Ocultar elementos interactivos que no aportan en un informe impreso
        clone.querySelectorAll('button, input, select, label').forEach(el => {
            if (!el.closest('.apexcharts-canvas')) {
                el.style.display = 'none';
            }
        });
        clone.querySelectorAll('span').forEach(sp => {
            if (sp.textContent && sp.textContent.includes('Auditar →')) {
                sp.style.display = 'none';
            }
        });

        // Ajustar la grilla de 5 KPIs para que quepa holgada en 756px (3 arriba + 2 abajo)
        const firstSection = clone.querySelector('section');
        if (firstSection && firstSection.classList.contains('grid')) {
            firstSection.style.display = 'grid';
            firstSection.style.gridTemplateColumns = 'repeat(3, minmax(0, 1fr))';
            firstSection.style.gap = '10px';
            firstSection.querySelectorAll(':scope > div').forEach(card => {
                card.style.padding = '10px';
            });
        }

        // Convertir todas las grillas de gráficas de 12 columnas a 1 columna vertical a ancho completo
        clone.querySelectorAll('section.grid').forEach((sec, idx) => {
            if (idx === 0) return; // Ya ajustamos la fila de KPIs arriba
            sec.style.display = 'flex';
            sec.style.flexDirection = 'column';
            sec.style.gap = '14px';
        });

        // Ajustar los contenedores de gráficas ApexCharts para que entren limpios en 756px
        clone.querySelectorAll('.apexcharts-canvas, .apexcharts-svg').forEach(svgEl => {
            svgEl.style.maxWidth = '100%';
        });

        // Compactar tablas para que quepan todas las columnas mensuales en Carta Vertical sin desbordar
        clone.querySelectorAll('table').forEach(tbl => {
            tbl.style.width = '100%';
            tbl.style.fontSize = '8.5px';
            tbl.style.tableLayout = 'auto';
            tbl.querySelectorAll('th, td').forEach(cell => {
                cell.style.padding = '4px 5px';
                cell.style.whiteSpace = 'nowrap';
            });
        });

        // Evitar saltos de página dentro de tarjetas o gráficas individuales
        clone.querySelectorAll('section, section > div').forEach(block => {
            block.style.pageBreakInside = 'avoid';
            block.style.breakInside = 'avoid';
        });

        wrapper.appendChild(clone);
        printHost.appendChild(wrapper);
        document.body.appendChild(printHost);

        // Esperar un frame para que el navegador calcule el layout del clon
        await new Promise(r => requestAnimationFrame(() => setTimeout(r, 150)));

        const opt = {
            margin: [8, 8, 8, 8],
            filename: `${filePrefix}_cresta_${RAW_DATA.cutoff_date}.pdf`,
            image: { type: 'jpeg', quality: 0.96 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                logging: false,
                scrollX: 0,
                scrollY: 0,
                x: 0,
                y: 0,
                width: PDF_WIDTH_PX,
                windowWidth: PDF_WIDTH_PX
            },
            jsPDF: {
                unit: 'mm',
                format: 'letter',
                orientation: 'portrait'
            },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };

        await html2pdf().set(opt).from(wrapper).save();
    } catch (err) {
        alert('Ocurrió un error al generar el PDF: ' + err.message);
    } finally {
        if (printHost && printHost.parentNode) {
            document.body.removeChild(printHost);
        }
        if (wasDark) {
            isDark = true;
            applyThemeUI();
            renderDashboard();
        }
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}
