<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleXLSX.php';

class DataProcessor
{
    private string $excelPath;
    private string $cachePath;
    private string $configPath;

    public function __construct(string $excelPath, string $cachePath, ?string $configPath = null)
    {
        $this->excelPath  = $excelPath;
        $this->cachePath  = $cachePath;
        $this->configPath = $configPath ?? dirname($excelPath) . '/onedrive.config.php';
    }

    public function syncFromOneDrive(bool $force = false): array
    {
        if (!file_exists($this->configPath)) {
            return ['synced' => false, 'source' => 'local', 'message' => 'Modo local (sin onedrive.config.php)'];
        }

        $cfg = require $this->configPath;
        $rawUrl = trim((string)($cfg['onedrive_url'] ?? ''));
        $ttl    = (int)($cfg['sync_ttl_seconds'] ?? 1800);

        if ($rawUrl === '' || str_contains($rawUrl, 'PEGA_AQUI')) {
            return ['synced' => false, 'source' => 'local', 'message' => 'URL de OneDrive pendiente de configurar'];
        }

        if (!$force && file_exists($this->excelPath)) {
            $age = time() - filemtime($this->excelPath);
            if ($age < $ttl) {
                return ['synced' => false, 'source' => 'onedrive_cached', 'message' => 'Caché reciente'];
            }
        }

        $downloadUrl = $this->buildDirectDownloadUrl($rawUrl);
        $tmpFile = $this->excelPath . '.tmp.' . uniqid();

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

        if (!$ok || $httpCode >= 400 || !file_exists($tmpFile) || filesize($tmpFile) < 2048) {
            @unlink($tmpFile);
            return [
                'synced'  => false,
                'source'  => 'local_fallback',
                'message' => "No se pudo descargar desde OneDrive (HTTP {$httpCode} {$curlErr}). Usando copia local."
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
                'message' => 'El enlace de OneDrive no devolvió un archivo Excel válido. Usando copia local.'
            ];
        }

        $newHash = md5_file($tmpFile);
        $oldHash = file_exists($this->excelPath) ? md5_file($this->excelPath) : '';

        if ($newHash !== $oldHash) {
            rename($tmpFile, $this->excelPath);
            touch($this->excelPath);
            return ['synced' => true, 'source' => 'onedrive', 'message' => 'Actualizado desde OneDrive'];
        }

        @unlink($tmpFile);
        touch($this->excelPath);
        return ['synced' => true, 'source' => 'onedrive', 'message' => 'Sin cambios nuevos en OneDrive'];
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

    public function getProcessedData(bool $forceSync = false): array
    {
        $syncStatus = $this->syncFromOneDrive($forceSync);

        if (!file_exists($this->excelPath)) {
            throw new RuntimeException("No se encontró el archivo data/DB.xlsx en el servidor. " . ($syncStatus['message'] ?? ''));
        }

        $excelMtime = filemtime($this->excelPath);

        if (!$forceSync && file_exists($this->cachePath)) {
            $cached = json_decode(file_get_contents($this->cachePath), true);
            if (is_array($cached) && ($cached['excel_mtime'] ?? 0) === $excelMtime && ($cached['schema_v'] ?? 1) === 5) {
                $cached['sync_info'] = $syncStatus;
                return $cached;
            }
        }

        $rawRows = SimpleXLSX::parse($this->excelPath);
        if (count($rawRows) < 2) {
            throw new RuntimeException("El archivo Excel no contiene datos suficientes.");
        }

        $headers = array_map(fn($h) => trim((string)$h), $rawRows[0]);
        $colMap = array_flip($headers);

        $getCol = function (array $row, string $name) use ($colMap) {
            if (!isset($colMap[$name])) {
                return null;
            }
            $idx = $colMap[$name];
            return $row[$idx] ?? null;
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

            $normalizedRows[] = [
                'solicitud'          => trim((string)$lastHeaderData['Solicitud']),
                'estado_sol'         => trim((string)($lastHeaderData['Estado Solicitud'] ?? '')),
                'solicitado_por'     => trim((string)($lastHeaderData['Solicitado por'] ?? '')),
                'autorizador_sol'    => trim((string)($lastHeaderData['Autorizador Solicitud'] ?? '')),
                'autorizador_oc'     => trim((string)($getCol($r, 'Autorizador') ?? '')),
                'comprador'          => trim((string)($lastHeaderData['Comprador'] ?? 'Sin asignar')),
                'sociedad'           => trim((string)($lastHeaderData['Sociedad'] ?? 'Sin sociedad')),
                'no_oc_odoo'         => trim((string)($getCol($r, 'NO. OC Odoo') ?? '')),
                'no_oc_sap'          => trim((string)($getCol($r, 'NO. OC SAP') ?? '')),
                'estado_oc'          => trim((string)($getCol($r, 'Estado OC') ?? '')),
                'ts_creacion_sol'    => $fCreacionSolTs,
                'ts_aprobacion_sol'  => $fAprobacionSolTs,
                'ts_creacion_oc'     => $fCreacionOcTs,
                'ts_aprobacion_oc'   => $fAprobacionOcTs,
                'ts_creacion_sap'    => $fCreacionSapTs,
            ];
        }

        $cutoffTs = $maxDateTs > 0 ? $maxDateTs : time();

        $solicitudesMap = [];
        $ocStats = [];
        $ocRecords = [];

        foreach ($normalizedRows as $row) {
            $solId = $row['solicitud'];
            if ($solId === '' || !$row['ts_creacion_sol']) {
                continue;
            }

            $isSolRechazada = (mb_strtolower($row['estado_sol']) === 'rechazado');
            $isOcCancelada  = (mb_strtolower($row['estado_oc']) === 'cancelado');

            if ($row['no_oc_odoo'] !== '') {
                $ym = date('Y-m', $row['ts_creacion_sol']);
                if (!isset($ocStats[$ym])) {
                    $ocStats[$ym] = ['total' => 0, 'canceladas' => 0];
                }
                $ocStats[$ym]['total']++;
                if ($isOcCancelada) {
                    $ocStats[$ym]['canceladas']++;
                }

                // Tiempos de aprobación de OC (Creación OC Odoo -> Aprobación OC Odoo) usando la columna exacta 'Autorizador'
                if (!$isSolRechazada && !$isOcCancelada && $row['ts_creacion_oc'] !== null && $row['ts_aprobacion_oc'] !== null) {
                    $diasAprobOc = round(max(0, ($row['ts_aprobacion_oc'] - $row['ts_creacion_oc']) / 86400), 2);
                    $aprobOcName = $row['autorizador_oc'] !== '' ? $row['autorizador_oc'] : 'Sin asignar';

                    $ocRecords[] = [
                        'oc_odoo'      => $row['no_oc_odoo'],
                        'solicitud'    => $solId,
                        'ym'           => date('Y-m', $row['ts_creacion_oc']),
                        'sociedad'     => $row['sociedad'] !== '' ? $row['sociedad'] : 'Sin sociedad',
                        'comprador'    => $row['comprador'] !== '' ? $row['comprador'] : 'Sin asignar',
                        'aprobador_oc' => $aprobOcName,
                        'd_aprob_oc'   => $diasAprobOc,
                    ];
                }
            }

            if (!isset($solicitudesMap[$solId])) {
                $solicitudesMap[$solId] = [
                    'solicitud'         => $solId,
                    'estado_sol'        => $row['estado_sol'],
                    'solicitado_por'    => $row['solicitado_por'],
                    'autorizador_sol'   => $row['autorizador_sol'] !== '' ? $row['autorizador_sol'] : 'Sin asignar',
                    'is_rechazada'      => $isSolRechazada,
                    'comprador'         => $row['comprador'] !== '' ? $row['comprador'] : 'Sin asignar',
                    'sociedad'          => $row['sociedad'] !== '' ? $row['sociedad'] : 'Sin sociedad',
                    'ts_creacion_sol'   => $row['ts_creacion_sol'],
                    'ts_aprobacion_sol' => $row['ts_aprobacion_sol'],
                    'valid_ocs'         => [],
                    'all_ocs_canceled'  => true,
                    'has_any_oc'        => false,
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

            $excluida = $sol['is_rechazada'] || ($sol['has_any_oc'] && $sol['all_ocs_canceled']);

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
                'id'              => $sol['solicitud'],
                'ym'              => $ym,
                'quincena'        => $quincena,
                'fecha'           => date('Y-m-d', $tsCreacionSol),
                'sociedad'        => $sol['sociedad'],
                'comprador'       => $sol['comprador'],
                'autorizador_sol' => $sol['autorizador_sol'],
                'solicitado_por'  => $sol['solicitado_por'],
                'estado_sol'      => $sol['estado_sol'],
                'oc_odoo'         => implode(', ', array_unique($ocCodes)),
                'estado_oc'       => implode(', ', array_unique($ocStates)),
                'is_rechazada'    => $sol['is_rechazada'],
                'excluida'        => $excluida,
                'aprobada'        => ($tsAprobacionSol !== null),
                'tiene_oc_odoo'   => $tieneOcOdoo,
                'tiene_oc_sap'    => $tieneOcSap,
                'd_aprob_sol'     => $diasAprobSol,
                'd_comprador'     => $diasGestionComprador,
                'd_aprob_sap'     => $diasAprobSap,
                'd_to_sap'        => $diasAprobToSap,
                'd_ciclo_total'   => $diasCicloTotal,
                'edad_dias'       => $edadDias,
            ];
        }

        ksort($meses);
        $listSociedades  = array_keys($sociedades);
        $listCompradores = array_keys($compradores);
        sort($listSociedades);
        sort($listCompradores);

        $output = [
            'schema_v'      => 5,
            'excel_mtime'   => $excelMtime,
            'generated_at'  => date('Y-m-d H:i:s'),
            'cutoff_date'   => date('Y-m-d', $cutoffTs),
            'sync_info'     => $syncStatus,
            'filters'       => [
                'meses'       => array_keys($meses),
                'sociedades'  => $listSociedades,
                'compradores' => $listCompradores,
            ],
            'oc_stats'      => $ocStats,
            'records'       => $records,
            'oc_records'    => $ocRecords,
        ];

        file_put_contents($this->cachePath, json_encode($output, JSON_UNESCAPED_UNICODE));

        return $output;
    }
}
