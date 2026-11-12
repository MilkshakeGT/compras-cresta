<?php
declare(strict_types=1);

class SimpleXLSX
{
    public static function parse(string $filepath): array
    {
        if (!file_exists($filepath)) {
            throw new RuntimeException("No se encontró el archivo Excel en: {$filepath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo .xlsx como ZIP.");
        }

        // 1. Leer tabla de cadenas compartidas (sharedStrings.xml)
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $xml = simplexml_load_string($ssXml);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $str = '';
                        foreach ($si->r as $r) {
                            $str .= (string)$r->t;
                        }
                        $sharedStrings[] = $str;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Leer primera hoja (sheet1.xml)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException("No se encontró xl/worksheets/sheet1.xml dentro del Excel.");
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $lastColIndex = -1;

            foreach ($row->c as $c) {
                $cellRef = (string)$c['r'];
                $colLetters = preg_replace('/[0-9]+/', '', $cellRef);
                $colIndex = self::colLettersToIndex($colLetters);

                // Rellenar celdas vacías saltadas por Excel
                while ($lastColIndex + 1 < $colIndex) {
                    $rowData[] = null;
                    $lastColIndex++;
                }

                $type = (string)$c['t'];
                $val = isset($c->v) ? (string)$c->v : null;

                if ($type === 's' && $val !== null) {
                    $val = $sharedStrings[(int)$val] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string)$c->is->t;
                } elseif ($type === 'b' && $val !== null) {
                    $val = ($val === '1');
                } elseif ($val !== null && is_numeric($val)) {
                    $val = strpos($val, '.') !== false ? (float)$val : (int)$val;
                }

                $rowData[] = $val;
                $lastColIndex = $colIndex;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    private static function colLettersToIndex(string $letters): int
    {
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    public static function excelDateToTimestamp($excelVal): ?int
    {
        if ($excelVal === null || $excelVal === '') {
            return null;
        }
        if (is_numeric($excelVal)) {
            // Excel epoch (1900-01-01 con ajuste de bug bisiesto 1900)
            $unix = ($excelVal - 25569) * 86400;
            return $unix > 0 ? (int)round($unix) : null;
        }
        if (is_string($excelVal)) {
            $ts = strtotime(trim($excelVal));
            return $ts !== false ? $ts : null;
        }
        return null;
    }
}
