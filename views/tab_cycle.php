<!-- ===================================================================== -->
<!-- MÓDULO 1: CICLO OPERATIVO Y SLA                                       -->
<!-- ===================================================================== -->
<main id="viewCycleSection" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6 flex-1 w-full">

    <!-- Tarjetas KPI Principales -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div onclick="openAuditModal('VALIDAS', 'ALL')" title="Haz clic para auditar el listado de solicitudes válidas"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-navy dark:hover:border-indigo-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-navy dark:group-hover:text-white transition">Solicitudes Válidas</span>
                    <span id="kpi1Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">--</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span id="kpi1Value" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-white">0</span>
                    <span class="text-[10px] text-cresta-navy dark:text-indigo-400 group-hover:underline font-bold">Auditar →</span>
                </div>
            </div>
            <p id="kpi1Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Excluye borrador, rechazadas y OC canceladas</p>
        </div>

        <div onclick="openAuditModal('TIEMPO_COMP', 'ALL')" title="Haz clic para auditar los tiempos de gestión por solicitud"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-blue dark:hover:border-sky-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-blue dark:group-hover:text-sky-300 transition">Gestión Comprador</span>
                    <span id="kpiTimeBadge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">--</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <div class="flex items-baseline gap-1">
                        <span id="kpiTimeValue" class="text-2xl xl:text-3xl font-bold text-cresta-navy dark:text-sky-400">0.0</span>
                        <span class="text-xs font-bold text-slate-500 dark:text-gray-400">días</span>
                    </div>
                    <span class="text-[10px] text-cresta-blue dark:text-sky-400 group-hover:underline font-bold">Auditar →</span>
                </div>
            </div>
            <p id="kpiTimeSub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Mediana: 0.0 d · Promedio a OC</p>
        </div>

        <div onclick="openAuditModal('SLA_COMP', 'ALL')" title="Haz clic para auditar tiempos de gestión por comprador"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-emerald-600 dark:hover:border-emerald-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-emerald-700 dark:group-hover:text-emerald-300 transition">SLA Comprador ≤14d</span>
                    <span id="kpi2Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">--</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <div class="flex items-baseline gap-1.5">
                        <span id="kpi2Value" class="text-2xl xl:text-3xl font-bold text-emerald-600 dark:text-emerald-400">0%</span>
                        <span class="text-[10px] text-slate-500 dark:text-gray-400">Meta 70%</span>
                    </div>
                    <span class="text-[10px] text-emerald-700 dark:text-emerald-400 group-hover:underline font-bold">Auditar →</span>
                </div>
            </div>
            <p id="kpi2Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Solicitudes maduras ≥14d (Odoo)</p>
        </div>

        <div onclick="openAuditModal('SAP_14', 'ALL')" title="Haz clic para auditar tiempos de ciclo hasta SAP"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-blue dark:hover:border-indigo-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-blue dark:group-hover:text-indigo-300 transition">En SAP ≤14 días</span>
                    <span id="kpi3Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 shrink-0">--</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span id="kpi3Value" class="text-2xl xl:text-3xl font-bold text-cresta-blue dark:text-indigo-400">0%</span>
                    <span class="text-[10px] text-cresta-blue dark:text-indigo-400 group-hover:underline font-bold">Auditar →</span>
                </div>
            </div>
            <p id="kpi3Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Ciclo desde aprobación (≥14d)</p>
        </div>

        <div onclick="openAuditModal('REZAGO', 'ALL')" title="Haz clic para auditar el detalle de solicitudes pendientes"
            class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm cursor-pointer hover:border-cresta-red dark:hover:border-amber-500/70 transition group flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] text-slate-500 dark:text-gray-400 font-bold uppercase group-hover:text-cresta-red dark:group-hover:text-amber-300 transition">Rezago Sin SAP</span>
                    <span id="kpi4Badge" class="text-[10px] px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-950 text-cresta-red dark:text-red-300 border border-red-200 dark:border-red-800 font-bold shrink-0">0 > 30d</span>
                </div>
                <div class="flex items-baseline justify-between mt-2">
                    <span id="kpi4Value" class="text-2xl xl:text-3xl font-bold text-cresta-red dark:text-amber-400">0</span>
                    <span class="text-[10px] text-cresta-red dark:text-amber-400 group-hover:underline font-bold">Auditar →</span>
                </div>
            </div>
            <p id="kpi4Sub" class="text-[11px] text-slate-500 dark:text-gray-400 mt-2">Aprobadas abiertas al corte</p>
        </div>
    </section>

    <!-- Panel Exclusivo de Comparación Trimestral (Ciclo) -->
    <section id="quarterComparisonSection" class="hidden bg-white dark:bg-gray-900 border-2 border-cresta-navy/20 dark:border-indigo-500/30 rounded-2xl p-5 shadow-sm">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white">COMPARATIVA TRIMESTRAL</span>
                    <h2 id="compareQTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Comparación Q vs Q</h2>
                </div>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Contrastando tiempos promedio (días) y cumplimiento de SLA entre los dos trimestres seleccionados</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            <div class="lg:col-span-6">
                <div id="chartCompareQ" class="w-full h-72"></div>
            </div>
            <div class="lg:col-span-6 overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead id="compareQTableHead" class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200"></thead>
                    <tbody id="compareQTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Fila 1 de Gráficas (Ciclo) -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-7">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">% Solicitudes en SAP dentro de plazo (por Quincena)</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Evaluado estrictamente sobre solicitudes que ya cumplieron la ventana de 7, 14 o 30 días</p>
            </div>
            <div id="chartQuincenas" class="w-full h-72"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-5">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Desglose del Ciclo Promedio a SAP (Días)</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Separación de responsabilidad: Aprobadores vs. Comprador</p>
            </div>
            <div id="chartCiclo" class="w-full h-72"></div>
        </div>
    </section>

    <!-- Fila 2 de Gráficas (Ciclo) -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-7">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Desempeño por Comprador (% OC en Odoo ≤14 días)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Gestión pura del comprador (Aprobación Solicitud → Creación OC Odoo) · Meta 70%</p>
                </div>
                <span id="badgeCompradoresPartial" class="hidden text-[10px] px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800 font-bold shrink-0">
                    Avance parcial (periodo en curso)
                </span>
            </div>
            <div id="chartCompradores" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-5">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Rezago Abierto Sin SAP por Mes de Origen</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Solicitudes aprobadas pendientes de sincronizar en SAP</p>
                </div>
                <button type="button" onclick="openAuditModal('REZAGO', 'GT30')"
                    class="text-[11px] px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/80 dark:hover:bg-red-900 text-cresta-red dark:text-red-200 border border-red-200 dark:border-red-800 font-bold shrink-0">
                    Ver > 30d
                </button>
            </div>
            <div id="chartRezago" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Fila 3: Ranking de Aprobadores -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Ranking Aprobadores de Solicitud (Días Promedio)</h2>
                    <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Creación Solicitud → Aprobación · Activos con ≥5 aprobaciones (más ágil arriba)</p>
                </div>
                <span id="badgeAprobSolAvg" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                    Prom: 0.0 d
                </span>
            </div>
            <div id="chartAprobadoresSol" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-6">
            <div class="mb-3 flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Ranking Aprobadores de OC Odoo (Días Promedio)</h2>
                    <p id="subAprobOc" class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Creación OC → Aprobación OC (Autorizador 1) · Ordenado de más ágil a más lento</p>
                </div>
                <span id="badgeAprobOcAvg" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                    Prom: 0.0 d
                </span>
            </div>
            <div id="chartAprobadoresOc" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Fila 4: Evolución de Comprador + Top Solicitantes por Volumen de Requisiciones -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-7">
            <div class="mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 id="chartCompTrendTitle" class="text-sm font-bold text-cresta-navy dark:text-white">
                        Evolución Mensual: Días Promedio por Comprador
                    </h2>
                    <p id="chartCompTrendSub" class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">
                        Velocidad de respuesta (Aprobación Solicitud → OC en Odoo)
                    </p>
                </div>
                <span id="chartCompTrendBadge" class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-300 font-bold shrink-0">
                    Comparativa Equipo
                </span>
            </div>
            <div id="chartCompradorTrend" class="w-full h-80"></div>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm lg:col-span-5">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-cresta-navy dark:text-white">Top 10 Solicitantes Internos (Requisiciones)</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Usuarios que generan mayor demanda de compra y su % de rechazo</p>
            </div>
            <div id="chartTopSolicitantesReqs" class="w-full h-80"></div>
        </div>
    </section>

    <!-- Tabla Ejecutiva Mensual -->
    <section class="bg-white dark:bg-gray-900 border border-slate-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm overflow-x-auto">
        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 id="monthlyTableTitle" class="text-sm font-bold text-cresta-navy dark:text-white">Indicadores Mensuales Consolidados</h2>
                <p class="text-xs text-slate-500 dark:text-gray-400 mt-0.5">Porcentajes calculados exclusivamente sobre solicitudes maduras (sin sesgo de corte reciente)</p>
            </div>
            <span class="text-[11px] text-slate-500 dark:text-gray-400">* Celda resaltada indica mejor desempeño mensual</span>
        </div>
        <table class="w-full text-xs text-left border-collapse">
            <thead id="monthlyTableHead" class="bg-cresta-navy text-white dark:bg-gray-800 dark:text-gray-200"></thead>
            <tbody id="monthlyTableBody" class="divide-y divide-slate-200 dark:divide-gray-800"></tbody>
        </table>
    </section>

</main>
