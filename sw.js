/* =========================================================================
 * SERVICE WORKER PWA · COMPRAS CRESTA (sw.js)
 * Habilita la instalación nativa sin cachear datos dinámicos ni sesiones PHP
 * ========================================================================= */

const SW_VERSION = 'compras-cresta-pwa-v1';

// =========================================================================
// SECCIÓN 1: INSTALACIÓN Y ACTIVACIÓN INMEDIATA
// =========================================================================
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== SW_VERSION)
                    .map((key) => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

// =========================================================================
// SECCIÓN 2: ESTRATEGIA NETWORK-ONLY PARA SEGURIDAD DE SESIÓN Y DATOS EN VIVO
// =========================================================================
self.addEventListener('fetch', (event) => {
    // Dejar pasar todas las peticiones directamente a la red (sin caché stale)
    // Cumple el criterio de instalabilidad PWA de Chrome/Android manteniendo
    // intactos los filtros RBAC, sesiones PHP y sincronizaciones de Odoo.
    return;
});
