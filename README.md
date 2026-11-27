# Portal Ejecutivo de Control de Compras y Proveedores · Grupo Cresta

> **URL de Producción:** `[https://compras-cresta.mksbox.com](https://compras-cresta.mksbox.com)`  
> **App Instalable (PWA):** **Compras Cresta** (Nombre corto: **Compras**)  
> **Versión:** `1.16` (Arquitectura Modular · Odoo 17 API + RBAC + Justificaciones por Solicitud + PWA Móvil)  
> **Autor y Arquitecto:** **Elder Juan Velásquez Vela** · Gerente de Supply Chain, Grupo Cresta  
> **Infraestructura:** Shared Hosting 20i (StackCP · PHP 8.4 · HTTPS · Despliegue continuo vía Git/GitHub)

---

## 1. Descripción General y Propósito

El **Portal Ejecutivo de Control de Compras y Proveedores (**Compras Cresta**)** es una plataforma web y **Progressive Web App (PWA)** de inteligencia de negocios (*Procurement Analytics*) diseñada para auditar, medir y gestionar el desempeño integral del departamento de Compras y Suministros de **Grupo Cresta** (incluyendo sus distintas sociedades operativas) tanto desde escritorio como instalada en teléfonos móviles (Android e iOS).

El sistema se conecta directamente mediante **JSON-RPC API a Odoo 17** (con soporte de sincronización incremental por ventana de fechas y respaldo opcional de carga manual de archivos `.xlsx` exportados de Odoo), consolidando solicitudes de compra (`purchase.request`), órdenes de compra (`purchase.order`), líneas de detalle e integración con **SAP Business One (HANA)** en indicadores clave de desempeño (KPIs), análisis de tiempos de ciclo (SLA), seguimiento de gasto, control de precios unitarios por SKU, trazabilidad documental y registro operativo de justificaciones por solicitud.

---

## 2. Stack Tecnológico

* **Backend:** PHP `8.4` (`declare(strict_types=1)`), zona horaria fijada explícitamente en **`America/Guatemala` (`UTC-6`)**, sin dependencias de Composer ni bases de datos externas (arquitectura *Zero-DB* respaldada por caché JSON indexada por `schema_v` y `filemtime`).
* **Integración Odoo 17:** `OdooConnector.php` vía JSON-RPC (`/jsonrpc`) con autenticación por API Key, paginación por lotes, sincronización incremental mediante marca de agua (`last_watermark`) y purga automática fuera de ventana.
* **Progressive Web App (PWA):** `manifest.json` + `sw.js` (estrategia *Network-Only* para garantizar seguridad de sesiones PHP, filtros RBAC y datos en tiempo real sin caché obsoleta) + iconos estandarizados en `assets/pwa/`.
* **Persistencia Operativa Independiente:** `StatusConfig.php` (`data/status_config.json`) y `SolNotesManager.php` (`data/sol_notes.json`) desacoplados de la caché de sincronización para que las reglas y justificaciones sobrevivan intactas a cualquier reimportación de Odoo.
* **Parser Excel de Respaldo:** `SimpleXLSX.php` (lectura directa de archivos `.xlsx` vía `ZipArchive` + `SimpleXML` en memoria).
* **Frontend & UI:** HTML5, **Tailwind CSS** (vía CDN con soporte nativo para modo claro y modo oscuro `darkMode: 'class'`), encabezado ejecutivo compacto responsivo y tipografía corporativa `Verdana`.
* **Motor de Visualización:** **ApexCharts** (gráficas interactivas de líneas, barras apiladas, barras horizontales, donas y gráficos combinados).
* **Motor de Exportación:**
  * **SheetJS (`xlsx@0.18.5`):** Exportación nativa a libros de Microsoft Excel (`.xlsx`) con autoajuste de ancho de columnas, tipos numéricos/moneda y columnas de justificación de compradores.
  * **jsPDF (`jspdf@2.5.1` UMD) + `ApexCharts.dataURI()`:** Generación de informes ejecutivos vectoriales en PDF tamaño **Carta Vertical (`Letter` Portrait)** con encabezado corporativo, resumen de filtros activos, tarjetas KPI, gráficas en alta resolución y tablas consolidadas.

---

## 3. Arquitectura y Estructura de Directorios

El proyecto sigue una separación modular estricta entre controlador frontal, capa de seguridad RBAC, conector Odoo, gestores de configuración/notas, manifiesto PWA, vistas HTML parciales y motores JavaScript por dominio funcional:

```text
compras-cresta/
├── index.php                     # Controlador frontal: RBAC, endpoints AJAX, arranque UI y registro PWA
├── manifest.json                 # Manifiesto estándar PWA ("Compras Cresta" / "Compras")
├── sw.js                         # Service Worker ligero (Network-Only seguro para sesiones PHP)
├── cron_sync.php                 # Script CLI para sincronización programada (Cron / Scheduled Tasks 20i)
├── README.md                     # Documentación técnica y manual de operación
├── src/
│   ├── Auth.php                  # Autenticación multiusuario, roles (manager/buyer), CRUD de usuarios y aislamiento
│   ├── OdooConnector.php         # Cliente JSON-RPC Odoo 17, ventana de fechas, purga y sync incremental
│   ├── StatusConfig.php          # Persistencia de estatus PR/OC incluidos y aliases de ex compradores (Caja Chica)
│   ├── SolNotesManager.php       # Gestor de estatus/justificaciones por solicitud (máx. 280 car.) con RBAC
│   ├── DataProcessor.php         # Motor analítico, limpieza de dualidad ERP, aislamiento RBAC y caché JSON
│   ├── UploadHandler.php         # Manejador de carga manual de reportes Excel de respaldo (Odoo)
│   └── SimpleXLSX.php            # Lector nativo de hojas de cálculo .xlsx y conversión de fechas Excel
├── views/
│   ├── login.php                 # Pantalla de inicio de sesión con soporte de instalación PWA
│   ├── header.php                # Encabezado ejecutivo compacto (2 filas): estado, módulos, búsqueda y filtros
│   ├── tab_cycle.php             # Vista Módulo 1: Ciclo Operativo (SLA) y Carga Operativa
│   ├── tab_spend.php             # Vista Módulo 2: Gestión de Gasto, Compradores y Productos
│   ├── tab_supplier.php          # Vista Módulo 3: Portal 360° de Proveedores (OCs en Firme)
│   ├── modals.php                # Modales de Auditoría por KPI, Ficha + Línea de Tiempo + Notas y Búsqueda Global
│   ├── admin_upload.php          # Modal de respaldo para carga manual de archivos .xlsx (solo Manager)
│   └── admin_users.php           # Modal de Configuración General (3 pestañas, exclusivo Manager)
├── assets/
│   ├── pwa/
│   │   ├── icon-192.png          # Icono estándar PWA 192x192 px (Android / iOS / Favicon)
│   │   └── icon-512.png          # Icono estándar PWA 512x512 px (Splash screen / Alta resolución)
│   ├── css/
│   │   └── app.css               # Variables corporativas Cresta y estilos de scrollbar/transiciones
│   └── js/
│       ├── app.js                # Estado global, formateadores, shortName(), tema oscuro y filtros
│       ├── export.js             # Motor de exportación a Excel (.xlsx) y PDF vectorial Carta Vertical
│       ├── modals.js             # Lógica de modales de auditoría, edición de notas (280 car.) y línea de tiempo
│       ├── module_cycle.js       # Renderizado de KPIs, gráficas y tablas del Módulo 1 (Ciclo y SLA)
│       ├── module_spend.js       # Renderizado de KPIs, gráficas y rastreador de precios del Módulo 2 (Gasto)
│       └── module_supplier.js    # Renderizado de KPIs, catálogo de artículos e historial del Módulo 3 (360°)
└── data/                         # Directorio de datos y configuración (excluido de Git público)
    ├── odoo.config.php           # URL de Odoo 17, base de datos, usuario y API Key
    ├── auth.config.php           # Catálogo de usuarios, contraseñas hasheadas, roles y compradores asignados
    ├── sync_config.json          # Rango de fechas de importación, modo día en curso y last_watermark
    ├── status_config.json        # Reglas activas de estatus PR/OC y aliases de ex compradores de Caja Chica
    ├── sol_notes.json            # Base persistente de justificaciones/estatus por solicitud (280 caracteres)
    ├── odoo_sync_meta.json       # Metadatos de última sincronización (fecha/hora Guatemala y conteos)
    ├── cache.json                # Caché procesada principal (generada automáticamente)
    └── details_cache.json        # Caché indexada de líneas de Solicitudes y OCs
```

---

## 4. Instalación como App Móvil (PWA · "Compras Cresta")

Cualquier comprador o manager puede instalar el portal directamente en su teléfono inteligente sin pasar por tiendas de aplicaciones, manteniendo el nombre estándar **Compras** y el icono corporativo:

### 4.1 En Android (Google Chrome / Edge)
1. Abre **`[https://compras-cresta.mksbox.com](https://compras-cresta.mksbox.com)`** en Google Chrome.
2. Toca el menú de opciones (**⋮**) arriba a la derecha.
3. Selecciona **"Instalar aplicación"** o **"Agregar a la pantalla principal"**.
4. Confirma tocando **Instalar**. El icono **Compras** aparecerá en tu cajón de aplicaciones y pantalla de inicio en modo pantalla completa (`standalone`).

### 4.2 En iPhone / iPad (Safari)
1. Abre **`[https://compras-cresta.mksbox.com](https://compras-cresta.mksbox.com)`** en **Safari** *(requerido por iOS para instalar PWAs)*.
2. Toca el botón **Compartir** (cuadro con flecha hacia arriba en la barra inferior).
3. Desliza hacia abajo y selecciona **"Agregar a inicio"** (*Add to Home Screen*).
4. Verifica que aparezca el icono corporativo y el nombre **Compras**, luego toca **Agregar**.

### 4.3 En Computadora (Chrome / Edge Desktop)
1. Abre **`[https://compras-cresta.mksbox.com](https://compras-cresta.mksbox.com)`**.
2. Haz clic en el icono de **Instalar Compras Cresta** ubicado al extremo derecho de la barra de direcciones del navegador.

---

## 5. Fuentes de Datos, Sincronización y Reglas de Negocio

### 5.1 Sincronización Directa con Odoo 17 (`src/OdooConnector.php`)
* **Extracción por API JSON-RPC:** Consulta directamente los modelos `purchase.request`, `purchase.request.line`, `purchase.order` y `purchase.order.line`, normalizando fechas UTC de Odoo hacia hora local de Guatemala (`America/Guatemala`).
* **Ventana de Importación e Incrementalidad (`data/sync_config.json`):**
  * Importa la totalidad de registros creados dentro del rango configurado (`date_from` a `date_to`) para garantizar trazabilidad completa en búsquedas y fichas documentales.
  * En ejecuciones posteriores utiliza `write_date >= last_watermark` (con margen de seguridad de 15 minutos) para actualizar únicamente documentos nuevos o modificados sin re-descargar históricos sin cambios.
  * Si el Manager adelanta la fecha `date_from`, `purgeRecordsBeforeDate()` elimina automáticamente de `cache.json` y `details_cache.json` cualquier registro anterior a esa fecha.
* **Automatización vía Cron (`cron_sync.php`):** Ejecutable desde CLI / Scheduled Tasks de 20i para mantener sincronizado el portal en segundo plano.

### 5.2 Control de Estatus y Limpieza de Ruido Histórico (`src/StatusConfig.php` + `src/DataProcessor.php`)
* **Separación entre Importación y Análisis:** Todos los documentos dentro de la ventana de fechas se conservan en caché para consulta documental, mientras que `applyStatusRulesToCache()` determina qué cuenta en los cálculos de KPIs y gráficas según las reglas configuradas en `data/status_config.json`.
* **Exclusión de Solicitudes Cerradas (`hecho` / `done`) sin SAP (`isLegacyDoneWithoutSap`):** Toda solicitud marcada en Odoo como **`hecho` / `done`** que no posee folio ni fecha de ingreso en SAP (tenga o no RFQ/OC en Odoo, producto de cierres administrativos o dualidad ERP histórica) se excluye automáticamente (`excluida = true`) y sus tiempos abiertos se anulan (`null`) para no inflar el indicador de **Rezago Sin SAP** ni castigar el cumplimiento de **En SAP ≤14 días**.
* **Blindaje de Rechazadas y Borradores:** Las solicitudes con estatus `Rechazado` o `Borrador` quedan excluidas tanto en backend (`DataProcessor.php`) como en frontend (`isCycleRecordExcluded()` en `module_cycle.js` y `modals.js`), garantizando coincidencia exacta entre las tarjetas KPI y los modales de auditoría.
* **Detección Global de Caja Chica (Activos + Ex Compradores):** Identifica órdenes emitidas a nombre de compradores internos comparando el nombre normalizado del proveedor contra los compradores activos y contra la lista de **Ex Compradores / Cajas Chicas Históricas** (`petty_cash_aliases` en `status_config.json`). El checkbox global **`Sin Caja Chica`** en la barra superior excluye estas operaciones en los **3 módulos** (`Ciclo SLA`, `Gasto y Productos` y `Proveedor 360°`).
* **Exclusión de Sesgo de Corte (Ventana de Madurez):** Los porcentajes de cumplimiento (`≤ 7 días`, `≤ 14 días`, `≤ 30 días`) se calculan exclusivamente sobre solicitudes cuya antigüedad respecto a la fecha de corte (`cutoff_date`) sea mayor o igual a la ventana evaluada.

### 5.3 Estatus Breve / Justificaciones por Solicitud (`src/SolNotesManager.php`)
* **Persistencia Desacoplada (`data/sol_notes.json`):** Cada solicitud puede almacenar un comentario o justificación breve de hasta **280 caracteres** (`MAX_LENGTH = 280`), junto con el autor (`updated_by`) y marca de tiempo (`updated_at` en hora de Guatemala). Al residir fuera de `cache.json`, las justificaciones nunca se pierden al sincronizar Odoo.
* **Control de Permisos (RBAC):**
  * Los usuarios con rol **`buyer`** solo pueden crear o modificar justificaciones de las solicitudes asignadas a sus nombres de comprador vinculados (`odoo_buyers`).
  * Los usuarios con rol **`manager`** pueden visualizar todas las notas en cualquier modal y editarlas si lo requieren.
* **Visibilidad Omnicanal:** Las justificaciones se muestran y editan directamente en:
  * **Modal de Auditoría (`#auditModal`):** Columna dedicada con edición rápida inline (`💬 + Justificar` / `✏️`) sin perder filtros ni cerrar la tabla.
  * **Ficha Detallada (`#docDetailModal`):** Caja editable con contador de caracteres en vivo (`0 / 280`) tanto al abrir la Solicitud como al consultar una Orden de Compra vinculada.
  * **Buscador Global (`#globalSearchModal`) y Exportaciones Excel (`.xlsx`):** Incluye la columna `Justificacion Comprador` en las descargas.

---

## 6. Guía Rápida de Mantenimiento (¿Dónde modificar cada cosa?)

| Necesidad de Cambio | Archivo(s) a Modificar |
| :--- | :--- |
| **Cambiar nombre o iconos de la App Móvil (PWA)** | `manifest.json`, `assets/pwa/icon-192.png` y `assets/pwa/icon-512.png` |
| **Ajustar mapeo de campos o estados desde Odoo 17** | `src/OdooConnector.php` (`mapPrState`, `mapPoState`, `syncFromOdoo`) |
| **Modificar reglas de exclusión de análisis o limpieza ERP** | `src/DataProcessor.php` (`applyStatusRulesToCache`, `isLegacyDoneWithoutSap`) |
| **Agregar nuevos estatus o ajustar Caja Chica histórica** | `src/StatusConfig.php` (`PR_OPTIONS`, `PO_OPTIONS`) y `views/admin_users.php` |
| **Ajustar validación o límite de caracteres de notas** | `src/SolNotesManager.php` (`MAX_LENGTH`, `saveNote`) |
| **Cambiar lógica de aislamiento de datos para Compradores** | `src/DataProcessor.php` (`scopePayloadForUser`) y `src/Auth.php` |
| **Ajustar diseño del encabezado compacto o barra de filtros** | `views/header.php` y `assets/js/app.js` |
| **Modificar KPIs, gráficas o tablas del Ciclo SLA** | `views/tab_cycle.php` y `assets/js/module_cycle.js` |
| **Modificar KPIs o rastreador de precios de Gasto y Productos** | `views/tab_spend.php` y `assets/js/module_spend.js` |
| **Modificar vista del Portal 360° de Proveedores** | `views/tab_supplier.php` y `assets/js/module_supplier.js` |
| **Ajustar modales de auditoría, notas inline o línea de tiempo** | `views/modals.php` y `assets/js/modals.js` |
| **Personalizar formato de exportación Excel o PDF** | `assets/js/export.js` |

---

## 7. Despliegue en 20i (GitHub)

1. Realizar cambios en el módulo correspondiente (`src/`, `views/`, `assets/` o raíz).
2. Hacer `git commit` y `git push` hacia la branch activa configurada en **20i Git Version Control**.
3. Ejecutar **Deploy / Pull** desde StackCP en 20i. El versionado automático `$assetVer` en `index.php` invalida la caché de CSS/JS en los navegadores de todos los usuarios al recargar.
4. *(Opcional)* Configurar en **StackCP → Scheduled Tasks** la ejecución periódica de `/usr/bin/php /ruta/al/sitio/cron_sync.php` para sincronización automática con Odoo 17.

---

## 8. Autoría

* **Diseño, Arquitectura Funcional, Reglas de Negocio y Desarrollo:** **Elder Juan Velásquez Vela** (Gerente de Supply Chain · Grupo Cresta).
* **Soporte de Desarrollo Web:** [mksbox.com](https://mksbox.com).
