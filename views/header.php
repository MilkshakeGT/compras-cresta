<?php
// =========================================================================
// SECCIÓN 1: ENCABEZADO EJECUTIVO COMPACTO (ESCRITORIO Y MÓVIL PWA)
// =========================================================================
?>
<header class="border-b border-slate-200 dark:border-gray-800 bg-white/95 dark:bg-gray-900/95 sticky top-0 z-30 backdrop-blur shadow-xs">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-2 space-y-2">

        <!-- =================================================================
             FILA 1: IDENTIDAD, MÓDULOS, BUSCADOR Y ACCIONES EN ALTURA REDUCIDA
             ================================================================= -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">

            <!-- Bloque Izquierdo: Marca + Título Completo + Fechas de Corte y Odoo -->
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between lg:justify-start gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-navy dark:bg-cresta-red text-white tracking-wide shrink-0">
                            GRUPO CRESTA
                        </span>
                        <h1 id="mainHeaderTitle" class="text-xs sm:text-base font-bold text-cresta-navy dark:text-white leading-tight truncate">
                            Evolución del Ciclo de Compras · Grupo Cresta
                        </h1>
                    </div>

                    <!-- Badge de Usuario visible arriba en móvil para ahorrar espacio vertical -->
                    <span class="lg:hidden inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap shrink-0 <?= !empty($isManager)
                        ? 'bg-indigo-50 text-cresta-navy border border-indigo-200 dark:bg-indigo-950/80 dark:text-indigo-300 dark:border-indigo-800'
                        : 'bg-sky-50 text-cresta-blue border border-sky-200 dark:bg-sky-950/80 dark:text-sky-300 dark:border-sky-800' ?>">
                        👤 <?= htmlspecialchars((string)($currentUser['display_name'] ?? '')) ?>
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5 text-[11px] text-slate-500 dark:text-gray-400">
                    <span>
                        Corte: <strong class="text-slate-700 dark:text-gray-200"><?= htmlspecialchars((string)($payload['cutoff_date'] ?? date('Y-m-d'))) ?></strong>
                    </span>
                    <?php if (!empty($odooSyncInfo['last_sync'])): ?>
                        <span class="text-slate-400 dark:text-gray-500" title="Última sincronización directa con Odoo">
                            · Odoo: <?= htmlspecialchars((string)$odooSyncInfo['last_sync']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bloque Derecho: Notificación + Usuario (Desktop) + Módulos + Buscador + Acciones -->
            <div class="flex flex-col lg:items-end gap-1.5 min-w-0">

                <!-- Subfila Superior: Notificación Verde + Badge de Usuario (Desktop) -->
                <div class="flex flex-wrap items-center justify-between lg:justify-end gap-1.5 w-full">
                    <?php if (!empty($uploadFeedback) && !empty($uploadFeedback['ok'])): ?>
                        <div id="headerSyncBanner" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800 text-[10px] font-semibold max-w-full">
                            <span class="truncate">✓ <?= htmlspecialchars((string)$uploadFeedback['message']) ?></span>
                            <button type="button" onclick="document.getElementById('headerSyncBanner').remove()" class="ml-1 opacity-70 hover:opacity-100 font-bold shrink-0" title="Ocultar aviso">✕</button>
                        </div>
                    <?php endif; ?>

                    <span class="hidden lg:inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap <?= !empty($isManager)
                        ? 'bg-indigo-50 text-cresta-navy border border-indigo-200 dark:bg-indigo-950/80 dark:text-indigo-300 dark:border-indigo-800'
                        : 'bg-sky-50 text-cresta-blue border border-sky-200 dark:bg-sky-950/80 dark:text-sky-300 dark:border-sky-800' ?>">
                        👤 <?= htmlspecialchars((string)($currentUser['display_name'] ?? '')) ?>
                        <span class="opacity-75">(<?= !empty($isManager) ? 'Manager' : 'Comprador' ?>)</span>
                    </span>
                </div>

                <!-- Subfila Inferior: Módulos + Buscador + Herramientas -->
                <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-1.5 text-xs w-full lg:w-auto">

                    <!-- Selector Principal de 3 Módulos (ancho completo en móvil con scroll suave) -->
                    <div class="inline-flex rounded-lg bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700 overflow-x-auto no-scrollbar max-w-full">
                        <button id="btnTabCycle" type="button" onclick="setMainTab('cycle')"
                            class="flex-1 sm:flex-none px-2.5 py-1 rounded-md text-[11px] font-bold text-white bg-cresta-navy dark:bg-cresta-red transition whitespace-nowrap">
                            Ciclo SLA
                        </button>
                        <button id="btnTabSpend" type="button" onclick="setMainTab('spend')"
                            class="flex-1 sm:flex-none px-2.5 py-1 rounded-md text-[11px] font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition whitespace-nowrap">
                            Gasto y Productos
                        </button>
                        <button id="btnTabSupplier" type="button" onclick="setMainTab('supplier')"
                            class="flex-1 sm:flex-none px-2.5 py-1 rounded-md text-[11px] font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition whitespace-nowrap">
                            Proveedor 360°
                        </button>
                    </div>

                    <!-- Buscador Global + Botones de Acción en una fila fluida -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto">
                        <!-- Buscador Global Compacto -->
                        <form onsubmit="handleQuickDocLookup(event)" class="inline-flex items-center flex-1 sm:flex-none min-w-0">
                            <input id="quickDocInput" type="text" placeholder="PR, OC, SKU..."
                                title="Busca por Solicitud (SV2396), OC (P17177), SKU (M017675) o descripción"
                                class="bg-slate-50 dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-l-lg px-2 py-1 text-[11px] w-full sm:w-36 sm:focus:w-44 transition-all focus:border-cresta-navy min-w-0">
                            <button type="submit" title="Buscar en solicitudes, OCs y líneas de artículos"
                                class="px-2.5 py-1 rounded-r-lg bg-slate-200 hover:bg-slate-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-cresta-navy dark:text-white text-[11px] font-bold border border-l-0 border-slate-300 dark:border-gray-700 shrink-0">
                                🔍
                            </button>
                        </form>

                        <!-- Barra de Acciones Compactas -->
                        <div class="inline-flex items-center gap-1 shrink-0">
                            <!-- Descargar PDF -->
                            <button id="btnExportTabPDF" type="button" onclick="exportActiveTabPDF()" title="Descargar informe gráfico en PDF"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-cresta-red hover:opacity-90 text-white text-[11px] font-bold transition">
                                <span>📄</span>
                                <span class="hidden xl:inline">PDF</span>
                            </button>

                            <?php if (!empty($isManager)): ?>
                                <!-- Sincronizar Odoo -->
                                <form method="POST" class="inline" onsubmit="const b=this.querySelector('button'); b.disabled=true; b.innerHTML='<span>⏳</span><span class=\'hidden xl:inline\'>Sinc...</span>';">
                                    <input type="hidden" name="action" value="sync_odoo_api">
                                    <button type="submit" title="Sincronizar directamente con Odoo 17"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-bold transition">
                                        <span>🔄</span>
                                        <span class="hidden xl:inline">Sincronizar</span>
                                    </button>
                                </form>

                                <!-- Respaldo Excel -->
                                <button type="button" onclick="openAdminUploadModal()" title="Respaldo: Cargar archivos Excel de Odoo"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 dark:border-gray-700 bg-white hover:bg-slate-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-cresta-navy dark:text-gray-200 text-[11px] font-bold transition">
                                    <span>📊</span>
                                    <span class="hidden 2xl:inline">Excel</span>
                                </button>

                                <!-- Configuración -->
                                <button type="button" onclick="openAdminUsersModal()" title="Configuración de usuarios, fechas y estatus"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 dark:border-gray-700 bg-white hover:bg-slate-100 dark:bg-gray-800 dark:hover:bg-gray-700 text-cresta-navy dark:text-gray-200 text-[11px] font-bold transition">
                                    <span>⚙️</span>
                                    <span class="hidden xl:inline">Config</span>
                                </button>
                            <?php endif; ?>

                            <!-- Modo Claro / Oscuro -->
                            <button id="btnThemeToggle" type="button" onclick="toggleTheme()" title="Alternar modo claro / oscuro"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 dark:border-gray-700 bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-200 text-[11px] font-bold transition">
                                <span id="themeIcon">🌙</span>
                                <span id="themeLabel" class="hidden">Oscuro</span>
                            </button>

                            <!-- Salir -->
                            <a href="?logout=1" title="Cerrar sesión"
                                class="inline-flex items-center px-2 py-1 rounded-lg border border-red-200 dark:border-red-900 bg-red-50 hover:bg-red-100 dark:bg-red-950/60 dark:hover:bg-red-900 text-cresta-red dark:text-red-300 text-[11px] font-bold transition">
                                Salir
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================================
             SECCIÓN 2: BARRA DE FILTROS COMPACTA Y RESPONSIVA (MÓVIL + DESKTOP)
             ================================================================= -->
        <div class="pt-1.5 border-t border-slate-100 dark:border-gray-800 flex flex-nowrap sm:flex-wrap items-center gap-1.5 text-[11px] overflow-x-auto no-scrollbar pb-0.5 sm:pb-0">
            <select id="filterYear" onchange="onYearChange()" title="Seleccionar Año"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-cresta-navy dark:text-white rounded-md px-2 py-1 font-bold shrink-0">
            </select>

            <div class="inline-flex rounded-md bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700 shrink-0">
                <button id="btnModeMonth" type="button" onclick="setPeriodMode('month')"
                    class="px-2 py-0.5 rounded font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                    Mes
                </button>
                <button id="btnModeQuarter" type="button" onclick="setPeriodMode('quarter')"
                    class="px-2 py-0.5 rounded font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                    Q
                </button>
                <button id="btnModeCompareQ" type="button" onclick="setPeriodMode('compare_q')"
                    class="px-2 py-0.5 rounded font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                    Comp. Q
                </button>
                <button id="btnModeYTD" type="button" onclick="setPeriodMode('ytd')"
                    class="px-2 py-0.5 rounded font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                    YTD
                </button>
            </div>

            <select id="filterMes" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium shrink-0">
            </select>

            <select id="filterQuarter" onchange="renderDashboard()"
                class="hidden bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium shrink-0">
            </select>

            <div id="compareQControls" class="hidden inline-flex items-center gap-1 shrink-0">
                <select id="filterCompareQ1" onchange="renderDashboard()" title="Trimestre Principal (Base)"
                    class="bg-white dark:bg-gray-800 border border-cresta-navy dark:border-indigo-500 text-cresta-navy dark:text-white rounded-md px-2 py-1 font-bold">
                </select>
                <span class="text-slate-400 font-bold">vs</span>
                <select id="filterCompareQ2" onchange="renderDashboard()" title="Trimestre de Referencia"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium">
                </select>
            </div>

            <select id="filterSociedad" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium shrink-0">
                <option value="ALL">Todas las Sociedades</option>
            </select>

            <select id="filterComprador" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium shrink-0">
                <option value="ALL">Todos los Compradores</option>
            </select>

            <select id="filterSolicitante" onchange="renderDashboard()"
                class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium max-w-[175px] shrink-0">
                <option value="ALL">Todos los Solicitantes</option>
            </select>

            <!-- Checkbox Global: Excluir Compras de Caja Chica en todos los módulos (Ciclo SLA, Gasto y 360°) -->
            <label title="Oculta órdenes de compra emitidas a nombre de compradores internos (Caja Chica)"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 cursor-pointer select-none whitespace-nowrap shrink-0">
                <input id="chkExcludePettyCash" type="checkbox" onchange="renderDashboard()" class="rounded text-cresta-navy">
                <span class="font-medium text-slate-700 dark:text-gray-300">Sin Caja Chica</span>
            </label>

            <!-- Controles adicionales para Gasto y Portal 360 Proveedor -->
            <div id="spendOnlyFilters" class="hidden flex items-center gap-1.5 shrink-0">
                <select id="filterProveedor" onchange="renderDashboard()"
                    class="bg-white dark:bg-gray-800 border border-slate-300 dark:border-gray-700 text-slate-800 dark:text-gray-100 rounded-md px-2 py-1 font-medium max-w-[185px]">
                    <option value="ALL">Todos los Proveedores</option>
                </select>

                <div class="inline-flex rounded-md bg-slate-100 dark:bg-gray-800 p-0.5 border border-slate-200 dark:border-gray-700">
                    <button id="btnCurrGTQ" type="button" onclick="setCurrency('GTQ')"
                        class="px-2 py-0.5 rounded font-bold text-white bg-cresta-navy dark:bg-cresta-red transition">
                        GTQ
                    </button>
                    <button id="btnCurrUSD" type="button" onclick="setCurrency('USD')"
                        class="px-2 py-0.5 rounded font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition">
                        USD
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
