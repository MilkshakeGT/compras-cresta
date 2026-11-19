# Portal Ejecutivo de Control de Compras y Proveedores · Grupo Cresta

> **URL de Producción:** `[https://compras-cresta.mksbox.com](https://compras-cresta.mksbox.com)`  
> **Versión:** `1.09` (Arquitectura Modular)  
> **Autor y Arquitecto:** **Elder Juan Velásquez Vela** · Supply Chain Manager, Grupo Cresta  
> **Infraestructura:** Shared Hosting 20i (StackCP · PHP 8.x · Despliegue continuo vía Git/GitHub)

---

## 1. Descripción General y Propósito

El **Portal Ejecutivo de Control de Compras y Proveedores** es una plataforma web de inteligencia de negocios (*Procurement Analytics*) diseñada para auditar, medir y gestionar el desempeño integral del departamento de Compras y Suministros de **Grupo Cresta** (incluyendo sus distintas sociedades operativas).

El sistema automatiza la consolidación de datos exportados desde **Odoo Community** y **SAP Business One (HANA)** alojados en **OneDrive**, transformando múltiples archivos `.xlsx` en indicadores clave de desempeño (KPIs), análisis de tiempos de ciclo (SLA), seguimiento de gasto, control de precios unitarios por SKU y trazabilidad completa de documentos y proveedores.

---

## 2. Stack Tecnológico

* **Backend:** PHP `8.x` (`strict_types=1`), sin dependencias de Composer ni bases de datos externas (arquitectura *Zero-DB* respaldada por caché JSON indexada por `filemtime` y `schema_v`).
* **Parser Excel Nativo:** `SimpleXLSX.php` (lectura directa de archivos `.xlsx` vía `ZipArchive` + `SimpleXML` en memoria).
* **Frontend & UI:** HTML5, **Tailwind CSS** (vía CDN con soporte nativo para modo claro y modo oscuro `darkMode: 'class'`), tipografía corporativa `Verdana`.
* **Motor de Visualización:** **ApexCharts** (gráficas interactivas de líneas, barras apiladas, barras horizontales, donas y gráficos combinados).
* **Motor de Exportación:**
  * **SheetJS (`xlsx@0.18.5`):** Exportación nativa a libros de Microsoft Excel (`.xlsx`) con autoajuste de ancho de columnas y tipos numéricos/moneda.
  * **jsPDF (`jspdf@2.5.1` UMD) + `ApexCharts.dataURI()`:** Generación de informes ejecutivos vectoriales en PDF tamaño **Carta Vertical (`Letter` Portrait)** con encabezado corporativo, resumen de filtros activos, tarjetas KPI, gráficas en alta resolución y tablas consolidadas.

---

## 3. Arquitectura y Estructura de Directorios

El proyecto sigue una separación modular estricta entre controlador frontal, capa de seguridad, motor ETL, vistas HTML parciales y motores JavaScript por dominio funcional:

```text
compras-cresta/
├── index.php                     # Controlador frontal: autenticación, endpoints AJAX y ensamblaje de vistas
├── README.md                     # Documentación técnica del proyecto
├── src/
│   ├── Auth.php                  # Gestión de sesiones seguras, login, timeout y logout
│   ├── DataProcessor.php         # Sincronización OneDrive, parser ETL, reglas de negocio y caché JSON
│   └── SimpleXLSX.php            # Lector nativo de hojas de cálculo .xlsx y conversión de fechas Excel
├── views/
│   ├── login.php                 # Pantalla de acceso restringido con validación de contraseña
│   ├── header.php                # Barra superior sticky, buscador rápido, navegación y filtros globales
│   ├── tab_cycle.php             # Vista Módulo 1: Ciclo Operativo (SLA) y Carga Operativa
│   ├── tab_spend.php             # Vista Módulo 2: Gestión de Gasto, Compradores y Productos
│   ├── tab_supplier.php          # Vista Módulo 3: Portal 360° de Proveedores (OCs en Firme)
│   └── modals.php                # Modal 1 (Auditoría por KPI) y Modal 2 (Ficha Detallada + Línea de Tiempo)
├── assets/
│   ├── css/
│   │   └── app.css               # Variables corporativas Cresta y estilos de scrollbar/transiciones
│   └── js/
│       ├── app.js                # Estado global, formateadores, shortName(), tema oscuro y filtros
│       ├── export.js             # Motor de exportación a Excel (.xlsx) y PDF vectorial Carta Vertical
│       ├── modals.js             # Lógica de modales de auditoría, búsqueda y línea de tiempo documental
│       ├── module_cycle.js       # Renderizado de KPIs, gráficas y tablas del Módulo 1 (Ciclo y SLA)
│       ├── module_spend.js       # Renderizado de KPIs, gráficas y rastreador de precios del Módulo 2 (Gasto)
│       └── module_supplier.js    # Renderizado de KPIs, catálogo de artículos e historial del Módulo 3 (360°)
└── data/                         # Directorio de datos y configuración (excluido de Git público)
    ├── onedrive.config.php       # URLs de descarga directa de OneDrive y TTL de sincronización
    ├── auth.config.php           # Contraseña de acceso al portal y tiempo de expiración de sesión
    ├── DB.xlsx                   # Base maestra de Solicitudes, Compradores, Aprobadores y OCs (Odoo/SAP)
    ├── Solicitudes.xlsx          # Desglose línea por línea de productos y cantidades solicitadas
    ├── OC.xlsx                   # Desglose línea por línea de OCs, proveedores, precios unitarios y divisas
    ├── cache.json                # Caché procesada principal (generada automáticamente)
    └── details_cache.json        # Caché indexada de líneas de Solicitudes y OCs (generada automáticamente)
```

---

## 4. Fuentes de Datos y Flujo ETL (`src/DataProcessor.php`)

### 4.1 Fuentes de Entrada
El sistema consume tres libros de Excel sincronizados automáticamente desde OneDrive (con caché local configurable mediante `sync_ttl_seconds` y botón de sincronización manual `?sync=1`):
1. **`DB.xlsx`:** Tabla maestra de seguimiento que vincula cada `Solicitud` con su `Estado Solicitud`, `Solicitado por`, `Autorizador Solicitud`, `Comprador`, `Sociedad`, `Fcreacion Solicitud`, `Aprobacion Solicitud`, `NO. OC Odoo`, `Fcreacion OC Odoo`, `Autorizador 1`, `Aprobacion OC Odoo`, `Estado OC`, `NO. OC SAP`, `Fcreacion OC SAP`, `Proveedor`, `Divisa` y `Total OC`.
2. **`Solicitudes.xlsx`:** Detalle granular de líneas por solicitud (`Solicitud`, `Producto Solicitado`, `Cantidad Solicitada`). Permite extraer el código SKU entre corchetes (`[CODIGO]`) para medir carga operativa real.
3. **`OC.xlsx`:** Detalle granular de líneas por orden de compra (`No. OC`, `Proveedor`, `Producto`, `Cantidad Comprada`, `Divisa`, `Precio Unitario`, `Total OC`). Sirve tanto para el rastreo de precios unitarios como de respaldo automático de `Proveedor`, `Divisa` y `Total OC` cuando `DB.xlsx` omita dichos valores.

### 4.2 Reglas de Negocio Implementadas
* **Forward-Fill de Encabezados:** En `DB.xlsx`, cuando una solicitud genera múltiples órdenes de compra en filas consecutivas, el parser propaga automáticamente los metadatos de cabecera de la solicitud hacia las filas hijas.
* **Exclusión de Sesgo de Corte (Ventana de Madurez):** Los porcentajes de cumplimiento (`≤ 7 días`, `≤ 14 días`, `≤ 30 días`) se calculan exclusivamente sobre solicitudes cuya antigüedad respecto a la fecha de corte (`cutoff_date`) sea mayor o igual a la ventana evaluada. Para periodos en curso sin solicitudes maduras, el sistema etiqueta explícitamente el indicador como *"Avance parcial"*.
* **Separación de Responsabilidades del Ciclo:**
  * *Tiempo Aprobador Solicitud:* `Aprobacion Solicitud - Fcreacion Solicitud`.
  * *Gestión Pura del Comprador (KPI Meta 70% ≤ 14 días):* `Fcreacion OC Odoo - Aprobacion Solicitud`.
  * *Aprobación OC (Autorizador 1):* `Aprobacion OC Odoo - Fcreacion OC Odoo`.
  * *Ciclo Total a SAP:* `Fcreacion OC SAP - Aprobacion Solicitud`.
* **Detección de Caja Chica:** Identifica automáticamente órdenes emitidas a nombre de compradores internos comparando el nombre normalizado del proveedor contra el catálogo de compradores activos, permitiendo excluirlas con un clic (`Ocultar Caja Chica`).
* **Estandarización de Nombres (`shortName`):** Convierte nombres completos de 2, 3 o 4 palabras a formato ejecutivo de **Un Primer Nombre + Un Primer Apellido** (ej. `Jaqueline Karina Solares Alvarez` → `Jaqueline Solares`), respetando partículas compuestas (`De`, `Del`, `La`, `San`).

---

## 5. Descripción Detallada de Módulos y Funciones

### Backend (`src/` y `index.php`)
* **`index.php`:**
  * Ejecuta `$auth->requireAuthOrRenderLogin()` antes de procesar o entregar cualquier dato.
  * Atiende los endpoints JSON protegidos:
    * `?action=detail&type={sol|oc}&id={codigo}`: Devuelve metadatos, fechas de trazabilidad y líneas de producto de una solicitud u OC.
    * `?action=firm_lines`: Devuelve bajo demanda todas las líneas de órdenes de compra en firme para el análisis de insumos y Portal 360°.
  * Calcula `$assetVer` usando el `max(filemtime)` de todos los archivos CSS y JS para invalidar automáticamente la caché del navegador ante cualquier actualización.
* **`src/Auth.php`:**
  * `startSession()`: Configura cookies de sesión `HttpOnly`, `SameSite=Strict` y `Secure` (en HTTPS).
  * `isAuthenticated()`: Valida sesión activa y controla inactividad (`session_timeout`, por defecto 8 horas).
  * `attemptLoginFromPost()`: Valida credenciales mediante comparación segura en tiempo constante (`hash_equals`).
* **`src/DataProcessor.php`:**
  * `syncFromOneDrive(bool $force)`: Descarga los `.xlsx` desde OneDrive validando integridad ZIP/XML y verificando cambios mediante `md5_file`.
  * `rebuildDetailsCacheIfNeeded(bool $force)`: Indexa `Solicitudes.xlsx` y `OC.xlsx` en `data/details_cache.json`.
  * `getProcessedData(bool $forceSync)`: Ejecuta el pipeline ETL completo y genera `data/cache.json` (`schema_v = 16`).

### Frontend (`assets/js/`)
* **`assets/js/app.js`:**
  * Gestiona el estado global (`mainTab`, `periodMode`, `activeCurrency`, `isDark`, `charts`).
  * `initFilters()`: Pobla selectores de Año, Mes, Trimestre (`Q1`–`Q4`), Comparación Trimestral (`Q vs Q`), YTD, Sociedad, Comprador, Solicitante y Proveedor.
  * `filterByEntity()`, `filterSpendRecordsBase()`, `filterByActivePeriod()`: Motores de filtrado reactivo en memoria.
  * `renderDashboard()`: Enruta el renderizado según la pestaña activa (`renderCycleView`, `renderSpendView` o `renderSupplier360View`).
* **`assets/js/module_cycle.js` (Módulo 1 · Ciclo Operativo y SLA):**
  * `calcKpiStats()` y `renderCycleView()`: Calcula los 5 KPIs principales (Solicitudes Válidas, Gestión Comprador en días, SLA Comprador ≤14d, En SAP ≤14d, Rezago Sin SAP).
  * `renderChartQuincenas()`: Soporta doble modo mediante `setQuincenaChartMode()`: **Acumulado** (`≤7d`, `≤14d`, `≤30d`) y **Rangos 100%** (`0-7d`, `8-14d`, `15-30d`, `>30d/Pendientes`).
  * `renderBuyerWorkloadSection()`: Cruza `Solicitudes.xlsx` para mostrar **Líneas de Solicitud Atendidas** y **SKUs Individuales Atendidos** por comprador.
  * Renderiza además: *Desglose del Ciclo Promedio*, *Rezago Abierto Sin SAP*, *Ranking de Aprobadores de Solicitud y de OC*, *Evolución Mensual por Comprador*, *Top 10 Solicitantes Internos* y la *Tabla Mensual Consolidada*.
* **`assets/js/module_spend.js` (Módulo 2 · Gasto y Productos):**
  * `calcSpendStats()` y `renderSpendView()`: Analiza gasto comprometido en `GTQ` o `USD`, ticket promedio/mediana, concentración Pareto Top 5, cotizaciones abiertas (RFQ) e integración SAP.
  * `renderProductsAnalyticsSection()` y `renderProductPriceTrend()`: Rastrea los Top 10 productos por monto y grafica la evolución mensual del precio unitario (mínimo, promedio y máximo) de cualquier insumo seleccionado.
* **`assets/js/module_supplier.js` (Módulo 3 · Portal 360° Proveedor):**
  * `renderSupplier360View()`: Vista dedicada exclusivamente a **OCs en firme** por proveedor (o consolidado global), mostrando compras totales, participación %, frecuencia de órdenes, primera y última compra, catálogo completo de artículos comprados con historial de precios (`Mín.`, `Prom.`, `Máx.`, `Último`) e historial cronológico de OCs.
* **`assets/js/modals.js` (Auditoría y Trazabilidad Documental):**
  * `openAuditModal()`: Despliega listados auditables al hacer clic en cualquier tarjeta KPI, con pestañas de subfiltro y búsqueda instantánea.
  * `openDocumentDetail()` y `renderDocumentTimeline()`: Abre la ficha completa de cualquier **Solicitud** u **Orden de Compra** (desde tablas o desde el buscador superior), dibujando una **línea de tiempo visual con fechas exactas, responsables y días transcurridos** entre cada etapa (`Creación Solicitud → Autorización Solicitud → Creación OC Odoo → Aprobación OC / Envío SAP`) junto con el detalle de líneas.
* **`assets/js/export.js` (Exportación Excel y PDF):**
  * `exportToExcel()`: Genera archivos `.xlsx` nativos con columnas autoajustadas desde cualquier modal o vista de proveedor.
  * `exportActiveTabPDF()`: Construye un informe ejecutivo vectorial en **Carta Vertical (`Letter` Portrait)** extrayendo cada gráfica en PNG de alta escala (`chart.dataURI({ scale: 2 })`) y maquetando encabezado, filtros activos, KPIs, gráficas y tablas sin recortes ni saltos de página rotos.

---

## 6. Configuración y Despliegue en 20i (GitHub)

### 6.1 Archivos de Configuración en `data/`
Antes de ejecutar en un entorno nuevo, asegúrate de crear dentro de `data/` (con permisos de escritura para PHP `0755`/`0644`):

1. **`data/auth.config.php`:**
```php
<?php
return [
    'dashboard_password' => 'TU_CONTRASEÑA_SEGURA',
    'session_timeout'    => 28800, // 8 horas
];
```

2. **`data/onedrive.config.php`:**
```php
<?php
return [
    'sync_ttl_seconds'         => 1800, // Sincronización automática cada 30 min
    'onedrive_url'             => 'URL_COMPARTIDA_DE_DB_XLSX',
    'onedrive_solicitudes_url' => 'URL_COMPARTIDA_DE_SOLICITUDES_XLSX',
    'onedrive_oc_url'          => 'URL_COMPARTIDA_DE_OC_XLSX',
];
```

### 6.2 Flujo de Actualización
1. Realizar cambios en el módulo correspondiente (`src/`, `views/` o `assets/js/`).
2. Hacer `git commit` y `git push` hacia la rama configurada en **20i Git Version Control**.
3. Ejecutar **Deploy / Pull** desde el panel de StackCP en 20i. El versionado automático `$assetVer` actualizará los recursos en el navegador de todos los usuarios al recargar.

---

## 7. Autoría

* **Diseño, Arquitectura Funcional, Reglas de Negocio y Desarrollo:** **Elder Juan Velásquez Vela** (Supply Chain Manager · Grupo Cresta).
* **Soporte de Desarrollo Web:** [mksbox.com](https://mksbox.com).
