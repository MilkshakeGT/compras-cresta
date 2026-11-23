<?php
declare(strict_types=1);

require_once __DIR__ . '/SimpleXLSX.php';

class UploadHandler
{
    private string $dataDir;

    private array $targets = [
        'file_db' => [
            'filename' => 'DB.xlsx',
            'label'    => 'Maestro Solicitudes y OCs (purchase.request)',
            'required' => ['referencia de solicitud', 'solicitud'],
        ],
        'file_sol' => [
            'filename' => 'Solicitudes.xlsx',
            'label'    => 'Líneas de Solicitudes (purchase.request.line)',
            'required' => ['productos para comprar/producto', 'producto solicitado'],
        ],
        'file_oc' => [
            'filename' => 'OC.xlsx',
            'label'    => 'Líneas de Órdenes de Compra (purchase.order.line)',
            'required' => ['referencia de la orden', 'no. oc', 'no. oc odoo'],
        ],
    ];

    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, '/');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    public function getFilesStatus(): array
    {
        $status = [];
        foreach ($this->targets as $key => $cfg) {
            $path = $this->dataDir . '/' . $cfg['filename'];
            if (file_exists($path)) {
                $sizeKb = round(filesize($path) / 1024, 1);
                $status[$key] = [
                    'exists'   => true,
                    'label'    => $cfg['label'],
                    'filename' => $cfg['filename'],
                    'updated'  => date('Y-m-d H:i', filemtime($path)),
                    'size'     => $sizeKb >= 1024 ? round($sizeKb / 1024, 2) . ' MB' : $sizeKb . ' KB',
                ];
            } else {
                $status[$key] = [
                    'exists'   => false,
                    'label'    => $cfg['label'],
                    'filename' => $cfg['filename'],
                    'updated'  => 'Sin cargar',
                    'size'     => '0 KB',
                ];
            }
        }
        return $status;
    }

    public function handleUploads(): array
    {
        $updatedCount = 0;
        $messages = [];

        foreach ($this->targets as $inputName => $cfg) {
            if (!isset($_FILES[$inputName]) || ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $file = $_FILES[$inputName];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'message' => "Error al subir {$cfg['label']} (código {$file['error']})."];
            }

            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') {
                return ['ok' => false, 'message' => "El archivo para {$cfg['label']} debe tener extensión .xlsx."];
            }

            // Validar integridad ZIP/XML del Excel
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true) {
                return ['ok' => false, 'message' => "El archivo subido para {$cfg['label']} no es un .xlsx válido."];
            }
            $hasSheet = ($zip->locateName('xl/worksheets/sheet1.xml') !== false);
            $zip->close();

            if (!$hasSheet) {
                return ['ok' => false, 'message' => "El archivo {$cfg['label']} no contiene una hoja válida (sheet1.xml)."];
            }

            // Validar que contenga al menos una columna esperada del reporte correspondiente
            $rows = SimpleXLSX::parse($file['tmp_name']);
            if (count($rows) < 2) {
                return ['ok' => false, 'message' => "El archivo {$cfg['label']} está vacío."];
            }

            $headersLower = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
            $matchedHeader = false;
            foreach ($cfg['required'] as $reqCol) {
                if (in_array($reqCol, $headersLower, true)) {
                    $matchedHeader = true;
                    break;
                }
            }

            if (!$matchedHeader) {
                return [
                    'ok' => false,
                    'message' => "El archivo '{$file['name']}' no corresponde a {$cfg['label']}. Verifica que subiste el reporte correcto."
                ];
            }

            $destPath = $this->dataDir . '/' . $cfg['filename'];
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                return ['ok' => false, 'message' => "No se pudo guardar {$cfg['filename']} en el servidor (revisa permisos de data/)."];
            }
            touch($destPath);

            $updatedCount++;
            $messages[] = $cfg['label'];
        }

        if ($updatedCount === 0) {
            return ['ok' => false, 'message' => 'Selecciona al menos un archivo .xlsx para actualizar.'];
        }

        return [
            'ok'      => true,
            'message' => "Se actualizaron {$updatedCount} archivo(s): " . implode(', ', $messages) . "."
        ];
    }
}
