<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleXLSX.php';

class DataProcessor
{
    private string $excelPath;
    private string $cachePath;

    public function __construct(string $excelPath, string $cachePath)
    {
        $this->excelPath = $excelPath;
        $this->cachePath = $cachePath;
    }

    public function getProcessedData(): array
    {
        if (!file_exists($this->excelPath)) {
            throw new RuntimeException("No se encontró el archivo data/DB.xlsx en el servidor.");
        }

        $excelMtime = filemtime($this->excelPath);

        // Si el caché existe y es más reciente que el Excel, devolver directo
        if (file_exists($this->cachePath)) {
            $cached = json_decode(file_get_contents($this->cachePath), true);
            if (is_array($cached) && ($cached['excel_mtime'] ?? 0) === $excelMtime) {
                return $cached;
            }
        }

        $rawRows = SimpleXLSX::parse($this->excelPath);
        if (count($rawRows) < 2) {
            throw new RuntimeException("El archivo Excel no contiene datos suficientes.");
        }

        // Mapear encabezados dinámicamente por nombre
        $headers = array_map(fn($h) => trim((string)$h), $rawRows[0]);
        $colMap = array_flip($headers);

        $getCol = function (array $row, string $name) use ($colMap) {
            if (!isset($colMap[$name])) {
                return null;
            }
            $idx = $colMap[$name];
            return $row[$idx] ?? null;
        };

        // 1. Normalizar filas y aplicar Fill-Down si hubiera celdas vacías heredadas de Odoo
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
                // Heredar de fila anterior si Odoo dejó la cabecera en blanco
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

        // Fecha de corte = última fecha registrada en el Excel
        $cutoffTs = $maxDateTs > 0 ? $maxDateTs : time();

        // 2. Agrupar a nivel de Solicitud Única para evitar distorsión por múltiples OCs
        $solicitudesMap = [];
        $ocStats = [];

        foreach ($normalizedRows as $row) {
            $solId = $row['solicitud'];
            if ($solId === '' || !$row['ts_creacion_sol']) {
                continue;
            }

            $isSolRechazada = (mb_strtolower($row['estado_sol']) === 'rechazado');
            $isOcCancelada  = (mb_strtolower($row['estado_oc']) === 'cancelado');

            // Conteo de OCs válidas vs canceladas para tasa de cancelación
            if ($row['no_oc_odoo'] !== '') {
                $ym = date('Y-m', $row['ts_creacion_sol']);
                if (!isset($ocStats[$ym])) {
                    $ocStats[$ym] = ['total' => 0, 'canceladas' => 0];
                }
                $ocStats[$ym]['total']++;
                if ($isOcCancelada) {
                    $ocStats[$ym]['canceladas']++;
                }
            }

            if (!isset($solicitudesMap[$solId])) {
                $solicitudesMap[$solId] = [
                    'solicitud'         => $solId,
                    'estado_sol'        => $row['estado_sol'],
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

        // 3. Calcular tiempos exactos por Solicitud Única
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

            // Excluir solicitudes rechazadas o donde todas sus OCs fueron canceladas
            $excluida = $sol['is_rechazada'] || ($sol['has_any_oc'] && $sol['all_ocs_canceled']);

            // Días de aprobación de solicitud
            $diasAprobSol = null;
            if ($tsAprobacionSol && $tsAprobacionSol >= $tsCreacionSol) {
                $diasAprobSol = round(($tsAprobacionSol - $tsCreacionSol) / 86400, 2);
            }

            // Evaluar OCs válidas asociadas a la solicitud (tomamos la primera OC creada en Odoo / última llegada a SAP)
            $firstOcOdooTs = null;
            $firstSapTs    = null;

            foreach ($sol['valid_ocs'] as $oc) {
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

            // Tramo 1: Gestión pura del comprador (Aprobación Solicitud -> Creación OC en Odoo)
            $diasGestionComprador = null;
            $tieneOcOdoo = ($firstOcOdooTs !== null);
            if (!$excluida && $tsAprobacionSol !== null) {
                $endTs = $tieneOcOdoo ? $firstOcOdooTs : $cutoffTs;
                $diasGestionComprador = round(max(0, ($endTs - $tsAprobacionSol) / 86400), 2);
            }

            // Tramo 2: Aprobación OC y sincronización SAP (Creación OC Odoo -> Creación OC SAP)
            $diasAprobSap = null;
            $tieneOcSap = ($firstSapTs !== null);
            if (!$excluida && $tieneOcOdoo && $tieneOcSap) {
                $diasAprobSap = round(max(0, ($firstSapTs - $firstOcOdooTs) / 86400), 2);
            }

            // Tramo 3: Ciclo completo hacia SAP desde aprobación de solicitud (Aprobación Sol -> SAP)
            $diasAprobToSap = null;
            if (!$excluida && $tsAprobacionSol !== null) {
                $endSapTs = $tieneOcSap ? $firstSapTs : $cutoffTs;
                $diasAprobToSap = round(max(0, ($endSapTs - $tsAprobacionSol) / 86400), 2);
            }

            // Tramo 4: Ciclo Total End-to-End (Creación Solicitud -> SAP)
            $diasCicloTotal = null;
            if (!$excluida && $tieneOcSap) {
                $diasCicloTotal = round(max(0, ($firstSapTs - $tsCreacionSol) / 86400), 2);
            }

            // Antigüedad de la solicitud respecto a la fecha de corte (para evitar censura en <=14d y <=30d)
            $edadDias = round(($cutoffTs - $tsCreacionSol) / 86400, 1);

            $records[] = [
                'id'              => $sol['solicitud'],
                'ym'              => $ym,
                'quincena'        => $quincena,
                'fecha'           => date('Y-m-d', $tsCreacionSol),
                'sociedad'        => $sol['sociedad'],
                'comprador'       => $sol['comprador'],
                'estado_sol'      => $sol['estado_sol'],
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
            'excel_mtime'   => $excelMtime,
            'generated_at'  => date('Y-m-d H:i:s'),
            'cutoff_date'   => date('Y-m-d', $cutoffTs),
            'filters'       => [
                'meses'       => array_keys($meses),
                'sociedades'  => $listSociedades,
                'compradores' => $listCompradores,
            ],
            'oc_stats'      => $ocStats,
            'records'       => $records,
        ];

        file_put_contents($this->cachePath, json_encode($output, JSON_UNESCAPED_UNICODE));

        return $output;
    }
}
