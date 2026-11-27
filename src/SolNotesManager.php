<?php
declare(strict_types=1);

// =========================================================================
// SECCIÓN 1: GESTOR DE ESTATUS / JUSTIFICACIONES POR SOLICITUD (280 CAR.)
// =========================================================================
class SolNotesManager
{
    private string $filePath;
    public const MAX_LENGTH = 280;

    public function __construct(string $dataDir)
    {
        date_default_timezone_set('America/Guatemala');

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->filePath = rtrim($dataDir, '/') . '/sol_notes.json';
    }

    private function normalizeSolKey(string $solId): string
    {
        return mb_strtoupper(trim($solId), 'UTF-8');
    }

    // =========================================================================
    // SECCIÓN 2: LECTURA DE NOTAS INDEXADAS POR SOLICITUD (SIN STATCACHE)
    // =========================================================================
    public function getAllNotes(): array
    {
        clearstatcache(true, $this->filePath);
        if (!file_exists($this->filePath)) {
            return [];
        }

        $raw = (string)@file_get_contents($this->filePath);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Indexar siempre con clave normalizada en mayúsculas para evitar fallos de coincidencia
        $normalizedMap = [];
        foreach ($decoded as $k => $v) {
            if (!is_array($v)) {
                continue;
            }
            $normKey = $this->normalizeSolKey((string)$k);
            if ($normKey !== '') {
                $normalizedMap[$normKey] = $v;
            }
        }

        return $normalizedMap;
    }

    public function getNoteForSol(string $solId): ?array
    {
        $normKey = $this->normalizeSolKey($solId);
        if ($normKey === '') {
            return null;
        }

        $all = $this->getAllNotes();
        return isset($all[$normKey]) && is_array($all[$normKey]) ? $all[$normKey] : null;
    }

    // =========================================================================
    // SECCIÓN 3: VALIDACIÓN DE LONGITUD, PERMISOS RBAC Y GUARDADO ATÓMICO
    // =========================================================================
    private function normalizeForMatch(string $text): string
    {
        $s = mb_strtolower(trim($text), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n'
        ]);
        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    public function canUserEditSol(?array $user, string $solBuyerName): bool
    {
        if ($user === null) {
            return false;
        }

        if (($user['role'] ?? '') === 'manager') {
            return true;
        }

        $allowedBuyers = (array)($user['odoo_buyers'] ?? []);
        if (empty($allowedBuyers)) {
            return false;
        }

        $targetNorm = $this->normalizeForMatch($solBuyerName);
        if ($targetNorm === '') {
            return false;
        }

        foreach ($allowedBuyers as $ab) {
            if ($this->normalizeForMatch((string)$ab) === $targetNorm) {
                return true;
            }
        }

        return false;
    }

    public function saveNote(string $solId, string $rawNote, array $user, string $solBuyerName): array
    {
        $cleanId = trim($solId);
        $normKey = $this->normalizeSolKey($cleanId);
        if ($normKey === '') {
            return ['ok' => false, 'error' => 'Identificador de solicitud inválido.'];
        }

        if (!$this->canUserEditSol($user, $solBuyerName)) {
            return ['ok' => false, 'error' => 'No tienes permisos para editar comentarios de esta solicitud.'];
        }

        $sanitized = preg_replace('/\s+/u', ' ', trim($rawNote)) ?? '';
        if (mb_strlen($sanitized, 'UTF-8') > self::MAX_LENGTH) {
            $sanitized = mb_substr($sanitized, 0, self::MAX_LENGTH, 'UTF-8');
        }

        $all    = $this->getAllNotes();
        $now    = date('Y-m-d H:i');
        $author = trim((string)($user['display_name'] ?? $user['username'] ?? 'Usuario'));

        if ($sanitized === '') {
            unset($all[$normKey]);
            $entry = [
                'note'       => '',
                'updated_by' => $author,
                'updated_at' => $now,
            ];
        } else {
            $entry = [
                'note'       => $sanitized,
                'updated_by' => $author,
                'updated_at' => $now,
            ];
            $all[$normKey] = $entry;
        }

        $jsonPayload = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $written     = @file_put_contents($this->filePath, $jsonPayload, LOCK_EX);

        if ($written === false) {
            return [
                'ok'    => false,
                'error' => 'No se pudo escribir en ' . $this->filePath . '. Verifica permisos de escritura en la carpeta data/.',
            ];
        }

        @chmod($this->filePath, 0664);
        clearstatcache(true, $this->filePath);

        return [
            'ok'      => true,
            'id'      => $cleanId,
            'note'    => $entry['note'],
            'note_by' => $entry['updated_by'],
            'note_at' => $entry['updated_at'],
        ];
    }
}
