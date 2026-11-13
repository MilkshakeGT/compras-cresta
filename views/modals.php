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
                <button type="button" onclick="exportAuditCSV()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                    Descargar CSV
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
                * Haz clic en cualquier número de <strong>Solicitud</strong> o de <strong>OC Odoo</strong> para ver su desglose de productos, cantidades y precios.
            </p>
            <table class="w-full text-xs text-left border-collapse">
                <thead id="modalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0"></thead>
                <tbody id="modalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 2 (Superpuesto z-50): Ficha Detallada de Solicitud u Orden de Compra -->
<div id="docDetailModal" class="fixed inset-0 z-50 hidden bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-indigo-500 rounded-2xl max-w-4xl w-full max-h-[86vh] flex flex-col shadow-2xl">
        <div class="p-5 border-b border-slate-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/70 dark:bg-gray-800/50 rounded-t-2xl">
            <div>
                <div class="flex items-center gap-2">
                    <span id="docModalBadge" class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">FICHA DE DOCUMENTO</span>
                    <span id="docModalSubBadge" class="text-xs text-slate-500 dark:text-gray-400"></span>
                </div>
                <h3 id="docModalTitle" class="text-lg font-bold text-cresta-navy dark:text-white mt-1">Cargando...</h3>
            </div>
            <div class="flex items-center gap-2">
                <button id="btnExportDocCSV" type="button" onclick="exportDocumentDetailCSV()"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                    Descargar CSV
                </button>
                <button type="button" onclick="closeDocDetailModal()"
                    class="px-3 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-800 dark:text-gray-200 text-xs font-bold">
                    Cerrar ✕
                </button>
            </div>
        </div>

        <div id="docModalMetaBox" class="px-5 py-3.5 border-b border-slate-200 dark:border-gray-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-white dark:bg-gray-900"></div>

        <div class="overflow-auto flex-1 p-5">
            <table class="w-full text-xs text-left border-collapse">
                <thead id="docModalTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 sticky top-0"></thead>
                <tbody id="docModalTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                <tfoot id="docModalTableFoot" class="bg-slate-50 dark:bg-gray-800/70 font-bold"></tfoot>
            </table>
        </div>
    </div>
</div>
