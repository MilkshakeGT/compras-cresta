/* ==========================================================================
   MÓDULO DE MODALES DE AUDITORÍA Y DETALLE DE DOCUMENTOS (modals.js)
   ========================================================================== */

let pendingSapCacheRows = [];
let pendingSapActiveFilter = 'all';

function isValidForSapBacklog(r) {
    if (!r) return false;
    if (r.excluida || r.is_rechazada || r.is_borrador) return false;

    const st = String(r.estado_sol || '').trim().toLowerCase();
    if (st === 'rechazado' || st === 'rechazada' || st === 'rejected' || st === 'borrador' || st === 'draft') {
        return false;
    }

    const hasOcOdoo = Boolean(r.tiene_oc_odoo) || String(r.oc_odoo || '').trim() !== '';
    const hasOcSap  = Boolean(r.tiene_oc_sap) || Boolean(r.f_creacion_sap);

    // Excluir solicitudes "hecho" que no tienen OC en Odoo ni en SAP (dualidad ERP histórica)
    if ((st === 'hecho' || st === 'done') && !hasOcOdoo && !hasOcSap) {
        return false;
    }

    return Boolean(r.aprobada) && !hasOcSap;
}

function openPendingSapModal(initialFilter = 'all') {
    const modal = document.getElementById('pendingSapModal');
    if (!modal) return;

    const allFiltered = typeof getFilteredCycleRecords === 'function'
        ? getFilteredCycleRecords()
        : (RAW_DATA.records || []);

    pendingSapCacheRows = allFiltered
        .filter(isValidForSapBacklog)
        .sort((a, b) => (Number(b.d_to_sap ?? b.edad_dias ?? 0) - Number(a.d_to_sap ?? a.edad_dias ?? 0)));

    pendingSapActiveFilter = initialFilter;
    const searchEl = document.getElementById('pendingSapSearch');
    if (searchEl) searchEl.value = '';

    modal.classList.remove('hidden');
    renderPendingSapTable();
}

function closePendingSapModal() {
    const modal = document.getElementById('pendingSapModal');
    if (modal) modal.classList.add('hidden');
}

function setPendingSapSubFilter(filterKey) {
    pendingSapActiveFilter = filterKey;
    renderPendingSapTable();
}

function renderPendingSapTable() {
    const tbody   = document.getElementById('pendingSapTableBody');
    const countEl = document.getElementById('pendingSapModalCount');
    const search  = String(document.getElementById('pendingSapSearch')?.value || '').trim().toLowerCase();

    ['all', 'critical', 'no_oc', 'with_oc'].forEach(k => {
        const btn = document.getElementById('btnSapFilter_' + k);
        if (!btn) return;
        if (k === pendingSapActiveFilter) {
            btn.className = 'px-3 py-1.5 rounded-lg text-xs font-bold bg-cresta-navy text-white dark:bg-cresta-red transition';
        } else {
            btn.className = 'px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-gray-800 dark:text-gray-300 transition';
        }
    });

    const filtered = pendingSapCacheRows.filter(r => {
        const days = Number(r.d_to_sap ?? r.edad_dias ?? 0);
        const hasOc = Boolean(r.tiene_oc_odoo) || String(r.oc_odoo || '').trim() !== '';

        if (pendingSapActiveFilter === 'critical' && days <= 30) return false;
        if (pendingSapActiveFilter === 'no_oc' && hasOc) return false;
        if (pendingSapActiveFilter === 'with_oc' && !hasOc) return false;

        if (search !== '') {
            const hay = `${r.id || ''} ${r.sociedad || ''} ${r.solicitado_por || ''} ${r.comprador || ''} ${r.oc_odoo || ''} ${r.estado_oc || ''}`.toLowerCase();
            if (!hay.includes(search)) return false;
        }
        return true;
    });

    if (countEl) {
        countEl.textContent = `${filtered.length} solicitud(es) encontrada(s)`;
    }

    if (!tbody) return;
    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-6 text-center text-slate-400">No hay solicitudes pendientes de SAP bajo este filtro.</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map(r => {
        const days = Number(r.d_to_sap ?? r.edad_dias ?? 0).toFixed(1);
        const hasOc = Boolean(r.tiene_oc_odoo) || String(r.oc_odoo || '').trim() !== '';
        const ocLinks = hasOc
            ? String(r.oc_odoo).split(',').map(s => s.trim()).filter(Boolean)
                .map(oc => `<button type="button" onclick="openDocDetailModal('oc','${escapeHtml(oc)}')" class="underline font-bold text-cresta-blue dark:text-sky-400 hover:opacity-80">${escapeHtml(oc)}</button>`)
                .join(', ')
            : '<span class="text-slate-400">—</span>';

        const stageBadge = hasOc
            ? `<span class="px-2 py-0.5 rounded bg-sky-50 text-cresta-blue border border-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:border-sky-800 text-[10px] font-semibold">${escapeHtml(r.estado_oc || 'Con OC Odoo')}</span>`
            : `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800 text-[10px] font-semibold">Sin OC en Odoo</span>`;

        const dayColor = Number(days) > 30 ? 'text-cresta-red font-extrabold' : 'text-amber-600 dark:text-amber-400 font-bold';

        return `
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40 border-b border-slate-100 dark:border-gray-800">
                <td class="py-2.5 px-3 font-bold">
                    <button type="button" onclick="openDocDetailModal('sol','${escapeHtml(r.id)}')" class="underline text-cresta-navy dark:text-white hover:text-cresta-red">
                        ${escapeHtml(r.id)}
                    </button>
                </td>
                <td class="py-2.5 px-3 text-slate-600 dark:text-gray-300">${escapeHtml(r.fecha || '')}</td>
                <td class="py-2.5 px-3 text-slate-700 dark:text-gray-200">${escapeHtml(r.sociedad || '')}</td>
                <td class="py-2.5 px-3 text-slate-700 dark:text-gray-200">${escapeHtml(r.solicitado_por || '')}</td>
                <td class="py-2.5 px-3 text-slate-700 dark:text-gray-200">${escapeHtml(r.comprador || '')}</td>
                <td class="py-2.5 px-3">${ocLinks}</td>
                <td class="py-2.5 px-3">${stageBadge}</td>
                <td class="py-2.5 px-3 text-right ${dayColor}">${days} d</td>
            </tr>
        `;
    }).join('');
}

function exportPendingSapToExcel() {
    if (!pendingSapCacheRows.length || typeof XLSX === 'undefined') return;
    const rows = pendingSapCacheRows.map(r => ({
        'Solicitud': r.id,
        'Fecha Creación': r.fecha,
        'Sociedad': r.sociedad,
        'Solicitante': r.solicitado_por,
        'Comprador': r.comprador,
        'OC Odoo': r.oc_odoo || '',
        'Estatus OC': r.estado_oc || 'Sin OC en Odoo',
        'Días desde Aprobación': Number((r.d_to_sap ?? r.edad_dias ?? 0).toFixed(1)),
    }));
    const ws = XLSX.utils.json_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Rezago_Sin_SAP');
    XLSX.writeFile(wb, `Rezago_Sin_SAP_${RAW_DATA.cutoff_date || 'corte'}.xlsx`);
}

function handleQuickDocLookup(e) {
    if (e) e.preventDefault();
    const q = String(document.getElementById('quickDocInput')?.value || '').trim();
    if (!q) return;

    if (/^(SV|TM|IO|CYE|OC|SOL)/i.test(q) && !q.includes(' ') && q.length <= 12) {
        openDocDetailModal('sol', q.toUpperCase());
        return;
    }
    if (/^P\d{3,}$/i.test(q)) {
        openDocDetailModal('oc', q.toUpperCase());
        return;
    }
    openLineSearchModal(q);
}

async function openDocDetailModal(type, id) {
    const modal = document.getElementById('docDetailModal');
    const body  = document.getElementById('docDetailBody');
    const title = document.getElementById('docDetailTitle');
    if (!modal || !body) return;

    modal.classList.remove('hidden');
    if (title) title.textContent = `${type === 'oc' ? 'Orden de Compra' : 'Solicitud'} · ${id}`;
    body.innerHTML = `<p class="py-8 text-center text-slate-400">Cargando trazabilidad y líneas del documento...</p>`;

    try {
        const res = await fetch(`?action=detail&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
        const data = await res.json();

        if (!data.found) {
            body.innerHTML = `<p class="py-6 text-center text-cresta-red font-semibold">${escapeHtml(data.error || 'Documento no encontrado.')}</p>`;
            return;
        }

        if (type === 'sol') {
            const m = data.meta || {};
            const lines = data.lines || [];
            body.innerHTML = `
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200 dark:border-gray-700 text-xs">
                    <div><span class="text-slate-400 block">Estatus Solicitud</span><strong>${escapeHtml(m.estado_sol || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Sociedad</span><strong>${escapeHtml(m.sociedad || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Solicitante</span><strong>${escapeHtml(m.solicitado_por || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Comprador</span><strong>${escapeHtml(m.comprador || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Creación PR</span><strong>${escapeHtml(m.f_creacion_sol || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Aprobación PR</span><strong>${escapeHtml(m.f_aprobacion_sol || '—')}</strong></div>
                    <div><span class="text-slate-400 block">OC Odoo</span><strong>${escapeHtml(m.oc_odoo || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Ingreso SAP</span><strong>${escapeHtml(m.f_creacion_sap || 'Pendiente')}</strong></div>
                </div>
                <div class="mt-4 overflow-x-auto border border-slate-200 dark:border-gray-700 rounded-xl">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-100 dark:bg-gray-800">
                            <tr><th class="py-2 px-3">Producto / Descripción</th><th class="py-2 px-3 text-right">Cantidad</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
                            ${lines.length ? lines.map(l => `<tr><td class="py-2 px-3">${escapeHtml(l.producto)}</td><td class="py-2 px-3 text-right font-bold">${Number(l.cantidad).toLocaleString()}</td></tr>`).join('') : '<tr><td colspan="2" class="py-4 text-center text-slate-400">Sin líneas registradas.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            const m = data.meta || {};
            const lines = (data.detail && data.detail.lineas) ? data.detail.lineas : [];
            const div = m.divisa || (data.detail && data.detail.divisa) || 'GTQ';
            body.innerHTML = `
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200 dark:border-gray-700 text-xs">
                    <div><span class="text-slate-400 block">Proveedor</span><strong>${escapeHtml(m.proveedor || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Estatus OC</span><strong>${escapeHtml(m.estado_oc || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Solicitud Origen</span><strong>${escapeHtml(m.solicitud || '—')}</strong></div>
                    <div><span class="text-slate-400 block">DocNum SAP</span><strong>${escapeHtml(m.oc_sap || 'Pendiente')}</strong></div>
                    <div><span class="text-slate-400 block">Creación OC</span><strong>${escapeHtml(m.f_creacion_oc || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Aprobación OC</span><strong>${escapeHtml(m.f_aprobacion_oc || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Fecha SAP</span><strong>${escapeHtml(m.f_creacion_sap || '—')}</strong></div>
                    <div><span class="text-slate-400 block">Monto Total</span><strong>${escapeHtml(div)} ${Number(m.monto || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></div>
                </div>
                <div class="mt-4 overflow-x-auto border border-slate-200 dark:border-gray-700 rounded-xl">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-100 dark:bg-gray-800">
                            <tr>
                                <th class="py-2 px-3">Descripción</th>
                                <th class="py-2 px-3 text-right">Cantidad</th>
                                <th class="py-2 px-3 text-right">Precio Unit.</th>
                                <th class="py-2 px-3 text-right">Total (${escapeHtml(div)})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
                            ${lines.length ? lines.map(l => `
                                <tr>
                                    <td class="py-2 px-3">${escapeHtml(l.producto)}</td>
                                    <td class="py-2 px-3 text-right">${Number(l.cantidad).toLocaleString()}</td>
                                    <td class="py-2 px-3 text-right">${Number(l.precio_unitario).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4})}</td>
                                    <td class="py-2 px-3 text-right font-bold">${Number(l.total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                </tr>
                            `).join('') : '<tr><td colspan="4" class="py-4 text-center text-slate-400">Sin líneas registradas.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;
        }
    } catch (err) {
        body.innerHTML = `<p class="py-6 text-center text-cresta-red font-semibold">Error consultando documento.</p>`;
    }
}

function closeDocDetailModal() {
    const modal = document.getElementById('docDetailModal');
    if (modal) modal.classList.add('hidden');
}

async function openLineSearchModal(query) {
    const modal = document.getElementById('lineSearchModal');
    const body  = document.getElementById('lineSearchBody');
    const title = document.getElementById('lineSearchTitle');
    if (!modal || !body) return;

    modal.classList.remove('hidden');
    if (title) title.textContent = `Resultados de búsqueda: "${query}"`;
    body.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-slate-400">Buscando coincidencias...</td></tr>`;

    try {
        const res = await fetch(`?action=search&q=${encodeURIComponent(query)}`);
        const data = await res.json();
        const items = data.results || [];
        if (!items.length) {
            body.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-slate-400">Sin resultados para "${escapeHtml(query)}".</td></tr>`;
            return;
        }
        body.innerHTML = items.map(it => `
            <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40 border-b border-slate-100 dark:border-gray-800">
                <td class="py-2 px-3 font-bold">
                    <button type="button" onclick="openDocDetailModal('${escapeHtml(it.doc_type)}','${escapeHtml(it.doc_id)}')" class="underline text-cresta-navy dark:text-white">
                        ${escapeHtml(it.doc_id)}
                    </button>
                </td>
                <td class="py-2 px-3">${escapeHtml(it.fecha || '')}</td>
                <td class="py-2 px-3">${escapeHtml(it.sociedad || '')}</td>
                <td class="py-2 px-3">${escapeHtml(it.comprador || '')}</td>
                <td class="py-2 px-3">${escapeHtml(it.producto || '')}</td>
                <td class="py-2 px-3 text-right">${Number(it.cantidad || 0).toLocaleString()}</td>
                <td class="py-2 px-3 text-right font-semibold">${it.total !== null ? `${escapeHtml(it.divisa)} ${Number(it.total).toLocaleString(undefined,{minimumFractionDigits:2})}` : escapeHtml(it.estado || '')}</td>
            </tr>
        `).join('');
    } catch (err) {
        body.innerHTML = `<tr><td colspan="7" class="py-6 text-center text-cresta-red">Error ejecutando búsqueda.</td></tr>`;
    }
}

function closeLineSearchModal() {
    const modal = document.getElementById('lineSearchModal');
    if (modal) modal.classList.add('hidden');
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
