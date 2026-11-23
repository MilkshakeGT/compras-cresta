<?php
declare(strict_types=1);

class OdooConnector
{
    private string $configPath;
    private string $cachePath;
    private string $detailsCachePath;
    private string $syncMetaPath;
    private string $syncConfigPath;

    // Aprobadores retirados / inactivos que distorsionan el ranking operativo
    private array $excludedApprovers = [
        'maria isabel',
        'maría isabel',
    ];

    public function __construct(string $configPath, string $cachePath, string $detailsCachePath)
    {
        $dataDir = dirname($cachePath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $this->configPath       = $configPath;
        $this->cachePath        = $cachePath;
        $this->detailsCachePath = $detailsCachePath;
        $this->syncMetaPath     = $dataDir . '/odoo_sync_meta.json';
        $this->syncConfigPath   = $dataDir . '/sync_config.json';
    }

    // =========================================================================
    // SECCIÓN 1: CONFIGURACIÓN DE VENTANA DE FECHAS (data/sync_config.json)
    // =========================================================================
    public function getSyncConfig(): array
    {
        $today = date('Y-m-d');
        $default = [
            'date_from'      => date('Y') . '-01-01',
            'date_to'        => $today,
            'follow_today'   => true,
            'incremental'    => true,
            'last_watermark' => null,
            'updated_at'     => null,
        ];

        if (!file_exists($this->syncConfigPath)) {
            return $default;
        }

        $saved = json_decode((string)file_get_contents($this->syncConfigPath), true);
        if (!is_array($saved)) {
            return $default;
        }

        $merged = array_merge($default, $saved);
        if (!empty($merged['follow_today'])) {
            $merged['date_to'] = $today;
        } elseif (empty($merged['date_to'])) {
            $merged['date_to'] = $today;
        }

        return $merged;
    }

    public function saveSyncConfig(array $input): array
    {
        $rawFrom = trim((string)($input['date_from'] ?? ''));
        $rawTo   = trim((string)($input['date_to'] ?? ''));
        $today   = date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom)) {
            return ['ok' => false, 'message' => 'Selecciona una fecha de inicio válida (AAAA-MM-DD).'];
        }

        if ($rawTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)) {
            $rawTo = $today;
        }

        if ($rawFrom > $rawTo) {
            return ['ok' => false, 'message' => 'La fecha de inicio no puede ser posterior a la fecha final.'];
        }

        $prevCfg = $this->getSyncConfig();
        $prevFrom = (string)($prevCfg['date_from'] ?? '');
        $dateFromAdvanced = ($prevFrom !== '' && $rawFrom > $prevFrom);
        $followToday = ($rawTo === $today) && !empty($input['follow_today']);

        $newCfg = [
            'date_from'      => $rawFrom,
            'date_to'        => $followToday ? $today : $rawTo,
            'follow_today'   => $followToday,
            'incremental'    => !empty($input['incremental']) || true,
            // Si la fecha inicial cambió o se modificó la ventana, reiniciar marca de agua incremental
            'last_watermark' => ($prevFrom === $rawFrom && ($prevCfg['date_to'] ?? '') === $rawTo)
                ? ($prevCfg['last_watermark'] ?? null)
                : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        file_put_contents(
            $this->syncConfigPath,
            json_encode($newCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        // Purga inmediata si la fecha de inicio se movió hacia adelante respecto a la anterior
        $purgedInfo = '';
        if ($dateFromAdvanced) {
            $purgedCount = $this->purgeRecordsBeforeDate($rawFrom);
            $purgedInfo = " Se eliminaron {$purgedCount} registros anteriores a {$rawFrom}.";
        }

        return [
            'ok'      => true,
            'message' => "Configuración de fechas guardada ({$rawFrom} al {$newCfg['date_to']}).{$purgedInfo}",
            'config'  => $newCfg,
        ];
    }

    // =========================================================================
    // SECCIÓN 2: PURGA SELECTIVA DE DATOS FUERA DE VENTANA
    // =========================================================================
    private function purgeRecordsBeforeDate(string $minDate): int
    {
        if (!file_exists($this->cachePath)) {
            return 0;
        }

        $cache = json_decode((string)file_get_contents($this->cachePath), true);
        if (!is_array($cache)) {
            return 0;
        }

        $beforeRecords = count($cache['records'] ?? []);
        $keptSolIds = [];

        $cache['records'] = array_values(array_filter($cache['records'] ?? [], function ($r) use ($minDate, &$keptSolIds) {
            $f = (string)($r['fecha'] ?? '');
            $keep = ($f === '' || $f >= $minDate);
            if ($keep && !empty($r['id'])) {
                $keptSolIds[(string)$r['id']] = true;
            }
            return $keep;
        }));

        $keptOcIds = [];
        $cache['spend_records'] = array_values(array_filter($cache['spend_records'] ?? [], function ($sr) use ($minDate, &$keptOcIds) {
            $f = (string)($sr['fecha'] ?? '');
            $keep = ($f === '' || $f >= $minDate);
            if ($keep && !empty($sr['oc_odoo'])) {
                $keptOcIds[(string)$sr['oc_odoo']] = true;
            }
            return $keep;
        }));

        $cache['oc_records'] = array_values(array_filter($cache['oc_records'] ?? [], function ($ocr) use ($minDate) {
            $ym = (string)($ocr['ym'] ?? '');
            $minYm = substr($minDate, 0, 7);
            return ($ym === '' || $ym >= $minYm);
        }));

        $minYm = substr($minDate, 0, 7);
        foreach (array_keys($cache['oc_stats'] ?? []) as $ymKey) {
            if ($ymKey < $minYm) {
                unset($cache['oc_stats'][$ymKey]);
            }
        }

        $this->rebuildFiltersFromCache($cache);
        file_put_contents($this->cachePath, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);

        // Limpiar también detalles de líneas huérfanas anteriores a $minDate
        if (file_exists($this->detailsCachePath)) {
            $details = json_decode((string)file_get_contents($this->detailsCachePath), true);
            if (is_array($details)) {
                if (isset($details['sol']) && is_array($details['sol'])) {
                    foreach (array_keys($details['sol']) as $solKey) {
                        if (!isset($keptSolIds[$solKey])) {
                            unset($details['sol'][$solKey]);
                        }
                    }
                }
                if (isset($details['oc']) && is_array($details['oc'])) {
                    foreach (array_keys($details['oc']) as $ocKey) {
                        if (!isset($keptOcIds[$ocKey])) {
                            unset($details['oc'][$ocKey]);
                        }
                    }
                }
                file_put_contents($this->detailsCachePath, json_encode($details, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }

        return max(0, $beforeRecords - count($cache['records']));
    }

    private function rebuildFiltersFromCache(array &$cache): void
    {
        $meses = [];
        $soc   = [];
        $comp  = [];
        $sol   = [];
        $prov  = [];

        foreach (($cache['records'] ?? []) as $r) {
            if (!empty($r['ym'])) $meses[(string)$r['ym']] = true;
            if (!empty($r['sociedad'])) $soc[(string)$r['sociedad']] = true;
            if (!empty($r['comprador'])) $comp[(string)$r['comprador']] = true;
            if (!empty($r['solicitado_por'])) $sol[(string)$r['solicitado_por']] = true;
        }

        foreach (($cache['spend_records'] ?? []) as $sr) {
            if (!empty($sr['ym'])) $meses[(string)$sr['ym']] = true;
            if (!empty($sr['sociedad'])) $soc[(string)$sr['sociedad']] = true;
            if (!empty($sr['comprador'])) $comp[(string)$sr['comprador']] = true;
            if (!empty($sr['solicitado_por'])) $sol[(string)$sr['solicitado_por']] = true;
            if (!empty($sr['proveedor']) && $sr['proveedor'] !== 'Sin proveedor') {
                $prov[(string)$sr['proveedor']] = true;
            }
        }

        $mList = array_keys($meses);
        $sList = array_keys($soc);
        $cList = array_keys($comp);
        $pList = array_keys($prov);
        $lList = array_keys($sol);
        sort($mList);
        sort($sList);
        sort($cList);
        sort($pList);
        sort($lList);

        $cache['filters'] = [
            'meses'        => $mList,
            'sociedades'   => $sList,
            'compradores'  => $cList,
            'proveedores'  => $pList,
            'solicitantes' => $lList,
        ];
    }

    // =========================================================================
    // SECCIÓN 3: CONEXIÓN JSON-RPC Y UTILIDADES DE FECHA
    // =========================================================================
    public function isConfigured(): bool
    {
        if (!file_exists($this->configPath)) {
            return false;
        }
        $cfg = require $this->configPath;
        return is_array($cfg)
            && !empty($cfg['url'])
            && !empty($cfg['db'])
            && !empty($cfg['username'])
            && !empty($cfg['api_key'])
            && !str_contains((string)$cfg['url'], 'TU-DOMINIO');
    }

    public function getLastSyncInfo(): ?array
    {
        if (!file_exists($this->syncMetaPath)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($this->syncMetaPath), true);
        return is_array($data) ? $data : null;
    }

    private function rpc(string $url, string $service, string $method, array $args): mixed
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => $service,
                'method'  => $method,
                'args'    => $args,
            ],
            'id' => random_int(1, 999999),
        ]);

        $ch = curl_init(rtrim($url, '/') . '/jsonrpc');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code >= 400) {
            throw new RuntimeException("Error HTTP {$code} conectando a Odoo: {$err}");
        }

        $decoded = json_decode((string)$raw, true);
        if (isset($decoded['error'])) {
            $msg = $decoded['error']['data']['message'] ?? $decoded['error']['message'] ?? 'Error RPC Odoo';
            throw new RuntimeException("Odoo RPC Error: {$msg}");
        }

        return $decoded['result'] ?? null;
    }

    private function searchReadAll(string $url, string $db, int $uid, string $apiKey, string $model, array $domain, array $fields, int $batchSize = 2500): array
    {
        $all = [];
        $offset = 0;

        while (true) {
            $batch = $this->rpc($url, 'object', 'execute_kw', [
                $db,
                $uid,
                $apiKey,
                $model,
                'search_read',
                [$domain],
                [
                    'fields' => $fields,
                    'offset' => $offset,
                    'limit'  => $batchSize,
                    'order'  => 'id asc',
                ],
            ]);

            if (!is_array($batch) || empty($batch)) {
                break;
            }

            foreach ($batch as $item) {
                $all[] = $item;
            }

            if (count($batch) < $batchSize) {
                break;
            }
            $offset += $batchSize;
        }

        return $all;
    }

    private function localDateToUtcStart(string $ymd): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' 00:00:00', new DateTimeZone('America/Guatemala'));
        return $dt ? $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : ($ymd . ' 00:00:00');
    }

    private function localDateToUtcEnd(string $ymd): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' 23:59:59', new DateTimeZone('America/Guatemala'));
        return $dt ? $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : ($ymd . ' 23:59:59');
    }

    private function odooDateToLocalTs(mixed $val): ?int
    {
        if ($val === false || $val === null || $val === '') {
            return null;
        }
        $str = trim((string)$val);
        if ($str === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $str . ' 12:00:00', new DateTimeZone('America/Guatemala'));
            return $dt ? $dt->getTimestamp() : null;
        }

        $dtUtc = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $str, new DateTimeZone('UTC'));
        if ($dtUtc) {
            return $dtUtc->setTimezone(new DateTimeZone('America/Guatemala'))->getTimestamp();
        }

        $fallback = strtotime($str . ' UTC');
        return $fallback !== false ? $fallback : null;
    }

    private function m2oName(mixed $fieldVal, string $default = ''): string
    {
        if (is_array($fieldVal) && isset($fieldVal[1])) {
            return trim((string)$fieldVal[1]);
        }
        return $default;
    }

    private function m2oId(mixed $fieldVal): ?int
    {
        if (is_array($fieldVal) && isset($fieldVal[0])) {
            return (int)$fieldVal[0];
        }
        return null;
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

    private function mapPrState(string $rawState): string
    {
        return match (trim($rawState)) {
            'draft'      => 'Borrador',
            'to_approve' => 'Por aprobar',
            'approved'   => 'Aprobado',
            'done'       => 'hecho',
            'rejected'   => 'Rechazado',
            default      => $rawState,
        };
    }

    private function mapPoState(string $rawState): string
    {
        return match (trim($rawState)) {
            'draft', 'sent'    => 'Solicitud de cotización',
            'to approve'       => 'Para aprobar 1',
            'approval_2'       => 'PARA APROBAR 2',
            'purchase', 'done' => 'Orden de compra',
            'cancel'           => 'Cancelado',
            default            => $rawState,
        };
    }

    // =========================================================================
    // SECCIÓN 4: SINCRONIZACIÓN POR RANGO E INCREMENTAL POR CAMBIOS
    // =========================================================================
    public function syncFromOdoo(): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('La conexión con Odoo no está configurada en data/odoo.config.php.');
        }

        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        $cfg    = require $this->configPath;
        $url    = (string)$cfg['url'];
        $db     = (string)$cfg['db'];
        $user   = (string)$cfg['username'];
        $apiKey = (string)$cfg['api_key'];

        $syncCfg  = $this->getSyncConfig();
        $dateFrom = (string)$syncCfg['date_from'];
        $dateTo   = (string)$syncCfg['date_to'];

        // 1. Si ya hay datos previos en caché, purgar cualquier registro anterior a date_from
        $this->purgeRecordsBeforeDate($dateFrom);

        $uid = $this->rpc($url, 'common', 'authenticate', [$db, $user, $apiKey, []]);
        if (!$uid || !is_int($uid)) {
            throw new RuntimeException('No se pudo autenticar en Odoo. Verifica data/odoo.config.php.');
        }

        $utcStart = $this->localDateToUtcStart($dateFrom);
        $utcEnd   = $this->localDateToUtcEnd($dateTo);

        // Marca de agua incremental: solo jalar registros modificados o creados desde la última sync
        $hasExistingCache = file_exists($this->cachePath) && file_exists($this->detailsCachePath);
        $watermark = (!empty($syncCfg['incremental']) && $hasExistingCache && !empty($syncCfg['last_watermark']))
            ? (string)$syncCfg['last_watermark']
            : null;

        // Ventana efectiva de consulta en Odoo:
        // - Fecha de creación dentro del rango configurado [dateFrom .. dateTo]
        // - Si existe watermark incremental, se traen los modificados/creados desde (watermark - 15 min margen)
        $prDomain = [
            ['create_date', '>=', $utcStart],
            ['create_date', '<=', $utcEnd],
        ];
        $poDomain = [
            ['create_date', '>=', $utcStart],
            ['create_date', '<=', $utcEnd],
        ];

        if ($watermark !== null) {
            $incFrom = gmdate('Y-m-d H:i:s', max(0, strtotime($watermark . ' UTC') - 900));
            $effectiveIncFrom = max($utcStart, $incFrom);
            $prDomain[] = ['write_date', '>=', $effectiveIncFrom];
            $poDomain[] = ['write_date', '>=', $effectiveIncFrom];
        }

        // Consultar cabeceras cambiadas/nuevas en el rango
        $changedPrList = $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.request', $prDomain, [
            'id', 'name', 'state', 'requested_by', 'assigned_to', 'follower_id',
            'date_start', 'create_date', 'write_date', 'approval_date', 'company_select', 'company_id'
        ]);

        $changedPoList = $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.order', $poDomain, [
            'id', 'name', 'state', 'request_id', 'partner_id', 'currency_id',
            'amount_total', 'create_date', 'write_date', 'date_approve', 'autorizador_1',
            'autorizador_2', 'doc_num', 'sap_date', 'follower_id', 'user_id',
            'company_select', 'company_id'
        ]);

        // Si en modo incremental no hubo cambios en PR ni PO, actualizar timestamp y no tocar datos
        if ($watermark !== null && empty($changedPrList) && empty($changedPoList)) {
            $nowStr = date('Y-m-d H:i:s');
            $syncCfg['last_watermark'] = gmdate('Y-m-d H:i:s');
            file_put_contents($this->syncConfigPath, json_encode($syncCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

            $prevMeta = $this->getLastSyncInfo() ?? [];
            $meta = array_merge($prevMeta, [
                'last_sync'     => $nowStr,
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
                'changed_pr'    => 0,
                'changed_po'    => 0,
                'mode'          => 'incremental_no_changes',
            ]);
            file_put_contents($this->syncMetaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

            return [
                'ok'      => true,
                'message' => "Sin cambios nuevos en Odoo ({$dateFrom} al {$dateTo}). Datos intactos.",
                'meta'    => $meta,
            ];
        }

        // Si es sincronización incremental con cambios, necesitamos también las PR/PO ya cacheadas
        // que no cambiaron para reconstruir el payload consolidado sin perder ni alterar lo no modificado.
        $existingCache = $hasExistingCache
            ? json_decode((string)file_get_contents($this->cachePath), true)
            : [];
        $existingDetails = file_exists($this->detailsCachePath)
            ? json_decode((string)file_get_contents($this->detailsCachePath), true)
            : [];

        $changedPrIds = array_map(fn($x) => (int)$x['id'], $changedPrList);
        $changedPoIds = array_map(fn($x) => (int)$x['id'], $changedPoList);

        // Descargar líneas únicamente de las PR y PO cambiadas/nuevas
        $prLinesRaw = !empty($changedPrIds)
            ? $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.request.line', [
                ['request_id', 'in', $changedPrIds]
            ], [
                'id', 'request_id', 'product_id', 'name', 'product_qty'
            ])
            : [];

        $poLinesRaw = !empty($changedPoIds)
            ? $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.order.line', [
                ['order_id', 'in', $changedPoIds],
                ['display_type', '=', false]
            ], [
                'id', 'order_id', 'product_id', 'name', 'product_qty',
                'price_unit', 'price_total', 'price_subtotal'
            ])
            : [];

        // Si venimos de una carga anterior, mantener intactos los registros cuyo ID/documento no cambió
        // y reemplazar/actualizar únicamente los modificados o creados.
        $mergedRawRows = [];
        $changedSolNames = [];
        $changedOcNames  = [];

        foreach ($changedPrList as $pr) {
            $sName = trim((string)($pr['name'] ?? ''));
            if ($sName !== '') $changedSolNames[$sName] = true;
        }
        foreach ($changedPoList as $po) {
            $oName = trim((string)($po['name'] ?? ''));
            if ($oName !== '') $changedOcNames[$oName] = true;
        }

        // Reconstruir detalles combinando los conservados + los actualizados
        $solDetails = is_array($existingDetails['sol'] ?? null) ? $existingDetails['sol'] : [];
        foreach (array_keys($changedSolNames) as $sName) {
            unset($solDetails[$sName]);
        }
        foreach ($prLinesRaw as $ln) {
            $solName = $this->m2oName($ln['request_id'] ?? null);
            if ($solName === '') continue;
            $prodName = $this->m2oName($ln['product_id'] ?? null);
            if ($prodName === '') {
                $prodName = trim((string)($ln['name'] ?? ''));
            }
            $qty = is_numeric($ln['product_qty'] ?? null) ? round((float)$ln['product_qty'], 4) : 0.0;
            if ($prodName === '' && $qty === 0.0) continue;
            $solDetails[$solName][] = [
                'producto' => $prodName !== '' ? $prodName : '(Sin descripción)',
                'cantidad' => $qty,
            ];
        }

        $ocDetails = is_array($existingDetails['oc'] ?? null) ? $existingDetails['oc'] : [];
        foreach (array_keys($changedOcNames) as $oName) {
            unset($ocDetails[$oName]);
        }
        $poById = [];
        $posByPrId = [];
        foreach ($changedPoList as $po) {
            $poId = (int)$po['id'];
            $poById[$poId] = $po;
            $reqId = $this->m2oId($po['request_id'] ?? null);
            if ($reqId !== null) {
                $posByPrId[$reqId][] = $po;
            }
        }

        foreach ($poLinesRaw as $ln) {
            $poId = $this->m2oId($ln['order_id'] ?? null);
            $ocName = $this->m2oName($ln['order_id'] ?? null);
            if ($ocName === '') continue;
            $poHead = ($poId !== null && isset($poById[$poId])) ? $poById[$poId] : [];
            $prov = $this->m2oName($poHead['partner_id'] ?? null, 'Sin proveedor');
            $div  = strtoupper($this->m2oName($poHead['currency_id'] ?? null, 'GTQ')) ?: 'GTQ';

            $prodDesc = trim((string)($ln['name'] ?? ''));
            if ($prodDesc === '') {
                $prodDesc = $this->m2oName($ln['product_id'] ?? null, '(Sin descripción)');
            }

            $qty = is_numeric($ln['product_qty'] ?? null) ? round((float)$ln['product_qty'], 4) : 0.0;
            $pu  = is_numeric($ln['price_unit'] ?? null) ? round((float)$ln['price_unit'], 4) : 0.0;
            $tot = is_numeric($ln['price_total'] ?? null)
                ? round((float)$ln['price_total'], 2)
                : (is_numeric($ln['price_subtotal'] ?? null) ? round((float)$ln['price_subtotal'], 2) : round($qty * $pu, 2));

            $ocDetails[$ocName]['proveedor'] = $prov;
            $ocDetails[$ocName]['divisa']    = $div;
            $ocDetails[$ocName]['lineas'][]  = [
                'producto'        => $prodDesc !== '' ? $prodDesc : '(Sin descripción)',
                'cantidad'        => $qty,
                'precio_unitario' => $pu,
                'total'           => $tot,
            ];
        }

        // Construir filas nuevas/actualizadas de PR y PO cambiadas
        $maxDateTs = 0;
        foreach ($changedPrList as $pr) {
            $prId   = (int)$pr['id'];
            $solRef = trim((string)($pr['name'] ?? ''));
            if ($solRef === '') continue;

            $estadoSol      = $this->mapPrState((string)($pr['state'] ?? ''));
            $solicitante    = $this->m2oName($pr['requested_by'] ?? null, 'Sin solicitante');
            $autorizadorSol = $this->m2oName($pr['assigned_to'] ?? null, '');
            $comprador      = $this->m2oName($pr['follower_id'] ?? null, 'Sin asignar');
            $sociedad       = $this->m2oName($pr['company_select'] ?? null)
                ?: $this->m2oName($pr['company_id'] ?? null, 'Sin sociedad');

            $tsCreacionSol   = $this->odooDateToLocalTs($pr['create_date'] ?? null)
                ?? $this->odooDateToLocalTs($pr['date_start'] ?? null);
            $tsAprobacionSol = $this->odooDateToLocalTs($pr['approval_date'] ?? null);

            if ($tsCreacionSol && $tsCreacionSol > $maxDateTs) {
                $maxDateTs = $tsCreacionSol;
            }

            $linkedPos = $posByPrId[$prId] ?? [];
            if (empty($linkedPos)) {
                $mergedRawRows[] = [
                    'solicitud'         => $solRef,
                    'estado_sol'        => $estadoSol,
                    'solicitado_por'    => $solicitante,
                    'autorizador_sol'   => $autorizadorSol,
                    'autorizador_oc_1'  => '',
                    'comprador'         => $comprador,
                    'sociedad'          => $sociedad,
                    'no_oc_odoo'        => '',
                    'no_oc_sap'         => '',
                    'estado_oc'         => '',
                    'proveedor'         => '',
                    'divisa'            => 'GTQ',
                    'total_oc'          => 0.0,
                    'ts_creacion_sol'   => $tsCreacionSol,
                    'ts_aprobacion_sol' => $tsAprobacionSol,
                    'ts_creacion_oc'    => null,
                    'ts_aprobacion_oc'  => null,
                    'ts_creacion_sap'   => null,
                ];
            } else {
                foreach ($linkedPos as $po) {
                    $noOcOdoo = trim((string)($po['name'] ?? ''));
                    $estadoOc = $this->mapPoState((string)($po['state'] ?? ''));
                    $prov     = $this->m2oName($po['partner_id'] ?? null, 'Sin proveedor');
                    $divisa   = strtoupper($this->m2oName($po['currency_id'] ?? null, 'GTQ')) ?: 'GTQ';
                    $totalOc  = is_numeric($po['amount_total'] ?? null) ? round((float)$po['amount_total'], 2) : 0.0;
                    $autOc1   = trim((string)($po['autorizador_1'] ?? ''));
                    $docSap   = trim((string)($po['doc_num'] ?? ''));
                    if ($docSap !== '' && is_numeric($docSap)) {
                        $docSap = (string)(int)round((float)$docSap);
                    }

                    $tsCreOc = $this->odooDateToLocalTs($po['create_date'] ?? null);
                    $tsAprOc = $this->odooDateToLocalTs($po['date_approve'] ?? null);
                    $tsSapOc = $this->odooDateToLocalTs($po['sap_date'] ?? null);

                    if ($tsCreOc && $tsCreOc > $maxDateTs) {
                        $maxDateTs = $tsCreOc;
                    }

                    $poComp = ($comprador !== '' && $comprador !== 'Sin asignar')
                        ? $comprador
                        : ($this->m2oName($po['follower_id'] ?? null) ?: $this->m2oName($po['user_id'] ?? null, 'Sin asignar'));

                    $mergedRawRows[] = [
                        'solicitud'         => $solRef,
                        'estado_sol'        => $estadoSol,
                        'solicitado_por'    => $solicitante,
                        'autorizador_sol'   => $autorizadorSol,
                        'autorizador_oc_1'  => $autOc1,
                        'comprador'         => $poComp,
                        'sociedad'          => $sociedad,
                        'no_oc_odoo'        => $noOcOdoo,
                        'no_oc_sap'         => $docSap,
                        'estado_oc'         => $estadoOc,
                        'proveedor'         => $prov,
                        'divisa'            => $divisa,
                        'total_oc'          => $totalOc,
                        'ts_creacion_sol'   => $tsCreacionSol,
                        'ts_aprobacion_sol' => $tsAprobacionSol,
                        'ts_creacion_oc'    => $tsCreOc,
                        'ts_aprobacion_oc'  => $tsAprOc,
                        'ts_creacion_sap'   => $tsSapOc,
                    ];
                }
            }
        }

        // OCs directas cambiadas/nuevas (sin solicitud vinculada)
        foreach ($changedPoList as $po) {
            if ($this->m2oId($po['request_id'] ?? null) !== null) {
                continue;
            }
            $noOcOdoo = trim((string)($po['name'] ?? ''));
            if ($noOcOdoo === '') continue;
            $tsCreOc = $this->odooDateToLocalTs($po['create_date'] ?? null);
            if (!$tsCreOc) continue;
            if ($tsCreOc > $maxDateTs) {
                $maxDateTs = $tsCreOc;
            }

            $socOc = $this->m2oName($po['company_select'] ?? null)
                ?: $this->m2oName($po['company_id'] ?? null, 'Sin sociedad');
            $compOc = $this->m2oName($po['follower_id'] ?? null)
                ?: $this->m2oName($po['user_id'] ?? null, 'Sin asignar');
            $docSap = trim((string)($po['doc_num'] ?? ''));
            if ($docSap !== '' && is_numeric($docSap)) {
                $docSap = (string)(int)round((float)$docSap);
            }

            $mergedRawRows[] = [
                'solicitud'         => 'SIN-SOL-' . $noOcOdoo,
                'estado_sol'        => 'Borrador',
                'solicitado_por'    => 'Sin solicitante',
                'autorizador_sol'   => '',
                'autorizador_oc_1'  => trim((string)($po['autorizador_1'] ?? '')),
                'comprador'         => $compOc,
                'sociedad'          => $socOc,
                'no_oc_odoo'        => $noOcOdoo,
                'no_oc_sap'         => $docSap,
                'estado_oc'         => $this->mapPoState((string)($po['state'] ?? '')),
                'proveedor'         => $this->m2oName($po['partner_id'] ?? null, 'Sin proveedor'),
                'divisa'            => strtoupper($this->m2oName($po['currency_id'] ?? null, 'GTQ')) ?: 'GTQ',
                'total_oc'          => is_numeric($po['amount_total'] ?? null) ? round((float)$po['amount_total'], 2) : 0.0,
                'ts_creacion_sol'   => $tsCreOc,
                'ts_aprobacion_sol' => null,
                'ts_creacion_oc'    => $tsCreOc,
                'ts_aprobacion_oc'  => $this->odooDateToLocalTs($po['date_approve'] ?? null),
                'ts_creacion_sap'   => $this->odooDateToLocalTs($po['sap_date'] ?? null),
            ];
        }

        // Calcular métricas sobre los registros cambiados y fusionar con los registros no modificados
        $newBatch = $this->buildNormalizedStructures($mergedRawRows, $solDetails, $maxDateTs);

        $finalRecords = [];
        foreach (($existingCache['records'] ?? []) as $oldR) {
            $rId = (string)($oldR['id'] ?? '');
            $rFecha = (string)($oldR['fecha'] ?? '');
            if ($rFecha !== '' && $rFecha < $dateFrom) continue;
            if (isset($changedSolNames[$rId])) continue;
            $finalRecords[] = $oldR;
        }
        foreach ($newBatch['records'] as $updR) {
            $finalRecords[] = $updR;
        }

        $finalSpend = [];
        foreach (($existingCache['spend_records'] ?? []) as $oldSr) {
            $srFecha = (string)($oldSr['fecha'] ?? '');
            $ocCode  = (string)($oldSr['oc_odoo'] ?? '');
            $solCode = (string)($oldSr['solicitud'] ?? '');
            if ($srFecha !== '' && $srFecha < $dateFrom) continue;
            if ($ocCode !== '' && isset($changedOcNames[$ocCode])) continue;
            if ($solCode !== '' && isset($changedSolNames[$solCode])) continue;
            $finalSpend[] = $oldSr;
        }
        foreach ($newBatch['spend_records'] as $updSr) {
            $finalSpend[] = $updSr;
        }

        $finalOcRecords = [];
        $minYm = substr($dateFrom, 0, 7);
        foreach (($existingCache['oc_records'] ?? []) as $oldOcr) {
            $ym = (string)($oldOcr['ym'] ?? '');
            $ocCode = (string)($oldOcr['oc_odoo'] ?? '');
            $solCode = (string)($oldOcr['solicitud'] ?? '');
            if ($ym !== '' && $ym < $minYm) continue;
            if ($ocCode !== '' && isset($changedOcNames[$ocCode])) continue;
            if ($solCode !== '' && isset($changedSolNames[$solCode])) continue;
            $finalOcRecords[] = $oldOcr;
        }
        foreach ($newBatch['oc_records'] as $updOcr) {
            $finalOcRecords[] = $updOcr;
        }

        // Recalcular oc_stats desde finalSpend para mantener consistencia exacta
        $finalOcStats = [];
        foreach ($finalSpend as $sr) {
            if (empty($sr['solicitud'])) continue;
            $ym = (string)($sr['ym'] ?? '');
            if ($ym === '') continue;
            if (!isset($finalOcStats[$ym])) {
                $finalOcStats[$ym] = ['total' => 0, 'canceladas' => 0];
            }
            $finalOcStats[$ym]['total']++;
            if (!empty($sr['is_cancelada'])) {
                $finalOcStats[$ym]['canceladas']++;
            }
        }

        $syncTs = time();
        $nowStr = date('Y-m-d H:i:s', $syncTs);
        $cutoffTs = max((int)($existingCache['excel_mtime'] ?? 0), $maxDateTs > 0 ? $maxDateTs : $syncTs);

        $detailsPayload = [
            'schema_v'  => 3,
            'source'    => 'odoo_api',
            'sol_mtime' => $syncTs,
            'oc_mtime'  => $syncTs,
            'sol'       => $solDetails,
            'oc'        => $ocDetails,
        ];
        file_put_contents($this->detailsCachePath, json_encode($detailsPayload, JSON_UNESCAPED_UNICODE), LOCK_EX);

        $output = [
            'schema_v'      => 17,
            'source'        => 'odoo_api',
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'excel_mtime'   => $syncTs,
            'sol_mtime'     => $syncTs,
            'oc_mtime'      => $syncTs,
            'generated_at'  => $nowStr,
            'cutoff_date'   => date('Y-m-d', $cutoffTs),
            'filters'       => [
                'meses'        => [],
                'sociedades'   => [],
                'compradores'  => [],
                'proveedores'  => [],
                'solicitantes' => [],
            ],
            'oc_stats'      => $finalOcStats,
            'records'       => $finalRecords,
            'oc_records'    => $finalOcRecords,
            'spend_records' => $finalSpend,
        ];
        $this->rebuildFiltersFromCache($output);
        file_put_contents($this->cachePath, json_encode($output, JSON_UNESCAPED_UNICODE), LOCK_EX);

        // Guardar nueva marca de agua UTC
        $syncCfg['last_watermark'] = gmdate('Y-m-d H:i:s');
        file_put_contents($this->syncConfigPath, json_encode($syncCfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        $meta = [
            'last_sync'  => $nowStr,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'changed_pr' => count($changedPrList),
            'changed_po' => count($changedPoList),
            'pr_count'   => count($finalRecords),
            'po_count'   => count($finalSpend),
            'mode'       => $watermark !== null ? 'incremental_updated' : 'full_window',
        ];
        file_put_contents($this->syncMetaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        return [
            'ok'      => true,
            'message' => sprintf(
                'Odoo sincronizado (%s a %s): %s PR y %s OC actualizados.',
                $dateFrom,
                $dateTo,
                count($changedPrList),
                count($changedPoList)
            ),
            'meta'    => $meta,
        ];
    }

    private function buildNormalizedStructures(array $normalizedRows, array $solDetails, int $maxDateTs): array
    {
        $buyerNamesNorm = [];
        foreach ($normalizedRows as $row) {
            if ($row['comprador'] !== '' && $row['comprador'] !== 'Sin asignar') {
                $buyerNamesNorm[$this->normalizeForMatch($row['comprador'])] = true;
            }
        }

        $cutoffTs = $maxDateTs > 0 ? $maxDateTs : time();
        $solicitudesMap = [];
        $ocRecords = [];
        $spendRecords = [];

        foreach ($normalizedRows as $row) {
            $solId = $row['solicitud'];
            if ($solId === '' || !$row['ts_creacion_sol']) {
                continue;
            }

            $isDirectPo = str_starts_with($solId, 'SIN-SOL-');
            $estadoSolLower = mb_strtolower($row['estado_sol']);
            $isSolRechazada = ($estadoSolLower === 'rechazado');
            $isSolBorrador  = ($estadoSolLower === 'borrador' || $estadoSolLower === 'draft');
            $estadoOcLower  = mb_strtolower($row['estado_oc']);
            $isOcCancelada  = ($estadoOcLower === 'cancelado');

            if ($row['no_oc_odoo'] !== '') {
                if (!$isDirectPo && !$isSolRechazada && !$isSolBorrador && !$isOcCancelada && $row['ts_creacion_oc'] !== null && $row['ts_aprobacion_oc'] !== null) {
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

                $allowSpend = $isDirectPo ? !$isOcCancelada : (!$isSolRechazada && !$isSolBorrador);
                $isFirm = in_array($estadoOcLower, ['orden de compra', 'para aprobar 1', 'para aprobar 2'], true) && $allowSpend;
                $isRfq  = ($estadoOcLower === 'solicitud de cotización' || $estadoOcLower === 'solicitud de cotizacion') && $allowSpend;

                $dAprobSolOc = (!$isDirectPo && $row['ts_aprobacion_sol'] && $row['ts_aprobacion_sol'] >= $row['ts_creacion_sol'])
                    ? round(($row['ts_aprobacion_sol'] - $row['ts_creacion_sol']) / 86400, 2)
                    : null;

                $dCompOc = (!$isDirectPo && $row['ts_aprobacion_sol'] && $row['ts_creacion_oc'])
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
                    'solicitud'        => $isDirectPo ? '' : $solId,
                    'estado_sol'       => $isDirectPo ? 'OC Directa' : $row['estado_sol'],
                    'autorizador_sol'  => $row['autorizador_sol'],
                    'autorizador_oc_1' => $row['autorizador_oc_1'],
                    'ym'               => $ocYm,
                    'fecha'            => $ocFecha,
                    'f_creacion_sol'   => !$isDirectPo && $row['ts_creacion_sol'] ? date('Y-m-d H:i', $row['ts_creacion_sol']) : null,
                    'f_aprobacion_sol' => !$isDirectPo && $row['ts_aprobacion_sol'] ? date('Y-m-d H:i', $row['ts_aprobacion_sol']) : null,
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

            if ($isDirectPo) {
                continue;
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
        foreach ($solicitudesMap as $sol) {
            $solId           = $sol['solicitud'];
            $tsCreacionSol   = $sol['ts_creacion_sol'];
            $tsAprobacionSol = $sol['ts_aprobacion_sol'];
            $ym              = date('Y-m', $tsCreacionSol);
            $dayOfMonth      = (int)date('d', $tsCreacionSol);
            $quincena        = $dayOfMonth <= 15 ? "{$ym}-Q1" : "{$ym}-Q2";

            $excluida = $sol['is_rechazada'] || $sol['is_borrador'] || ($sol['has_any_oc'] && $sol['all_ocs_canceled']);

            $linesForSol = $solDetails[$solId] ?? [];
            $lineasCount = count($linesForSol);
            $skusSet = [];
            foreach ($linesForSol as $lItem) {
                $sku = $this->extractSkuCode((string)($lItem['producto'] ?? ''));
                if ($sku !== '') {
                    $skusSet[$sku] = true;
                }
            }

            $diasAprobSol = ($tsAprobacionSol && $tsAprobacionSol >= $tsCreacionSol)
                ? round(($tsAprobacionSol - $tsCreacionSol) / 86400, 2)
                : null;

            $firstOcOdooTs = null;
            $firstSapTs    = null;
            $ocCodes       = [];
            $ocStates      = [];

            foreach ($sol['valid_ocs'] as $oc) {
                if ($oc['no_oc_odoo'] !== '') $ocCodes[] = $oc['no_oc_odoo'];
                if ($oc['estado_oc'] !== '') $ocStates[] = $oc['estado_oc'];
                if ($oc['ts_creacion_oc'] !== null && ($firstOcOdooTs === null || $oc['ts_creacion_oc'] < $firstOcOdooTs)) {
                    $firstOcOdooTs = $oc['ts_creacion_oc'];
                }
                if ($oc['ts_creacion_sap'] !== null && ($firstSapTs === null || $oc['ts_creacion_sap'] < $firstSapTs)) {
                    $firstSapTs = $oc['ts_creacion_sap'];
                }
            }

            $tieneOcOdoo = ($firstOcOdooTs !== null);
            $diasGestionComprador = (!$excluida && $tsAprobacionSol !== null)
                ? round(max(0, (($tieneOcOdoo ? $firstOcOdooTs : $cutoffTs) - $tsAprobacionSol) / 86400), 2)
                : null;

            $tieneOcSap = ($firstSapTs !== null);
            $diasAprobSap = (!$excluida && $tieneOcOdoo && $tieneOcSap)
                ? round(max(0, ($firstSapTs - $firstOcOdooTs) / 86400), 2)
                : null;

            $diasAprobToSap = (!$excluida && $tsAprobacionSol !== null)
                ? round(max(0, (($tieneOcSap ? $firstSapTs : $cutoffTs) - $tsAprobacionSol) / 86400), 2)
                : null;

            $diasCicloTotal = (!$excluida && $tieneOcSap)
                ? round(max(0, ($firstSapTs - $tsCreacionSol) / 86400), 2)
                : null;

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

        return [
            'records'       => $records,
            'oc_records'    => $ocRecords,
            'spend_records' => $spendRecords,
        ];
    }
}
