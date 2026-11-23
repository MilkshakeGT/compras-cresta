<?php
// Espera las variables $filesStatus y $uploadFeedback definidas en index.php
$filesStatus    = $filesStatus ?? [];
$uploadFeedback = $uploadFeedback ?? null;
$autoOpenAdmin  = ($uploadFeedback !== null);
?>
<!-- Modal Administración: Carga Directa de Reportes Crudos de Odoo (.xlsx) -->
<div id="adminUploadModal" class="fixed inset-0 z-50 <?= $autoOpenAdmin ? '' : 'hidden' ?> bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-indigo-500 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        <!-- Cabecera -->
        <div class="p-5 bg-cresta-navy text-white flex items-center justify-between gap-3">
            <div>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-red tracking-wider uppercase">
                    ADMINISTRACIÓN DE DATOS · ODOO
                </span>
                <h3 class="text-base sm:text-lg font-bold mt-1">Cargar Reportes Crudos de Odoo (.xlsx)</h3>
                <p class="text-xs text-slate-300 mt-0.5">Sube los archivos exportados directamente de Odoo sin fórmulas ni edición manual</p>
            </div>
            <button type="button" onclick="closeAdminUploadModal()"
                class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition shrink-0">
                Cerrar ✕
            </button>
        </div>

        <!-- Formulario de Carga -->
        <form method="POST" enctype="multipart/form-data" onsubmit="onAdminUploadSubmit(this)" class="p-5 overflow-y-auto space-y-4 text-xs">
            <input type="hidden" name="action" value="upload_odoo_files">

            <?php if ($uploadFeedback !== null): ?>
                <div class="p-3.5 rounded-xl border font-semibold <?= !empty($uploadFeedback['ok'])
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-300'
                    : 'bg-red-50 border-red-200 text-cresta-red dark:bg-red-950/70 dark:border-red-800 dark:text-red-300' ?>">
                    <?= htmlspecialchars((string)$uploadFeedback['message']) ?>
                </div>
            <?php endif; ?>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-gray-800/60 border border-slate-200 dark:border-gray-700 text-[11px] text-slate-600 dark:text-gray-300">
                Puedes actualizar <strong>uno, dos o los tres archivos</strong> a la vez. Los archivos que dejes sin seleccionar conservarán su última versión cargada en el servidor.
            </div>

            <!-- Archivo 1: Maestro Solicitudes y OCs -->
            <?php $stDb = $filesStatus['file_db'] ?? ['exists' => false, 'updated' => 'Sin cargar', 'size' => '0 KB']; ?>
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800/40 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="input_file_db" class="font-bold text-cresta-navy dark:text-white text-xs">
                        1. Maestro de Solicitudes y OCs <span class="text-slate-400 font-normal">(purchase.request)</span>
                    </label>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $stDb['exists']
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                        : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
                        <?= $stDb['exists'] ? "Vigente: {$stDb['updated']} ({$stDb['size']})" : 'Pendiente de cargar' ?>
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-gray-400">
                    Columnas clave: <code>Referencia de Solicitud</code>, <code>Estado</code>, <code>Seguidor</code>, <code>Purchase Orders</code>, <code>Purchase Orders/DocNum SAP</code>...
                </p>
                <input id="input_file_db" name="file_db" type="file" accept=".xlsx"
                    class="block w-full text-xs text-slate-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-cresta-navy file:text-white hover:file:bg-cresta-blue cursor-pointer">
            </div>

            <!-- Archivo 2: Líneas de Solicitud -->
            <?php $stSol = $filesStatus['file_sol'] ?? ['exists' => false, 'updated' => 'Sin cargar', 'size' => '0 KB']; ?>
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800/40 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="input_file_sol" class="font-bold text-cresta-navy dark:text-white text-xs">
                        2. Líneas de Solicitudes <span class="text-slate-400 font-normal">(purchase.request.line)</span>
                    </label>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $stSol['exists']
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                        : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
                        <?= $stSol['exists'] ? "Vigente: {$stSol['updated']} ({$stSol['size']})" : 'Pendiente de cargar' ?>
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-gray-400">
                    Columnas clave: <code>Referencia de Solicitud</code>, <code>Productos para comprar/Producto</code>, <code>Productos para comprar/Cantidad</code>
                </p>
                <input id="input_file_sol" name="file_sol" type="file" accept=".xlsx"
                    class="block w-full text-xs text-slate-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-cresta-navy file:text-white hover:file:bg-cresta-blue cursor-pointer">
            </div>

            <!-- Archivo 3: Líneas de Órdenes de Compra -->
            <?php $stOc = $filesStatus['file_oc'] ?? ['exists' => false, 'updated' => 'Sin cargar', 'size' => '0 KB']; ?>
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800/40 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="input_file_oc" class="font-bold text-cresta-navy dark:text-white text-xs">
                        3. Líneas de Órdenes de Compra <span class="text-slate-400 font-normal">(purchase.order.line)</span>
                    </label>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $stOc['exists']
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800'
                        : 'bg-amber-50 text-amber-800 border border-amber-200' ?>">
                        <?= $stOc['exists'] ? "Vigente: {$stOc['updated']} ({$stOc['size']})" : 'Pendiente de cargar' ?>
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-gray-400">
                    Columnas clave: <code>Referencia de la orden</code>, <code>Proveedor</code>, <code>Líneas de la orden/Descripción</code>, <code>Cantidad</code>, <code>Divisa</code>, <code>Precio unitario</code>, <code>Total</code>
                </p>
                <input id="input_file_oc" name="file_oc" type="file" accept=".xlsx"
                    class="block w-full text-xs text-slate-600 dark:text-gray-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-cresta-navy file:text-white hover:file:bg-cresta-blue cursor-pointer">
            </div>

            <!-- Botones de acción -->
            <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-200 dark:border-gray-800">
                <button type="button" onclick="closeAdminUploadModal()"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-slate-700 dark:text-gray-300 font-bold transition">
                    Cancelar
                </button>
                <button id="btnSubmitAdminUpload" type="submit"
                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-sm transition">
                    Subir y Procesar Datos →
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdminUploadModal() {
    document.getElementById('adminUploadModal').classList.remove('hidden');
}
function closeAdminUploadModal() {
    document.getElementById('adminUploadModal').classList.add('hidden');
}
function onAdminUploadSubmit(form) {
    const btn = document.getElementById('btnSubmitAdminUpload');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Procesando archivos Odoo...';
    }
}
</script>
