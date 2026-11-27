/* =========================================================================
 * MODALES DE AUDITORÍA, BÚSQUEDA GLOBAL, DETALLE Y NOTAS (assets/js/modals.js)
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

// Estado de edición rápida de justificación dentro de #auditModal
let activeQuickNoteSolId = '';

// Estado de Búsqueda Global por Ítem / SKU / Descripción
let globalSearchResults = [];
let globalSearchQuery   = '';
let globalSearchTab     = 'ALL'; // 'ALL' | 'sol' | 'oc'

// Mapa en vivo de notas descargadas desde ?action=get_sol_notes
let liveSolNotesMap = {};

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

// =========================================================================
// PERMISOS Y SINCRONIZACIÓN DE JUSTIFICACIONES (280 CARACTERES)
// =========================================================================
function normalizeBuyerText(str) {
    return String(str ?? '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ');
}

/**
 * Resuelve siempre la nota más reciente de una solicitud consultando:
 * 1) liveSolNotesMap (descargado en vivo desde ?action=get_sol_notes)
 * 2) RAW_DATA.records (fuente maestra en memoria)
 * 3) El objeto local recibido
 */
function resolveLatestSolNote(recOrSolId) {
    const isObj = (recOrSolId && typeof recOrSolId === 'object');
    const cleanId = isObj
        ? String(recOrSolId.id || recOrSolId.solicitud || '').trim().toUpperCase()
        : String(recOrSolId || '').trim().toUpperCase();

    if (cleanId && liveSolNotesMap && Object.prototype.hasOwnProperty.call(liveSolNotesMap, cleanId)) {
        const entry = liveSolNotesMap[cleanId];
        if (entry && typeof entry === 'object') {
            return {
                note:    String(entry.note || '').trim(),
                noteBy:  String(entry.updated_by || entry.note_by || '').trim(),
                noteAt:  String(entry.updated_at || entry.note_at || '').trim(),
                noteUsr: String(entry.updated_username || entry.note_username || '').trim()
            };
        }
        return { note: '', noteBy: '', noteAt: '', noteUsr: '' };
    }

    let note    = isObj ? String(recOrSolId.buyer_note || '').trim() : '';
    let noteBy  = isObj ? String(recOrSolId.note_by || '').trim() : '';
    let noteAt  = isObj ? String(recOrSolId.note_at || '').trim() : '';
    let noteUsr = isObj ? String(recOrSolId.note_username || '').trim() : '';

    if (cleanId && typeof RAW_DATA !== 'undefined' && Array.isArray(RAW_DATA.records)) {
        const master = RAW_DATA.records.find(r => String(r.id || '').trim().toUpperCase() === cleanId);
        if (master) {
            const mNote = String(master.buyer_note || '').trim();
            if (mNote !== '' || note === '') {
                note    = mNote;
                noteBy  = String(master.note_by || '').trim();
                noteAt  = String(master.note_at || '').trim();
                noteUsr = String(master.note_username || '').trim();
            }
        }
    }

    return { note, noteBy, noteAt, noteUsr };
}

/**
 * Consulta ?action=get_sol_notes en vivo y sincroniza toda la memoria del frontend.
 */
async function refreshLiveSolNotes(reRenderAudit = true) {
    try {
        const resp = await fetch(`?action=get_sol_notes&_t=${Date.now()}`, {
            cache: 'no-store'
        });
        const data = await resp.json();
        if (!resp.ok || !data || !data.ok || typeof data.notes !== 'object') {
            return;
        }

        const freshMap = {};
        Object.keys(data.notes).forEach(k => {
            const normKey = String(k).trim().toUpperCase();
            if (normKey && data.notes[k] && typeof data.notes[k] === 'object') {
                freshMap[normKey] = data.notes[k];
            }
        });
        liveSolNotesMap = freshMap;

        const applyEntry = (item, key) => {
            if (!item || !key) return;
            const entry = liveSolNotesMap[key];
            item.buyer_note    = entry ? String(entry.note || '') : '';
            item.note_by       = entry ? String(entry.updated_by || '') : '';
            item.note_at       = entry ? String(entry.updated_at || '') : '';
            item.note_username = entry ? String(entry.updated_username || '') : '';
        };

        if (typeof RAW_DATA !== 'undefined') {
            (RAW_DATA.records || []).forEach(r => {
                applyEntry(r, String(r.id || '').trim().toUpperCase());
            });
            (RAW_DATA.spend_records || []).forEach(sr => {
                applyEntry(sr, String(sr.solicitud || '').trim().toUpperCase());
            });
        }

        Object.keys(cachedUniverses).forEach(catKey => {
            (cachedUniverses[catKey] || []).forEach(item => {
                const key = String(item.id || item.solicitud || '').trim().toUpperCase();
                applyEntry(item, key);
            });
        });

        if (reRenderAudit) {
            const auditModalEl = document.getElementById('auditModal');
            if (auditModalEl && !auditModalEl.classList.contains('hidden')) {
                renderModalTable();
            }
        }
    } catch (e) {
        // Silencioso para no interrumpir la navegación si hay microcorte de red
    }
}

/**
 * Determina si el usuario actual puede crear o editar la nota:
 * - Manager: siempre puede crear y editar.
 * - Comprador: puede crear en sus solicitudes asignadas, y editar si él mismo la registró.
 */
function canCurrentUserEditSolNote(recOrBuyer) {
    const ctx = (typeof RAW_DATA !== 'undefined' && RAW_DATA.user_context) ? RAW_DATA.user_context : null;
    if (!ctx) return false;
    if (ctx.role === 'manager') return true;

    const isObj = (recOrBuyer && typeof recOrBuyer === 'object');
    const solBuyerName = isObj ? String(recOrBuyer.comprador || '') : String(recOrBuyer || '');
    const noteInfo = isObj ? resolveLatestSolNote(recOrBuyer) : { note: '', noteBy: '', noteUsr: '' };

    const allowed = Array.isArray(ctx.allowed_buyers) ? ctx.allowed_buyers : [];
    const targetNorm = normalizeBuyerText(solBuyerName);
    const isAssignedBuyer = targetNorm !== '' && allowed.some(b => normalizeBuyerText(b) === targetNorm);

    if (noteInfo.note !== '') {
        const curUserNorm   = normalizeBuyerText(ctx.username || '');
        const curDispNorm   = normalizeBuyerText(ctx.display_name || '');
        const authorUsrNorm = normalizeBuyerText(noteInfo.noteUsr || '');
        const authorByNorm  = normalizeBuyerText(noteInfo.noteBy || '');

        if (authorUsrNorm !== '' && curUserNorm !== '' && authorUsrNorm === curUserNorm) {
            return true;
        }
        if (authorByNorm !== '' && curDispNorm !== '' && authorByNorm === curDispNorm) {
            return true;
        }
        return isAssignedBuyer;
    }

    return isAssignedBuyer;
}

function syncSolNoteInMemory(solId, noteText, noteBy, noteAt, noteUsername = '') {
    const cleanId = String(solId ?? '').trim().toUpperCase();
    if (!cleanId) return;

    if (String(noteText || '').trim() === '') {
        delete liveSolNotesMap[cleanId];
    } else {
        liveSolNotesMap[cleanId] = {
            note:             noteText,
            updated_by:       noteBy,
            updated_username: noteUsername,
            updated_at:       noteAt
        };
    }

    const applyUpdate = (item) => {
        if (!item) return;
        item.buyer_note    = noteText;
        item.note_by       = noteBy;
        item.note_at       = noteAt;
        item.note_username = noteUsername;
    };

    if (typeof RAW_DATA !== 'undefined') {
        (RAW_DATA.records || []).forEach(r => {
            if (String(r.id || '').trim().toUpperCase() === cleanId) applyUpdate(r);
        });
        (RAW_DATA.spend_records || []).forEach(sr => {
            if (String(sr.solicitud || '').trim().toUpperCase() === cleanId) applyUpdate(sr);
        });
    }

    Object.keys(cachedUniverses).forEach(catKey => {
        (cachedUniverses[catKey] || []).forEach(item => {
            const itemSol = String(item.id || item.solicitud || '').trim().toUpperCase();
            if (itemSol === cleanId) {
                applyUpdate(item);
            }
        });
    });

    (globalSearchResults || []).forEach(gs => {
        if ((gs.doc_type === 'sol' && String(gs.doc_id || '').trim().toUpperCase() === cleanId) ||
            (gs.doc_type === 'oc' && String(gs.related_doc || '').trim().toUpperCase() === cleanId)) {
            gs.buyer_note = noteText;
        }
    });
}

async function persistSolNoteRequest(solId, noteText) {
    const resp = await fetch('?action=save_sol_note', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: solId, note: noteText })
    });
    const data = await resp.json();
    if (!resp.ok || !data.ok) {
        throw new Error(data.error || 'No se pudo guardar la justificación.');
    }
    syncSolNoteInMemory(
        data.id,
        data.note || '',
        data.note_by || '',
        data.note_at || '',
        data.note_username || ''
    );
    return data;
}

function renderSolLink(solId) {
    const clean = String(solId ?? '').trim();
    if (!clean) return '—';
    return `<button type="button" onclick="event.stopPropagation(); openDocumentDetail('sol', '${escapeHtml(clean)}')"
        title="Ver línea de tiempo, productos solicitados y justificación en ${escapeHtml(clean)}"
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
            title="Ver línea de tiempo, precios y proveedor de ${escapeHtml(code)}"
            class="inline-block font-semibold text-cresta-blue dark:text-sky-400 underline decoration-dotted hover:decoration-solid hover:text-cresta-red dark:hover:text-white transition cursor-pointer">
            ${escapeHtml(code)}
        </button>
    `).join(', ');
}

// =========================================================================
// BUSCADOR GLOBAL INTELIGENTE (DOCUMENTO EXACTO O ÍTEM / SKU / DESCRIPCIÓN)
// =========================================================================
function handleQuickDocLookup(e) {
    e.preventDefault();
    const raw = document.getElementById('quickDocInput').value.trim();
    if (!raw) return;

    const upper = raw.toUpperCase();

    const isExactOc = /^P\d+$/i.test(upper) || (RAW_DATA.spend_records || []).some(r => String(r.oc_odoo || '').toUpperCase() === upper);
    if (isExactOc) {
        openDocumentDetail('oc', upper);
        return;
    }

    const isExactSol = (RAW_DATA.records || []).some(r => String(r.id || '').toUpperCase() === upper);
    if (isExactSol) {
        openDocumentDetail('sol', upper);
        return;
    }

    openGlobalSearchModal(raw);
}

async function openGlobalSearchModal(query) {
    const modal = document.getElementById('globalSearchModal');
    const titleEl = document.getElementById('gsTitle');
    const subEl = document.getElementById('gsSubtitle');
    const tbody = document.getElementById('gsTableBody');

    globalSearchQuery = query;
    globalSearchResults = [];
    globalSearchTab = 'ALL';

    titleEl.textContent = `Resultados para "${query}"`;
    subEl.textContent = 'Buscando en líneas de solicitudes y órdenes de compra...';
    tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-slate-400">Consultando líneas de productos...</td></tr>`;
    updateGlobalSearchTabButtons();
    modal.classList.remove('hidden');

    try {
        const resp = await fetch(`?action=search&q=${encodeURIComponent(query)}`);
        const data = await resp.json();

        globalSearchResults = data.results || [];
        updateGlobalSearchTabButtons();
        renderGlobalSearchTable();
    } catch (err) {
        subEl.textContent = 'Error en la búsqueda';
        tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-cresta-red font-semibold">Error consultando líneas: ${escapeHtml(err.message)}</td></tr>`;
    }
}

function closeGlobalSearchModal() {
    document.getElementById('globalSearchModal').classList.add('hidden');
}

function setGlobalSearchTab(tab) {
    globalSearchTab = tab;
    updateGlobalSearchTabButtons();
    renderGlobalSearchTable();
}

function updateGlobalSearchTabButtons() {
    const solCount = globalSearchResults.filter(r => r.doc_type === 'sol').length;
    const ocCount  = globalSearchResults.filter(r => r.doc_type === 'oc').length;

    const btnAll = document.getElementById('gsTabAll');
    const btnSol = document.getElementById('gsTabSol');
    const btnOc  = document.getElementById('gsTabOc');

    const activeCls   = 'px-3 py-1 rounded-md font-bold bg-cresta-navy dark:bg-cresta-red text-white transition whitespace-nowrap';
    const inactiveCls = 'px-3 py-1 rounded-md font-bold text-slate-700 dark:text-gray-300 hover:opacity-80 transition whitespace-nowrap';

    btnAll.textContent = `Todos (${globalSearchResults.length})`;
    btnSol.textContent = `Líneas de Solicitud (${solCount})`;
    btnOc.textContent  = `Líneas de OC (${ocCount})`;

    btnAll.className = globalSearchTab === 'ALL' ? activeCls : inactiveCls;
    btnSol.className = globalSearchTab === 'sol' ? activeCls : inactiveCls;
    btnOc.className  = globalSearchTab === 'oc'  ? activeCls : inactiveCls;
}

function getSortedFilteredGlobalSearchRows() {
    const filtered = globalSearchTab === 'ALL'
        ? [...globalSearchResults]
        : globalSearchResults.filter(r => r.doc_type === globalSearchTab);

    const sortMode = document.getElementById('gsSortSelect').value;

    const cmpStr = (a, b) => String(a || '').localeCompare(String(b || ''), 'es', { sensitivity: 'base' });

    filtered.sort((a, b) => {
        if (sortMode === 'fecha_desc') return cmpStr(b.fecha, a.fecha);
        if (sortMode === 'fecha_asc') return cmpStr(a.fecha, b.fecha);
        if (sortMode === 'comprador_asc') return cmpStr(shortName(a.comprador), shortName(b.comprador)) || cmpStr(b.fecha, a.fecha);
        if (sortMode === 'comprador_desc') return cmpStr(shortName(b.comprador), shortName(a.comprador)) || cmpStr(b.fecha, a.fecha);
        if (sortMode === 'proveedor_asc') {
            const pa = a.proveedor || 'zzz';
            const pb = b.proveedor || 'zzz';
            return cmpStr(pa, pb) || cmpStr(b.fecha, a.fecha);
        }
        if (sortMode === 'proveedor_desc') return cmpStr(b.proveedor, a.proveedor) || cmpStr(b.fecha, a.fecha);
        if (sortMode === 'solicitante_asc') return cmpStr(shortName(a.solicitante), shortName(b.solicitante)) || cmpStr(b.fecha, a.fecha);
        if (sortMode === 'solicitante_desc') return cmpStr(shortName(b.solicitante), shortName(a.solicitante)) || cmpStr(b.fecha, a.fecha);
        return 0;
    });

    return filtered;
}

function highlightMatchText(text, query) {
    const safeText = escapeHtml(text);
    const tokens = String(query || '').trim().split(/\s+/).filter(t => t.length >= 2);
    if (!tokens.length) return safeText;

    const escapedTokens = tokens.map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const regex = new RegExp(`(${escapedTokens.join('|')})`, 'gi');
    return safeText.replace(regex, '<mark class="bg-amber-200/80 dark:bg-amber-700/60 text-slate-900 dark:text-white px-0.5 rounded">$1</mark>');
}

function renderGlobalSearchTable() {
    const rows = getSortedFilteredGlobalSearchRows();
    const tbody = document.getElementById('gsTableBody');
    const subEl = document.getElementById('gsSubtitle');

    subEl.textContent = `${rows.length.toLocaleString()} coincidencia(s) encontrada(s)`;

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-slate-400 dark:text-gray-400">No se encontraron líneas de solicitud ni de órdenes de compra que coincidan con "${escapeHtml(globalSearchQuery)}".</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const isOc = (r.doc_type === 'oc');
        const typeBadge = isOc
            ? `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-cresta-blue border border-blue-200 dark:bg-sky-950 dark:text-sky-300 dark:border-sky-800">OC</span>`
            : `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-cresta-navy border border-slate-300 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800">SOL</span>`;

        const targetSol = isOc ? (r.related_doc || '') : (r.doc_id || '');
        const resolved = resolveLatestSolNote(targetSol);
        const noteText = resolved.note || String(r.buyer_note || '').trim();

        const noteSnippet = noteText
            ? `<div class="mt-1 px-1.5 py-0.5 rounded bg-amber-50 dark:bg-indigo-950/70 border border-amber-200 dark:border-indigo-800 text-[10px] text-slate-700 dark:text-gray-200 max-w-[220px] whitespace-normal" title="${escapeHtml(noteText)}">💬 ${escapeHtml(noteText)}</div>`
            : '';

        const docLinkHtml = isOc
            ? `${renderOcLinks(r.doc_id)}${r.related_doc ? `<div class="text-[10px] text-slate-400 mt-0.5">Sol: ${renderSolLink(r.related_doc)}</div>` : ''}${noteSnippet}`
            : `${renderSolLink(r.doc_id)}${r.related_doc ? `<div class="text-[10px] text-slate-400 mt-0.5">OC: ${renderOcLinks(r.related_doc)}</div>` : ''}${noteSnippet}`;

        const montoHtml = isOc && r.total !== null
            ? `<span class="font-bold text-cresta-navy dark:text-sky-400">${formatMoney(r.total, r.divisa || 'GTQ')}</span>`
            : `<span class="text-slate-400">—</span>`;

        const provHtml = r.proveedor
            ? `<span class="font-medium text-slate-700 dark:text-gray-200">${escapeHtml(r.proveedor)}</span>`
            : `<span class="text-slate-400 italic text-[11px]">Por cotizar</span>`;

        return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50">
            <td class="py-2 px-3 align-top">${typeBadge}</td>
            <td class="py-2 px-3 align-top whitespace-nowrap">${docLinkHtml}</td>
            <td class="py-2 px-3 align-top whitespace-nowrap text-slate-600 dark:text-gray-300 font-medium">${escapeHtml(r.fecha || '—')}</td>
            <td class="py-2 px-3 align-top text-slate-800 dark:text-gray-200 max-w-xs">${highlightMatchText(r.producto, globalSearchQuery)}</td>
            <td class="py-2 px-3 align-top text-right font-semibold text-slate-700 dark:text-gray-300">${Number(r.cantidad).toLocaleString('en-US', { maximumFractionDigits: 4 })}</td>
            <td class="py-2 px-3 align-top text-right whitespace-nowrap">${montoHtml}</td>
            <td class="py-2 px-3 align-top whitespace-nowrap text-slate-700 dark:text-gray-300">${escapeHtml(shortName(r.comprador))}</td>
            <td class="py-2 px-3 align-top whitespace-nowrap text-slate-600 dark:text-gray-300">${escapeHtml(shortName(r.solicitante))}</td>
            <td class="py-2 px-3 align-top">${provHtml}</td>
        </tr>`;
    }).join('');
}

function exportGlobalSearchExcel() {
    const rows = getSortedFilteredGlobalSearchRows();
    if (!rows.length) return;

    const headers = ['Tipo Documento', 'Documento', 'Doc. Relacionado', 'Fecha Creacion', 'Sociedad', 'SKU', 'Producto / Descripcion', 'Cantidad', 'Divisa', 'Precio Unitario', 'Total Linea', 'Comprador', 'Solicitante', 'Proveedor', 'Estado', 'Justificacion Comprador'];
    const dataRows = rows.map(r => {
        const targetSol = r.doc_type === 'oc' ? (r.related_doc || '') : (r.doc_id || '');
        const noteText = resolveLatestSolNote(targetSol).note || r.buyer_note || '';
        return [
            r.doc_type === 'oc' ? 'Orden de Compra' : 'Solicitud',
            r.doc_id,
            r.related_doc || '',
            r.fecha || '',
            r.sociedad || '',
            r.sku || '',
            String(r.producto).replace(/\n/g, ' '),
            Number(r.cantidad),
            r.divisa || '',
            r.pu !== null ? Number(r.pu) : '',
            r.total !== null ? Number(r.total) : '',
            shortName(r.comprador),
            shortName(r.solicitante),
            r.proveedor || '',
            r.estado || '',
            noteText
        ];
    });

    const cleanQ = globalSearchQuery.replace(/[^a-zA-Z0-9]/g, '_').slice(0, 25);
    exportToExcel(
        headers,
        dataRows,
        `busqueda_${cleanQ}_${RAW_DATA.cutoff_date}.xlsx`,
        'Resultados Busqueda'
    );
}

// =========================================================================
// MODAL 1: AUDITORÍA DE LISTADOS Y EDICIÓN RÁPIDA DE JUSTIFICACIONES
// =========================================================================
function openAuditModal(category, defaultSubFilter) {
    activeModalCategory = category || 'REZAGO';
    activeModalSubFilter = defaultSubFilter || 'ALL';
    document.getElementById('modalSearch').value = '';
    closeQuickAuditNote();

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
            <th class="py-2.5 px-3">Fecha</th>
            <th class="py-2.5 px-3">Sociedad</th>
            <th class="py-2.5 px-3">Solicitante</th>
            <th class="py-2.5 px-3">Comprador</th>
            <th class="py-2.5 px-3">OC Odoo</th>
            <th class="py-2.5 px-3">Estatus</th>
            <th class="py-2.5 px-3 min-w-[230px]">Justificación Comprador</th>
            <th class="py-2.5 px-3 text-right">${cfg.daysLabel}</th>
        </tr>`;
    }

    renderModalTabs();
    document.getElementById('auditModal').classList.remove('hidden');
    renderModalTable();

    // Sincronizar en vivo desde el servidor para garantizar que se vean comentarios recientes
    refreshLiveSolNotes(true);
}

function closeAuditModal() {
    closeQuickAuditNote();
    document.getElementById('auditModal').classList.add('hidden');
}

function openQuickAuditNote(solId) {
    const cleanId = String(solId ?? '').trim().toUpperCase();
    if (!cleanId) return;

    const baseList = cachedUniverses[activeModalCategory] || [];
    const rec = baseList.find(item => String(item.id || '').trim().toUpperCase() === cleanId)
        || (RAW_DATA.records || []).find(item => String(item.id || '').trim().toUpperCase() === cleanId);

    if (!rec || !canCurrentUserEditSolNote(rec)) {
        return;
    }

    const noteInfo = resolveLatestSolNote(rec);

    activeQuickNoteSolId = String(rec.id || cleanId).trim();
    const bar     = document.getElementById('auditQuickNoteBar');
    const titleEl = document.getElementById('aqnSolTitle');
    const metaEl  = document.getElementById('aqnMetaInfo');
    const inputEl = document.getElementById('aqnInput');

    titleEl.textContent = `${activeQuickNoteSolId} · ${shortName(rec.comprador || '')}`;
    metaEl.textContent  = noteInfo.noteAt ? `(Última act.: ${noteInfo.noteBy || ''} · ${noteInfo.noteAt})` : '(Sin nota previa)';
    inputEl.value       = noteInfo.note || '';
    updateQuickNoteCounter();

    bar.classList.remove('hidden');
    inputEl.focus();
}

function closeQuickAuditNote() {
    activeQuickNoteSolId = '';
    const bar = document.getElementById('auditQuickNoteBar');
    if (bar) bar.classList.add('hidden');
}

function updateQuickNoteCounter() {
    const inputEl = document.getElementById('aqnInput');
    const cntEl   = document.getElementById('aqnCharCounter');
    if (!inputEl || !cntEl) return;
    const len = inputEl.value.length;
    cntEl.textContent = `${len} / 280`;
    cntEl.className = len >= 260
        ? 'text-[11px] font-bold text-cresta-red'
        : 'text-[11px] font-bold text-slate-500 dark:text-gray-400';
}

async function submitQuickAuditNote() {
    if (!activeQuickNoteSolId) return;
    const inputEl = document.getElementById('aqnInput');
    const btnEl   = document.getElementById('aqnSaveBtn');
    const rawNote = inputEl ? inputEl.value : '';

    btnEl.disabled = true;
    btnEl.textContent = 'Guardando...';

    try {
        await persistSolNoteRequest(activeQuickNoteSolId, rawNote);
        closeQuickAuditNote();
        renderModalTable();
    } catch (err) {
        alert(err.message || 'Error guardando justificación.');
    } finally {
        btnEl.disabled = false;
        btnEl.textContent = 'Guardar';
    }
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
                const noteText = resolveLatestSolNote(r.solicitud).note;
                const hay = `${r.oc_odoo} ${r.oc_sap} ${r.solicitud} ${r.sociedad} ${r.solicitado_por} ${r.proveedor} ${r.comprador} ${r.estado_oc} ${noteText}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        }

        if (typeof isCycleRecordExcluded === 'function' && isCycleRecordExcluded(r)) {
            return false;
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
            if (r.tiene_oc_sap || !r.aprobada) return false;
            if (activeModalSubFilter === 'GT30' && !(r.d_to_sap !== null && r.d_to_sap > 30)) return false;
            if (activeModalSubFilter === 'NO_OC' && r.tiene_oc_odoo) return false;
            if (activeModalSubFilter === 'PEND_SAP' && !r.tiene_oc_odoo) return false;
        }

        if (q) {
            const noteText = resolveLatestSolNote(r).note;
            const hay = `${r.id} ${r.sociedad} ${r.solicitado_por} ${r.comprador} ${r.oc_odoo} ${r.estado_oc} ${r.estado_sol} ${noteText}`.toLowerCase();
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

function renderAuditNoteCell(r) {
    const canEdit  = canCurrentUserEditSolNote(r);
    const noteInfo = resolveLatestSolNote(r);
    const note     = noteInfo.note;

    if (note) {
        const subMeta = noteInfo.noteAt
            ? `<div class="text-[9px] text-slate-500 dark:text-gray-400 mt-1">${escapeHtml(shortName(noteInfo.noteBy || ''))} · ${escapeHtml(noteInfo.noteAt)}</div>`
            : '';

        return `
            <div class="p-1.5 rounded-lg bg-amber-50/90 dark:bg-indigo-950/60 border border-amber-200 dark:border-indigo-800 max-w-[280px]">
                <div class="flex items-start justify-between gap-1.5">
                    <span class="text-[11px] font-medium text-slate-800 dark:text-gray-100 leading-snug break-words">💬 ${escapeHtml(note)}</span>
                    ${canEdit ? `<button type="button" onclick="event.stopPropagation(); openQuickAuditNote('${escapeHtml(r.id)}')"
                        title="Editar justificación"
                        class="shrink-0 px-1.5 py-0.5 rounded bg-white hover:bg-slate-100 dark:bg-gray-800 dark:hover:bg-gray-700 border border-slate-200 dark:border-gray-700 text-cresta-navy dark:text-sky-300 text-[10px] font-bold shadow-2xs">
                        ✏️
                    </button>` : ''}
                </div>
                ${subMeta}
            </div>
        `;
    }

    if (canEdit) {
        return `<button type="button" onclick="event.stopPropagation(); openQuickAuditNote('${escapeHtml(r.id)}')"
            class="inline-flex items-center gap-1 px-2 py-1 rounded border border-dashed border-slate-300 dark:border-gray-700 hover:border-cresta-navy dark:hover:border-sky-400 text-[10px] font-semibold text-slate-500 dark:text-gray-400 hover:text-cresta-navy dark:hover:text-sky-300 transition">
            💬 + Justificar
        </button>`;
    }

    return `<span class="text-slate-400 italic text-[11px]">Sin comentario</span>`;
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

            const noteText = resolveLatestSolNote(r.solicitud).note;
            const noteHint = noteText
                ? `<div class="text-[10px] text-slate-600 dark:text-gray-300 mt-0.5 max-w-[220px] truncate" title="${escapeHtml(noteText)}">💬 ${escapeHtml(noteText)}</div>`
                : '';

            return `<tr class="hover:bg-slate-50 dark:hover:bg-gray-800/50">
                <td class="py-2 px-3">${renderOcLinks(r.oc_odoo)} <span class="text-[10px] text-slate-400">(${renderSolLink(r.solicitud)})</span>${noteHint}</td>
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
        tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-slate-400 dark:text-gray-400">No hay solicitudes con este filtro.</td></tr>`;
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
            <td class="py-2 px-3 align-top">${renderSolLink(r.id)}</td>
            <td class="py-2 px-3 align-top text-slate-600 dark:text-gray-300 whitespace-nowrap">${escapeHtml(r.fecha)}</td>
            <td class="py-2 px-3 align-top text-slate-600 dark:text-gray-300">${escapeHtml(r.sociedad)}</td>
            <td class="py-2 px-3 align-top text-slate-600 dark:text-gray-300">${escapeHtml(shortName(r.solicitado_por || ''))}</td>
            <td class="py-2 px-3 align-top text-slate-700 dark:text-gray-300">${escapeHtml(shortName(r.comprador))}</td>
            <td class="py-2 px-3 align-top">${renderOcLinks(r.oc_odoo)}</td>
            <td class="py-2 px-3 align-top">${getRowStatusBadge(r)}</td>
            <td class="py-2 px-3 align-top">${renderAuditNoteCell(r)}</td>
            <td class="py-2 px-3 align-top text-right whitespace-nowrap ${diasCls}">${diasTxt}</td>
        </tr>`;
    }).join('');
}

function exportAuditExcel() {
    const cfg = MODAL_CONFIG[activeModalCategory];
    const rows = getFilteredModalRows();

    if (cfg.mode === 'spend') {
        const headers = ['OC Odoo', 'OC SAP', 'Solicitud', 'Fecha OC', 'Sociedad', 'Solicitante', 'Proveedor', 'Comprador', 'Estado OC', 'Divisa', 'Total OC', 'Justificacion Comprador'];
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
            Number(r.monto.toFixed(2)),
            resolveLatestSolNote(r.solicitud).note || ''
        ]);

        exportToExcel(
            headers,
            dataRows,
            `auditoria_${activeModalCategory.toLowerCase()}_${activeCurrency}_${RAW_DATA.cutoff_date}.xlsx`,
            'Ordenes de Compra'
        );
        return;
    }

    const headers = ['Solicitud', 'Fecha Creacion', 'Sociedad', 'Solicitante', 'Comprador', 'OC Odoo', 'Estatus', 'Justificacion Comprador', 'Actualizado Por', 'Fecha Nota', cfg.daysLabel];
    const dataRows = rows.map(r => {
        const statusTxt = r.tiene_oc_sap
            ? 'En SAP'
            : (r.tiene_oc_odoo ? (r.estado_oc || 'OC Odoo sin SAP') : (!r.aprobada ? (r.estado_sol || 'Sin aprobar') : 'Sin OC en Odoo'));
        const rawDays = r[cfg.daysField];
        const daysVal = rawDays !== null && rawDays !== undefined ? Number(rawDays.toFixed(1)) : '';
        const noteInfo = resolveLatestSolNote(r);

        return [
            r.id,
            r.fecha,
            r.sociedad,
            r.solicitado_por || '',
            r.comprador,
            r.oc_odoo || '',
            statusTxt,
            noteInfo.note || '',
            noteInfo.noteBy || '',
            noteInfo.noteAt || '',
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

// =========================================================================
// RENDERIZADOR DE LÍNEA DE TIEMPO PARA SOLICITUD Y ORDEN DE COMPRA
// =========================================================================
function fmtDaysPill(days, goodThreshold = 7, warnThreshold = 14) {
    if (days === null || days === undefined || isNaN(days)) return '';
    const d = Number(days);
    let cls = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    if (d > warnThreshold) {
        cls = 'bg-red-100 text-cresta-red dark:bg-red-950 dark:text-red-300';
    } else if (d > goodThreshold) {
        cls = 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    }
    return `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold ${cls}">+${d.toFixed(1)} d</span>`;
}

function renderDocumentTimeline(data) {
    const box = document.getElementById('docModalTimelineBox');
    if (!box) return;

    const steps = [];
    let totalBadgeHtml = '';

    if (data.type === 'sol') {
        const m = data.meta || {};
        const fCre = m.f_creacion_sol || m.fecha || null;
        const fApr = m.f_aprobacion_sol || null;
        const fOc  = m.f_creacion_oc || null;

        steps.push({
            state: fCre ? 'done' : 'pending',
            title: '1. Creación Solicitud',
            date: fCre || 'Sin fecha',
            actor: shortName(m.solicitado_por || ''),
            delta: ''
        });

        if (m.is_rechazada) {
            steps.push({
                state: 'rejected',
                title: '2. Solicitud Rechazada',
                date: fApr || 'Rechazada',
                actor: shortName(m.autorizador_sol || ''),
                delta: ''
            });
        } else if (fApr) {
            steps.push({
                state: 'done',
                title: '2. Autorización Solicitud',
                date: fApr,
                actor: `Aut: ${shortName(m.autorizador_sol || '—')}`,
                delta: fmtDaysPill(m.d_aprob_sol, 2, 5)
            });
        } else {
            steps.push({
                state: 'pending',
                title: '2. Autorización Solicitud',
                date: `Estatus: ${m.estado_sol || 'Pendiente'}`,
                actor: `Aut: ${shortName(m.autorizador_sol || '—')}`,
                delta: `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">En espera</span>`
            });
        }

        if (!m.is_rechazada) {
            if (fOc) {
                steps.push({
                    state: 'done',
                    title: '3. OC Generada en Odoo',
                    date: fOc,
                    actor: `Comp: ${shortName(m.comprador || '—')}`,
                    delta: fmtDaysPill(m.d_comprador, 7, 14)
                });
            } else {
                steps.push({
                    state: 'pending',
                    title: '3. OC en Odoo',
                    date: 'Pendiente de colocar OC',
                    actor: `Comp: ${shortName(m.comprador || '—')}`,
                    delta: m.d_comprador !== null ? fmtDaysPill(m.d_comprador, 7, 14) : ''
                });
            }
        }

        if (m.d_ciclo_total !== null && m.d_ciclo_total !== undefined) {
            totalBadgeHtml = `<span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">Ciclo total a SAP: ${Number(m.d_ciclo_total).toFixed(1)} días</span>`;
        } else if (m.d_aprob_sol !== null && m.d_aprob_sol !== undefined) {
            totalBadgeHtml = `<span class="text-[11px] font-semibold text-slate-600 dark:text-gray-300">Tiempo aprobación: ${Number(m.d_aprob_sol).toFixed(1)} días</span>`;
        }
    } else if (data.type === 'oc') {
        const m  = data.meta || {};
        const sm = data.sol_meta || {};

        const fCreSol = m.f_creacion_sol || sm.f_creacion_sol || null;
        const fAprSol = m.f_aprobacion_sol || sm.f_aprobacion_sol || null;
        const fCreOc  = m.f_creacion_oc || m.fecha || null;
        const fAprOc  = m.f_aprobacion_oc || null;
        const fSap    = m.f_creacion_sap || null;

        steps.push({
            state: fCreSol ? 'done' : 'pending',
            title: '1. Creación Solicitud',
            date: fCreSol || 'Sin fecha',
            actor: `${m.solicitud || '—'} · ${shortName(m.solicitado_por || '')}`,
            delta: ''
        });

        steps.push({
            state: fAprSol ? 'done' : 'pending',
            title: '2. Aprobación Solicitud',
            date: fAprSol || 'Sin fecha aprobación',
            actor: `Aut: ${shortName(m.autorizador_sol || sm.autorizador_sol || '—')}`,
            delta: fmtDaysPill(m.d_aprob_sol ?? sm.d_aprob_sol, 2, 5)
        });

        steps.push({
            state: fCreOc ? 'done' : 'pending',
            title: '3. Creación OC Odoo',
            date: fCreOc || 'Sin fecha',
            actor: `Comp: ${shortName(m.comprador || '—')}`,
            delta: fmtDaysPill(m.d_comprador, 7, 14)
        });

        if (m.is_cancelada) {
            steps.push({
                state: 'rejected',
                title: '4. OC Cancelada',
                date: 'Anulada en Odoo',
                actor: m.estado_oc || 'Cancelado',
                delta: ''
            });
        } else if (m.en_sap && fSap) {
            const subAut = fAprOc ? `Aprob. OC: ${fAprOc.split(' ')[0]} · ` : '';
            steps.push({
                state: 'done',
                title: '4. Aprobada y en SAP',
                date: fSap,
                actor: `${subAut}SAP ${m.oc_sap || ''}`,
                delta: fmtDaysPill(m.d_to_sap_oc, 2, 5)
            });
        } else if (fAprOc) {
            steps.push({
                state: 'pending',
                title: '4. OC Aprobada (Sin SAP)',
                date: `Aprob. OC: ${fAprOc}`,
                actor: `Estatus: ${m.estado_oc || 'Pendiente SAP'}`,
                delta: fmtDaysPill(m.d_aprob_oc, 2, 5)
            });
        } else {
            steps.push({
                state: 'pending',
                title: '4. Pendiente Aprob. / SAP',
                date: `Estatus actual: ${m.estado_oc || 'En proceso'}`,
                actor: m.autorizador_oc_1 ? `Aut. 1: ${shortName(m.autorizador_oc_1)}` : 'Sin aprobar para SAP',
                delta: `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">En curso</span>`
            });
        }

        if (fCreSol && fSap) {
            const t1 = new Date(fCreSol.replace(' ', 'T')).getTime();
            const t2 = new Date(fSap.replace(' ', 'T')).getTime();
            if (!isNaN(t1) && !isNaN(t2) && t2 >= t1) {
                const totD = ((t2 - t1) / 86400000).toFixed(1);
                totalBadgeHtml = `<span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">Ciclo completo (Sol → SAP): ${totD} días</span>`;
            }
        }
    }

    const colsClass = steps.length >= 4 ? 'sm:grid-cols-4' : 'sm:grid-cols-3';

    const stepsHtml = steps.map(st => {
        let dotCls = 'bg-emerald-600 text-white';
        let cardBorder = 'border-emerald-200 dark:border-emerald-900/70 bg-white dark:bg-gray-900';
        let icon = '✓';

        if (st.state === 'pending') {
            dotCls = 'bg-amber-500 text-white';
            cardBorder = 'border-amber-200 dark:border-amber-900/70 bg-amber-50/40 dark:bg-amber-950/20';
            icon = '⏳';
        } else if (st.state === 'rejected') {
            dotCls = 'bg-cresta-red text-white';
            cardBorder = 'border-red-200 dark:border-red-900/70 bg-red-50/40 dark:bg-red-950/20';
            icon = '✕';
        }

        return `
            <div class="border ${cardBorder} rounded-xl p-2.5 flex flex-col justify-between">
                <div class="flex items-center justify-between gap-1">
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full ${dotCls} inline-flex items-center justify-center text-[9px] font-bold shrink-0">${icon}</span>
                        <span class="font-bold text-[11px] text-slate-800 dark:text-gray-100">${escapeHtml(st.title)}</span>
                    </div>
                    ${st.delta}
                </div>
                <div class="mt-1.5 pl-5">
                    <div class="text-[11px] font-semibold text-cresta-navy dark:text-sky-300">${escapeHtml(st.date)}</div>
                    <div class="text-[10px] text-slate-500 dark:text-gray-400 truncate">${escapeHtml(st.actor)}</div>
                </div>
            </div>
        `;
    }).join('');

    box.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400">
                Línea de Tiempo y Trazabilidad de Autorizaciones
            </span>
            ${totalBadgeHtml}
        </div>
        <div class="grid grid-cols-1 ${colsClass} gap-2.5">
            ${stepsHtml}
        </div>
    `;
}

// =========================================================================
// CAJA DE JUSTIFICACIÓN EN MODAL 2 (DETALLE DE SOLICITUD U OC VINCULADA)
// =========================================================================
function renderDocumentNoteSection(data) {
    const noteBox = document.getElementById('docModalNoteBox');
    if (!noteBox) return;

    const solId = data.type === 'sol'
        ? String(data.id || '').trim()
        : String((data.meta && data.meta.solicitud) || (data.sol_meta && data.sol_meta.id) || '').trim();

    if (!solId || solId.startsWith('SIN-SOL-')) {
        noteBox.classList.add('hidden');
        noteBox.innerHTML = '';
        return;
    }

    const srcMeta = data.type === 'sol' ? (data.meta || {}) : (data.sol_meta || data.meta || {});
    const currentNote = String(srcMeta.buyer_note || '').trim();
    const noteBy = String(srcMeta.note_by || '').trim();
    const noteAt = String(srcMeta.note_at || '').trim();
    const canEdit = Boolean(data.can_edit_note);

    const metaLabel = (currentNote && noteAt)
        ? `Actualizado por <strong>${escapeHtml(noteBy)}</strong> el ${escapeHtml(noteAt)}`
        : 'Sin actualización registrada';

    if (!canEdit) {
        noteBox.classList.remove('hidden');
        noteBox.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-cresta-navy dark:text-indigo-300">
                    💬 Estatus / Justificación del Comprador (${escapeHtml(solId)})
                </span>
                <span class="text-[10px] text-slate-500 dark:text-gray-400">${metaLabel}</span>
            </div>
            <p class="text-xs text-slate-800 dark:text-gray-200">
                ${currentNote ? escapeHtml(currentNote) : '<span class="italic text-slate-400">El comprador aún no ha registrado un estatus o justificación para esta solicitud.</span>'}
            </p>
        `;
        return;
    }

    noteBox.classList.remove('hidden');
    noteBox.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-cresta-navy dark:text-indigo-300">
                    💬 Estatus / Justificación del Comprador (${escapeHtml(solId)})
                </span>
                <span id="docNoteSavedFeedback" class="hidden text-[10px] font-bold text-emerald-700 dark:text-emerald-400">✓ Guardado</span>
            </div>
            <div class="flex items-center gap-3 text-[10px] text-slate-500 dark:text-gray-400">
                <span id="docNoteMetaLabel">${metaLabel}</span>
                <span id="docNoteCharCount" class="font-bold">${currentNote.length} / 280</span>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-start gap-2">
            <textarea id="docNoteTextarea" rows="2" maxlength="280"
                oninput="updateDocNoteCounter()"
                placeholder="Indica brevemente en qué etapa se encuentra esta solicitud o la justificación del pendiente (máx. 280 caracteres)..."
                class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-slate-800 dark:text-gray-100 text-xs resize-none">${escapeHtml(currentNote)}</textarea>
            <button id="docNoteSaveBtn" type="button" onclick="saveDocDetailNote('${escapeHtml(solId)}')"
                class="px-3.5 py-2 rounded-lg bg-cresta-navy hover:bg-cresta-blue dark:bg-cresta-red dark:hover:opacity-90 text-white font-bold text-xs transition shrink-0">
                Guardar Nota
            </button>
        </div>
    `;
}

function updateDocNoteCounter() {
    const ta  = document.getElementById('docNoteTextarea');
    const cnt = document.getElementById('docNoteCharCount');
    if (!ta || !cnt) return;
    const len = ta.value.length;
    cnt.textContent = `${len} / 280`;
    cnt.className = len >= 260 ? 'font-bold text-cresta-red' : 'font-bold';
}

async function saveDocDetailNote(solId) {
    const ta       = document.getElementById('docNoteTextarea');
    const btn      = document.getElementById('docNoteSaveBtn');
    const fb       = document.getElementById('docNoteSavedFeedback');
    const metaLbl  = document.getElementById('docNoteMetaLabel');
    if (!ta || !btn) return;

    btn.disabled = true;
    btn.textContent = 'Guardando...';
    if (fb) fb.classList.add('hidden');

    try {
        const res = await persistSolNoteRequest(solId, ta.value);
        ta.value = res.note || '';
        updateDocNoteCounter();

        if (activeDocDetail && activeDocDetail.meta) {
            activeDocDetail.meta.buyer_note    = res.note || '';
            activeDocDetail.meta.note_by       = res.note_by || '';
            activeDocDetail.meta.note_at       = res.note_at || '';
            activeDocDetail.meta.note_username = res.note_username || '';
        }
        if (activeDocDetail && activeDocDetail.sol_meta) {
            activeDocDetail.sol_meta.buyer_note    = res.note || '';
            activeDocDetail.sol_meta.note_by       = res.note_by || '';
            activeDocDetail.sol_meta.note_at       = res.note_at || '';
            activeDocDetail.sol_meta.note_username = res.note_username || '';
        }

        if (metaLbl) {
            metaLbl.innerHTML = res.note
                ? `Actualizado por <strong>${escapeHtml(res.note_by)}</strong> el ${escapeHtml(res.note_at)}`
                : 'Nota eliminada';
        }
        if (fb) {
            fb.classList.remove('hidden');
            setTimeout(() => fb.classList.add('hidden'), 2500);
        }

        const auditModalEl = document.getElementById('auditModal');
        if (auditModalEl && !auditModalEl.classList.contains('hidden')) {
            renderModalTable();
        }
    } catch (err) {
        alert(err.message || 'No se pudo guardar la nota.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar Nota';
    }
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
    const noteBox = document.getElementById('docModalNoteBox');
    const timelineBox = document.getElementById('docModalTimelineBox');
    const thead = document.getElementById('docModalTableHead');
    const tbody = document.getElementById('docModalTableBody');
    const tfoot = document.getElementById('docModalTableFoot');

    activeDocDetail = null;
    badgeEl.textContent = type === 'oc' ? 'DETALLE DE ORDEN DE COMPRA' : 'DETALLE DE SOLICITUD';
    subBadgeEl.textContent = 'Consultando...';
    titleEl.textContent = id;
    metaBox.innerHTML = `<div class="col-span-4 text-slate-400">Cargando información del documento...</div>`;
    if (noteBox) {
        noteBox.classList.add('hidden');
        noteBox.innerHTML = '';
    }
    if (timelineBox) timelineBox.innerHTML = '';
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

            renderDocumentNoteSection(data);
            renderDocumentTimeline(data);

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
                <div><span class="text-slate-400 block text-[10px] uppercase">Sociedad / Fecha OC</span><strong class="text-slate-800 dark:text-gray-200">${escapeHtml(m.sociedad || '—')}</strong><span class="block text-[10px] text-slate-400">${escapeHtml(m.fecha || '')}</span></div>
                <div><span class="text-slate-400 block text-[10px] uppercase">Solicitud / SAP</span><div>${renderSolLink(m.solicitud || '')}</div><span class="block text-[10px] text-emerald-600 font-semibold">${m.oc_sap ? 'SAP ' + escapeHtml(m.oc_sap) : (m.estado_oc || '')}</span></div>
            `;

            renderDocumentNoteSection(data);
            renderDocumentTimeline(data);

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

function exportDocumentDetailExcel() {
    if (!activeDocDetail) return;

    if (activeDocDetail.type === 'sol') {
        const m = activeDocDetail.meta || {};
        const lines = activeDocDetail.lines || [];
        const headers = ['Solicitud', 'Fecha Creacion', 'Fecha Aprobacion', 'Dias Aprobacion', 'Sociedad', 'Solicitante', 'Autorizador', 'Comprador', 'OC Odoo', 'Justificacion Comprador', 'Linea', 'Producto Solicitado', 'Cantidad Solicitada'];
        const dataRows = lines.map((l, idx) => [
            activeDocDetail.id,
            m.f_creacion_sol || m.fecha || '',
            m.f_aprobacion_sol || '',
            m.d_aprob_sol ?? '',
            m.sociedad || '',
            m.solicitado_por || '',
            m.autorizador_sol || '',
            m.comprador || '',
            m.oc_odoo || '',
            resolveLatestSolNote(activeDocDetail.id).note || m.buyer_note || '',
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

        const headers = ['No. OC', 'OC SAP', 'Estado OC', 'Solicitud', 'Justificacion Comprador', 'Fecha Creacion Sol', 'Fecha Aprobacion Sol', 'Fecha Creacion OC', 'Fecha Aprobacion OC', 'Fecha SAP', 'Sociedad', 'Proveedor', 'Comprador', 'Solicitante', 'Linea', 'Producto', 'Cantidad Comprada', 'Divisa', 'Precio Unitario', 'Total Linea'];
        const dataRows = lines.map((l, idx) => [
            activeDocDetail.id,
            m.oc_sap || '',
            m.estado_oc || '',
            m.solicitud || '',
            resolveLatestSolNote(m.solicitud || '').note || m.buyer_note || '',
            m.f_creacion_sol || '',
            m.f_aprobacion_sol || '',
            m.f_creacion_oc || m.fecha || '',
            m.f_aprobacion_oc || '',
            m.f_creacion_sap || '',
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
