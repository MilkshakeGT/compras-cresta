<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras Cresta · Acceso al Portal</title>

    <!-- Configuración Progressive Web App (PWA) -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1B2A4A">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Compras">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/pwa/icon-192.png">
    <link rel="apple-touch-icon" href="assets/pwa/icon-192.png">

    <script>
        if (localStorage.getItem('cresta_theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Verdana', 'Geneva', 'Tahoma', 'sans-serif'],
                    },
                    colors: {
                        cresta: {
                            navy:  '#1B2A4A',
                            blue:  '#264073',
                            red:   '#C8102E',
                            slate: '#F4F6F9',
                            ink:   '#0F172A'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-cresta-slate dark:bg-gray-950 text-cresta-ink dark:text-gray-100 min-h-screen flex flex-col items-center justify-center p-4 antialiased">

    <div class="w-full max-w-md bg-white dark:bg-gray-900 border-2 border-cresta-navy dark:border-gray-800 rounded-2xl shadow-xl overflow-hidden">
        <!-- Encabezado Corporativo -->
        <div class="bg-cresta-navy px-6 py-6 text-white border-b-4 border-cresta-red">
            <div class="flex items-center justify-between">
                <span class="inline-block px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase bg-cresta-red text-white rounded">
                    GRUPO CRESTA
                </span>
                <span class="text-[11px] text-slate-300 font-semibold">Compras &amp; Suministros</span>
            </div>
            <h1 class="text-xl font-bold mt-3 tracking-tight">Compras Cresta</h1>
            <p class="text-xs text-slate-300 mt-1">Control Ejecutivo de Compras y Proveedores</p>
        </div>

        <!-- Formulario de Acceso -->
        <form method="POST" action="" class="p-6 space-y-4">
            <?php if (!empty($loginError)): ?>
                <div class="p-3 rounded-xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800 text-xs text-cresta-red dark:text-red-300 font-semibold">
                    <?= htmlspecialchars((string)$loginError) ?>
                </div>
            <?php endif; ?>

            <div>
                <label for="login_username" class="block text-xs font-bold text-slate-700 dark:text-gray-300 mb-1.5">
                    Usuario
                </label>
                <input id="login_username" name="login_username" type="text" required autocomplete="username" autofocus
                    placeholder="ej. evelasquez"
                    value="<?= htmlspecialchars((string)($_POST['login_username'] ?? '')) ?>"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-gray-700 bg-slate-50 dark:bg-gray-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-cresta-navy dark:focus:border-sky-400">
            </div>

            <div>
                <label for="login_password" class="block text-xs font-bold text-slate-700 dark:text-gray-300 mb-1.5">
                    Contraseña
                </label>
                <input id="login_password" name="login_password" type="password" required autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-gray-700 bg-slate-50 dark:bg-gray-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-cresta-navy dark:focus:border-sky-400">
            </div>

            <button type="submit"
                class="w-full py-2.5 px-4 rounded-xl bg-cresta-navy hover:bg-cresta-blue dark:bg-cresta-red dark:hover:opacity-90 text-white font-bold text-sm transition shadow-sm cursor-pointer">
                Ingresar al Portal
            </button>
        </form>

        <div class="px-6 py-3 bg-slate-50 dark:bg-gray-950 border-t border-slate-200 dark:border-gray-800 text-[11px] text-slate-500 dark:text-gray-400 flex items-center justify-between">
            <span>Acceso restringido · RBAC activo</span>
            <span>Powered by <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-cresta-navy dark:text-indigo-400 hover:underline">mksbox.com</a></span>
        </div>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js').catch(() => {});
            });
        }
    </script>
</body>
</html>
