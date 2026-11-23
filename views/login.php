<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso · Control de Compras y Proveedores · Grupo Cresta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cresta: {
                            navy: '#1B2A4A',
                            blue: '#264073',
                            red: '#C8102E',
                            light: '#F8FAFC'
                        }
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: Verdana, Geneva, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-100 flex flex-col justify-between p-4">

    <div class="flex-1 flex items-center justify-center">
        <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden">
            <!-- Encabezado Corporativo -->
            <div class="bg-cresta-navy px-6 py-6 text-white">
                <span class="inline-block px-2.5 py-0.5 text-[10px] font-bold rounded bg-cresta-red tracking-wider uppercase">
                    GRUPO CRESTA · SUPPLY CHAIN
                </span>
                <h1 class="text-lg font-bold mt-2">Control de Compras y Proveedores</h1>
                <p class="text-xs text-slate-300 mt-1">Inicia sesión con tu usuario asignado para continuar</p>
            </div>

            <!-- Formulario de Autenticación -->
            <form method="POST" class="p-6 space-y-4">
                <?php if (!empty($loginError)): ?>
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-cresta-red font-semibold">
                        <?= htmlspecialchars($loginError) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="login_username" class="block text-xs font-bold text-slate-700 mb-1.5">
                        Usuario
                    </label>
                    <input
                        id="login_username"
                        name="login_username"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        value="<?= htmlspecialchars((string)($_POST['login_username'] ?? '')) ?>"
                        placeholder="ej. evelasquez"
                        class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:outline-none focus:border-cresta-navy"
                    >
                </div>

                <div>
                    <label for="login_password" class="block text-xs font-bold text-slate-700 mb-1.5">
                        Contraseña
                    </label>
                    <input
                        id="login_password"
                        name="login_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                        class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:outline-none focus:border-cresta-navy"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full py-2.5 px-4 rounded-xl bg-cresta-navy hover:bg-cresta-blue text-white text-xs font-bold tracking-wide transition shadow-sm"
                >
                    Ingresar al Tablero →
                </button>
            </form>
        </div>
    </div>

    <!-- Footer con créditos -->
    <footer class="py-4 text-center">
        <p class="text-[11px] text-slate-400">
            Dashboard construido por <span class="font-semibold text-slate-500">Elder Velásquez</span> con ayuda de
            <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-cresta-navy">mksbox.com</a>
        </p>
    </footer>

</body>
</html>
