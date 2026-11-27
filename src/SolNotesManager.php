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

    // =========================================================================
    // SECCIÓN 2: LECTURA DE NOTAS INDEXADAS POR SOLICITUD
    // =========================================================================
    public function getAllNotes(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $raw = (string)file_get_contents($this->filePath);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getNoteForSol(string $solId): ?array
    {
        $cleanId = trim($solId);
        if ($cleanId === '') {
            return null;
        }

        $all = $this->getAllNotes();
        if (isset($all[$cleanId]) && is_array($all[$cleanId])) {
            return $all[$cleanId];
        }

        // Búsqueda insensible a mayúsculas/minúsculas como respaldo
        $upperId = mb_strtoupper($cleanId);
        foreach ($all as $k => $v) {
            if (mb_strtoupper((string)$k) === $upperId && is_array($v)) {
                return $v;
            }
        }

        return null;
    }

    // =========================================================================
    // SECCIÓN 3: VALIDACIÓN DE LONGITUD, PERMISOS RBAC Y GUARDADO ATÓMICO
    // =========================================================================
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

    /**
     * Verifica si el usuario autenticado tiene permiso para comentar sobre la solicitud.
     * - Manager: acceso global.
     * - Buyer: únicamente solicitudes asignadas a sus nombres de comprador vinculados.
     */
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

    /**
     * Guarda o elimina (si se envía vacío) el comentario breve de una solicitud.
     */
    public function saveNote(string $solId, string $rawNote, array $user, string $solBuyerName): array
    {
        $cleanId = trim($solId);
        if ($cleanId === '') {
            return ['ok' => false, 'error' => 'Identificador de solicitud inválido.'];
        }

        if (!$this->canUserEditSol($user, $solBuyerName)) {
            return ['ok' => false, 'error' => 'No tienes permisos para editar comentarios de esta solicitud.'];
        }

        // Limpiar saltos de línea excesivos y recortar a 280 caracteres exactos
        $sanitized = preg_replace('/\s+/u', ' ', trim($rawNote)) ?? '';
        if (mb_strlen($sanitized, 'UTF-8') > self::MAX_LENGTH) {
            $sanitized = mb_substr($sanitized, 0, self::MAX_LENGTH, 'UTF-8');
        }

        $all = $this->getAllNotes();
        $now = date('Y-m-d H:i');
        $author = trim((string)($user['display_name'] ?? $user['username'] ?? 'Usuario'));

        if ($sanitized === '') {
            unset($all[$cleanId]);
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
            $all[$cleanId] = $entry;
        }

        $written = file_put_contents(
            $this->filePath,
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        if ($written === false) {
            return ['ok' => false, 'error' => 'No se pudo escribir en data/sol_notes.json. Verifica permisos de carpeta.'];
        }

        return [
            'ok'      => true,
            'id'      => $cleanId,
            'note'    => $entry['note'],
            'note_by' => $entry['updated_by'],
            'note_at' => $entry['updated_at'],
        ];
    }
}
