<!-- ===================================================================== -->
<!-- MÓDULO 2: GESTIÓN DE GASTO, COMPRADORES Y PRODUCTOS                   -->
<!-- ===================================================================== -->
<main id="viewSpendSection" class="hidden max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 flex-1 w-full">

    <!-- 5 Tarjetas KPI Ejecutivas de Gasto -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div onclick="openAuditModal('SPEND_FIRM', 'ALL')" title="Haz clic para auditar el detalle de OCs firmes"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-navy dark:hover:border-indigo-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-navy dark:group-hover:text-white transition">Gasto Comprometido</span>
                    <span id="spKpi1Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">--</span>
                </div>
                <div class="flex items-baseline justify-between mt-2 gap-1">
                    <span id="spKpi1Value" class="text-xl xl:text-2xl font-bold text-cresta-navy dark:text-white truncate">Q 0</span>
                    <span class="text-[10px] text-cresta-navy dark:text-indigo-400 group-hover:underline font-bold shrink-0">Auditar →</span>
                </div>
            </div>
            <p id="spKpi1Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">OCs firmes en divisa seleccionada</p>
        </div>

        <div onclick="openAuditModal('SPEND_FIRM', 'ALL')" title="Haz clic para auditar tickets de órdenes de compra"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-blue dark:hover:border-sky-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-blue dark:group-hover:text-sky-300 transition">OCs Firmes / Ticket</span>
                    <span id="spKpi2Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">0 OCs</span>
                </div>
                <div class="flex items-baseline justify-between mt-2 gap-1">
                    <span id="spKpi2Value" class="text-xl xl:text-2xl font-bold text-cresta-blue dark:text-sky-400 truncate">Q 0</span>
                    <span class="text-[10px] text-cresta-blue dark:text-sky-400 group-hover:underline font-bold shrink-0">Auditar →</span>
                </div>
            </div>
            <p id="spKpi2Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Ticket promedio · Mediana: Q 0</p>
        </div>

        <div onclick="openAuditModal('SPEND_FIRM', 'TOP5_PROV')" title="Haz clic para auditar OCs de los 5 proveedores principales"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-emerald-600 dark:hover:border-emerald-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">Proveedores Activos</span>
                    <span id="spKpi3Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">Top 5: 0%</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span id="spKpi3Value" class="text-2xl xl:text-3xl font-bold text-emerald-600 dark:text-emerald-400">0</span>
                    <span class="text-[10px] text-emerald-700 dark:text-emerald-400 group-hover:underline font-bold">Top 5 →</span>
                </div>
            </div>
            <p id="spKpi3Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Concentración Pareto en 5 mayores</p>
        </div>

        <div onclick="openAuditModal('SPEND_RFQ', 'ALL')" title="Haz clic para auditar solicitudes de cotización sin convertir"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-amber-600 dark:hover:border-amber-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-amber-700 dark:group-hover:text-amber-300 transition">Cotizaciones (RFQ)</span>
                    <span id="spKpi4Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 font-bold shrink-0">0 abiertas</span>
                </div>
                <div class="flex items-baseline justify-between mt-2 gap-1">
                    <span id="spKpi4Value" class="text-xl xl:text-2xl font-bold text-amber-600 dark:text-amber-400 truncate">Q 0</span>
                    <span class="text-[10px] text-amber-700 dark:text-amber-400 group-hover:underline font-bold shrink-0">Auditar →</span>
                </div>
            </div>
            <p id="spKpi4Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">En cotización sin orden firme</p>
        </div>

        <div onclick="openAuditModal('SPEND_NO_SAP', 'ALL')" title="Haz clic para auditar OCs firmes pendientes de SAP"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-red dark:hover:border-red-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-red dark:group-hover:text-red-300 transition">Integración SAP</span>
                    <span id="spKpi5Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-950 text-cresta-red dark:text-red-300 border border-red-200 dark:border-red-800 font-bold shrink-0">Canc: 0%</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span id="spKpi5Value" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-indigo-400">0%</span>
                    <span class="text-[10px] text-cresta-red dark:text-red-400 group-hover:underline font-bold">Sin SAP →</span>
                </div>
            </div>
            <p id="spKpi5Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">OCs firmes sincronizadas en SAP</p>
        </div>
    </section>

    <!-- Panel Exclusivo de Comparación Trimestral de Gasto -->
    <section id="spendCompareQSection" class="hidden bg-white dark:bg-gray-900 border-2 border-cresta-navy/20 dark:border-indigo-500/30 rounded-2xl p-5 shadow-sm">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">COMPARATIVA DE GASTO Q vs Q</span>
                    <h2 id="spendCompareQTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Comparación Trimestral</h2>
                </div>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Contraste de monto comprometido, cantidad de OCs, ticket promedio y proveedores entre trimestres</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead id="spendCompareQHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200"></thead>
                <tbody id="spendCompareQBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </section>

    <!-- Fila 1 (Gasto): Evolución Mensual del Gasto + Distribución por Sociedad -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-8">
            <div class="mb-3">
                <h2 id="spChartTrendTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Evolución Mensual del Gasto Comprometido y OCs</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Barras = Monto total comprometido · Línea = Cantidad de órdenes de compra emitidas</p>
            </div>
            <div id="chartSpendTrend" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-4">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Participación del Gasto por Sociedad</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Distribución por empresa del grupo en el periodo</p>
            </div>
            <div id="chartSpendSociedades" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Fila 2 (Gasto): Top 10 Proveedores por Monto + Top Compradores por Monto -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top 10 Proveedores por Monto Comprado</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Principales socios comerciales por valor económico en el periodo</p>
                </div>
                <span id="badgeParetoInfo" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                    Pareto 80%
                </span>
            </div>
            <div id="chartTopProveedoresMonto" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top Compradores por Monto Gestionado</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Capital adjudicado en OCs firmes y cantidad de órdenes emitidas</p>
            </div>
            <div id="chartTopCompradoresMonto" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Fila 3 (Gasto): Top 10 Solicitantes Internos por Monto + Top 10 Proveedores por Frecuencia -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top 10 Solicitantes Internos por Monto Comprado</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Usuarios/áreas internas que originan el mayor gasto comprometido</p>
            </div>
            <div id="chartTopSolicitantesMonto" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top 10 Proveedores por Frecuencia (Cantidad de OCs)</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Proveedores de alta recurrencia operativa (candidatos a contratos marco)</p>
            </div>
            <div id="chartTopProveedoresFreq" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Fila 4 (Gasto): Inteligencia de Productos / Artículos Más Comprados y Precios Unitarios -->
    <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-slate-100 dark:border-gray-800 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">ANÁLISIS DE INSUMOS · OC.XLSX</span>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Productos Más Comprados y Evolución de Precios Unitarios</h2>
                </div>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Calculado estrictamente sobre líneas de órdenes de compra en firme</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <label for="selectProductTrack" class="font-bold text-slate-600 dark:text-gray-300 shrink-0">Rastrear Artículo:</label>
                <select id="selectProductTrack" onchange="renderProductPriceTrend()"
                    class="bg-slate-50 dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-3 py-1.5 font-medium max-w-xs sm:max-w-md">
                </select>
            </div>
        </div>

        <!-- 4 Mini-KPIs del Producto Seleccionado -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200/70 dark:border-gray-700/70">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Gasto en el Artículo</span>
                <span id="prodKpiSpend" class="text-base font-bold text-cresta-navy dark:text-white mt-0.5 block">Q 0.00</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200/70 dark:border-gray-700/70">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Cantidad Comprada</span>
                <span id="prodKpiQty" class="text-base font-bold text-cresta-blue dark:text-sky-400 mt-0.5 block">0</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200/70 dark:border-gray-700/70">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Precio Unit. Promedio</span>
                <span id="prodKpiAvgPu" class="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 block">Q 0.00</span>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200/70 dark:border-gray-700/70">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Rango Precio (Mín – Máx)</span>
                <span id="prodKpiRange" class="text-sm font-bold text-cresta-red dark:text-amber-400 mt-1 block">Q 0 - Q 0</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-6">
                <h3 class="text-xs font-bold text-slate-700 dark:text-gray-300 uppercase mb-2">Top 10 Productos por Monto Comprado (Periodo)</h3>
                <div id="chartTopProductsMonto" class="w-full h-80"></div>
            </div>
            <div class="lg:col-span-6">
                <h3 id="prodTrendChartTitle" class="text-xs font-bold text-slate-700 dark:text-gray-300 uppercase mb-2">Variación Mensual del Precio Unitario</h3>
                <div id="chartProductPriceTrend" class="w-full h-80"></div>
            </div>
        </div>
    </section>

    <!-- Tabla Ejecutiva Top 15 Proveedores Detallada -->
    <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Ranking Detallado de Proveedores (Top 15 del Periodo)</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Incluye monto total, participación %, peso Pareto acumulado, ticket promedio y sociedades atendidas</p>
            </div>
            <button type="button" onclick="openAuditModal('SPEND_FIRM', 'ALL')"
                class="text-xs px-3 py-1.5 rounded-lg bg-cresta-navy dark:bg-cresta-red text-white font-bold hover:opacity-90 transition shrink-0">
                Auditar todas las OCs →
            </button>
        </div>
        <table class="w-full text-xs text-left border-collapse">
            <thead class="bg-cresta-navy text-white dark:bg-gray-800 dark:text-gray-200">
                <tr>
                    <th class="py-3 px-3 font-bold rounded-tl-lg">#</th>
                    <th class="py-3 px-3 font-bold">Proveedor</th>
                    <th class="py-3 px-3 text-right font-bold">OCs</th>
                    <th class="py-3 px-3 text-right font-bold">Sociedades</th>
                    <th class="py-3 px-3 text-right font-bold">Ticket Prom.</th>
                    <th class="py-3 px-3 text-right font-bold">Monto Total</th>
                    <th class="py-3 px-3 text-right font-bold">% Part.</th>
                    <th class="py-3 px-3 text-right font-bold rounded-tr-lg">% Acum.</th>
                </tr>
            </thead>
            <tbody id="topSuppliersTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
        </table>
    </section>

</main>
