<?php
// Espera $isManager, $allUsersList, $allOdooBuyers y $userAdminFeedback definidos en index.php
if (empty($isManager)) {
    return;
}
$allUsersList      = $allUsersList ?? [];
$allOdooBuyers     = $allOdooBuyers ?? [];
$userAdminFeedback = $userAdminFeedback ?? null;
$autoOpenUsers     = ($userAdminFeedback !== null);
?>
<!-- Modal Administración de Usuarios y Permisos RBAC (Solo Manager) -->
<div id="adminUsersModal" class="fixed inset-0 z-50 <?= $autoOpenUsers ? '' : 'hidden' ?> bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-indigo-500 rounded-2xl max-w-5xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
        <!-- Cabecera -->
        <div class="p-5 bg-cresta-navy text-white flex items-center justify-between gap-3">
            <div>
                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-red tracking-wider uppercase">
                    SEGURIDAD Y PERMISOS · RBAC
                </span>
                <h3 class="text-base sm:text-lg font-bold mt-1">Gestión de Usuarios y Asignación de Compradores</h3>
                <p class="text-xs text-slate-300 mt-0.5">Asigna uno o varios compradores de Odoo por usuario (cobertura de vacaciones o cartera)</p>
            </div>
            <button type="button" onclick="closeAdminUsersModal()"
                class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition shrink-0">
                Cerrar ✕
            </button>
        </div>

        <div class="p-5 overflow-y-auto space-y-5 text-xs">
            <?php if ($userAdminFeedback !== null): ?>
                <div class="p-3.5 rounded-xl border font-semibold <?= !empty($userAdminFeedback['ok'])
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-300'
                    : 'bg-red-50 border-red-200 text-cresta-red dark:bg-red-950/70 dark:border-red-800 dark:text-red-300' ?>">
                    <?= htmlspecialchars((string)$userAdminFeedback['message']) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Columna Izquierda: Formulario Crear / Editar Usuario -->
                <form method="POST" id="userAdminForm" class="lg:col-span-5 p-4 rounded-xl border border-slate-200 dark:border-gray-700 bg-slate-50/70 dark:bg-gray-800/40 space-y-3">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" id="ua_mode" name="user_mode" value="create">

                    <div class="flex items-center justify-between">
                        <h4 id="ua_form_title" class="font-bold text-cresta-navy dark:text-white text-sm">Nuevo Usuario</h4>
                        <button type="button" onclick="resetUserAdminForm()" class="text-[11px] text-cresta-blue dark:text-sky-400 underline font-semibold">
                            + Limpiar / Nuevo
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="ua_username" class="block font-bold text-slate-700 dark:text-gray-300 mb-1">Usuario (Login)</label>
                            <input id="ua_username" name="username" type="text" required placeholder="ej. csantos"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-slate-800 dark:text-gray-100">
                        </div>
                        <div>
                            <label for="ua_display_name" class="block font-bold text-slate-700 dark:text-gray-300 mb-1">Nombre Visible</label>
                            <input id="ua_display_name" name="display_name" type="text" required placeholder="ej. Cristian Santos"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-slate-800 dark:text-gray-100">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="ua_role" class="block font-bold text-slate-700 dark:text-gray-300 mb-1">Perfil / Rol</label>
                            <select id="ua_role" name="role" onchange="toggleBuyerCheckboxesBox()"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-slate-800 dark:text-gray-100 font-semibold">
                                <option value="buyer">Comprador (Solo sus datos)</option>
                                <option value="manager">Manager (Acceso total + Carga)</option>
                            </select>
                        </div>
                        <div>
                            <label for="ua_password" class="block font-bold text-slate-700 dark:text-gray-300 mb-1">
                                Contraseña <span id="ua_pass_hint" class="font-normal text-slate-400">(mín. 6 car.)</span>
                            </label>
                            <input id="ua_password" name="password" type="password" placeholder="••••••••"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-slate-800 dark:text-gray-100">
                        </div>
                    </div>

                    <!-- Selector Multi-Comprador (Checkboxes) -->
                    <div id="ua_buyers_wrapper" class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700 dark:text-gray-300">
                                Compradores de Odoo Asignados
                            </label>
                            <span id="ua_selected_count" class="text-[10px] px-2 py-0.5 rounded-full bg-cresta-navy/10 text-cresta-navy dark:bg-indigo-950 dark:text-indigo-300 font-bold">
                                0 seleccionados
                            </span>
                        </div>
                        <div class="max-h-44 overflow-y-auto p-2.5 rounded-lg border border-slate-300 dark:border-gray-700 bg-white dark:bg-gray-900 space-y-1.5">
                            <?php if (empty($allOdooBuyers)): ?>
                                <p class="text-[11px] text-slate-400 italic">Carga primero DB.xlsx para listar los compradores de Odoo.</p>
                            <?php else: ?>
                                <?php foreach ($allOdooBuyers as $buyerName): ?>
                                    <?php if ($buyerName === 'Sin asignar') continue; ?>
                                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-gray-200 hover:bg-slate-50 dark:hover:bg-gray-800 px-1.5 py-1 rounded cursor-pointer">
                                        <input type="checkbox" name="odoo_buyers[]" value="<?= htmlspecialchars($buyerName) ?>"
                                            onchange="updateSelectedBuyersCounter()"
                                            class="ua-buyer-chk rounded text-cresta-navy">
                                        <span><?= htmlspecialchars($buyerName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-gray-400">
                            Puedes marcar 1 comprador habitual o varios simultáneamente para cubrir vacaciones.
                        </p>
                    </div>

                    <label class="inline-flex items-center gap-2 pt-1 cursor-pointer select-none">
                        <input id="ua_active" name="active" type="checkbox" value="1" checked class="rounded text-emerald-600">
                        <span class="font-semibold text-slate-700 dark:text-gray-300">Usuario activo (permitir inicio de sesión)</span>
                    </label>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold shadow-sm transition">
                            Guardar Usuario y Permisos
                        </button>
                    </div>
                </form>

                <!-- Columna Derecha: Tabla de Usuarios Registrados -->
                <div class="lg:col-span-7 overflow-x-auto border border-slate-200 dark:border-gray-700 rounded-xl">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead class="bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Usuario</th>
                                <th class="py-2.5 px-3">Rol</th>
                                <th class="py-2.5 px-3">Compradores Asignados</th>
                                <th class="py-2.5 px-3">Estado</th>
                                <th class="py-2.5 px-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-gray-800">
                            <?php foreach ($allUsersList as $uItem): ?>
                                <?php
                                    $uJson = htmlspecialchars(json_encode([
                                        'username'     => $uItem['username'],
                                        'display_name' => $uItem['display_name'],
                                        'role'         => $uItem['role'],
                                        'odoo_buyers'  => $uItem['odoo_buyers'] ?? [],
                                        'active'       => !empty($uItem['active']),
                                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    $isMgrRow = ($uItem['role'] ?? '') === 'manager';
                                    $assigned = (array)($uItem['odoo_buyers'] ?? []);
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-gray-800/40">
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-cresta-navy dark:text-white"><?= htmlspecialchars($uItem['display_name']) ?></div>
                                        <div class="text-[10px] text-slate-400">@<?= htmlspecialchars($uItem['username']) ?></div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <?php if ($isMgrRow): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cresta-navy text-white dark:bg-cresta-red">Manager</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-cresta-blue border border-blue-200 dark:bg-sky-950 dark:text-sky-300 dark:border-sky-800">Comprador</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <?php if ($isMgrRow): ?>
                                            <span class="text-[11px] text-slate-400 italic">Acceso global (Todos)</span>
                                        <?php elseif (empty($assigned)): ?>
                                            <span class="text-[11px] text-cresta-red font-semibold">Sin asignar</span>
                                        <?php else: ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach ($assigned as $bName): ?>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-gray-800 text-slate-700 dark:text-gray-200 text-[10px] font-medium">
                                                        <?= htmlspecialchars($bName) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <?php if (!empty($uItem['active'])): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800">Activo</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-300 dark:bg-gray-800 dark:text-gray-400">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-3 text-right whitespace-nowrap space-x-1">
                                        <button type="button" onclick='editAdminUser(<?= $uJson ?>)'
                                            class="px-2 py-1 rounded bg-slate-200 hover:bg-slate-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-cresta-navy dark:text-white font-bold text-[11px]">
                                            Editar
                                        </button>
                                        <?php if (($currentUser['username'] ?? '') !== $uItem['username']): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar definitivamente al usuario <?= htmlspecialchars($uItem['username']) ?>?')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="username" value="<?= htmlspecialchars($uItem['username']) ?>">
                                                <button type="submit" class="px-2 py-1 rounded bg-red-50 hover:bg-red-100 dark:bg-red-950 text-cresta-red dark:text-red-300 font-bold text-[11px]">
                                                    ✕
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openAdminUsersModal() {
    document.getElementById('adminUsersModal').classList.remove('hidden');
}
function closeAdminUsersModal() {
    document.getElementById('adminUsersModal').classList.add('hidden');
}
function toggleBuyerCheckboxesBox() {
    const role = document.getElementById('ua_role').value;
    const box  = document.getElementById('ua_buyers_wrapper');
    if (role === 'manager') {
        box.classList.add('opacity-40', 'pointer-events-none');
    } else {
        box.classList.remove('opacity-40', 'pointer-events-none');
    }
}
function updateSelectedBuyersCounter() {
    const chks = document.querySelectorAll('.ua-buyer-chk:checked');
    document.getElementById('ua_selected_count').textContent = `${chks.length} seleccionado(s)`;
}
function resetUserAdminForm() {
    document.getElementById('ua_mode').value = 'create';
    document.getElementById('ua_form_title').textContent = 'Nuevo Usuario';
    const uInput = document.getElementById('ua_username');
    uInput.value = '';
    uInput.readOnly = false;
    uInput.classList.remove('bg-slate-100', 'dark:bg-gray-800');
    document.getElementById('ua_display_name').value = '';
    document.getElementById('ua_role').value = 'buyer';
    document.getElementById('ua_password').value = '';
    document.getElementById('ua_pass_hint').textContent = '(mín. 6 car.)';
    document.getElementById('ua_active').checked = true;
    document.querySelectorAll('.ua-buyer-chk').forEach(c => c.checked = false);
    toggleBuyerCheckboxesBox();
    updateSelectedBuyersCounter();
}
function editAdminUser(userObj) {
    document.getElementById('ua_mode').value = 'edit';
    document.getElementById('ua_form_title').textContent = `Editando: @${userObj.username}`;
    const uInput = document.getElementById('ua_username');
    uInput.value = userObj.username;
    uInput.readOnly = true;
    uInput.classList.add('bg-slate-100', 'dark:bg-gray-800');
    document.getElementById('ua_display_name').value = userObj.display_name || '';
    document.getElementById('ua_role').value = userObj.role || 'buyer';
    document.getElementById('ua_password').value = '';
    document.getElementById('ua_pass_hint').textContent = '(dejar vacío para no cambiar)';
    document.getElementById('ua_active').checked = !!userObj.active;

    const assignedSet = new Set(userObj.odoo_buyers || []);
    document.querySelectorAll('.ua-buyer-chk').forEach(c => {
        c.checked = assignedSet.has(c.value);
    });
    toggleBuyerCheckboxesBox();
    updateSelectedBuyersCounter();
}
</script>
