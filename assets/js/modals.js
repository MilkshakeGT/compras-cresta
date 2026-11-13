/* =========================================================================
 * MODALES DE AUDITORÍA Y DETALLE DE DOCUMENTOS (assets/js/modals.js)
 * ========================================================================= */

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

function renderSolLink(solId) {
    const clean = String(solId ?? '').trim();
    if (!clean) return '—';
    return `<button type="button" onclick="event.stopPropagation(); openDocumentDetail('sol', '${escapeHtml(clean)}')"
        title="Ver detalle de productos solicitados en ${escapeHtml(clean)}"
        class="font-bold text-cresta-navy dark:text-indigo-300 underline decoration-dotted hover:decoration-solid hover:text-cresta-red dark:hover:text-white transition cursor-pointer">
        ${escapeHtml(clean)}
    </button>`;
}

function renderOcLinks(ocListStr) {
    const raw = String(ocListStr ?? '').trim();
    if (!raw) return '—';
    const parts = raw.split(',').map(s => s.trim()).filter(Boolean);
    return parts.map(code => `
        <button type="button" onclick="event.stopPropagation(); openDocumentDetail('oc', '${escapeHtml(code)}')"
            title="Ver líneas, precios y proveedor de ${escapeHtml(code)}"
            class="inline-block font-semibold text-cresta-blue dark:text-sky-400 underline decoration-dotted hover:decoration-solid hover:text-cresta-red dark:hover:text-white transition cursor-pointer">
            ${escapeHtml(code)}
        </button>
    `).join(', ');
}

function handleQuickDocLookup(e) {
    e.preventDefault();
    const val = document.getElementById('quickDocInput').value.trim().toUpperCase();
    if (!val) return;
    const type = /^P\d+/i.test(val) ? 'oc' : 'sol';
    openDocumentDetail(type, val);
}

// =========================================================================
// MODAL 1: AUDITORÍA DE LISTADOS
// =========================================================================
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
            <th class="py-2.5 px-3">Solicitante</th>
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
            <th class="py-2.5 px-3">Solicitante</th>
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
                const hay = `${r.oc_odoo} ${r.oc_sap} ${r.solicitud} ${r.sociedad} ${r.solicitado_por} ${r.proveedor} ${r.comprador} ${r.estado_oc}`.toLowerCase();
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
            const hay = `${r.id} ${r.sociedad} ${r.solicitado_por} ${r.comprador} ${r.oc_odoo} ${r.estado_oc} ${r.estado_sol}`.toLowerCase();
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
        return `<span class="px-2 py-0.5 rounded bg-slate-100 text-cresta-navy border border-slate-300 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800">${escapeHtml(r.estado_oc || 'OC Odoo (Sin SAP)')}</span>`;
    }
    if (!r.aprobada) {
        return `<span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">${escapeHtml(r.estado_sol || 'Sin aprobar')}</span>`;
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
            tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-slate-400 dark:text-gray-400">No hay órdenes de compra con este filtro.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.slice(0, 300).map(r => {
            const sapBadge = r.en_sap
                ? `<span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800">SAP ${escapeHtml(r.oc_sap)}</span>`
                : `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800">${escapeHtml(r.estado_oc)} (Sin SAP)</span>`;

            return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50">
                <td class="py-2 px-3">${renderOcLinks(r.oc_odoo)} <span class="text-[10px] text-slate-400">(${renderSolLink(r.solicitud)})</span></td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.fecha)}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.sociedad)}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(shortName(r.solicitado_por || ''))}</td>
                <td class="py-2 px-3 font-medium text-slate-800 dark:text-gray-200">${escapeHtml(r.proveedor)}</td>
                <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(shortName(r.comprador))}</td>
                <td class="py-2 px-3">${sapBadge}</td>
                <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${formatMoney(r.monto, r.divisa)}</td>
            </tr>`;
        }).join('');
        return;
    }

    document.getElementById('modalSubtitle').textContent = `${rows.length.toLocaleString()} solicitudes encontradas`;

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-8 text-center text-slate-400 dark:text-gray-400">No hay solicitudes con este filtro.</td></tr>`;
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
            <td class="py-2 px-3">${renderSolLink(r.id)}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.fecha)}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.sociedad)}</td>
            <td class="py-2 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(shortName(r.solicitado_por || ''))}</td>
            <td class="py-2 px-3 text-slate-700 dark:text-gray-300">${escapeHtml(shortName(r.comprador))}</td>
            <td class="py-2 px-3">${renderOcLinks(r.oc_odoo)}</td>
            <td class="py-2 px-3">${getRowStatusBadge(r)}</td>
            <td class="py-2 px-3 text-right ${diasCls}">${diasTxt}</td>
        </tr>`;
    }).join('');
}

function exportAuditCSV() {
    const cfg = MODAL_CONFIG[activeModalCategory];
    const rows = getFilteredModalRows();

    if (cfg.mode === 'spend') {
        const headers = ['OC Odoo', 'OC SAP', 'Solicitud', 'Fecha OC', 'Sociedad', 'Solicitante', 'Proveedor', 'Comprador', 'Estado OC', 'Divisa', 'Total OC'];
        const csvLines = [headers.join(',')];

        rows.forEach(r => {
            const line = [
                `"${r.oc_odoo}"`,
                `"${r.oc_sap || ''}"`,
                `"${r.solicitud}"`,
                `"${r.fecha}"`,
                `"${r.sociedad}"`,
                `"${(r.solicitado_por || '').replace(/"/g, '""')}"`,
                `"${r.proveedor.replace(/"/g, '""')}"`,
                `"${r.comprador}"`,
                `"${r.estado_oc}"`,
                `"${r.divisa}"`,
                r.monto.toFixed(2)
            ];
            csvLines.push(line.join(','));
        });

        downloadCsvBlob(csvLines.join('\n'), `auditoria_${activeModalCategory.toLowerCase()}_${activeCurrency}_${RAW_DATA.cutoff_date}.csv`);
        return;
    }

    function exportAuditExcel() {
    const cfg = MODAL_CONFIG[activeModalCategory];
    const rows = getFilteredModalRows();

    if (cfg.mode === 'spend') {
        const headers = ['OC Odoo', 'OC SAP', 'Solicitud', 'Fecha OC', 'Sociedad', 'Solicitante', 'Proveedor', 'Comprador', 'Estado OC', 'Divisa', 'Total OC'];
        const dataRows = rows.map(r => [
            r.oc_odoo,
            r.oc_sap || '',
            r.solicitud,
            r.fecha,
            r.sociedad,
            r.solicitado_por || '',
            r.proveedor,
            r.comprador,
            r.estado_oc,
            r.divisa,
            Number(r.monto.toFixed(2))
        ]);

        exportToExcel(
            headers,
            dataRows,
            `auditoria_${activeModalCategory.toLowerCase()}_${activeCurrency}_${RAW_DATA.cutoff_date}.xlsx`,
            'Ordenes de Compra'
        );
        return;
    }

    const headers = ['Solicitud', 'Fecha Creacion', 'Sociedad', 'Solicitante', 'Comprador', 'OC Odoo', 'Estatus', cfg.daysLabel];
    const dataRows = rows.map(r => {
        const statusTxt = r.tiene_oc_sap
            ? 'En SAP'
            : (r.tiene_oc_odoo ? (r.estado_oc || 'OC Odoo sin SAP') : (!r.aprobada ? (r.estado_sol || 'Sin aprobar') : 'Sin OC en Odoo'));
        const rawDays = r[cfg.daysField];
        const daysVal = rawDays !== null && rawDays !== undefined ? Number(rawDays.toFixed(1)) : '';

        return [
            r.id,
            r.fecha,
            r.sociedad,
            r.solicitado_por || '',
            r.comprador,
            r.oc_odoo || '',
            statusTxt,
            daysVal
        ];
    });

    exportToExcel(
        headers,
        dataRows,
        `auditoria_${activeModalCategory.toLowerCase()}_cresta_${RAW_DATA.cutoff_date}.xlsx`,
        'Auditoria Solicitudes'
    );
}

function exportDocumentDetailExcel() {
    if (!activeDocDetail) return;

    if (activeDocDetail.type === 'sol') {
        const m = activeDocDetail.meta || {};
        const lines = activeDocDetail.lines || [];
        const headers = ['Solicitud', 'Fecha', 'Sociedad', 'Solicitante', 'Comprador', 'OC Odoo', 'Linea', 'Producto Solicitado', 'Cantidad Solicitada'];
        const dataRows = lines.map((l, idx) => [
            activeDocDetail.id,
            m.fecha || '',
            m.sociedad || '',
            m.solicitado_por || '',
            m.comprador || '',
            m.oc_odoo || '',
            idx + 1,
            String(l.producto).replace(/\n/g, ' '),
            Number(l.cantidad)
        ]);

        exportToExcel(
            headers,
            dataRows,
            `detalle_solicitud_${activeDocDetail.id}.xlsx`,
            `Solicitud ${activeDocDetail.id}`
        );
        return;
    }

    if (activeDocDetail.type === 'oc') {
        const m = activeDocDetail.meta || {};
        const d = activeDocDetail.detail || {};
        const lines = d.lineas || [];
        const prov = d.proveedor || m.proveedor || '';
        const divisa = d.divisa || m.divisa || 'GTQ';

        const headers = ['No. OC', 'OC SAP', 'Solicitud', 'Fecha OC', 'Sociedad', 'Proveedor', 'Comprador', 'Solicitante', 'Linea', 'Producto', 'Cantidad Comprada', 'Divisa', 'Precio Unitario', 'Total Linea'];
        const dataRows = lines.map((l, idx) => [
            activeDocDetail.id,
            m.oc_sap || '',
            m.solicitud || '',
            m.fecha || '',
            m.sociedad || '',
            prov,
            m.comprador || '',
            m.solicitado_por || '',
            idx + 1,
            String(l.producto).replace(/\n/g, ' '),
            Number(l.cantidad),
            divisa,
            Number(l.precio_unitario),
            Number(l.total)
        ]);

        exportToExcel(
            headers,
            dataRows,
            `detalle_oc_${activeDocDetail.id}.xlsx`,
            `OC ${activeDocDetail.id}`
        );
    }
}

    const headers = ['Solicitud', 'Fecha Creacion', 'Sociedad', 'Solicitante', 'Comprador', 'OC Odoo', 'Estatus', cfg.daysLabel];
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
            `"${(r.solicitado_por || '').replace(/"/g, '""')}"`,
            `"${r.comprador}"`,
            `"${r.oc_odoo || ''}"`,
            `"${statusTxt}"`,
            daysVal
        ];
        csvLines.push(line.join(','));
    });

    downloadCsvBlob(csvLines.join('\n'), `auditoria_${activeModalCategory.toLowerCase()}_cresta_${RAW_DATA.cutoff_date}.csv`);
}

// =========================================================================
// MODAL 2: FICHA DETALLADA DE SOLICITUD U ORDEN DE COMPRA
// =========================================================================
async function openDocumentDetail(type, id) {
    const modal = document.getElementById('docDetailModal');
    const titleEl = document.getElementById('docModalTitle');
    const badgeEl = document.getElementById('docModalBadge');
    const subBadgeEl = document.getElementById('docModalSubBadge');
    const metaBox = document.getElementById('docModalMetaBox');
    const thead = document.getElementById('docModalTableHead');
    const tbody = document.getElementById('docModalTableBody');
    const tfoot = document.getElementById('docModalTableFoot');

    activeDocDetail = null;
    badgeEl.textContent = type === 'oc' ? 'DETALLE DE ORDEN DE COMPRA' : 'DETALLE DE SOLICITUD';
    subBadgeEl.textContent = 'Consultando...';
    titleEl.textContent = id;
    metaBox.innerHTML = `<div class="col-span-4 text-slate-400">Cargando información del documento...</div>`;
    thead.innerHTML = '';
    tbody.innerHTML = '';
    tfoot.innerHTML = '';
    modal.classList.remove('hidden');

    try {
        const resp = await fetch(`?action=detail&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
        const data = await resp.json();

        if (!data.found) {
            subBadgeEl.textContent = 'No encontrado';
            metaBox.innerHTML = `<div class="col-span-4 text-cresta-red font-semibold">No se encontraron líneas de detalle para "${escapeHtml(id)}" en los archivos sincronizados.</div>`;
            return;
        }

        activeDocDetail = data;

        if (type === 'sol') {
            const m = data.meta || {};
            const lines = data.lines || [];
            subBadgeEl.textContent = `${lines.length} línea(s) solicitada(s)`;
            titleEl.textContent = `Solicitud ${data.id}`;

            metaBox.innerHTML = `
                <div><span class="text-slate-400 block text-[10px] uppercase">Fecha Creación</span><strong class="text-slate-800 dark:text-gray-200">${escapeHtml(m.fecha || '—')}</strong></div>
                <div><span class="text-slate-400 block text-[10px] uppercase">Sociedad</span><strong class="text-slate-800 dark:text-gray-200">${escapeHtml(m.sociedad || '—')}</strong></div>
                <div><span class="text-slate-400 block text-[10px] uppercase">Solicitado por</span><strong class="text-slate-800 dark:text-gray-200">${escapeHtml(m.solicitado_por || '—')}</strong></div>
                <div><span class="text-slate-400 block text-[10px] uppercase">Comprador / OC</span><strong class="text-slate-800 dark:text-gray-200">${escapeHtml(shortName(m.comprador || '—'))}</strong><div class="mt-0.5">${renderOcLinks(m.oc_odoo || '')}</div></div>
            `;

            thead.innerHTML = `<tr>
                <th class="py-2.5 px-3 w-12">#</th>
                <th class="py-2.5 px-3">Producto / Servicio Solicitado</th>
                <th class="py-2.5 px-3 text-right w-36">Cantidad Solicitada</th>
            </tr>`;

            if (!lines.length) {
                tbody.innerHTML = `<tr><td colspan="3" class="py-6 text-center text-slate-400">Esta solicitud no tiene líneas detalladas en Solicitudes.xlsx</td></tr>`;
                tfoot.innerHTML = '';
            } else {
                const totalQty = lines.reduce((s, l) => s + (Number(l.cantidad) || 0), 0);
                tbody.innerHTML = lines.map((l, idx) => `
                    <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
                        <td class="py-2 px-3 text-slate-400 font-bold">${idx + 1}</td>
                        <td class="py-2 px-3 text-slate-800 dark:text-gray-200 whitespace-pre-line">${escapeHtml(l.producto)}</td>
                        <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${Number(l.cantidad).toLocaleString('en-US', { maximumFractionDigits: 4 })}</td>
                    </tr>
                `).join('');

                tfoot.innerHTML = `<tr>
                    <td colspan="2" class="py-2.5 px-3 text-right text-slate-600 dark:text-gray-300">Total Unidades Solicitadas:</td>
                    <td class="py-2.5 px-3 text-right text-cresta-navy dark:text-white">${totalQty.toLocaleString('en-US', { maximumFractionDigits: 4 })}</td>
                </tr>`;
            }
        } else if (type === 'oc') {
            const m = data.meta || {};
            const d = data.detail || {};
            const lines = d.lineas || [];
            const prov = d.proveedor || m.proveedor || 'Sin proveedor';
            const divisa = d.divisa || m.divisa || 'GTQ';

            subBadgeEl.textContent = `${lines.length} línea(s) · Divisa ${divisa}`;
            titleEl.textContent = `Orden de Compra ${data.id}`;

            metaBox.innerHTML = `
                <div class="col-span-2"><span class="text-slate-400 block text-[10px] uppercase">Proveedor</span><strong class="text-cresta-navy dark:text-white">${escapeHtml(prov)}</strong></div>
                <div><span class="text-slate-400 block text-[10px] uppercase">Sociedad / Fecha</span><strong class="text-slate-800 dark:text-gray-200">${escapeHtml(m.sociedad || '—')}</strong><span class="block text-[10px] text-slate-400">${escapeHtml(m.fecha || '')}</span></div>
                <div><span class="text-slate-400 block text-[10px] uppercase">Solicitud / SAP</span><div>${renderSolLink(m.solicitud || '')}</div><span class="block text-[10px] text-emerald-600 font-semibold">${m.oc_sap ? 'SAP ' + escapeHtml(m.oc_sap) : (m.estado_oc || '')}</span></div>
            `;

            thead.innerHTML = `<tr>
                <th class="py-2.5 px-3 w-10">#</th>
                <th class="py-2.5 px-3">Producto / Descripción</th>
                <th class="py-2.5 px-3 text-right">Cantidad</th>
                <th class="py-2.5 px-3 text-right">Precio Unit. (${divisa})</th>
                <th class="py-2.5 px-3 text-right">Subtotal (${divisa})</th>
            </tr>`;

            if (!lines.length) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-slate-400">Esta OC no tiene líneas detalladas en OC.xlsx</td></tr>`;
                tfoot.innerHTML = '';
            } else {
                const totalMonto = lines.reduce((s, l) => s + (Number(l.total) || 0), 0);
                tbody.innerHTML = lines.map((l, idx) => `
                    <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
                        <td class="py-2 px-3 text-slate-400 font-bold">${idx + 1}</td>
                        <td class="py-2 px-3 text-slate-800 dark:text-gray-200 whitespace-pre-line">${escapeHtml(l.producto)}</td>
                        <td class="py-2 px-3 text-right text-slate-700 dark:text-gray-300">${Number(l.cantidad).toLocaleString('en-US', { maximumFractionDigits: 4 })}</td>
                        <td class="py-2 px-3 text-right text-slate-600 dark:text-gray-400">${formatMoney(l.precio_unitario, divisa)}</td>
                        <td class="py-2 px-3 text-right font-bold text-cresta-navy dark:text-sky-400">${formatMoney(l.total, divisa)}</td>
                    </tr>
                `).join('');

                tfoot.innerHTML = `<tr>
                    <td colspan="4" class="py-2.5 px-3 text-right text-slate-600 dark:text-gray-300">Total Orden de Compra (${divisa}):</td>
                    <td class="py-2.5 px-3 text-right text-cresta-red dark:text-emerald-400 text-sm">${formatMoney(totalMonto, divisa)}</td>
                </tr>`;
            }
        }
    } catch (err) {
        subBadgeEl.textContent = 'Error';
        metaBox.innerHTML = `<div class="col-span-4 text-cresta-red font-semibold">Error consultando detalle: ${escapeHtml(err.message)}</div>`;
    }
}

function closeDocDetailModal() {
    document.getElementById('docDetailModal').classList.add('hidden');
}

function exportDocumentDetailCSV() {
    if (!activeDocDetail) return;

    if (activeDocDetail.type === 'sol') {
        const m = activeDocDetail.meta || {};
        const lines = activeDocDetail.lines || [];
        const headers = ['Solicitud', 'Fecha', 'Sociedad', 'Solicitante', 'Comprador', 'OC Odoo', 'Linea', 'Producto Solicitado', 'Cantidad Solicitada'];
        const csvLines = [headers.join(',')];

        lines.forEach((l, idx) => {
            csvLines.push([
                `"${activeDocDetail.id}"`,
                `"${m.fecha || ''}"`,
                `"${m.sociedad || ''}"`,
                `"${(m.solicitado_por || '').replace(/"/g, '""')}"`,
                `"${(m.comprador || '').replace(/"/g, '""')}"`,
                `"${m.oc_odoo || ''}"`,
                idx + 1,
                `"${String(l.producto).replace(/"/g, '""').replace(/\n/g, ' ')}"`,
                l.cantidad
            ].join(','));
        });

        downloadCsvBlob(csvLines.join('\n'), `detalle_solicitud_${activeDocDetail.id}.csv`);
        return;
    }

    if (activeDocDetail.type === 'oc') {
        const m = activeDocDetail.meta || {};
        const d = activeDocDetail.detail || {};
        const lines = d.lineas || [];
        const prov = d.proveedor || m.proveedor || '';
        const divisa = d.divisa || m.divisa || 'GTQ';

        const headers = ['No. OC', 'OC SAP', 'Solicitud', 'Fecha OC', 'Sociedad', 'Proveedor', 'Comprador', 'Solicitante', 'Linea', 'Producto', 'Cantidad Comprada', 'Divisa', 'Precio Unitario', 'Total Linea'];
        const csvLines = [headers.join(',')];

        lines.forEach((l, idx) => {
            csvLines.push([
                `"${activeDocDetail.id}"`,
                `"${m.oc_sap || ''}"`,
                `"${m.solicitud || ''}"`,
                `"${m.fecha || ''}"`,
                `"${m.sociedad || ''}"`,
                `"${prov.replace(/"/g, '""')}"`,
                `"${(m.comprador || '').replace(/"/g, '""')}"`,
                `"${(m.solicitado_por || '').replace(/"/g, '""')}"`,
                idx + 1,
                `"${String(l.producto).replace(/"/g, '""').replace(/\n/g, ' ')}"`,
                l.cantidad,
                `"${divisa}"`,
                l.precio_unitario,
                l.total
            ].join(','));
        });

        downloadCsvBlob(csvLines.join('\n'), `detalle_oc_${activeDocDetail.id}.csv`);
    }
}
