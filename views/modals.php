<!-- ===================================================================== -->
<!-- MODAL 1: AUDITORÍA INTERACTIVA POR KPI                                -->
<!-- ===================================================================== -->
<div id="auditModal" class="fixed inset-0 z-50 hidden bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-indigo-500 rounded-2xl max-w-6xl w-full max-h-[88vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="p-4 sm:p-5 bg-cresta-navy text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span id="modalBadge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-red">AUDITORÍA</span>
                    <span id="modalSubtitle" class="text-xs text-slate-300">0 registros</span>
                </div>
                <h3 id="modalTitle" class="text-base sm:text-lg font-bold mt-1">Detalle de Solicitudes</h3>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="exportAuditExcel()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-sm">
                    📥 Descargar Excel (.xlsx)
                </button>
                <button type="button" onclick="closeAuditModal()"
                    class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <div class="px-4 sm:px-5 py-3 bg-slate-50 dark:bg-gray-950 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
            <div id="modalTabsContainer" class="flex flex-wrap gap-1.5"></div>
            <input id="modalSearch" type="text" oninput="renderModalTable()" placeholder="Buscar solicitud, OC, proveedor, comprador o nota..."
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-3 py-1.5 text-xs w-full sm:w-72">
        </div>

        <!-- Barra rápida de edición inline de justificación (280 car.) dentro del modal de auditoría -->
        <div id="auditQuickNoteBar" class="hidden px-4 sm:px-5 py-3 bg-amber-50/90 dark:bg-indigo-950/60 border-b border-amber-200 dark:border-indigo-800 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-1.5">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded bg-cresta-navy dark:bg-cresta-red text-white text-[10px] font-bold">JUSTIFICACIÓN COMPRADOR</span>
                    <strong id="aqnSolTitle" class="text-cresta-navy dark:text-white">Solicitud</strong>
                    <span id="aqnMetaInfo" class="text-[10px] text-slate-500 dark:text-gray-400"></span>
                </div>
                <span id="aqnCharCounter" class="text-[11px] font-bold text-slate-500 dark:text-gray-400">0 / 280</span>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <input id="aqnInput" type="text" maxlength="280" oninput="updateQuickNoteCounter()"
                    placeholder="Escribe el estatus actual o la justificación breve (máx. 280 caracteres)..."
                    class="flex-1 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-slate-800 dark:text-gray-100 text-xs">
                <div class="flex items-center gap-1.5 shrink-0">
                    <button id="aqnSaveBtn" type="button" onclick="submitQuickAuditNote()"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition">
                        Guardar
                    </button>
                    <button type="button" onclick="closeQuickAuditNote()"
                        class="px-2.5 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-300 font-bold text-xs transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-y-auto flex-1 p-4 sm:p-5">
            <table class="w-full text-xs text-left border-collapse">
                <thead id="modalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200 sticky top-0"></thead>
                <tbody id="modalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL 2: FICHA DETALLADA + LÍNEA DE TIEMPO + JUSTIFICACIÓN (280 CAR.) -->
<!-- ===================================================================== -->
<div id="docDetailModal" class="fixed inset-0 z-[60] hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-blue dark:border-sky-500 rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="p-4 sm:p-5 bg-cresta-blue dark:bg-gray-800 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span id="docModalBadge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-red uppercase">DETALLE DOCUMENTO</span>
                    <span id="docModalSubBadge" class="text-xs text-slate-200">Cargando...</span>
                </div>
                <h3 id="docModalTitle" class="text-base sm:text-lg font-bold mt-1">Documento</h3>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="exportDocumentDetailExcel()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-sm">
                    📥 Exportar Detalle (.xlsx)
                </button>
                <button type="button" onclick="closeDocDetailModal()"
                    class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <div id="docModalMetaBox" class="px-4 sm:px-5 py-3 bg-slate-50 dark:bg-gray-950 border-b border-slate-200 dark:border-gray-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs"></div>

        <!-- Caja de Estatus / Justificación del Comprador (280 caracteres) -->
        <div id="docModalNoteBox" class="hidden px-4 sm:px-5 py-3 bg-amber-50/50 dark:bg-indigo-950/30 border-b border-slate-200 dark:border-gray-800 text-xs"></div>

        <div id="docModalTimelineBox" class="px-4 sm:px-5 py-3 bg-white dark:bg-gray-900 border-b border-slate-200 dark:border-gray-800"></div>

        <div class="overflow-y-auto flex-1 p-4 sm:p-5">
            <table class="w-full text-xs text-left border-collapse">
                <thead id="docModalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200 sticky top-0"></thead>
                <tbody id="docModalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                <tfoot id="docModalTableFoot" class="bg-slate-50 dark:bg-gray-800/60 font-bold"></tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- MODAL 3: BÚSQUEDA GLOBAL POR ÍTEM / SKU / DESCRIPCIÓN                 -->
<!-- ===================================================================== -->
<div id="globalSearchModal" class="fixed inset-0 z-[55] hidden bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-indigo-500 rounded-2xl max-w-6xl w-full max-h-[88vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="p-4 sm:p-5 bg-cresta-navy text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-red uppercase">BÚSQUEDA DE ARTÍCULOS Y DOCUMENTOS</span>
                    <span id="gsSubtitle" class="text-xs text-slate-300">0 resultados</span>
                </div>
                <h3 id="gsTitle" class="text-base sm:text-lg font-bold mt-1">Resultados de búsqueda</h3>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="exportGlobalSearchExcel()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-sm">
                    📥 Exportar Resultados (.xlsx)
                </button>
                <button type="button" onclick="closeGlobalSearchModal()"
                    class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <div class="px-4 sm:px-5 py-3 bg-slate-50 dark:bg-gray-950 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
            <div class="inline-flex rounded-lg bg-slate-200/80 dark:bg-gray-800 p-0.5">
                <button id="gsTabAll" type="button" onclick="setGlobalSearchTab('ALL')"
                    class="px-3 py-1 rounded-md font-bold bg-cresta-navy dark:bg-cresta-red text-white transition">
                    Todos (0)
                </button>
                <button id="gsTabSol" type="button" onclick="setGlobalSearchTab('sol')"
                    class="px-3 py-1 rounded-md font-bold text-slate-700 dark:text-gray-300 hover:opacity-80 transition">
                    Líneas de Solicitud (0)
                </button>
                <button id="gsTabOc" type="button" onclick="setGlobalSearchTab('oc')"
                    class="px-3 py-1 rounded-md font-bold text-slate-700 dark:text-gray-300 hover:opacity-80 transition">
                    Líneas de OC (0)
                </button>
            </div>

            <div class="flex items-center gap-2">
                <label for="gsSortSelect" class="font-bold text-slate-600 dark:text-gray-400">Ordenar por:</label>
                <select id="gsSortSelect" onchange="renderGlobalSearchTable()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium">
                    <option value="fecha_desc">Fecha (Más reciente primero)</option>
                    <option value="fecha_asc">Fecha (Más antigua primero)</option>
                    <option value="comprador_asc">Comprador (A → Z)</option>
                    <option value="comprador_desc">Comprador (Z → A)</option>
                    <option value="proveedor_asc">Proveedor (A → Z)</option>
                    <option value="proveedor_desc">Proveedor (Z → A)</option>
                    <option value="solicitante_asc">Solicitante (A → Z)</option>
                    <option value="solicitante_desc">Solicitante (Z → A)</option>
                </select>
            </div>
        </div>

        <div class="overflow-y-auto flex-1 p-4 sm:p-5">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200 sticky top-0">
                    <tr>
                        <th class="py-2.5 px-3">Tipo</th>
                        <th class="py-2.5 px-3">Documento</th>
                        <th class="py-2.5 px-3">Fecha Creación</th>
                        <th class="py-2.5 px-3">Producto / Descripción</th>
                        <th class="py-2.5 px-3 text-right">Cantidad</th>
                        <th class="py-2.5 px-3 text-right">Monto</th>
                        <th class="py-2.5 px-3">Comprador</th>
                        <th class="py-2.5 px-3">Solicitante</th>
                        <th class="py-2.5 px-3">Proveedor</th>
                    </tr>
                </thead>
                <tbody id="gsTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </div>
</div>
