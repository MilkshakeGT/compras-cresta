<!-- ===================================================================== -->
<!-- MÓDULO 3: PORTAL 360° PROVEEDOR (EXCLUSIVO OCs EN FIRME)              -->
<!-- ===================================================================== -->
<main id="viewSupplierSection" class="hidden max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 flex-1 w-full">

    <!-- Banner Selector Protagonista del Proveedor -->
    <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">PORTAL 360° PROVEEDOR · OCs EN FIRME</span>
                <span id="sup360PeriodTag" class="text-xs text-slate-500 dark:text-gray-400 font-semibold">--</span>
            </div>
            <h2 id="sup360Title" class="text-base sm:text-lg font-bold text-cresta-navy dark:text-white mt-1">
                Todos los Proveedores (Consolidado OCs Firmes)
            </h2>
            <p id="sup360Sub" class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">
                Selecciona un proveedor específico para auditar qué le compramos, cuándo, evolución de precios y órdenes históricas
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <select id="sup360Selector" onchange="onSupplier360SelectChange()"
                class="bg-slate-50 dark:bg-gray-800 border-2 border-cresta-navy dark:border-indigo-500 text-cresta-navy dark:text-white rounded-xl px-3.5 py-2.5 font-bold shadow-xs max-w-xs sm:max-w-md">
                <option value="ALL">Todos los Proveedores (Vista Global)</option>
            </select>
            <button type="button" onclick="exportSupplier360CSV()"
                class="px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition shrink-0">
                Exportar a Excel
            </button>
        </div>
    </section>

    <!-- 5 Tarjetas KPI del Proveedor (Exclusivo OCs en Firme) -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase">Compras Firmes</span>
                    <span id="s360Kpi1Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">--</span>
                </div>
                <div class="mt-2">
                    <span id="s360Kpi1Val" class="text-xl xl:text-2xl font-bold text-cresta-navy dark:text-white block truncate">Q 0.00</span>
                </div>
            </div>
            <p id="s360Kpi1Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Participación del gasto: 0%</p>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase">OCs / Ticket Prom.</span>
                    <span id="s360Kpi2Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">0 OCs</span>
                </div>
                <div class="mt-2">
                    <span id="s360Kpi2Val" class="text-xl xl:text-2xl font-bold text-cresta-blue dark:text-sky-400 block truncate">Q 0.00</span>
                </div>
            </div>
            <p id="s360Kpi2Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Mediana por orden: Q 0.00</p>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase">Artículos / Líneas</span>
                    <span id="s360Kpi3Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">0 líneas</span>
                </div>
                <div class="mt-2">
                    <span id="s360Kpi3Val" class="text-2xl xl:text-3xl font-bold text-emerald-600 dark:text-emerald-400 block">0</span>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Productos distintos comprados</p>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase">Última Compra</span>
                    <span id="s360Kpi4Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">0 Soc.</span>
                </div>
                <div class="mt-2">
                    <span id="s360Kpi4Val" class="text-xl xl:text-2xl font-bold text-cresta-navy dark:text-indigo-400 block">—</span>
                </div>
            </div>
            <p id="s360Kpi4Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Primera compra: —</p>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase">Sincronizado SAP</span>
                    <span id="s360Kpi5Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold shrink-0">OCs Firmes</span>
                </div>
                <div class="mt-2">
                    <span id="s360Kpi5Val" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-white block">0%</span>
                </div>
            </div>
            <p id="s360Kpi5Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">0 de 0 órdenes en SAP</p>
        </div>
    </section>

    <!-- Panel Comparativa Trimestral Q vs Q del Proveedor -->
    <section id="sup360CompareQSection" class="hidden bg-white dark:bg-gray-900 border-2 border-cresta-navy/20 dark:border-indigo-500/30 rounded-2xl p-5 shadow-sm">
        <div class="mb-3">
            <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">COMPARATIVA TRIMESTRAL PROVEEDOR</span>
            <h3 id="sup360CompareQTitle" class="text-sm font-bold text-cresta-navy dark:text-white mt-1">Q1 vs Q2</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead id="sup360CompareQHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200"></thead>
                <tbody id="sup360CompareQBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </section>

    <!-- Gráficas del Proveedor: Tendencia Mensual + Qué le Compramos (Top Productos) -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3">
                <h3 class="text-sm font-bold text-cresta-navy dark:text-white">Tendencia Mensual de Compras al Proveedor</h3>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Evolución de monto adjudicado y frecuencia de órdenes por mes</p>
            </div>
            <div id="chartSup360Trend" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3">
                <h3 class="text-sm font-bold text-cresta-navy dark:text-white">¿Qué le Compramos? · Top 10 Productos / Servicios</h3>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Distribución del gasto por artículo comprado al proveedor</p>
            </div>
            <div id="chartSup360Products" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Tabla 1 del Proveedor: Catálogo de Productos Comprados y Precios -->
    <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-cresta-navy dark:text-white">Detalle de Productos y Precios Comprados al Proveedor</h3>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Consolidado de cantidades, precios unitarios (mínimo, promedio, máximo y último) y monto total</p>
            </div>
            <input id="sup360ProdSearch" type="text" oninput="renderSup360TablesOnly()" placeholder="Filtrar producto..."
                class="bg-slate-50 dark:bg-gray-800 border border-slate-300 dark:border-gray-700 rounded-lg px-3 py-1.5 text-xs w-56">
        </div>
        <table class="w-full text-xs text-left border-collapse">
            <thead class="bg-cresta-navy text-white dark:bg-gray-800 dark:text-gray-200">
                <tr>
                    <th class="py-2.5 px-3 font-bold rounded-tl-lg">Producto / Servicio</th>
                    <th class="py-2.5 px-3 text-right font-bold">OCs</th>
                    <th class="py-2.5 px-3 text-right font-bold">Cant. Total</th>
                    <th class="py-2.5 px-3 text-right font-bold">Precio Mín.</th>
                    <th class="py-2.5 px-3 text-right font-bold">Precio Prom.</th>
                    <th class="py-2.5 px-3 text-right font-bold">Precio Máx.</th>
                    <th class="py-2.5 px-3 text-right font-bold">Último Precio</th>
                    <th class="py-2.5 px-3 text-right font-bold">Monto Total</th>
                    <th class="py-2.5 px-3 text-right font-bold rounded-tr-lg">%</th>
                </tr>
            </thead>
            <tbody id="sup360ProductsTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
        </table>
    </section>

    <!-- Tabla 2 del Proveedor: Historial Cronológico de Órdenes de Compra Firmes -->
    <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-cresta-navy dark:text-white">Historial Cronológico de Órdenes de Compra Firmes</h3>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Haz clic en cualquier número de OC o Solicitud para abrir su ficha completa de líneas</p>
            </div>
            <span id="sup360OcsCountLabel" class="text-xs font-bold text-slate-500">0 órdenes</span>
        </div>
        <table class="w-full text-xs text-left border-collapse">
            <thead class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200">
                <tr>
                    <th class="py-2.5 px-3 font-bold">Fecha OC</th>
                    <th class="py-2.5 px-3 font-bold">No. OC Odoo</th>
                    <th class="py-2.5 px-3 font-bold">Solicitud</th>
                    <th class="py-2.5 px-3 font-bold">Sociedad</th>
                    <th class="py-2.5 px-3 font-bold">Proveedor</th>
                    <th class="py-2.5 px-3 font-bold">Comprador</th>
                    <th class="py-2.5 px-3 font-bold">SAP</th>
                    <th class="py-2.5 px-3 text-right font-bold">Total OC</th>
                </tr>
            </thead>
            <tbody id="sup360OcsTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
        </table>
    </section>

</main>
