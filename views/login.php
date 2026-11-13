<?php
// Espera la variable $loginError definida desde Auth::requireAuthOrRenderLogin()
$loginError = $loginError ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido · Grupo Cresta Supply Chain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cresta: {
                            navy: '#1B2A4A',
                            blue: '#264073',
                            red: '#C8102E'
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
<body class="min-h-screen bg-slate-100 flex flex-col justify-center items-center p-4">
    <div class="max-w-md w-full bg-white border border-slate-200 rounded-2xl shadow-lg overflow-hidden">
        <div class="bg-cresta-navy px-6 py-5 text-white">
            <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded bg-cresta-red tracking-wider uppercase">
                GRUPO CRESTA · SUPPLY CHAIN
            </span>
            <h1 class="text-lg font-bold mt-2">Control Ejecutivo de Compras</h1>
            <p class="text-xs text-slate-300 mt-1">Información financiera y operativa confidencial</p>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <?php if ($loginError !== ''): ?>
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-cresta-red text-xs font-semibold">
                    <?= htmlspecialchars($loginError) ?>
                </div>
            <?php endif; ?>

            <div>
                <label for="access_password" class="block text-xs font-bold text-slate-700 uppercase mb-1.5">
                    Contraseña de Acceso
                </label>
                <input id="access_password" name="access_password" type="password" required autofocus
                    placeholder="Ingresa la contraseña del tablero"
                    class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:border-cresta-navy focus:ring-2 focus:ring-cresta-navy/20 outline-none">
            </div>

            <button type="submit"
                class="w-full py-2.5 px-4 rounded-xl bg-cresta-navy hover:bg-cresta-blue text-white text-xs font-bold uppercase tracking-wide transition shadow-sm">
                Ingresar al Dashboard →
            </button>
        </form>
    </div>

    <p class="text-[11px] text-slate-400 mt-6 text-center">
        Dashboard construido por <span class="font-semibold text-slate-500">Elder Velásquez</span> con ayuda de
        <a href="https://mksbox.com" target="_blank" rel="noopener noreferrer" class="underline hover:text-cresta-navy">mksbox.com</a>
    </p>
</body>
</html>
