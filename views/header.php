<!-- Header Ejecutivo -->
<header class="border-b border-slate-200 dark:border-gray-800 bg-white/95 dark:bg-gray-900/95 sticky top-0 z-30 backdrop-blur shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 space-y-3">
        <!-- Fila Superior: Marca + Buscador Rápido + Navegación de 3 Módulos + Acciones -->
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-md text-white bg-cresta-navy dark:bg-cresta-red tracking-wide">
                        GRUPO CRESTA · SUPPLY CHAIN
                    </span>
                    <span class="text-xs text-slate-500 dark:text-gray-400">
                        Corte: <strong class="text-slate-800 dark:text-gray-200"><?= htmlspecialchars($payload['cutoff_date']) ?></strong>
                    </span>
                    <?php if ($forceSync && !empty($syncInfo['message'])): ?>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800 font-semibold">
                            <?= htmlspecialchars($syncInfo['message']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h1 id="mainHeaderTitle" class="text-lg sm:text-xl font-bold text-cresta-navy dark:text-white mt-1">
                    Evolución del Ciclo de Compras · Grupo Cresta
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <!-- Buscador Directo de Solicitud u OC -->
                <form onsubmit="handleQuickDocLookup(event)" class="inline-flex items-center">
                    <input id="quickDocInput" type="text" placeholder="Buscar Solicitud / OC..."
                        title="Escribe un código (ej. SV2396 o P17177) y presiona Enter"
                        class="bg-slate-50 dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-l-lg px-2.5 py-2 w-36 sm:w-40 focus:border-cresta-navy">
                    <button type="submit" title="Ver detalle del documento"
                        class="px-2.5 py-2 rounded-r-lg bg-slate-200 hover:bg-slate-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-cresta-navy dark:text-white font-bold border border-l-0 border-slate-300 dark:border-gray-700">
                        🔍
                    </button>
                </form>

                <!-- Selector Principal de 3 Módulos -->
                <div class="inline-flex rounded-xl bg-slate-100 dark:bg-gray-800 p-1 border border-slate-200 dark:border-gray-700">
                    <button id="btnTabCycle" type="button" onclick="setMainTab('cycle')"
                        class="px-3 py-1.5 rounded-lg font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                        Ciclo Operativo (SLA)
                    </button>
                    <button id="btnTabSpend" type="button" onclick="setMainTab('spend')"
                        class="px-3 py-1.5 rounded-lg font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        Gasto y Productos
                    </button>
                    <button id="btnTabSupplier" type="button" onclick="setMainTab('supplier')"
                        class="px-3 py-1.5 rounded-lg font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        Portal 360° Proveedor
                    </button>
                </div>

                <!-- Botón Sincronizar OneDrive -->
                <a href="?sync=1" title="Forzar actualización desde OneDrive"
                    class="inline-flex items-center gap-1 px-2.5 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-white hover:bg-slate-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-cresta-navy dark:text-gray-200 font-bold transition">
                    <span>↻</span>
                    <span class="hidden md:inline">Sincronizar</span>
                </a>

                <!-- Toggle Modo Claro / Oscuro -->
                <button id="btnThemeToggle" type="button" onclick="toggleTheme()" title="Alternar modo claro / oscuro"
                    class="inline-flex items-center gap-1 px-2.5 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 font-bold transition">
                    <span id="themeIcon">🌙</span>
                    <span id="themeLabel" class="hidden xl:inline">Oscuro</span>
                </button>

                <!-- Botón Cerrar Sesión -->
                <a href="?logout=1" title="Cerrar sesión y bloquear dashboard"
                    class="inline-flex items-center gap-1 px-2.5 py-2 rounded-lg border border-red-200 dark:border-red-900 bg-red-50 hover:bg-red-100 dark:bg-red-950/60 dark:hover:bg-red-900 text-cresta-red dark:text-red-300 font-bold transition">
                    <span>Salir</span>
                </a>
            </div>
        </div>

        <!-- Fila Inferior del Header: Barra Unificada de Filtros Temporales y Dimensionales -->
        <div class="pt-2.5 border-t border-slate-100 dark:border-gray-800 flex flex-wrap items-center gap-2 text-xs">
            <select id="filterYear" onchange="onYearChange()" title="Seleccionar Año"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-cresta-navy dark:text-white rounded-lg px-2.5 py-1.5 font-bold shadow-xs">
            </select>

            <div class="inline-flex rounded-lg bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700">
                <button id="btnModeMonth" type="button" onclick="setPeriodMode('month')"
                    class="px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                    Mes
                </button>
                <button id="btnModeQuarter" type="button" onclick="setPeriodMode('quarter')"
                    class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                    Trimestre (Q)
                </button>
                <button id="btnModeCompareQ" type="button" onclick="setPeriodMode('compare_q')"
                    class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                    Comparar Q
                </button>
                <button id="btnModeYTD" type="button" onclick="setPeriodMode('ytd')"
                    class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                    YTD
                </button>
            </div>

            <select id="filterMes" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
            </select>

            <select id="filterQuarter" onchange="renderDashboard()"
                class="hidden bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
            </select>

            <div id="compareQControls" class="hidden inline-flex items-center gap-1.5">
                <select id="filterCompareQ1" onchange="renderDashboard()" title="Trimestre Principal (Base)"
                    class="bg-white dark:bg-gray-800 border border-cresta-navy dark:border-indigo-500 text-cresta-navy dark:text-white rounded-lg px-2.5 py-1.5 font-bold shadow-xs">
                </select>
                <span class="text-slate-400 font-bold">vs</span>
                <select id="filterCompareQ2" onchange="renderDashboard()" title="Trimestre de Referencia"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                </select>
            </div>

            <select id="filterSociedad" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                <option value="ALL">Todas las Sociedades</option>
            </select>

            <select id="filterComprador" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs">
                <option value="ALL">Todos los Compradores</option>
            </select>

            <select id="filterSolicitante" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs max-w-[200px]">
                <option value="ALL">Todos los Solicitantes</option>
            </select>

            <!-- Controles compartidos entre Gasto y Portal 360 Proveedor -->
            <div id="spendOnlyFilters" class="hidden flex flex-wrap items-center gap-2">
                <select id="filterProveedor" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-lg px-2.5 py-1.5 font-medium shadow-xs max-w-[210px]">
                    <option value="ALL">Todos los Proveedores</option>
                </select>

                <div class="inline-flex rounded-lg bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700">
                    <button id="btnCurrGTQ" type="button" onclick="setCurrency('GTQ')"
                        class="px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                        GTQ (Q)
                    </button>
                    <button id="btnCurrUSD" type="button" onclick="setCurrency('USD')"
                        class="px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        USD ($)
                    </button>
                </div>

                <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 cursor-pointer select-none">
                    <input id="chkExcludePettyCash" type="checkbox" onchange="renderDashboard()" class="rounded text-cresta-navy">
                    <span class="text-[11px] font-medium text-slate-700 dark:text-gray-300">Ocultar Caja Chica</span>
                </label>
            </div>
        </div>
    </div>
</header>
