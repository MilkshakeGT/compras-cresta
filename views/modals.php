<!-- Modal 1: Auditoría de Listados (Ciclo + Gasto) -->
<div id="auditModal" class="fixed inset-0 z-40 hidden bg-slate-900/60 dark:bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-2xl max-w-6xl w-full max-h-[88vh] flex flex-col shadow-2xl">
        <div class="p-5 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span id="modalBadge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">AUDITORÍA</span>
                    <span id="modalSubtitle" class="text-xs text-slate-500 dark:text-gray-400"></span>
                </div>
                <h3 id="modalTitle" class="text-base font-bold text-cresta-navy dark:text-white mt-1">Detalle de Registros</h3>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="exportAuditExcel()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                    Exportar a Excel
                </button>
                <button type="button" onclick="closeAuditModal()"
                    class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-300 text-xs font-bold">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <div class="px-5 py-3 bg-slate-50 dark:bg-gray-800/40 border-b border-slate-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div id="modalTabsContainer" class="flex flex-wrap gap-1.5"></div>
            <input id="modalSearch" type="text" oninput="renderModalTable()" placeholder="Buscar solicitud, solicitante, proveedor, OC..."
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-slate-800 dark:text-gray-100 w-64">
        </div>

        <div class="overflow-auto flex-1 p-5">
            <p class="text-[11px] text-slate-400 dark:text-gray-500 mb-2">
                * Haz clic en cualquier número de <strong>Solicitud</strong> o de <strong>OC Odoo</strong> para ver su línea de tiempo, productos, cantidades y precios.
            </p>
            <table class="w-full text-xs text-left border-collapse">
                <thead id="modalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0"></thead>
                <tbody id="modalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 3 (z-40): Resultados de Búsqueda Global por Ítem, SKU o Descripción -->
<div id="globalSearchModal" class="fixed inset-0 z-40 hidden bg-slate-900/65 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-2xl max-w-6xl w-full max-h-[90vh] flex flex-col shadow-2xl">
        <!-- Cabecera -->
        <div class="p-5 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">BÚSQUEDA EN LÍNEAS · ÍTEM / SKU</span>
                    <span id="gsSubtitle" class="text-xs text-slate-500 dark:text-gray-400">Buscando...</span>
                </div>
                <h3 id="gsTitle" class="text-base font-bold text-cresta-navy dark:text-white mt-1">Resultados de Búsqueda</h3>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="exportGlobalSearchExcel()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                    Exportar a Excel
                </button>
                <button type="button" onclick="closeGlobalSearchModal()"
                    class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-300 text-xs font-bold">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <!-- Barra de Pestañas (Todos / Solicitudes / OCs) + Ordenamiento -->
        <div class="px-5 py-3 bg-slate-50 dark:bg-gray-800/40 border-b border-slate-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="inline-flex rounded-lg bg-slate-200/70 dark:bg-gray-800 p-0.5 gap-1">
                <button id="gsTabAll" type="button" onclick="setGlobalSearchTab('ALL')"
                    class="px-3 py-1 rounded-md font-bold bg-cresta-navy dark:bg-cresta-red text-white transition">
                    Todos (0)
                </button>
                <button id="gsTabSol" type="button" onclick="setGlobalSearchTab('sol')"
                    class="px-3 py-1 rounded-md font-bold text-slate-700 dark:text-gray-300 transition">
                    Líneas de Solicitud (0)
                </button>
                <button id="gsTabOc" type="button" onclick="setGlobalSearchTab('oc')"
                    class="px-3 py-1 rounded-md font-bold text-slate-700 dark:text-gray-300 transition">
                    Líneas de OC (0)
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label for="gsSortSelect" class="font-bold text-slate-500 dark:text-gray-400">Ordenar por:</label>
                <select id="gsSortSelect" onchange="renderGlobalSearchTable()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 rounded-lg px-2.5 py-1.5 font-semibold text-slate-800 dark:text-gray-100">
                    <option value="fecha_desc">Fecha de creación (Más reciente)</option>
                    <option value="fecha_asc">Fecha de creación (Más antigua)</option>
                    <option value="comprador_asc">Comprador (A → Z)</option>
                    <option value="comprador_desc">Comprador (Z → A)</option>
                    <option value="proveedor_asc">Proveedor (A → Z)</option>
                    <option value="proveedor_desc">Proveedor (Z → A)</option>
                    <option value="solicitante_asc">Solicitante (A → Z)</option>
                    <option value="solicitante_desc">Solicitante (Z → A)</option>
                </select>
            </div>
        </div>

        <!-- Tabla de Resultados -->
        <div class="overflow-auto flex-1 p-5">
            <p class="text-[11px] text-slate-400 dark:text-gray-500 mb-2">
                * Haz clic en cualquier código de <strong>Solicitud</strong> o de <strong>OC Odoo</strong> para abrir su ficha completa con línea de tiempo de autorizaciones.
            </p>
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0">
                    <tr>
                        <th class="py-2.5 px-3">Tipo</th>
                        <th class="py-2.5 px-3">Documento</th>
                        <th class="py-2.5 px-3">Fecha Creación</th>
                        <th class="py-2.5 px-3">Artículo / SKU / Descripción</th>
                        <th class="py-2.5 px-3 text-right">Cant.</th>
                        <th class="py-2.5 px-3 text-right">Total Línea</th>
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

<!-- Modal 2 (Superpuesto z-50): Ficha Detallada de Solicitud u Orden de Compra -->
<div id="docDetailModal" class="fixed inset-0 z-50 hidden bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-indigo-500 rounded-2xl max-w-4xl w-full max-h-[88vh] flex flex-col shadow-2xl">
        <div class="p-5 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/70 dark:bg-gray-800/50 rounded-t-2xl">
            <div>
                <div class="flex items-center gap-2">
                    <span id="docModalBadge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">FICHA DE DOCUMENTO</span>
                    <span id="docModalSubBadge" class="text-xs text-slate-500 dark:text-gray-400"></span>
                </div>
                <h3 id="docModalTitle" class="text-lg font-bold text-cresta-navy dark:text-white mt-1">Cargando...</h3>
            </div>
            <div class="flex items-center gap-2">
                <button id="btnExportDocExcel" type="button" onclick="exportDocumentDetailExcel()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                    Exportar a Excel
                </button>
                <button type="button" onclick="closeDocDetailModal()"
                    class="px-3 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-800 dark:text-gray-200 text-xs font-bold">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <!-- Metadatos del Documento -->
        <div id="docModalMetaBox" class="px-5 py-3 border-b border-slate-200 dark:border-gray-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-white dark:bg-gray-900"></div>

        <!-- Línea de Tiempo de Autorizaciones y Ciclo -->
        <div id="docModalTimelineBox" class="px-5 py-3.5 border-b border-slate-200 dark:border-gray-800 bg-slate-50/80 dark:bg-gray-800/30 text-xs"></div>

        <!-- Tabla de Líneas de Productos / Servicios -->
        <div class="overflow-auto flex-1 p-5">
            <table class="w-full text-xs text-left border-collapse">
                <thead id="docModalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0"></thead>
                <tbody id="docModalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                <tfoot id="docModalTableFoot" class="bg-slate-50 dark:bg-gray-800/70 font-bold"></tfoot>
            </table>
        </div>
    </div>
</div>
