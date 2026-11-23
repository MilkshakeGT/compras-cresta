<?php
declare(strict_types=1);

class OdooConnector
{
    private string $configPath;
    private string $cachePath;
    private string $detailsCachePath;
    private string $syncMetaPath;

    // Aprobadores retirados / inactivos que distorsionan el ranking operativo
    private array $excludedApprovers = [
        'maria isabel',
        'maría isabel',
    ];

    public function __construct(string $configPath, string $cachePath, string $detailsCachePath)
    {
        $this->configPath       = $configPath;
        $this->cachePath        = $cachePath;
        $this->detailsCachePath = $detailsCachePath;
        $this->syncMetaPath     = dirname($cachePath) . '/odoo_sync_meta.json';
    }

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

    // =========================================================================
    // CLIENTE JSON-RPC NATIVO DE ODOO 17
    // =========================================================================
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

    // =========================================================================
    // UTILIDADES DE CONVERSIÓN UTC -> GUATEMALA Y NORMALIZACIÓN
    // =========================================================================
    private function odooDateToLocalTs(mixed $val): ?int
    {
        if ($val === false || $val === null || $val === '') {
            return null;
        }
        $str = trim((string)$val);
        if ($str === '') {
            return null;
        }

        // Si viene solo fecha YYYY-MM-DD (como date_start), mantener día local a mediodía
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $str . ' 12:00:00', new DateTimeZone('America/Guatemala'));
            return $dt ? $dt->getTimestamp() : null;
        }

        // Odoo almacena todos los datetime en UTC
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
    // SINCRONIZACIÓN COMPLETA DESDE ODOO HACIA CACHE.JSON Y DETAILS_CACHE.JSON
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

        $uid = $this->rpc($url, 'common', 'authenticate', [$db, $user, $apiKey, []]);
        if (!$uid || !is_int($uid)) {
            throw new RuntimeException('No se pudo autenticar en Odoo. Verifica data/odoo.config.php.');
        }

        // 1. Descargar Solicitudes de Compra (purchase.request)
        $prList = $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.request', [], [
            'id', 'name', 'state', 'requested_by', 'assigned_to', 'follower_id',
            'date_start', 'create_date', 'approval_date', 'company_select', 'company_id'
        ]);

        // 2. Descargar Líneas de Solicitud (purchase.request.line)
        $prLinesRaw = $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.request.line', [], [
            'id', 'request_id', 'product_id', 'name', 'product_qty'
        ]);

        // 3. Descargar Órdenes de Compra (purchase.order)
        $poList = $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.order', [], [
            'id', 'name', 'state', 'request_id', 'partner_id', 'currency_id',
            'amount_total', 'create_date', 'date_approve', 'autorizador_1',
            'autorizador_2', 'doc_num', 'sap_date', 'follower_id', 'user_id',
            'company_select', 'company_id'
        ]);

        // 4. Descargar Líneas de Órdenes de Compra (purchase.order.line)
        $poLinesRaw = $this->searchReadAll($url, $db, $uid, $apiKey, 'purchase.order.line', [
            ['display_type', '=', false]
        ], [
            'id', 'order_id', 'product_id', 'name', 'product_qty',
            'price_unit', 'price_total', 'price_subtotal'
        ]);

        // Indexar cabeceras de PR por ID para enlace directo con PO
        $prById = [];
        foreach ($prList as $pr) {
            $prById[(int)$pr['id']] = $pr;
        }

        // Indexar cabeceras de PO por ID para detalle de líneas
        $poById = [];
        $posByPrId = [];
        foreach ($poList as $po) {
            $poId = (int)$po['id'];
            $poById[$poId] = $po;
            $reqId = $this->m2oId($po['request_id'] ?? null);
            if ($reqId !== null) {
                $posByPrId[$reqId][] = $po;
            }
        }

        // Construir details_cache['sol']
        $solDetails = [];
        foreach ($prLinesRaw as $ln) {
            $solName = $this->m2oName($ln['request_id'] ?? null);
            if ($solName === '') {
                continue;
            }
            $prodName = $this->m2oName($ln['product_id'] ?? null);
            if ($prodName === '') {
                $prodName = trim((string)($ln['name'] ?? ''));
            }
            $qty = is_numeric($ln['product_qty'] ?? null) ? round((float)$ln['product_qty'], 4) : 0.0;
            if ($prodName === '' && $qty === 0.0) {
                continue;
            }
            $solDetails[$solName][] = [
                'producto' => $prodName !== '' ? $prodName : '(Sin descripción)',
                'cantidad' => $qty,
            ];
        }

        // Construir details_cache['oc']
        $ocDetails = [];
        foreach ($poLinesRaw as $ln) {
            $poId = $this->m2oId($ln['order_id'] ?? null);
            $ocName = $this->m2oName($ln['order_id'] ?? null);
            if ($ocName === '') {
                continue;
            }
            $poHead = ($poId !== null && isset($poById[$poId])) ? $poById[$poId] : [];
            $prov = $this->m2oName($poHead['partner_id'] ?? null, 'Sin proveedor');
            $div  = strtoupper($this->m2oName($poHead['currency_id'] ?? null, 'GTQ'));
            if ($div === '') {
                $div = 'GTQ';
            }

            $prodDesc = trim((string)($ln['name'] ?? ''));
            if ($prodDesc === '') {
                $prodDesc = $this->m2oName($ln['product_id'] ?? null, '(Sin descripción)');
            }

            $qty = is_numeric($ln['product_qty'] ?? null) ? round((float)$ln['product_qty'], 4) : 0.0;
            $pu  = is_numeric($ln['price_unit'] ?? null) ? round((float)$ln['price_unit'], 4) : 0.0;
            $tot = is_numeric($ln['price_total'] ?? null)
                ? round((float)$ln['price_total'], 2)
                : (is_numeric($ln['price_subtotal'] ?? null) ? round((float)$ln['price_subtotal'], 2) : round($qty * $pu, 2));

            if (!isset($ocDetails[$ocName])) {
                $ocDetails[$ocName] = [
                    'proveedor' => $prov,
                    'divisa'    => $div,
                    'lineas'    => [],
                ];
            }
            $ocDetails[$ocName]['lineas'][] = [
                'producto'        => $prodDesc !== '' ? $prodDesc : '(Sin descripción)',
                'cantidad'        => $qty,
                'precio_unitario' => $pu,
                'total'           => $tot,
            ];
        }

        // Construir filas normalizadas equivalentes a DB.xlsx
        $normalizedRows = [];
        $maxDateTs = 0;

        foreach ($prList as $pr) {
            $prId   = (int)$pr['id'];
            $solRef = trim((string)($pr['name'] ?? ''));
            if ($solRef === '') {
                continue;
            }

            $estadoSol = $this->mapPrState((string)($pr['state'] ?? ''));
            $solicitante = $this->m2oName($pr['requested_by'] ?? null, 'Sin solicitante');
            $autorizadorSol = $this->m2oName($pr['assigned_to'] ?? null, '');
            $comprador = $this->m2oName($pr['follower_id'] ?? null, 'Sin asignar');
            $sociedad = $this->m2oName($pr['company_select'] ?? null);
            if ($sociedad === '') {
                $sociedad = $this->m2oName($pr['company_id'] ?? null, 'Sin sociedad');
            }

            // Usar create_date si existe (tiene hora exacta) o date_start
            $tsCreacionSol = $this->odooDateToLocalTs($pr['create_date'] ?? null)
                ?? $this->odooDateToLocalTs($pr['date_start'] ?? null);
            $tsAprobacionSol = $this->odooDateToLocalTs($pr['approval_date'] ?? null);

            if ($tsCreacionSol && $tsCreacionSol > $maxDateTs) {
                $maxDateTs = $tsCreacionSol;
            }

            $linkedPos = $posByPrId[$prId] ?? [];
            if (empty($linkedPos)) {
                $normalizedRows[] = [
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
                    $divisa   = strtoupper($this->m2oName($po['currency_id'] ?? null, 'GTQ'));
                    if ($divisa === '') {
                        $divisa = 'GTQ';
                    }
                    $totalOc  = is_numeric($po['amount_total'] ?? null) ? round((float)$po['amount_total'], 2) : 0.0;
                    $autOc1   = trim((string)($po['autorizador_1'] ?? ''));
                    $docSap   = trim((string)($po['doc_num'] ?? ''));
                    if ($docSap !== '' && is_numeric($docSap)) {
                        $docSap = (string)(int)round((float)$docSap);
                    }

                    $tsCreOc  = $this->odooDateToLocalTs($po['create_date'] ?? null);
                    $tsAprOc  = $this->odooDateToLocalTs($po['date_approve'] ?? null);
                    $tsSapOc  = $this->odooDateToLocalTs($po['sap_date'] ?? null);

                    if ($tsCreOc && $tsCreOc > $maxDateTs) {
                        $maxDateTs = $tsCreOc;
                    }

                    $poComp = $comprador;
                    if ($poComp === '' || $poComp === 'Sin asignar') {
                        $poComp = $this->m2oName($po['follower_id'] ?? null)
                            ?: $this->m2oName($po['user_id'] ?? null, 'Sin asignar');
                    }

                    $normalizedRows[] = [
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

        // Incluir también OCs directas sin solicitud vinculada (request_id vacío) para gasto completo
        foreach ($poList as $po) {
            if ($this->m2oId($po['request_id'] ?? null) !== null) {
                continue;
            }
            $noOcOdoo = trim((string)($po['name'] ?? ''));
            if ($noOcOdoo === '') {
                continue;
            }
            $tsCreOc = $this->odooDateToLocalTs($po['create_date'] ?? null);
            if (!$tsCreOc) {
                continue;
            }
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

            $normalizedRows[] = [
                'solicitud'         => 'SIN-SOL-' . $noOcOdoo,
                'estado_sol'        => 'Borrador', // Excluida del conteo de solicitudes, incluida en spend_records
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

        // Construir indicadores consolidados (misma lógica exacta de DataProcessor)
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

            $isDirectPo = str_starts_with($solId, 'SIN-SOL-');
            if (!$isDirectPo) {
                $solicitantesSet[$row['solicitado_por']] = true;
            }

            $estadoSolLower = mb_strtolower($row['estado_sol']);
            $isSolRechazada = ($estadoSolLower === 'rechazado');
            $isSolBorrador  = ($estadoSolLower === 'borrador' || $estadoSolLower === 'draft');
            $estadoOcLower  = mb_strtolower($row['estado_oc']);
            $isOcCancelada  = ($estadoOcLower === 'cancelado');

            if ($row['no_oc_odoo'] !== '') {
                if (!$isDirectPo) {
                    $ym = date('Y-m', $row['ts_creacion_sol']);
                    if (!isset($ocStats[$ym])) {
                        $ocStats[$ym] = ['total' => 0, 'canceladas' => 0];
                    }
                    $ocStats[$ym]['total']++;
                    if ($isOcCancelada) {
                        $ocStats[$ym]['canceladas']++;
                    }
                }

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

                if ($provName !== 'Sin proveedor') {
                    $proveedoresSet[$provName] = true;
                }

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

            $linesForSol = $solDetails[$solId] ?? [];
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

        $syncTs = time();
        $nowStr = date('Y-m-d H:i:s', $syncTs);

        // Guardar details_cache.json
        $detailsPayload = [
            'schema_v'  => 3,
            'source'    => 'odoo_api',
            'sol_mtime' => $syncTs,
            'oc_mtime'  => $syncTs,
            'sol'       => $solDetails,
            'oc'        => $ocDetails,
        ];
        file_put_contents($this->detailsCachePath, json_encode($detailsPayload, JSON_UNESCAPED_UNICODE));

        // Guardar cache.json
        $output = [
            'schema_v'      => 17,
            'source'        => 'odoo_api',
            'excel_mtime'   => $syncTs,
            'sol_mtime'     => $syncTs,
            'oc_mtime'      => $syncTs,
            'generated_at'  => $nowStr,
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

        // Guardar metadatos de sincronización
        $meta = [
            'last_sync'  => $nowStr,
            'pr_count'   => count($prList),
            'po_count'   => count($poList),
            'sol_lines'  => count($prLinesRaw),
            'po_lines'   => count($poLinesRaw),
        ];
        file_put_contents($this->syncMetaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'ok'      => true,
            'message' => sprintf(
                'Sincronización directa con Odoo completada (%s solicitudes y %s OCs actualizadas).',
                number_format(count($prList)),
                number_format(count($poList))
            ),
            'meta'    => $meta,
        ];
    }
}
