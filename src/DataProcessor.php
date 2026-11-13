<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleXLSX.php';

class DataProcessor
{
    private string $excelPath;
    private string $cachePath;
    private string $configPath;
    private string $solExcelPath;
    private string $ocExcelPath;
    private string $detailsCachePath;

    // Aprobadores retirados / inactivos que distorsionan el ranking operativo
    private array $excludedApprovers = [
        'maria isabel',
        'maría isabel',
    ];

    public function __construct(string $excelPath, string $cachePath, ?string $configPath = null)
    {
        $dataDir = dirname($excelPath);
        $this->excelPath        = $excelPath;
        $this->cachePath        = $cachePath;
        $this->configPath       = $configPath ?? $dataDir . '/onedrive.config.php';
        $this->solExcelPath     = $dataDir . '/Solicitudes.xlsx';
        $this->ocExcelPath      = $dataDir . '/OC.xlsx';
        $this->detailsCachePath = $dataDir . '/details_cache.json';
    }

    private function downloadSingleOneDriveFile(string $rawUrl, string $targetPath, int $ttl, bool $force): array
    {
        if ($rawUrl === '' || str_contains($rawUrl, 'PEGA_AQUI')) {
            return ['synced' => false, 'source' => 'local', 'message' => 'URL pendiente'];
        }

        if (!$force && file_exists($targetPath)) {
            $age = time() - filemtime($targetPath);
            if ($age < $ttl) {
                return ['synced' => false, 'source' => 'onedrive_cached', 'message' => 'Caché reciente'];
            }
        }

        $downloadUrl = $this->buildDirectDownloadUrl($rawUrl);
        $tmpFile = $targetPath . '.tmp.' . uniqid();

        $ch = curl_init($downloadUrl);
        $fp = fopen($tmpFile, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ]);

        $ok = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode >= 400 || !file_exists($tmpFile) || filesize($tmpFile) < 1024) {
            @unlink($tmpFile);
            return [
                'synced'  => false,
                'source'  => 'local_fallback',
                'message' => "Error HTTP {$httpCode} {$curlErr}"
            ];
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($tmpFile);
        $isValidXlsx = false;

        if ($openResult === true) {
            $isValidXlsx = ($zip->locateName('xl/worksheets/sheet1.xml') !== false);
            $zip->close();
        }

        if (!$isValidXlsx) {
            @unlink($tmpFile);
            return [
                'synced'  => false,
                'source'  => 'local_fallback',
                'message' => 'Respuesta no es un .xlsx válido'
            ];
        }

        $newHash = md5_file($tmpFile);
        $oldHash = file_exists($targetPath) ? md5_file($targetPath) : '';

        if ($newHash !== $oldHash) {
            rename($tmpFile, $targetPath);
            touch($targetPath);
            return ['synced' => true, 'source' => 'onedrive', 'message' => 'Actualizado'];
        }

        @unlink($tmpFile);
        touch($targetPath);
        return ['synced' => true, 'source' => 'onedrive', 'message' => 'Sin cambios'];
    }

    public function syncFromOneDrive(bool $force = false): array
    {
        if (!file_exists($this->configPath)) {
            return ['synced' => false, 'source' => 'local', 'message' => 'Modo local (sin onedrive.config.php)'];
        }

        $cfg = require $this->configPath;
        $ttl = (int)($cfg['sync_ttl_seconds'] ?? 1800);

        $urlDb  = trim((string)($cfg['onedrive_url'] ?? ''));
        $urlSol = trim((string)($cfg['onedrive_solicitudes_url'] ?? ''));
        $urlOc  = trim((string)($cfg['onedrive_oc_url'] ?? ''));

        $resDb  = $this->downloadSingleOneDriveFile($urlDb, $this->excelPath, $ttl, $force);
        $resSol = $this->downloadSingleOneDriveFile($urlSol, $this->solExcelPath, $ttl, $force);
        $resOc  = $this->downloadSingleOneDriveFile($urlOc, $this->ocExcelPath, $ttl, $force);

        $msgs = ["DB: {$resDb['message']}"];
        if ($urlSol !== '' && !str_contains($urlSol, 'PEGA_AQUI')) {
            $msgs[] = "Solicitudes: {$resSol['message']}";
        }
        if ($urlOc !== '' && !str_contains($urlOc, 'PEGA_AQUI')) {
            $msgs[] = "OC: {$resOc['message']}";
        }

        return [
            'synced'  => ($resDb['synced'] || $resSol['synced'] || $resOc['synced']),
            'source'  => $resDb['source'],
            'message' => implode(' · ', $msgs)
        ];
    }

    private function buildDirectDownloadUrl(string $url): string
    {
        if (str_contains($url, 'sharepoint.com')) {
            $parts = parse_url($url);
            $query = [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            $query['download'] = '1';
            $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
            return $base . '?' . http_build_query($query);
        }

        $base64 = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        return "https://api.onedrive.com/v1.0/shares/u!{$base64}/root/content";
    }

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

    private function rebuildDetailsCacheIfNeeded(bool $force = false): void
    {
        $solMtime = file_exists($this->solExcelPath) ? filemtime($this->solExcelPath) : 0;
        $ocMtime  = file_exists($this->ocExcelPath) ? filemtime($this->ocExcelPath) : 0;

        if (!$force && file_exists($this->detailsCachePath)) {
            $existing = json_decode(file_get_contents($this->detailsCachePath), true);
            if (is_array($existing)
                && ($existing['schema_v'] ?? 0) === 1
                && ($existing['sol_mtime'] ?? -1) === $solMtime
                && ($existing['oc_mtime'] ?? -1) === $ocMtime
            ) {
                return;
            }
        }

        $solDetails = [];
        if ($solMtime > 0) {
            $rows = SimpleXLSX::parse($this->solExcelPath);
            if (count($rows) > 1) {
                $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
                $colMap  = array_flip($headers);
                $idxId   = $colMap['solicitud'] ?? 0;
                $idxProd = $colMap['producto solicitado'] ?? 1;
                $idxQty  = $colMap['cantidad solicitada'] ?? 2;

                for ($i = 1; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    $id = trim((string)($r[$idxId] ?? ''));
                    if ($id === '') {
                        continue;
                    }
                    $prod = trim((string)($r[$idxProd] ?? ''));
                    $qtyRaw = $r[$idxQty] ?? null;
                    $qty = is_numeric($qtyRaw) ? round((float)$qtyRaw, 4) : 0.0;

                    if ($prod === '' && $qty === 0.0) {
                        continue;
                    }
                    if (!isset($solDetails[$id])) {
                        $solDetails[$id] = [];
                    }
                    $solDetails[$id][] = [
                        'producto' => $prod !== '' ? $prod : '(Sin descripción)',
                        'cantidad' => $qty,
                    ];
                }
            }
        }

        $ocDetails = [];
        if ($ocMtime > 0) {
            $rows = SimpleXLSX::parse($this->ocExcelPath);
            if (count($rows) > 1) {
                $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
                $colMap  = array_flip($headers);
                $idxId   = $colMap['no. oc'] ?? 0;
                $idxProv = $colMap['proveedor'] ?? 1;
                $idxProd = $colMap['producto'] ?? 2;
                $idxQty  = $colMap['cantidad comprada'] ?? 3;
                $idxDiv  = $colMap['divisa'] ?? 4;
                $idxPu   = $colMap['precio unitario'] ?? 5;
                $idxTot  = $colMap['total oc'] ?? 6;

                for ($i = 1; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    $id = trim((string)($r[$idxId] ?? ''));
                    if ($id === '') {
                        continue;
                    }
                    $prov = trim((string)($r[$idxProv] ?? ''));
                    $prod = trim((string)($r[$idxProd] ?? ''));
                    $qty  = is_numeric($r[$idxQty] ?? null) ? round((float)$r[$idxQty], 4) : 0.0;
                    $div  = strtoupper(trim((string)($r[$idxDiv] ?? 'GTQ')));
                    if ($div === '') {
                        $div = 'GTQ';
                    }
                    $pu  = is_numeric($r[$idxPu] ?? null) ? round((float)$r[$idxPu], 4) : 0.0;
                    $tot = is_numeric($r[$idxTot] ?? null) ? round((float)$r[$idxTot], 2) : round($qty * $pu, 2);

                    if (!isset($ocDetails[$id])) {
                        $ocDetails[$id] = [
                            'proveedor' => $prov !== '' ? $prov : 'Sin proveedor',
                            'divisa'    => $div,
                            'lineas'    => [],
                        ];
                    }
                    $ocDetails[$id]['lineas'][] = [
                        'producto'        => $prod !== '' ? $prod : '(Sin descripción)',
                        'cantidad'        => $qty,
                        'precio_unitario' => $pu,
                        'total'           => $tot,
                    ];
                }
            }
        }

        $detailsPayload = [
            'schema_v'  => 1,
            'sol_mtime' => $solMtime,
            'oc_mtime'  => $ocMtime,
            'sol'       => $solDetails,
            'oc'        => $ocDetails,
        ];

        file_put_contents($this->detailsCachePath, json_encode($detailsPayload, JSON_UNESCAPED_UNICODE));
    }

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
                'found'  => ($meta !== null || !empty($lines)),
                'type'   => 'sol',
                'id'     => $id,
                'meta'   => $meta,
                'lines'  => $lines,
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
            $ocInfo = $details['oc'][$id] ?? null;
            return [
                'found'  => ($meta !== null || $ocInfo !== null),
                'type'   => 'oc',
                'id'     => $id,
                'meta'   => $meta,
                'detail' => $ocInfo,
            ];
        }

        return ['found' => false, 'error' => 'Tipo de documento inválido'];
    }

    public function getProcessedData(bool $forceSync = false): array
    {
        $syncStatus = $this->syncFromOneDrive($forceSync);
        $this->rebuildDetailsCacheIfNeeded($forceSync);

        if (!file_exists($this->excelPath)) {
            throw new RuntimeException("No se encontró el archivo data/DB.xlsx en el servidor. " . ($syncStatus['message'] ?? ''));
        }

        $excelMtime = filemtime($this->excelPath);

        if (!$forceSync && file_exists($this->cachePath)) {
            $cached = json_decode(file_get_contents($this->cachePath), true);
            if (is_array($cached) && ($cached['excel_mtime'] ?? 0) === $excelMtime && ($cached['schema_v'] ?? 1) === 12) {
                $cached['sync_info'] = $syncStatus;
                return $cached;
            }
        }

        $rawRows = SimpleXLSX::parse($this->excelPath);
        if (count($rawRows) < 2) {
            throw new RuntimeException("El archivo Excel no contiene datos suficientes.");
        }

        $headers = array_map(fn($h) => trim((string)$h), $rawRows[0]);
        $colMap = [];
        foreach ($headers as $idx => $hName) {
            $colMap[mb_strtolower($hName)] = $idx;
        }

        $getCol = function (array $row, string $name) use ($colMap) {
            $key = mb_strtolower(trim($name));
            if (!isset($colMap[$key])) {
                return null;
            }
            return $row[$colMap[$key]] ?? null;
        };

        $lastHeaderData = [
            'Solicitud'             => null,
            'Estado Solicitud'      => null,
            'Solicitado por'        => null,
            'Autorizador Solicitud' => null,
            'Comprador'             => null,
            'Fcreacion Solicitud'   => null,
            'Aprobacion Solicitud'  => null,
            'Sociedad'              => null,
        ];

        $normalizedRows = [];
        $maxDateTs = 0;

        for ($i = 1; $i < count($rawRows); $i++) {
            $r = $rawRows[$i];
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $sol = trim((string)($getCol($r, 'Solicitud') ?? ''));
            if ($sol !== '') {
                foreach ($lastHeaderData as $k => $_) {
                    $lastHeaderData[$k] = $getCol($r, $k);
                }
            } else {
                if ($lastHeaderData['Solicitud'] === null) {
                    continue;
                }
            }

            $fCreacionSolTs   = SimpleXLSX::excelDateToTimestamp($lastHeaderData['Fcreacion Solicitud']);
            $fAprobacionSolTs = SimpleXLSX::excelDateToTimestamp($lastHeaderData['Aprobacion Solicitud']);
            $fCreacionOcTs    = SimpleXLSX::excelDateToTimestamp($getCol($r, 'Fcreacion OC Odoo'));
            $fAprobacionOcTs  = SimpleXLSX::excelDateToTimestamp($getCol($r, 'Aprobacion OC Odoo'));
            $fCreacionSapTs   = SimpleXLSX::excelDateToTimestamp($getCol($r, 'Fcreacion OC SAP'));

            if ($fCreacionSolTs && $fCreacionSolTs > $maxDateTs) {
                $maxDateTs = $fCreacionSolTs;
            }

            $rawTotalOc = $getCol($r, 'Total OC');
            $totalOc = is_numeric($rawTotalOc) ? round((float)$rawTotalOc, 2) : 0.0;
            $divisa  = strtoupper(trim((string)($getCol($r, 'Divisa') ?? 'GTQ')));
            if ($divisa === '') {
                $divisa = 'GTQ';
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
                'autorizador_oc_1'   => trim((string)($getCol($r, 'Autorizador 1') ?? '')),
                'comprador'          => trim((string)($lastHeaderData['Comprador'] ?? 'Sin asignar')),
                'sociedad'           => trim((string)($lastHeaderData['Sociedad'] ?? 'Sin sociedad')),
                'no_oc_odoo'         => trim((string)($getCol($r, 'NO. OC Odoo') ?? '')),
                'no_oc_sap'          => trim((string)($getCol($r, 'NO. OC SAP') ?? '')),
                'estado_oc'          => trim((string)($getCol($r, 'Estado OC') ?? '')),
                'proveedor'          => trim((string)($getCol($r, 'Proveedor') ?? '')),
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
                $isRfq  = ($estadoOcLower === 'solicitud de cotización') && !$isSolRechazada && !$isSolBorrador;

                if ($provName !== 'Sin proveedor') {
                    $proveedoresSet[$provName] = true;
                }

                $spendRecords[] = [
                    'oc_odoo'        => $row['no_oc_odoo'],
                    'oc_sap'         => $row['no_oc_sap'],
                    'solicitud'      => $solId,
                    'ym'             => $ocYm,
                    'fecha'          => $ocFecha,
                    'sociedad'       => $row['sociedad'] !== '' ? $row['sociedad'] : 'Sin sociedad',
                    'comprador'      => $row['comprador'] !== '' ? $row['comprador'] : 'Sin asignar',
                    'solicitado_por' => $row['solicitado_por'],
                    'proveedor'      => $provName,
                    'is_caja_chica'  => $isCajaChica,
                    'divisa'         => $row['divisa'],
                    'monto'          => $row['total_oc'],
                    'estado_oc'      => $row['estado_oc'],
                    'is_firm'        => $isFirm,
                    'is_rfq'         => $isRfq,
                    'is_cancelada'   => $isOcCancelada,
                    'en_sap'         => ($row['no_oc_sap'] !== ''),
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
            $tsCreacionSol   = $sol['ts_creacion_sol'];
            $tsAprobacionSol = $sol['ts_aprobacion_sol'];
            $ym              = date('Y-m', $tsCreacionSol);
            $dayOfMonth      = (int)date('d', $tsCreacionSol);
            $quincena        = $dayOfMonth <= 15 ? "{$ym}-Q1" : "{$ym}-Q2";

            $sociedades[$sol['sociedad']]   = true;
            $compradores[$sol['comprador']] = true;
            $meses[$ym]                     = true;

            $excluida = $sol['is_rechazada'] || $sol['is_borrador'] || ($sol['has_any_oc'] && $sol['all_ocs_canceled']);

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
                'id'                 => $sol['solicitud'],
                'ym'                 => $ym,
                'quincena'           => $quincena,
                'fecha'              => date('Y-m-d', $tsCreacionSol),
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
            'schema_v'      => 12,
            'excel_mtime'   => $excelMtime,
            'generated_at'  => date('Y-m-d H:i:s'),
            'cutoff_date'   => date('Y-m-d', $cutoffTs),
            'sync_info'     => $syncStatus,
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
