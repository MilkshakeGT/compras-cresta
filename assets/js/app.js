/* =========================================================================
 * NÚCLEO GLOBAL · ESTADO, UTILIDADES, FILTROS Y NAVEGACIÓN (app.js)
 * ========================================================================= */

let mainTab = 'cycle'; // 'cycle' | 'spend' | 'supplier'
let periodMode = 'month'; // 'month' | 'quarter' | 'compare_q' | 'ytd'
let activeCurrency = 'GTQ'; // 'GTQ' | 'USD'
let charts = {};
let isDark = false;
let currentTop5ProvidersSet = new Set();
let activeDocDetail = null;

// Caché bajo demanda de líneas de OCs firmes (OC.xlsx + DB.xlsx)
let firmLinesCache = null;
let firmLinesPromise = null;
let cachedSup360Products = [];
let cachedSup360Ocs = [];

if (window.location.search.includes('sync=1')) {
    window.history.replaceState({}, document.title, window.location.pathname);
}

const MONTH_NAMES = {
    '01': 'Ene', '02': 'Feb', '03': 'Mar', '04': 'Abr',
    '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Ago',
    '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dic'
};

const QUARTER_DEFS = [
    { id: 'Q1', label: 'Q1 (Ene–Mar)', short: 'Q1', months: ['01', '02', '03'] },
    { id: 'Q2', label: 'Q2 (Abr–Jun)', short: 'Q2', months: ['04', '05', '06'] },
    { id: 'Q3', label: 'Q3 (Jul–Sep)', short: 'Q3', months: ['07', '08', '09'] },
    { id: 'Q4', label: 'Q4 (Oct–Dic)', short: 'Q4', months: ['10', '11', '12'] }
];

// =========================================================================
// CARGA BAJO DEMANDA DE LÍNEAS DE OCs EN FIRME
// =========================================================================
async function ensureFirmLinesLoaded() {
    if (firmLinesCache !== null) return firmLinesCache;
    if (!firmLinesPromise) {
        firmLinesPromise = fetch('?action=firm_lines')
            .then(r => r.json())
            .then(data => {
                firmLinesCache = data.lines || [];
                return firmLinesCache;
            })
            .catch(() => {
                firmLinesCache = [];
                return firmLinesCache;
            });
    }
    return firmLinesPromise;
}

// =========================================================================
// UTILIDADES DE FORMATEO Y ESTADÍSTICA
// =========================================================================
function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getQuarterFromYm(ym) {
    const m = ym.split('-')[1];
    const found = QUARTER_DEFS.find(q => q.months.includes(m));
    return found ? found.id : 'Q1';
}

function formatMoney(val, curr = activeCurrency) {
    if (val === null || val === undefined || isNaN(val)) return curr === 'USD' ? '$ 0.00' : 'Q 0.00';
    const prefix = curr === 'USD' ? '$ ' : 'Q ';
    return prefix + Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatMoneyCompact(val, curr = activeCurrency) {
    const prefix = curr === 'USD' ? '$' : 'Q';
    if (Math.abs(val) >= 1000000) return `${prefix}${(val / 1000000).toFixed(2)}M`;
    if (Math.abs(val) >= 1000) return `${prefix}${(val / 1000).toFixed(1)}k`;
    return `${prefix}${val.toFixed(0)}`;
}

function formatYm(ym) {
    const [y, m] = ym.split('-');
    return `${MONTH_NAMES[m] || m} ${y}`;
}

function formatMonthOnly(ym) {
    const m = ym.split('-')[1];
    return MONTH_NAMES[m] || m;
}

function formatYearQuarter(yq) {
    const [y, qId] = yq.split('-');
    const qObj = QUARTER_DEFS.find(q => q.id === qId);
    return qObj ? `${qObj.short} ${y} (${qObj.label.split(' ')[1]})` : yq;
}

function formatYearQuarterShort(yq) {
    const [y, qId] = yq.split('-');
    return `${qId} ${y}`;
}

function shortName(fullName) {
    return String(fullName || '').split(' ').slice(0, 2).join(' ');
}

function shortSupplier(name) {
    return name.length > 26 ? name.slice(0, 25) + '…' : name;
}

function shortProduct(name) {
    return name.length > 32 ? name.slice(0, 31) + '…' : name;
}

function median(arr) {
    if (!arr.length) return 0;
    const s = [...arr].sort((a, b) => a - b);
    const mid = Math.floor(s.length / 2);
    return s.length % 2 !== 0 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
}

function avg(arr) {
    if (!arr.length) return 0;
    return arr.reduce((a, b) => a + b, 0) / arr.length;
}

function getChartThemeConfig() {
    return {
        mode: isDark ? 'dark' : 'light',
        labelColor: isDark ? '#9CA3AF' : '#475569',
        gridColor: isDark ? '#374151' : '#E2E8F0'
    };
}

function downloadCsvBlob(content, filename) {
    const blob = new Blob(["\uFEFF" + content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// =========================================================================
// TEMA CLARO / OSCURO Y NAVEGACIÓN DE PESTAÑAS
// =========================================================================
function initTheme() {
    const saved = localStorage.getItem('cresta_dashboard_theme');
    isDark = (saved === 'dark');
    applyThemeUI();
}

function toggleTheme() {
    isDark = !isDark;
    localStorage.setItem('cresta_dashboard_theme', isDark ? 'dark' : 'light');
    applyThemeUI();
    renderDashboard();
}

function applyThemeUI() {
    const htmlEl = document.documentElement;
    const iconEl = document.getElementById('themeIcon');
    const labelEl = document.getElementById('themeLabel');

    if (isDark) {
        htmlEl.classList.add('dark');
        iconEl.textContent = '☀️';
        labelEl.textContent = 'Claro';
    } else {
        htmlEl.classList.remove('dark');
        iconEl.textContent = '🌙';
        labelEl.textContent = 'Oscuro';
    }
    updateMainTabButtons();
    updatePeriodButtons();
    updateCurrencyButtons();
}

function setMainTab(tab) {
    mainTab = tab;
    updateMainTabButtons();
    renderDashboard();
}

function updateMainTabButtons() {
    const btnCycle = document.getElementById('btnTabCycle');
    const btnSpend = document.getElementById('btnTabSpend');
    const btnSup   = document.getElementById('btnTabSupplier');

    const secCycle = document.getElementById('viewCycleSection');
    const secSpend = document.getElementById('viewSpendSection');
    const secSup   = document.getElementById('viewSupplierSection');

    const spendFilters = document.getElementById('spendOnlyFilters');
    const filterProvEl = document.getElementById('filterProveedor');
    const titleEl = document.getElementById('mainHeaderTitle');

    const activeCls = 'px-3 py-1.5 rounded-lg font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-3 py-1.5 rounded-lg font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    btnCycle.className = mainTab === 'cycle' ? activeCls : inactiveCls;
    btnSpend.className = mainTab === 'spend' ? activeCls : inactiveCls;
    btnSup.className   = mainTab === 'supplier' ? activeCls : inactiveCls;

    secCycle.classList.add('hidden');
    secSpend.classList.add('hidden');
    secSup.classList.add('hidden');
    spendFilters.classList.add('hidden');
    filterProvEl.classList.remove('hidden');

    if (mainTab === 'cycle') {
        secCycle.classList.remove('hidden');
        titleEl.textContent = 'Evolución del Ciclo de Compras · Grupo Cresta';
    } else if (mainTab === 'spend') {
        secSpend.classList.remove('hidden');
        spendFilters.classList.remove('hidden');
        titleEl.textContent = 'Gestión de Gasto, Productos y Proveedores · Grupo Cresta';
    } else {
        secSup.classList.remove('hidden');
        spendFilters.classList.remove('hidden');
        filterProvEl.classList.add('hidden');
        titleEl.textContent = 'Portal 360° de Proveedores (OCs en Firme) · Grupo Cresta';
    }
}

function setCurrency(curr) {
    activeCurrency = curr;
    updateCurrencyButtons();
    renderDashboard();
}

function updateCurrencyButtons() {
    const btnG = document.getElementById('btnCurrGTQ');
    const btnU = document.getElementById('btnCurrUSD');
    const activeCls = 'px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    btnG.className = activeCurrency === 'GTQ' ? activeCls : inactiveCls;
    btnU.className = activeCurrency === 'USD' ? activeCls : inactiveCls;
}

function setPeriodMode(mode) {
    periodMode = mode;
    updatePeriodButtons();
    renderDashboard();
}

function updatePeriodButtons() {
    const btnM = document.getElementById('btnModeMonth');
    const btnQ = document.getElementById('btnModeQuarter');
    const btnCQ = document.getElementById('btnModeCompareQ');
    const btnY = document.getElementById('btnModeYTD');

    const selYear = document.getElementById('filterYear');
    const selMes = document.getElementById('filterMes');
    const selQ = document.getElementById('filterQuarter');
    const compQBox = document.getElementById('compareQControls');
    const compQSec = document.getElementById('quarterComparisonSection');
    const spendCompQSec = document.getElementById('spendCompareQSection');
    const supCompQSec = document.getElementById('sup360CompareQSection');

    const activeCls = 'px-2.5 py-1 rounded-md font-bold text-white bg-cresta-navy dark:bg-cresta-red transition';
    const inactiveCls = 'px-2.5 py-1 rounded-md font-bold text-slate-600 dark:text-gray-400 hover:text-slate-900 dark:hover:text-white transition';

    btnM.className = periodMode === 'month' ? activeCls : inactiveCls;
    btnQ.className = periodMode === 'quarter' ? activeCls : inactiveCls;
    btnCQ.className = periodMode === 'compare_q' ? activeCls : inactiveCls;
    btnY.className = periodMode === 'ytd' ? activeCls : inactiveCls;

    selYear.classList.add('hidden');
    selMes.classList.add('hidden');
    selQ.classList.add('hidden');
    compQBox.classList.add('hidden');
    compQSec.classList.add('hidden');
    spendCompQSec.classList.add('hidden');
    supCompQSec.classList.add('hidden');

    if (periodMode === 'compare_q') {
        compQBox.classList.remove('hidden');
        compQSec.classList.remove('hidden');
        spendCompQSec.classList.remove('hidden');
        supCompQSec.classList.remove('hidden');
    } else if (periodMode === 'quarter') {
        selYear.classList.remove('hidden');
        selQ.classList.remove('hidden');
    } else {
        selYear.classList.remove('hidden');
        selMes.classList.remove('hidden');
    }
}

// =========================================================================
// INICIALIZACIÓN Y MOTOR DE FILTRADO GLOBAL
// =========================================================================
function populateYearPeriodOptions(year) {
    const selMes = document.getElementById('filterMes');
    const selQ = document.getElementById('filterQuarter');
    const yearMeses = RAW_DATA.filters.meses.filter(ym => ym.startsWith(`${year}-`));

    selMes.innerHTML = '';
    yearMeses.forEach(ym => {
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = formatYm(ym);
        selMes.appendChild(opt);
    });
    if (yearMeses.length > 0) {
        selMes.value = yearMeses[yearMeses.length - 1];
    }

    const availableQs = new Set(yearMeses.map(getQuarterFromYm));
    selQ.innerHTML = '';
    QUARTER_DEFS.forEach(q => {
        if (availableQs.has(q.id)) {
            const opt = document.createElement('option');
            opt.value = q.id;
            opt.textContent = `${q.label} ${year}`;
            selQ.appendChild(opt);
        }
    });
    if (selQ.options.length > 0) {
        selQ.value = selQ.options[selQ.options.length - 1].value;
    }
}

function populateAllCompareQuarters() {
    const selCQ1 = document.getElementById('filterCompareQ1');
    const selCQ2 = document.getElementById('filterCompareQ2');

    const yqSet = new Set(RAW_DATA.filters.meses.map(ym => `${ym.split('-')[0]}-${getQuarterFromYm(ym)}`));
    const allYQs = [...yqSet].sort();

    selCQ1.innerHTML = '';
    selCQ2.innerHTML = '';

    allYQs.forEach(yq => {
        const o1 = document.createElement('option');
        o1.value = yq;
        o1.textContent = formatYearQuarter(yq);
        selCQ1.appendChild(o1);

        const o2 = document.createElement('option');
        o2.value = yq;
        o2.textContent = formatYearQuarter(yq);
        selCQ2.appendChild(o2);
    });

    if (allYQs.length >= 2) {
        selCQ1.value = allYQs[allYQs.length - 1];
        selCQ2.value = allYQs[allYQs.length - 2];
    } else if (allYQs.length === 1) {
        selCQ1.value = allYQs[0];
        selCQ2.value = allYQs[0];
    }
}

function initFilters() {
    const selYear = document.getElementById('filterYear');
    const selSoc = document.getElementById('filterSociedad');
    const selComp = document.getElementById('filterComprador');
    const selSol = document.getElementById('filterSolicitante');
    const selProv = document.getElementById('filterProveedor');
    const selSup360 = document.getElementById('sup360Selector');

    const years = [...new Set(RAW_DATA.filters.meses.map(ym => ym.split('-')[0]))].sort();
    years.forEach(y => {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = `Año ${y}`;
        selYear.appendChild(opt);
    });
    const latestYear = years.length > 0 ? years[years.length - 1] : String(new Date().getFullYear());
    selYear.value = latestYear;

    populateYearPeriodOptions(latestYear);
    populateAllCompareQuarters();

    RAW_DATA.filters.sociedades.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        selSoc.appendChild(opt);
    });

    RAW_DATA.filters.compradores.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        selComp.appendChild(opt);
    });

    (RAW_DATA.filters.solicitantes || []).forEach(sol => {
        const opt = document.createElement('option');
        opt.value = sol;
        opt.textContent = sol;
        selSol.appendChild(opt);
    });

    (RAW_DATA.filters.proveedores || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p;
        opt.textContent = p;
        selProv.appendChild(opt);

        const opt360 = document.createElement('option');
        opt360.value = p;
        opt360.textContent = p;
        selSup360.appendChild(opt360);
    });
}

function onYearChange() {
    const year = document.getElementById('filterYear').value;
    populateYearPeriodOptions(year);
    renderDashboard();
}

function filterByEntity(records) {
    const soc = document.getElementById('filterSociedad').value;
    const comp = document.getElementById('filterComprador').value;
    const sol = document.getElementById('filterSolicitante').value;
    return records.filter(r => {
        if (soc !== 'ALL' && r.sociedad !== soc) return false;
        if (comp !== 'ALL' && r.comprador !== comp) return false;
        if (sol !== 'ALL' && r.solicitado_por !== sol) return false;
        return true;
    });
}

function filterSpendRecordsBase(records, overrideProv = null) {
    const soc = document.getElementById('filterSociedad').value;
    const comp = document.getElementById('filterComprador').value;
    const sol = document.getElementById('filterSolicitante').value;
    const prov = overrideProv !== null ? overrideProv : document.getElementById('filterProveedor').value;
    const hidePetty = document.getElementById('chkExcludePettyCash').checked;

    return records.filter(r => {
        if (r.divisa !== activeCurrency) return false;
        if (soc !== 'ALL' && r.sociedad !== soc) return false;
        if (comp !== 'ALL' && r.comprador !== comp) return false;
        if (sol !== 'ALL' && r.solicitado_por !== sol) return false;
        if (prov !== 'ALL' && r.proveedor !== prov) return false;
        if (hidePetty && r.is_caja_chica) return false;
        return true;
    });
}

function filterBySociedadOnly(records) {
    const soc = document.getElementById('filterSociedad').value;
    const sol = document.getElementById('filterSolicitante').value;
    return records.filter(r => {
        if (soc !== 'ALL' && r.sociedad !== soc) return false;
        if (sol !== 'ALL' && r.solicitado_por !== sol) return false;
        return true;
    });
}

function filterRecordsByYearQuarter(records, yq) {
    const [year, qId] = yq.split('-');
    const qDef = QUARTER_DEFS.find(q => q.id === qId) || QUARTER_DEFS[0];
    return records.filter(r => r.ym.startsWith(`${year}-`) && qDef.months.includes(r.ym.split('-')[1]));
}

function getMaxYmForYearQuarter(yq) {
    const [year, qId] = yq.split('-');
    const qDef = QUARTER_DEFS.find(q => q.id === qId) || QUARTER_DEFS[0];
    const qMeses = RAW_DATA.filters.meses.filter(ym => ym.startsWith(`${year}-`) && qDef.months.includes(ym.split('-')[1]));
    return qMeses.length ? qMeses[qMeses.length - 1] : `${year}-${qDef.months[2]}`;
}

function getMaxYmForCurrentSelection() {
    const year = document.getElementById('filterYear').value;
    if (periodMode === 'compare_q') {
        return getMaxYmForYearQuarter(document.getElementById('filterCompareQ1').value);
    }
    if (periodMode === 'quarter') {
        return getMaxYmForYearQuarter(`${year}-${document.getElementById('filterQuarter').value}`);
    }
    return document.getElementById('filterMes').value;
}

function filterByActivePeriod(records) {
    const year = document.getElementById('filterYear').value;
    if (periodMode === 'compare_q') {
        return filterRecordsByYearQuarter(records, document.getElementById('filterCompareQ1').value);
    }
    if (periodMode === 'month') {
        const targetYm = document.getElementById('filterMes').value;
        return records.filter(r => r.ym === targetYm);
    }
    if (periodMode === 'quarter') {
        return filterRecordsByYearQuarter(records, `${year}-${document.getElementById('filterQuarter').value}`);
    }
    const targetYm = document.getElementById('filterMes').value;
    return records.filter(r => r.ym.startsWith(`${year}-`) && r.ym <= targetYm);
}

function getActivePeriodLabel() {
    const year = document.getElementById('filterYear').value;
    if (periodMode === 'compare_q') {
        return `${formatYearQuarterShort(document.getElementById('filterCompareQ1').value)} vs ${formatYearQuarterShort(document.getElementById('filterCompareQ2').value)}`;
    }
    if (periodMode === 'quarter') {
        return `${document.getElementById('filterQuarter').value} ${year}`;
    }
    if (periodMode === 'ytd') {
        return `YTD ${year} (hasta ${formatYm(document.getElementById('filterMes').value)})`;
    }
    return formatYm(document.getElementById('filterMes').value);
}

// =========================================================================
// ENRUTADOR PRINCIPAL DEL DASHBOARD
// =========================================================================
function renderDashboard() {
    if (mainTab === 'cycle') {
        renderCycleView();
    } else if (mainTab === 'spend') {
        renderSpendView();
    } else {
        renderSupplier360View();
    }
}
