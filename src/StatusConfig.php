<?php
declare(strict_types=1);

// =========================================================================
// SECCIÓN 1: GESTOR DE CONFIGURACIÓN DE ESTATUS PR / OC (data/status_config.json)
// =========================================================================
class StatusConfig
{
    private string $filePath;

    public const PR_OPTIONS = [
        'Por aprobar' => 'Por aprobar (to_approve)',
        'Aprobado'    => 'Aprobado (approved)',
        'hecho'       => 'Hecho (done)',
        'Borrador'    => 'Borrador (draft)',
        'Rechazado'   => 'Rechazado (rejected)',
    ];

    public const PO_OPTIONS = [
        'Orden de compra'          => 'Orden de compra (purchase / done)',
        'Para aprobar 1'           => 'Para aprobar 1 (to approve)',
        'PARA APROBAR 2'           => 'Para aprobar 2 (approval_2)',
        'Solicitud de cotización'  => 'Solicitud de cotización (draft / sent)',
        'Cancelado'                => 'Cancelado (cancel)',
    ];

    public function __construct(string $dataDir)
    {
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->filePath = rtrim($dataDir, '/') . '/status_config.json';
    }

    public function getConfig(): array
    {
        $default = [
            'pr_included' => [
                'Por aprobar' => true,
                'Aprobado'    => true,
                'hecho'       => true,
                'Borrador'    => false,
                'Rechazado'   => false,
            ],
            'po_included' => [
                'Orden de compra'         => true,
                'Para aprobar 1'          => true,
                'PARA APROBAR 2'          => true,
                'Solicitud de cotización' => true,
                'Cancelado'               => false,
            ],
            'updated_at' => null,
        ];

        if (!file_exists($this->filePath)) {
            return $default;
        }

        $saved = json_decode((string)file_get_contents($this->filePath), true);
        if (!is_array($saved)) {
            return $default;
        }

        return [
            'pr_included' => array_merge($default['pr_included'], is_array($saved['pr_included'] ?? null) ? $saved['pr_included'] : []),
            'po_included' => array_merge($default['po_included'], is_array($saved['po_included'] ?? null) ? $saved['po_included'] : []),
            'updated_at'  => $saved['updated_at'] ?? null,
        ];
    }

    public function saveConfig(array $input): array
    {
        $postedPr = is_array($input['pr_included'] ?? null) ? $input['pr_included'] : [];
        $postedPo = is_array($input['po_included'] ?? null) ? $input['po_included'] : [];

        $prMap = [];
        foreach (array_keys(self::PR_OPTIONS) as $st) {
            $prMap[$st] = !empty($postedPr[$st]);
        }

        $poMap = [];
        foreach (array_keys(self::PO_OPTIONS) as $st) {
            $poMap[$st] = !empty($postedPo[$st]);
        }

        $payload = [
            'pr_included' => $prMap,
            'po_included' => $poMap,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $ok = file_put_contents(
            $this->filePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;

        if (!$ok) {
            return ['ok' => false, 'message' => 'No se pudo guardar data/status_config.json. Revisa permisos.'];
        }

        return [
            'ok'      => true,
            'message' => 'Reglas de estatus PR / OC guardadas y aplicadas al análisis.',
            'config'  => $payload,
        ];
    }
}
