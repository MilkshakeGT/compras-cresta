# Portal Ejecutivo de Control de Compras y Proveedores · Grupo Cresta

> **URL de Producción:** `[https://compras-cresta.mksbox.com](https://compras-cresta.mksbox.com)`  
> **Versión:** `1.17` (Arquitectura Modular · Odoo 17 API + RBAC)  
> **Autor y Arquitecto:** **Elder Juan Velásquez Vela** · Gerente de Supply Chain, Grupo Cresta  
> **Infraestructura:** Shared Hosting 20i (StackCP · PHP 8.4 · Despliegue continuo vía Git/GitHub + Scheduled Tasks)

---

## 1. Descripción General y Propósito

El **Portal Ejecutivo de Control de Compras y Proveedores** es una plataforma web de inteligencia de negocios (*Procurement Analytics*) diseñada para auditar, medir y gestionar el desempeño integral del departamento de Compras y Suministros de **Grupo Cresta** (incluyendo sus distintas sociedades operativas).

El sistema se conecta directamente mediante **JSON-RPC API a Odoo 17** (con soporte de sincronización incremental por ventana de fechas y respaldo opcional de carga manual de archivos `.xlsx` exportados de Odoo), consolidando solicitudes de compra (`purchase.request`), órdenes de compra (`purchase.order`), líneas de detalle e integración con **SAP Business One (HANA)** en indicadores clave de desempeño (KPIs), análisis de tiempos de ciclo (SLA), seguimiento de gasto, control de precios unitarios por SKU y trazabilidad completa de documentos y proveedores.

---

## 2. Stack Tecnológico

* **Backend:** PHP `8.4` (`declare(strict_types=1)`), zona horaria fijada explícitamente en **`America/Guatemala` (`UTC-6`)**, sin dependencias de Composer ni bases de datos externas (arquitectura *Zero-DB* respaldada por caché JSON indexada por `schema_v = 17` y `filemtime`).
* **Integración Odoo 17:** `OdooConnector.php` vía JSON-RPC (`/jsonrpc`) con autenticación por API Key, paginación por lotes, sincronización incremental mediante marca de agua (`last_watermark`) y purga automática fuera de ventana.
* **Parser Excel de Respaldo:** `SimpleXLSX.php` (lectura directa de archivos `.xlsx` vía `ZipArchive` + `SimpleXML` en memoria).
* **Frontend & UI:** HTML5, **Tailwind CSS** (vía CDN con soporte nativo para modo claro y modo oscuro `darkMode: 'class'`), encabezado ejecutivo compacto responsivo y tipografía corporativa `Verdana`.
* **Motor de Visualización:** **ApexCharts** (gráficas interactivas de líneas, barras apiladas, barras horizontales, donas y gráficos combinados).
* **Motor de Exportación:**
  * **SheetJS (`xlsx@0.18.5`):** Exportación nativa a libros de Microsoft Excel (`.xlsx`) con autoajuste de ancho de columnas y tipos numéricos/moneda.
  * **jsPDF (`jspdf@2.5.1` UMD) + `ApexCharts.dataURI()`:** Generación de informes ejecutivos vectoriales en PDF tamaño **Carta Vertical (`Letter` Portrait)** con encabezado corporativo, resumen de filtros activos, tarjetas KPI, gráficas en alta resolución y tablas consolidadas.

---

## 3. Arquitectura y Estructura de Directorios

El proyecto sigue una separación modular estricta entre controlador frontal, capa de seguridad RBAC, conector Odoo, motor de reglas analíticas, vistas HTML parciales y motores JavaScript por dominio funcional:

```text
compras-cresta/
├── index.php                     # Controlador frontal: RBAC, endpoints AJAX, acciones POST y ensamblaje
├── cron_sync.php                 # Script CLI para sincronización programada (Cron / Scheduled Tasks 20i)
├── README.md                     # Documentación técnica del proyecto
├── src/
│   ├── Auth.php                  # Autenticación multiusuario, roles (manager/buyer), CRUD de usuarios y aislamiento
│   ├── OdooConnector.php         # Cliente JSON-RPC Odoo 17, ventana de fechas, purga y sync incremental
│   ├── StatusConfig.php          # Persistencia y reglas de inclusión/exclusión de estatus PR y OC
│   ├── DataProcessor.php         # Motor analítico, limpieza de dualidad ERP, aislamiento RBAC y caché JSON
│   ├── UploadHandler.php         # Manejador de carga manual de reportes Excel de respaldo (Odoo)
│   └── SimpleXLSX.php            # Lector nativo de hojas de cálculo .xlsx y conversión de fechas Excel
├── views/
│   ├── login.php                 # Pantalla de inicio de sesión con usuario y contraseña
│   ├── header.php                # Encabezado ejecutivo compacto (2 filas): estado, módulos, búsqueda y filtros
│   ├── tab_cycle.php             # Vista Módulo 1: Ciclo Operativo (SLA) y Carga Operativa
│   ├── tab_spend.php             # Vista Módulo 2: Gestión de Gasto, Compradores y Productos
│   ├── tab_supplier.php          # Vista Módulo 3: Portal 360° de Proveedores (OCs en Firme)
│   ├── modals.php                # Modales de Auditoría por KPI, Búsqueda Global y Ficha + Línea de Tiempo
│   ├── admin_upload.php          # Modal de respaldo para carga manual de archivos .xlsx (solo Manager)
│   └── admin_users.php           # Modal de Configuración General (3 pestañas, exclusivo Manager)
├── assets/
│   ├── css/
│   │   └── app.css               # Variables corporativas Cresta y estilos de scrollbar/transiciones
│   └── js/
│       ├── app.js                # Estado global, formateadores, shortName(), tema oscuro y filtros
│       ├── export.js             # Motor de exportación a Excel (.xlsx) y PDF vectorial Carta Vertical
│       ├── modals.js             # Lógica de modales de auditoría, búsqueda global y línea de tiempo
│       ├── module_cycle.js       # Renderizado de KPIs, gráficas y tablas del Módulo 1 (Ciclo y SLA)
│       ├── module_spend.js       # Renderizado de KPIs, gráficas y rastreador de precios del Módulo 2 (Gasto)
│       └── module_supplier.js    # Renderizado de KPIs, catálogo de artículos e historial del Módulo 3 (360°)
└── data/                         # Directorio de datos y configuración (excluido de Git público)
    ├── odoo.config.php           # URL de Odoo 17, base de datos, usuario y API Key
    ├── auth.config.php           # Catálogo de usuarios, contraseñas hasheadas, roles y compradores asignados
    ├── sync_config.json          # Rango de fechas de importación, modo día en curso y last_watermark
    ├── status_config.json        # Reglas activas de estatus incluidos/excluidos para PR y OC
    ├── odoo_sync_meta.json       # Metadatos de última sincronización (fecha/hora Guatemala y conteos)
    ├── cache.json                # Caché procesada principal (schema_v = 17, generada automáticamente)
    └── details_cache.json        # Caché indexada de líneas de Solicitudes y OCs (schema_v = 3)
```

---

## 4. Fuentes de Datos, Sincronización y Reglas de Negocio

### 4.1 Sincronización Directa con Odoo 17 (`src/OdooConnector.php`)
* **Extracción por API JSON-RPC:** Consulta directamente los modelos `purchase.request`, `purchase.request.line`, `purchase.order` y `purchase.order.line`, normalizando fechas UTC de Odoo hacia hora local de Guatemala (`America/Guatemala`).
* **Ventana de Importación e Incrementalidad (`data/sync_config.json`):**
  * Importa la totalidad de registros creados dentro del rango configurado (`date_from` a `date_to`) para garantizar trazabilidad completa en búsquedas y fichas documentales.
  * En ejecuciones posteriores utiliza `write_date >= last_watermark` (con margen de seguridad de 15 minutos) para actualizar únicamente documentos nuevos o modificados sin re-descargar históricos sin cambios.
  * Si el Manager adelanta la fecha `date_from`, `purgeRecordsBeforeDate()` elimina automáticamente de `cache.json` y `details_cache.json` cualquier registro anterior a esa fecha.
* **Automatización vía Cron (`cron_sync.php`):** Ejecutable únicamente desde CLI / Scheduled Tasks de 20i para mantener sincronizado el portal en segundo plano.

### 4.2 Control de Estatus y Limpieza de Ruido Histórico (`src/StatusConfig.php` + `src/DataProcessor.php`)
* **Separación entre Importación y Análisis:** Todos los documentos dentro de la ventana de fechas se conservan en caché para consulta documental, mientras que `applyStatusRulesToCache()` determina qué cuenta en los cálculos de KPIs y gráficas según las reglas configuradas en `data/status_config.json`.
* **Exclusión de Dualidad ERP (`isLegacyDoneWithoutOcOrSap`):** Las solicitudes marcadas como **`hecho` / `done`** que no poseen referencia de OC en Odoo ni folio/fecha en SAP (correspondientes a cierres manuales realizados directamente en SAP durante la transición de sistemas) se marcan automáticamente como excluidas (`excluida = true`) y sus tiempos abiertos se anulan (`null`) para evitar que inflen el indicador de **Rezago Sin SAP** o penalicen el cumplimiento de **≤ 14 días**.
* **Blindaje de Rechazadas y Borradores:** Las solicitudes con estatus `Rechazado` o `Borrador` quedan excluidas tanto en backend (`DataProcessor.php`) como en frontend (`isCycleRecordExcluded()` en `module_cycle.js` y `modals.js`), garantizando consistencia exacta entre las tarjetas KPI y los modales de auditoría.
* **Exclusión de Sesgo de Corte (Ventana de Madurez):** Los porcentajes de cumplimiento (`≤ 7 días`, `≤ 14 días`, `≤ 30 días`) se calculan exclusivamente sobre solicitudes cuya antigüedad respecto a la fecha de corte (`cutoff_date`) sea mayor o igual a la ventana evaluada.
* **Separación de Responsabilidades del Ciclo:**
  * *Tiempo Aprobador Solicitud:* `Aprobacion Solicitud - Fcreacion Solicitud`.
  * *Gestión Pura del Comprador (KPI Meta 70% ≤ 14 días):* `Fcreacion OC Odoo - Aprobacion Solicitud`.
  * *Aprobación OC (Autorizador 1):* `Aprobacion OC Odoo - Fcreacion OC Odoo`.
  * *Ciclo Total a SAP:* `Fcreacion OC SAP - Aprobacion Solicitud`.
* **Detección de Caja Chica:** Identifica órdenes emitidas a nombre de compradores internos comparando el nombre normalizado del proveedor contra el catálogo de compradores activos, permitiendo excluirlas con un clic (`Sin Caja Chica`).

---

## 5. Control de Acceso por Roles (RBAC) y Configuración

### 5.1 Perfiles de Usuario (`src/Auth.php`)
1. **`manager`:**
   * Acceso global a todas las sociedades, compradores, proveedores y solicitantes.
   * Acceso exclusivo a los botones administrativos del encabezado: **`Sincronizar`**, **`Excel`** (respaldo manual) y **`Config`** (modal de configuración).
2. **`buyer` (Comprador):**
   * Aislamiento estricto a nivel de servidor (`scopePayloadForUser()` y validación en endpoints AJAX `detail`, `firm_lines` y `search`): solo recibe y visualiza las solicitudes y órdenes de compra asignadas a sus nombres de comprador vinculados en Odoo (`odoo_buyers`), manteniendo como referencia anónima el promedio mensual del equipo (`team_avg_by_month`).

### 5.2 Panel de Configuración General (`views/admin_users.php`)
Accesible únicamente para el rol **`manager`** desde el botón **`⚙️ Config`** del encabezado, organizado en **3 pestañas**:
1. **👥 Usuarios y Compradores:** Alta, edición, activación/desactivación y eliminación de usuarios, asignación de rol (`manager` o `buyer`) y vinculación múltiple de nombres de comprador de Odoo.
2. **📅 Fechas de Sincronización:** Configuración de `date_from`, `date_to` y opción automática de seguimiento al día en curso (`follow_today`), con purga inmediata al adelantar la fecha inicial.
3. **🏷️ Estatus (PR / OC):** Interruptores visuales para definir qué estatus de `purchase.request` (`Por aprobar`, `Aprobado`, `hecho`, `Borrador`, `Rechazado`) y `purchase.order` (`Orden de compra`, `Para aprobar 1`, `PARA APROBAR 2`, `Solicitud de cotización`, `Cancelado`) se incluyen o excluyen en los análisis.

---

## 6. Guía Rápida de Mantenimiento (¿Dónde modificar cada cosa?)

| Necesidad de Cambio | Archivo(s) a Modificar |
| :--- | :--- |
| **Ajustar mapeo de campos o estados desde Odoo 17** | `src/OdooConnector.php` (`mapPrState`, `mapPoState`, `syncFromOdoo`) |
| **Modificar reglas de exclusión de análisis o limpieza ERP** | `src/DataProcessor.php` (`applyStatusRulesToCache`, `isLegacyDoneWithoutOcOrSap`) |
| **Agregar nuevos estatus a la pestaña de Configuración** | `src/StatusConfig.php` (`PR_OPTIONS`, `PO_OPTIONS`) |
| **Cambiar lógica de aislamiento de datos para Compradores** | `src/DataProcessor.php` (`scopePayloadForUser`) y `src/Auth.php` |
| **Ajustar diseño del encabezado compacto o barra de filtros** | `views/header.php` y `assets/js/app.js` |
| **Modificar KPIs, gráficas o tablas del Ciclo SLA** | `views/tab_cycle.php` y `assets/js/module_cycle.js` |
| **Modificar KPIs o rastreador de precios de Gasto y Productos** | `views/tab_spend.php` y `assets/js/module_spend.js` |
| **Modificar vista del Portal 360° de Proveedores** | `views/tab_supplier.php` y `assets/js/module_supplier.js` |
| **Ajustar modales de auditoría, buscador global o línea de tiempo** | `views/modals.php` y `assets/js/modals.js` |
| **Personalizar formato de exportación Excel o PDF** | `assets/js/export.js` |

---

## 7. Configuración Inicial y Despliegue en 20i (GitHub)

### 7.1 Archivos de Configuración en `data/`
Antes de ejecutar en un entorno nuevo, asegúrate de crear dentro de `data/` (con permisos de escritura para PHP `0755`/`0644`):

1. **`data/odoo.config.php`:**

```php
<?php
return [
    'url'      => 'https://tu-instancia.odoo.com',
    'db'       => 'nombre_base_datos',
    'username' => 'usuario_api@grupocresta.com',
    'api_key'  => 'TU_API_KEY_ODOO_17',
];
```

2. **`data/auth.config.php`:** Se inicializa automáticamente con el usuario administrador por defecto al primer acceso o se gestiona directamente desde **`⚙️ Config → Usuarios y Compradores`**.

### 7.2 Flujo de Actualización y Cron
1. Realizar cambios en el módulo correspondiente (`src/`, `views/` o `assets/js/`).
2. Hacer `git commit` y `git push` hacia la rama configurada en **20i Git Version Control**.
3. Ejecutar **Deploy / Pull** desde StackCP en 20i. El versionado automático `$assetVer` en `index.php` invalida la caché de CSS/JS en los navegadores de todos los usuarios al recargar.
4. *(Opcional)* Configurar en **StackCP → Scheduled Tasks** la ejecución periódica de `/usr/bin/php /ruta/al/sitio/cron_sync.php` para sincronización automática con Odoo 17.

---

## 8. Autoría

* **Diseño, Arquitectura Funcional, Reglas de Negocio y Desarrollo:** **Elder Juan Velásquez Vela** (Gerente de Supply Chain · Grupo Cresta).
* **Soporte de Desarrollo Web:** [mksbox.com](https://mksbox.com).
