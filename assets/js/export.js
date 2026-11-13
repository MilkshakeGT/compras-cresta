/* =========================================================================
 * MOTOR CENTRAL DE EXPORTACIÓN A EXCEL (.XLSX) Y PDF GRÁFICO (export.js)
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

    // Autoajustar el ancho de cada columna según su contenido
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

    try {
        // Si está en modo oscuro, cambiar temporalmente a claro para impresión nítida en papel blanco
        if (wasDark) {
            isDark = false;
            applyThemeUI();
            renderDashboard();
            await new Promise(r => setTimeout(r, 450));
        }

        // Crear contenedor temporal con encabezado corporativo y resumen de filtros
        const wrapper = document.createElement('div');
        wrapper.style.width = '1220px';
        wrapper.style.backgroundColor = '#FFFFFF';
        wrapper.style.color = '#0F172A';
        wrapper.style.padding = '24px';
        wrapper.style.fontFamily = 'Verdana, Geneva, sans-serif';

        const filtersHtml = getActiveFiltersSummaryList()
            .map(f => `<span style="display:inline-block; background:#F1F5F9; border:1px solid #CBD5E1; border-radius:6px; padding:4px 8px; margin:2px 4px 2px 0; font-size:10px;"><strong>${escapeHtml(f.label)}:</strong> ${escapeHtml(f.value)}</span>`)
            .join('');

        const headerDiv = document.createElement('div');
        headerDiv.style.borderBottom = '2px solid #1B2A4A';
        headerDiv.style.paddingBottom = '12px';
        headerDiv.style.marginBottom = '16px';
        headerDiv.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="background:#1B2A4A; color:#FFFFFF; font-size:10px; font-weight:bold; padding:3px 8px; border-radius:4px; letter-spacing:0.5px;">GRUPO CRESTA · SUPPLY CHAIN</span>
                    <h1 style="font-size:18px; font-weight:bold; color:#1B2A4A; margin:6px 0 2px 0;">${escapeHtml(reportTitle)}</h1>
                </div>
                <div style="text-align:right; font-size:10px; color:#475569;">
                    <div><strong>Corte de datos:</strong> ${escapeHtml(RAW_DATA.cutoff_date)}</div>
                    <div><strong>Generado:</strong> ${new Date().toLocaleString('es-GT')}</div>
                </div>
            </div>
            <div style="margin-top:8px;">${filtersHtml}</div>
        `;
        wrapper.appendChild(headerDiv);

        // Clonar el contenido visual de la pestaña activa
        const clone = sourceEl.cloneNode(true);
        clone.classList.remove('hidden');
        clone.style.maxWidth = '100%';
        clone.style.padding = '0';

        // Ocultar botones interactivos ("Auditar ->", "Exportar a Excel", inputs de búsqueda) dentro del PDF
        clone.querySelectorAll('button, input').forEach(el => {
            if (!el.closest('.apexcharts-canvas')) {
                el.style.display = 'none';
            }
        });

        // Evitar que las tarjetas o gráficas se corten a la mitad entre páginas
        clone.querySelectorAll('section').forEach(sec => {
            sec.style.pageBreakInside = 'avoid';
            sec.style.breakInside = 'avoid';
            sec.style.marginBottom = '16px';
        });

        wrapper.appendChild(clone);
        document.body.appendChild(wrapper);

        const opt = {
            margin: [8, 8, 8, 8],
            filename: `${filePrefix}_cresta_${RAW_DATA.cutoff_date}.pdf`,
            image: { type: 'jpeg', quality: 0.96 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                logging: false,
                windowWidth: 1240
            },
            jsPDF: {
                unit: 'mm',
                format: 'letter',
                orientation: 'landscape'
            },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };

        await html2pdf().set(opt).from(wrapper).save();
        document.body.removeChild(wrapper);
    } catch (err) {
        alert('Ocurrió un error al generar el PDF: ' + err.message);
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
