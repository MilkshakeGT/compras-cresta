<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleXLSX.php';

class DataProcessor
{
    private string $excelPath;
    private string $cachePath;
    private string $solExcelPath;
    private string $ocExcelPath;
    private string $detailsCachePath;

    // Aprobadores retirados / inactivos que distorsionan el ranking operativo
    private array $excludedApprovers = [
        'maria isabel',
        'maría isabel',
    ];

    public function __construct(string $excelPath, string $cachePath)
    {
        $dataDir = dirname($excelPath);
        $this->excelPath        = $excelPath;
        $this->cachePath        = $cachePath;
        $this->solExcelPath     = $dataDir . '/Solicitudes.xlsx';
        $this->ocExcelPath      = $dataDir . '/OC.xlsx';
        $this->detailsCachePath = $dataDir . '/details_cache.json';
    }

    // =========================================================================
    // UTILIDADES DE NORMALIZACIÓN Y LIMPIEZA
    // =========================================================================
    private function isExcludedApprover(string $name): bool
    {
        $norm = mb_strtolower(trim($name));
        if ($norm === '' || $norm === 'sin asignar') {
            return true;
        }
        foreach ($this->excludedApprovers as $ex) {
            if (str_contains($norm, $ex)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeForMatch(string $text): string
    {
        $s = mb_strtolower(trim($text));
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n'
        ]);
        return preg_replace('/\s+/', ' ', $s);
    }

    private function extractSkuCode(string $productText): string
    {
        $clean = trim($productText);
        if ($clean === '') {
            return '';
        }
        if (preg_match('/^\[([^\]]+)\]/', $clean, $m)) {
            return strtoupper(trim($m[1]));
        }
        return mb_strtoupper($clean);
    }

    private function findColumnIndex(array $colMap, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $key = mb_strtolower(trim($alias));
            if (isset($colMap[$key])) {
                return (int)$colMap[$key];
            }
        }
        return null;
    }

    // =========================================================================
    // INDEXACIÓN DE LÍNEAS CRUDAS DE SOLICITUDES Y ÓRDENES DE COMPRA (ODOO)
    // =========================================================================
    private function rebuildDetailsCacheIfNeeded(bool $force = false): void
    {
        $solMtime = file_exists($this->solExcelPath) ? filemtime($this->solExcelPath) : 0;
        $ocMtime  = file_exists($this->ocExcelPath) ? filemtime($this->ocExcelPath) : 0;

        if (!$force && file_exists($this->detailsCachePath)) {
            $existing = json_decode(file_get_contents($this->detailsCachePath), true);
            if (is_array($existing)
                && ($existing['schema_v'] ?? 0) === 3
                && ($existing['sol_mtime'] ?? -1) === $solMtime
                && ($existing['oc_mtime'] ?? -1) === $ocMtime
            ) {
                return;
            }
        }

        // 1. Parseo de Líneas de Solicitud (purchase.request.line) con forward-fill
        $solDetails = [];
        if ($solMtime > 0) {
            $rows = SimpleXLSX::parse($this->solExcelPath);
            if (count($rows) > 1) {
                $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
                $colMap  = array_flip($headers);

                $idxId   = $this->findColumnIndex($colMap, ['referencia de solicitud', 'solicitud']) ?? 0;
                $idxProd = $this->findColumnIndex($colMap, ['productos para comprar/producto', 'producto solicitado', 'producto']) ?? 1;
                $idxQty  = $this->findColumnIndex($colMap, ['productos para comprar/cantidad', 'cantidad solicitada', 'cantidad']) ?? 2;

                $lastSolId = '';
                for ($i = 1; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    $rawId = trim((string)($r[$idxId] ?? ''));
                    if ($rawId !== '') {
                        $lastSolId = $rawId;
                    }
                    if ($lastSolId === '') {
                        continue;
                    }

                    $prod = trim((string)($r[$idxProd] ?? ''));
                    $qtyRaw = $r[$idxQty] ?? null;
                    $qty = is_numeric($qtyRaw) ? round((float)$qtyRaw, 4) : 0.0;

                    if ($prod === '' && $qty === 0.0) {
                        continue;
                    }
                    if (!isset($solDetails[$lastSolId])) {
                        $solDetails[$lastSolId] = [];
                    }
                    $solDetails[$lastSolId][] = [
                        'producto' => $prod !== '' ? $prod : '(Sin descripción)',
                        'cantidad' => $qty,
                    ];
                }
            }
        }

        // 2. Parseo de Líneas de Órdenes de Compra (purchase.order.line) con forward-fill
        $ocDetails = [];
        if ($ocMtime > 0) {
            $rows = SimpleXLSX::parse($this->ocExcelPath);
            if (count($rows) > 1) {
                $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
                $colMap  = array_flip($headers);

                $idxId   = $this->findColumnIndex($colMap, ['referencia de la orden', 'no. oc', 'no. oc odoo']) ?? 0;
                $idxProv = $this->findColumnIndex($colMap, ['proveedor', 'partner']) ?? 1;
                $idxProd = $this->findColumnIndex($colMap, ['líneas de la orden/descripción', 'lineas de la orden/descripcion', 'producto', 'descripción']) ?? 2;
                $idxQty  = $this->findColumnIndex($colMap, ['líneas de la orden/cantidad', 'lineas de la orden/cantidad', 'cantidad comprada', 'cantidad']) ?? 3;
                $idxDiv  = $this->findColumnIndex($colMap, ['líneas de la orden/divisa', 'lineas de la orden/divisa', 'divisa', 'moneda']) ?? 4;
                $idxPu   = $this->findColumnIndex($colMap, ['líneas de la orden/precio unitario', 'lineas de la orden/precio unitario', 'precio unitario']) ?? 5;
                $idxTot  = $this->findColumnIndex($colMap, ['líneas de la orden/total', 'lineas de la orden/total', 'total oc', 'subtotal']) ?? 6;

                $lastOcId = '';
                $lastProv = 'Sin proveedor';

                for ($i = 1; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    $rawId = trim((string)($r[$idxId] ?? ''));
                    if ($rawId !== '') {
                        $lastOcId = $rawId;
                        $rawProv = trim((string)($r[$idxProv] ?? ''));
                        $lastProv = $rawProv !== '' ? $rawProv : 'Sin proveedor';
                    }
                    if ($lastOcId === '') {
                        continue;
                    }

                    $prod = trim((string)($r[$idxProd] ?? ''));
                    $qty  = is_numeric($r[$idxQty] ?? null) ? round((float)$r[$idxQty], 4) : 0.0;
                    $div  = strtoupper(trim((string)($r[$idxDiv] ?? 'GTQ')));
                    if ($div === '') {
                        $div = 'GTQ';
                    }
                    $pu  = is_numeric($r[$idxPu] ?? null) ? round((float)$r[$idxPu], 4) : 0.0;
                    $tot = is_numeric($r[$idxTot] ?? null) ? round((float)$r[$idxTot], 2) : round($qty * $pu, 2);

                    if (!isset($ocDetails[$lastOcId])) {
                        $ocDetails[$lastOcId] = [
                            'proveedor' => $lastProv,
                            'divisa'    => $div,
                            'lineas'    => [],
                        ];
                    }
                    $ocDetails[$lastOcId]['lineas'][] = [
                        'producto'        => $prod !== '' ? $prod : '(Sin descripción)',
                        'cantidad'        => $qty,
                        'precio_unitario' => $pu,
                        'total'           => $tot,
                    ];
                }
            }
        }

        $detailsPayload = [
            'schema_v'  => 3,
            'sol_mtime' => $solMtime,
            'oc_mtime'  => $ocMtime,
            'sol'       => $solDetails,
            'oc'        => $ocDetails,
        ];

        file_put_contents($this->detailsCachePath, json_encode($detailsPayload, JSON_UNESCAPED_UNICODE));
    }

    // =========================================================================
    // ENDPOINTS AJAX DE DETALLE DOCUMENTAL Y LÍNEAS FIRMES
    // =========================================================================
    public function getDocumentDetail(string $type, string $id): array
    {
        $this->rebuildDetailsCacheIfNeeded(false);

        $id = trim($id);
        $details = file_exists($this->detailsCachePath)
            ? json_decode(file_get_contents($this->detailsCachePath), true)
            : [];

        $mainCache = file_exists($this->cachePath)
            ? json_decode(file_get_contents($this->cachePath), true)
            : [];

        $cutoffDate = $mainCache['cutoff_date'] ?? date('Y-m-d');

        if ($type === 'sol') {
            $meta = null;
            foreach (($mainCache['records'] ?? []) as $rec) {
                if (($rec['id'] ?? '') === $id) {
                    $meta = $rec;
                    break;
                }
            }
            $lines = $details['sol'][$id] ?? [];
            return [
                'found'       => ($meta !== null || !empty($lines)),
                'type'        => 'sol',
                'id'          => $id,
                'cutoff_date' => $cutoffDate,
                'meta'        => $meta,
                'lines'       => $lines,
            ];
        }

        if ($type === 'oc') {
            $meta = null;
            foreach (($mainCache['spend_records'] ?? []) as $srec) {
                if (($srec['oc_odoo'] ?? '') === $id) {
                    $meta = $srec;
                    break;
                }
            }
            $solMeta = null;
            $solId = $meta['solicitud'] ?? '';
            if ($solId !== '') {
                foreach (($mainCache['records'] ?? []) as $rec) {
                    if (($rec['id'] ?? '') === $solId) {
                        $solMeta = $rec;
                        break;
                    }
                }
            }

            $ocInfo = $details['oc'][$id] ?? null;
            return [
                'found'       => ($meta !== null || $ocInfo !== null),
                'type'        => 'oc',
                'id'          => $id,
                'cutoff_date' => $cutoffDate,
                'meta'        => $meta,
                'sol_meta'    => $solMeta,
                'detail'      => $ocInfo,
            ];
        }

        return ['found' => false, 'error' => 'Tipo de documento inválido'];
    }

    public function getFirmOcLines(): array
    {
        $this->rebuildDetailsCacheIfNeeded(false);

        $details = file_exists($this->detailsCachePath)
            ? json_decode(file_get_contents($this->detailsCachePath), true)
            : [];
        $mainCache = file_exists($this->cachePath)
            ? json_decode(file_get_contents($this->cachePath), true)
            : [];

        $ocDetailMap = $details['oc'] ?? [];
        $firmLines = [];

        foreach (($mainCache['spend_records'] ?? []) as $srec) {
            if (empty($srec['is_firm'])) {
                continue;
            }
            $ocId = $srec['oc_odoo'] ?? '';
            if ($ocId === '') {
                continue;
            }

            $ocInfo = $ocDetailMap[$ocId] ?? null;
            $lineas = $ocInfo['lineas'] ?? [];

            if (!empty($lineas)) {
                foreach ($lineas as $l) {
                    $firmLines[] = [
                        'oc_odoo'        => $ocId,
                        'solicitud'      => $srec['solicitud'] ?? '',
                        'ym'             => $srec['ym'] ?? '',
                        'fecha'          => $srec['fecha'] ?? '',
                        'sociedad'       => $srec['sociedad'] ?? '',
                        'comprador'      => $srec['comprador'] ?? '',
                        'solicitado_por' => $srec['solicitado_por'] ?? '',
                        'proveedor'      => $srec['proveedor'] ?? '',
                        'is_caja_chica'  => !empty($srec['is_caja_chica']),
                        'divisa'         => $srec['divisa'] ?? 'GTQ',
                        'en_sap'         => !empty($srec['en_sap']),
                        'producto'       => $l['producto'] ?? '(Sin descripción)',
                        'cantidad'       => (float)($l['cantidad'] ?? 0),
                        'pu'             => (float)($l['precio_unitario'] ?? 0),
                        'total'          => (float)($l['total'] ?? 0),
                    ];
                }
            } else {
                $firmLines[] = [
                    'oc_odoo'        => $ocId,
                    'solicitud'      => $srec['solicitud'] ?? '',
                    'ym'             => $srec['ym'] ?? '',
                    'fecha'          => $srec['fecha'] ?? '',
                    'sociedad'       => $srec['sociedad'] ?? '',
                    'comprador'      => $srec['comprador'] ?? '',
                    'solicitado_por' => $srec['solicitado_por'] ?? '',
                    'proveedor'      => $srec['proveedor'] ?? '',
                    'is_caja_chica'  => !empty($srec['is_caja_chica']),
                    'divisa'         => $srec['divisa'] ?? 'GTQ',
                    'en_sap'         => !empty($srec['en_sap']),
                    'producto'       => '(Sin detalle de línea)',
                    'cantidad'       => 1.0,
                    'pu'             => (float)($srec['monto'] ?? 0),
                    'total'          => (float)($srec['monto'] ?? 0),
                ];
            }
        }

        return [
            'count' => count($firmLines),
            'lines' => $firmLines,
        ];
    }

    // =========================================================================
    // MOTOR PRINCIPAL ETL DE SOLICITUDES Y OCs (purchase.request)
    // =========================================================================
    public function getProcessedData(bool $forceRebuild = false): array
    {
        $this->rebuildDetailsCacheIfNeeded($forceRebuild);

        if (!file_exists($this->excelPath)) {
            throw new RuntimeException("No se encontró el archivo data/DB.xlsx. Usa el botón 'Cargar Datos' para subir los reportes de Odoo.");
        }

        $excelMtime = filemtime($this->excelPath);
        $solMtime   = file_exists($this->solExcelPath) ? filemtime($this->solExcelPath) : 0;
        $ocMtime    = file_exists($this->ocExcelPath) ? filemtime($this->ocExcelPath) : 0;

        if (!$forceRebuild && file_exists($this->cachePath)) {
            $cached = json_decode(file_get_contents($this->cachePath), true);
            if (is_array($cached)
                && ($cached['excel_mtime'] ?? 0) === $excelMtime
                && ($cached['sol_mtime'] ?? -1) === $solMtime
                && ($cached['oc_mtime'] ?? -1) === $ocMtime
                && ($cached['schema_v'] ?? 1) === 17
            ) {
                return $cached;
            }
        }

        $detailsData = file_exists($this->detailsCachePath)
            ? json_decode(file_get_contents($this->detailsCachePath), true)
            : [];
        $solLinesMap = $detailsData['sol'] ?? [];
        $ocDetailMap = $detailsData['oc'] ?? [];

        // Precalcular totales, proveedor y divisa por No. OC desde OC.xlsx
        $ocSummaryFromDetail = [];
        foreach ($ocDetailMap as $ocCode => $ocInfo) {
            $sumTot = 0.0;
            foreach (($ocInfo['lineas'] ?? []) as $ln) {
                $sumTot += (float)($ln['total'] ?? 0);
            }
            $ocSummaryFromDetail[$ocCode] = [
                'proveedor' => trim((string)($ocInfo['proveedor'] ?? '')),
                'divisa'    => strtoupper(trim((string)($ocInfo['divisa'] ?? 'GTQ'))),
                'total'     => round($sumTot, 2),
            ];
        }

        $rawRows = SimpleXLSX::parse($this->excelPath);
        if (count($rawRows) < 2) {
            throw new RuntimeException("El archivo maestro de solicitudes no contiene datos suficientes.");
        }

        $headers = array_map(fn($h) => trim((string)$h), $rawRows[0]);
        $colMap = [];
        foreach ($headers as $idx => $hName) {
            $colMap[mb_strtolower($hName)] = $idx;
        }

        $getCol = function (array $row, array|string $names) use ($colMap) {
            $list = is_array($names) ? $names : [$names];
            foreach ($list as $name) {
                $key = mb_strtolower(trim($name));
                if (isset($colMap[$key])) {
                    return $row[$colMap[$key]] ?? null;
                }
            }
            return null;
        };

        // Aliases duales: Reporte crudo Odoo (purchase.request) + Formato histórico
        $headerAliases = [
            'Solicitud'             => ['Referencia de Solicitud', 'Solicitud'],
            'Estado Solicitud'      => ['Estado', 'Estado Solicitud'],
            'Solicitado por'        => ['Solicitado por'],
            'Autorizador Solicitud' => ['Autorizador', 'Autorizador Solicitud'],
            'Comprador'             => ['Seguidor', 'Comprador'],
            'Fcreacion Solicitud'   => ['Fecha de creación', 'Fecha de creacion', 'Fcreacion Solicitud'],
            'Aprobacion Solicitud'  => ['Approval Date', 'Aprobacion Solicitud'],
            'Sociedad'              => ['Seleccionar compañía', 'Seleccionar compania', 'Sociedad', 'Compañía'],
        ];

        $lastHeaderData = array_fill_keys(array_keys($headerAliases), null);
        $normalizedRows = [];
        $maxDateTs = 0;

        for ($i = 1; $i < count($rawRows); $i++) {
            $r = $rawRows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $sol = trim((string)($getCol($r, $headerAliases['Solicitud']) ?? ''));
            if ($sol !== '') {
                foreach ($headerAliases as $k => $aliases) {
                    $lastHeaderData[$k] = $getCol($r, $aliases);
                }
            } else {
                if ($lastHeaderData['Solicitud'] === null) {
                    continue;
                }
            }

            $fCreacionSolTs   = SimpleXLSX::excelDateToTimestamp($lastHeaderData['Fcreacion Solicitud']);
            $fAprobacionSolTs = SimpleXLSX::excelDateToTimestamp($lastHeaderData['Aprobacion Solicitud']);
            $fCreacionOcTs    = SimpleXLSX::excelDateToTimestamp($getCol($r, ['Purchase Orders/Creado el', 'Fcreacion OC Odoo', 'Fecha OC Odoo']));
            $fAprobacionOcTs  = SimpleXLSX::excelDateToTimestamp($getCol($r, ['Purchase Orders/Fecha de confirmación', 'Purchase Orders/Fecha de confirmacion', 'Aprobacion OC Odoo']));
            $fCreacionSapTs   = SimpleXLSX::excelDateToTimestamp($getCol($r, ['Purchase Orders/Fecha SAP', 'Fcreacion OC SAP', 'Fecha OC SAP']));

            if ($fCreacionSolTs && $fCreacionSolTs > $maxDateTs) {
                $maxDateTs = $fCreacionSolTs;
            }
            if ($fCreacionOcTs && $fCreacionOcTs > $maxDateTs) {
                $maxDateTs = $fCreacionOcTs;
            }

            $noOcOdoo = trim((string)($getCol($r, ['Purchase Orders', 'NO. OC Odoo', 'No. OC Odoo', 'No. OC']) ?? ''));
            $ocFallback = ($noOcOdoo !== '' && isset($ocSummaryFromDetail[$noOcOdoo])) ? $ocSummaryFromDetail[$noOcOdoo] : null;

            $rawTotalOc = $getCol($r, ['Total OC', 'Total', 'Monto OC']);
            $totalOc = is_numeric($rawTotalOc) ? round((float)$rawTotalOc, 2) : 0.0;
            if ($totalOc == 0.0 && $ocFallback !== null && $ocFallback['total'] > 0) {
                $totalOc = $ocFallback['total'];
            }

            $divisa = strtoupper(trim((string)($getCol($r, ['Divisa', 'Moneda']) ?? '')));
            if ($divisa === '' && $ocFallback !== null && $ocFallback['divisa'] !== '') {
                $divisa = $ocFallback['divisa'];
            }
            if ($divisa === '') {
                $divisa = 'GTQ';
            }

            $proveedor = trim((string)($getCol($r, ['Proveedor', 'Supplier', 'Partner']) ?? ''));
            if (($proveedor === '' || $proveedor === 'Sin proveedor') && $ocFallback !== null && $ocFallback['proveedor'] !== '') {
                $proveedor = $ocFallback['proveedor'];
            }

            // Limpiar DocNum SAP (evitar que "104817.0" aparezca con decimal)
            $rawSapDoc = trim((string)($getCol($r, ['Purchase Orders/DocNum SAP', 'NO. OC SAP', 'No. OC SAP']) ?? ''));
            if ($rawSapDoc !== '' && is_numeric($rawSapDoc)) {
                $rawSapDoc = (string)(int)round((float)$rawSapDoc);
            }

            $solicitanteName = trim((string)($lastHeaderData['Solicitado por'] ?? ''));
            if ($solicitanteName === '') {
                $solicitanteName = 'Sin solicitante';
            }

            $normalizedRows[] = [
                'solicitud'          => trim((string)$lastHeaderData['Solicitud']),
                'estado_sol'         => trim((string)($lastHeaderData['Estado Solicitud'] ?? '')),
                'solicitado_por'     => $solicitanteName,
                'autorizador_sol'    => trim((string)($lastHeaderData['Autorizador Solicitud'] ?? '')),
                'autorizador_oc_1'   => trim((string)($getCol($r, ['Purchase Orders/Autorizador 1', 'Autorizador 1', 'Autorizador OC']) ?? '')),
                'comprador'          => trim((string)($lastHeaderData['Comprador'] ?? 'Sin asignar')),
                'sociedad'           => trim((string)($lastHeaderData['Sociedad'] ?? 'Sin sociedad')),
                'no_oc_odoo'         => $noOcOdoo,
                'no_oc_sap'          => $rawSapDoc,
                'estado_oc'          => trim((string)($getCol($r, ['Purchase Orders/Estado', 'Estado OC']) ?? '')),
                'proveedor'          => $proveedor,
                'divisa'             => $divisa,
                'total_oc'           => $totalOc,
                'ts_creacion_sol'    => $fCreacionSolTs,
                'ts_aprobacion_sol'  => $fAprobacionSolTs,
                'ts_creacion_oc'     => $fCreacionOcTs,
                'ts_aprobacion_oc'   => $fAprobacionOcTs,
                'ts_creacion_sap'    => $fCreacionSapTs,
            ];
        }

        $buyerNamesNorm = [];
        foreach ($normalizedRows as $row) {
            if ($row['comprador'] !== '' && $row['comprador'] !== 'Sin asignar') {
                $buyerNamesNorm[$this->normalizeForMatch($row['comprador'])] = true;
            }
        }

        $cutoffTs = $maxDateTs > 0 ? $maxDateTs : time();

        $solicitudesMap  = [];
        $ocStats         = [];
        $ocRecords       = [];
        $spendRecords    = [];
        $proveedoresSet  = [];
        $solicitantesSet = [];

        foreach ($normalizedRows as $row) {
            $solId = $row['solicitud'];
            if ($solId === '' || !$row['ts_creacion_sol']) {
                continue;
            }

            $solicitantesSet[$row['solicitado_por']] = true;

            $estadoSolLower = mb_strtolower($row['estado_sol']);
            $isSolRechazada = ($estadoSolLower === 'rechazado');
            $isSolBorrador  = ($estadoSolLower === 'borrador' || $estadoSolLower === 'draft');
            $estadoOcLower  = mb_strtolower($row['estado_oc']);
            $isOcCancelada  = ($estadoOcLower === 'cancelado');

            if ($row['no_oc_odoo'] !== '') {
                $ym = date('Y-m', $row['ts_creacion_sol']);
                if (!isset($ocStats[$ym])) {
                    $ocStats[$ym] = ['total' => 0, 'canceladas' => 0];
                }
                $ocStats[$ym]['total']++;
                if ($isOcCancelada) {
                    $ocStats[$ym]['canceladas']++;
                }

                if (!$isSolRechazada && !$isSolBorrador && !$isOcCancelada && $row['ts_creacion_oc'] !== null && $row['ts_aprobacion_oc'] !== null) {
                    $diasAprobOc = round(max(0, ($row['ts_aprobacion_oc'] - $row['ts_creacion_oc']) / 86400), 2);
                    $aprobOcName = $row['autorizador_oc_1'] !== '' ? $row['autorizador_oc_1'] : 'Sin asignar';

                    $ocRecords[] = [
                        'oc_odoo'           => $row['no_oc_odoo'],
                        'solicitud'         => $solId,
                        'ym'                => date('Y-m', $row['ts_creacion_oc']),
                        'sociedad'          => $row['sociedad'] !== '' ? $row['sociedad'] : 'Sin sociedad',
                        'comprador'         => $row['comprador'] !== '' ? $row['comprador'] : 'Sin asignar',
                        'solicitado_por'    => $row['solicitado_por'],
                        'aprobador_oc'      => $aprobOcName,
                        'aprob_oc_excluido' => $this->isExcludedApprover($aprobOcName),
                        'd_aprob_oc'        => $diasAprobOc,
                    ];
                }

                $provName = $row['proveedor'] !== '' ? $row['proveedor'] : 'Sin proveedor';
                $provNorm = $this->normalizeForMatch($provName);
                $isCajaChica = isset($buyerNamesNorm[$provNorm]);

                $ocTs = $row['ts_creacion_oc'] ?? $row['ts_creacion_sol'];
                $ocYm = date('Y-m', $ocTs);
                $ocFecha = date('Y-m-d', $ocTs);

                $isFirm = in_array($estadoOcLower, ['orden de compra', 'para aprobar 1', 'para aprobar 2'], true)
                          && !$isSolRechazada && !$isSolBorrador;
                $isRfq  = ($estadoOcLower === 'solicitud de cotización' || $estadoOcLower === 'solicitud de cotizacion')
                          && !$isSolRechazada && !$isSolBorrador;

                if ($provName !== 'Sin proveedor') {
                    $proveedoresSet[$provName] = true;
                }

                $dAprobSolOc = ($row['ts_aprobacion_sol'] && $row['ts_aprobacion_sol'] >= $row['ts_creacion_sol'])
                    ? round(($row['ts_aprobacion_sol'] - $row['ts_creacion_sol']) / 86400, 2)
                    : null;

                $dCompOc = ($row['ts_aprobacion_sol'] && $row['ts_creacion_oc'])
                    ? round(max(0, ($row['ts_creacion_oc'] - $row['ts_aprobacion_sol']) / 86400), 2)
                    : null;

                $dAprobOc = ($row['ts_creacion_oc'] && $row['ts_aprobacion_oc'])
                    ? round(max(0, ($row['ts_aprobacion_oc'] - $row['ts_creacion_oc']) / 86400), 2)
                    : null;

                $dSapOc = ($row['ts_creacion_oc'] && $row['ts_creacion_sap'])
                    ? round(max(0, ($row['ts_creacion_sap'] - $row['ts_creacion_oc']) / 86400), 2)
                    : null;

                $spendRecords[] = [
                    'oc_odoo'          => $row['no_oc_odoo'],
                    'oc_sap'           => $row['no_oc_sap'],
                    'solicitud'        => $solId,
                    'estado_sol'       => $row['estado_sol'],
                    'autorizador_sol'  => $row['autorizador_sol'],
                    'autorizador_oc_1' => $row['autorizador_oc_1'],
                    'ym'               => $ocYm,
                    'fecha'            => $ocFecha,
                    'f_creacion_sol'   => $row['ts_creacion_sol'] ? date('Y-m-d H:i', $row['ts_creacion_sol']) : null,
                    'f_aprobacion_sol' => $row['ts_aprobacion_sol'] ? date('Y-m-d H:i', $row['ts_aprobacion_sol']) : null,
                    'f_creacion_oc'    => $row['ts_creacion_oc'] ? date('Y-m-d H:i', $row['ts_creacion_oc']) : null,
                    'f_aprobacion_oc'  => $row['ts_aprobacion_oc'] ? date('Y-m-d H:i', $row['ts_aprobacion_oc']) : null,
                    'f_creacion_sap'   => $row['ts_creacion_sap'] ? date('Y-m-d H:i', $row['ts_creacion_sap']) : null,
                    'd_aprob_sol'      => $dAprobSolOc,
                    'd_comprador'      => $dCompOc,
                    'd_aprob_oc'       => $dAprobOc,
                    'd_to_sap_oc'      => $dSapOc,
                    'sociedad'         => $row['sociedad'] !== '' ? $row['sociedad'] : 'Sin sociedad',
                    'comprador'        => $row['comprador'] !== '' ? $row['comprador'] : 'Sin asignar',
                    'solicitado_por'   => $row['solicitado_por'],
                    'proveedor'        => $provName,
                    'is_caja_chica'    => $isCajaChica,
                    'divisa'           => $row['divisa'],
                    'monto'            => $row['total_oc'],
                    'estado_oc'        => $row['estado_oc'],
                    'is_firm'          => $isFirm,
                    'is_rfq'           => $isRfq,
                    'is_cancelada'     => $isOcCancelada,
                    'en_sap'           => ($row['no_oc_sap'] !== ''),
                ];
            }

            if (!isset($solicitudesMap[$solId])) {
                $aprobSolName = $row['autorizador_sol'] !== '' ? $row['autorizador_sol'] : 'Sin asignar';
                $solicitudesMap[$solId] = [
                    'solicitud'          => $solId,
                    'estado_sol'         => $row['estado_sol'],
                    'solicitado_por'     => $row['solicitado_por'],
                    'autorizador_sol'    => $aprobSolName,
                    'aprob_sol_excluido' => $this->isExcludedApprover($aprobSolName),
                    'is_rechazada'       => $isSolRechazada,
                    'is_borrador'        => $isSolBorrador,
                    'comprador'          => $row['comprador'] !== '' ? $row['comprador'] : 'Sin asignar',
                    'sociedad'           => $row['sociedad'] !== '' ? $row['sociedad'] : 'Sin sociedad',
                    'ts_creacion_sol'    => $row['ts_creacion_sol'],
                    'ts_aprobacion_sol'  => $row['ts_aprobacion_sol'],
                    'valid_ocs'          => [],
                    'all_ocs_canceled'   => true,
                    'has_any_oc'         => false,
                ];
            }

            if ($row['no_oc_odoo'] !== '') {
                $solicitudesMap[$solId]['has_any_oc'] = true;
                if (!$isOcCancelada) {
                    $solicitudesMap[$solId]['all_ocs_canceled'] = false;
                    $solicitudesMap[$solId]['valid_ocs'][] = $row;
                }
            }
        }

        $records = [];
        $sociedades = [];
        $compradores = [];
        $meses = [];

        foreach ($solicitudesMap as $sol) {
            $solId           = $sol['solicitud'];
            $tsCreacionSol   = $sol['ts_creacion_sol'];
            $tsAprobacionSol = $sol['ts_aprobacion_sol'];
            $ym              = date('Y-m', $tsCreacionSol);
            $dayOfMonth      = (int)date('d', $tsCreacionSol);
            $quincena        = $dayOfMonth <= 15 ? "{$ym}-Q1" : "{$ym}-Q2";

            $sociedades[$sol['sociedad']]   = true;
            $compradores[$sol['comprador']] = true;
            $meses[$ym]                     = true;

            $excluida = $sol['is_rechazada'] || $sol['is_borrador'] || ($sol['has_any_oc'] && $sol['all_ocs_canceled']);

            $linesForSol = $solLinesMap[$solId] ?? [];
            $lineasCount = count($linesForSol);
            $skusSet = [];
            foreach ($linesForSol as $lItem) {
                $sku = $this->extractSkuCode((string)($lItem['producto'] ?? ''));
                if ($sku !== '') {
                    $skusSet[$sku] = true;
                }
            }

            $diasAprobSol = null;
            if ($tsAprobacionSol && $tsAprobacionSol >= $tsCreacionSol) {
                $diasAprobSol = round(($tsAprobacionSol - $tsCreacionSol) / 86400, 2);
            }

            $firstOcOdooTs = null;
            $firstSapTs    = null;
            $ocCodes       = [];
            $ocStates      = [];

            foreach ($sol['valid_ocs'] as $oc) {
                if ($oc['no_oc_odoo'] !== '') {
                    $ocCodes[] = $oc['no_oc_odoo'];
                }
                if ($oc['estado_oc'] !== '') {
                    $ocStates[] = $oc['estado_oc'];
                }
                if ($oc['ts_creacion_oc'] !== null) {
                    if ($firstOcOdooTs === null || $oc['ts_creacion_oc'] < $firstOcOdooTs) {
                        $firstOcOdooTs = $oc['ts_creacion_oc'];
                    }
                }
                if ($oc['ts_creacion_sap'] !== null) {
                    if ($firstSapTs === null || $oc['ts_creacion_sap'] < $firstSapTs) {
                        $firstSapTs = $oc['ts_creacion_sap'];
                    }
                }
            }

            $diasGestionComprador = null;
            $tieneOcOdoo = ($firstOcOdooTs !== null);
            if (!$excluida && $tsAprobacionSol !== null) {
                $endTs = $tieneOcOdoo ? $firstOcOdooTs : $cutoffTs;
                $diasGestionComprador = round(max(0, ($endTs - $tsAprobacionSol) / 86400), 2);
            }

            $diasAprobSap = null;
            $tieneOcSap = ($firstSapTs !== null);
            if (!$excluida && $tieneOcOdoo && $tieneOcSap) {
                $diasAprobSap = round(max(0, ($firstSapTs - $firstOcOdooTs) / 86400), 2);
            }

            $diasAprobToSap = null;
            if (!$excluida && $tsAprobacionSol !== null) {
                $endSapTs = $tieneOcSap ? $firstSapTs : $cutoffTs;
                $diasAprobToSap = round(max(0, ($endSapTs - $tsAprobacionSol) / 86400), 2);
            }

            $diasCicloTotal = null;
            if (!$excluida && $tieneOcSap) {
                $diasCicloTotal = round(max(0, ($firstSapTs - $tsCreacionSol) / 86400), 2);
            }

            $edadDias = round(($cutoffTs - $tsCreacionSol) / 86400, 1);

            $records[] = [
                'id'                 => $solId,
                'ym'                 => $ym,
                'quincena'           => $quincena,
                'fecha'              => date('Y-m-d', $tsCreacionSol),
                'f_creacion_sol'     => date('Y-m-d H:i', $tsCreacionSol),
                'f_aprobacion_sol'   => $tsAprobacionSol ? date('Y-m-d H:i', $tsAprobacionSol) : null,
                'f_creacion_oc'      => $firstOcOdooTs ? date('Y-m-d H:i', $firstOcOdooTs) : null,
                'f_creacion_sap'     => $firstSapTs ? date('Y-m-d H:i', $firstSapTs) : null,
                'sociedad'           => $sol['sociedad'],
                'comprador'          => $sol['comprador'],
                'autorizador_sol'    => $sol['autorizador_sol'],
                'aprob_sol_excluido' => $sol['aprob_sol_excluido'],
                'solicitado_por'     => $sol['solicitado_por'],
                'estado_sol'         => $sol['estado_sol'],
                'oc_odoo'            => implode(', ', array_unique($ocCodes)),
                'estado_oc'          => implode(', ', array_unique($ocStates)),
                'is_rechazada'       => $sol['is_rechazada'],
                'is_borrador'        => $sol['is_borrador'],
                'excluida'           => $excluida,
                'aprobada'           => ($tsAprobacionSol !== null),
                'tiene_oc_odoo'      => $tieneOcOdoo,
                'tiene_oc_sap'       => $tieneOcSap,
                'd_aprob_sol'        => $diasAprobSol,
                'd_comprador'        => $diasGestionComprador,
                'd_aprob_sap'        => $diasAprobSap,
                'd_to_sap'           => $diasAprobToSap,
                'd_ciclo_total'      => $diasCicloTotal,
                'edad_dias'          => $edadDias,
                'lineas_count'       => $lineasCount,
                'skus'               => array_keys($skusSet),
            ];
        }

        ksort($meses);
        $listSociedades   = array_keys($sociedades);
        $listCompradores  = array_keys($compradores);
        $listProveedores  = array_keys($proveedoresSet);
        $listSolicitantes = array_keys($solicitantesSet);
        sort($listSociedades);
        sort($listCompradores);
        sort($listProveedores);
        sort($listSolicitantes);

        $output = [
            'schema_v'      => 17,
            'excel_mtime'   => $excelMtime,
            'sol_mtime'     => $solMtime,
            'oc_mtime'      => $ocMtime,
            'generated_at'  => date('Y-m-d H:i:s'),
            'cutoff_date'   => date('Y-m-d', $cutoffTs),
            'filters'       => [
                'meses'        => array_keys($meses),
                'sociedades'   => $listSociedades,
                'compradores'  => $listCompradores,
                'proveedores'  => $listProveedores,
                'solicitantes' => $listSolicitantes,
            ],
            'oc_stats'      => $ocStats,
            'records'       => $records,
            'oc_records'    => $ocRecords,
            'spend_records' => $spendRecords,
        ];

        file_put_contents($this->cachePath, json_encode($output, JSON_UNESCAPED_UNICODE));

        return $output;
    }
}
