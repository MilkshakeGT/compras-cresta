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

    /**
     * Verifica si el usuario autenticado tiene permiso para comentar/editar la solicitud:
     * - Manager: acceso global para crear y editar cualquier justificación.
     * - Buyer: puede crear en sus solicitudes asignadas y editar las que él mismo registró.
     */
    public function canUserEditSol(?array $user, string $solBuyerName, ?array $existingEntry = null): bool
    {
        if ($user === null) {
            return false;
        }

        if (($user['role'] ?? '') === 'manager') {
            return true;
        }

        if ($existingEntry !== null && trim((string)($existingEntry['note'] ?? '')) !== '') {
            $curUsername   = $this->normalizeForMatch((string)($user['username'] ?? ''));
            $curDisplay    = $this->normalizeForMatch((string)($user['display_name'] ?? ''));
            $entryUsername = $this->normalizeForMatch((string)($existingEntry['updated_username'] ?? ''));
            $entryAuthor   = $this->normalizeForMatch((string)($existingEntry['updated_by'] ?? ''));

            if ($entryUsername !== '' && $curUsername !== '' && $entryUsername === $curUsername) {
                return true;
            }
            if ($entryAuthor !== '' && $curDisplay !== '' && $entryAuthor === $curDisplay) {
                return true;
            }
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

        $all           = $this->getAllNotes();
        $existingEntry = $all[$normKey] ?? null;

        if (!$this->canUserEditSol($user, $solBuyerName, is_array($existingEntry) ? $existingEntry : null)) {
            return ['ok' => false, 'error' => 'Solo el autor de la justificación o un Manager puede editar este comentario.'];
        }

        $sanitized = preg_replace('/\s+/u', ' ', trim($rawNote)) ?? '';
        if (mb_strlen($sanitized, 'UTF-8') > self::MAX_LENGTH) {
            $sanitized = mb_substr($sanitized, 0, self::MAX_LENGTH, 'UTF-8');
        }

        $now        = date('Y-m-d H:i');
        $author     = trim((string)($user['display_name'] ?? $user['username'] ?? 'Usuario'));
        $authorUser = trim((string)($user['username'] ?? ''));

        if ($sanitized === '') {
            unset($all[$normKey]);
            $entry = [
                'note'             => '',
                'updated_by'       => $author,
                'updated_username' => $authorUser,
                'updated_at'       => $now,
            ];
        } else {
            $entry = [
                'note'             => $sanitized,
                'updated_by'       => $author,
                'updated_username' => $authorUser,
                'updated_at'       => $now,
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
            'ok'            => true,
            'id'            => $cleanId,
            'note'          => $entry['note'],
            'note_by'       => $entry['updated_by'],
            'note_username' => $entry['updated_username'],
            'note_at'       => $entry['updated_at'],
        ];
    }
}
