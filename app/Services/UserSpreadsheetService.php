<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PharData;
use RuntimeException;
use Throwable;
use ZipArchive;

class UserSpreadsheetService
{
    public const HEADERS = ['nombre', 'email', 'password', 'rol', 'estado', 'telefono', 'ci', 'urbanizacion', 'supervisor'];

    public function rowsFromUpload(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv' => $this->rowsFromCsv($file->getRealPath()),
            'xlsx' => $this->rowsFromXlsx($file->getRealPath()),
            default => throw new RuntimeException('Formato no soportado. Usa CSV o XLSX.'),
        };
    }

    public function xlsx(array $headers, array $rows): string
    {
        $sheetRows = array_merge([$headers], $rows);
        $files = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->relsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelsXml(),
            'xl/worksheets/sheet1.xml' => $this->sheetXml($sheetRows),
        ];

        return $this->zip($files);
    }

    private function rowsFromCsv(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\n|\r/', $contents);
        $firstLine = collect($lines)->first(fn (?string $line) => trim((string) $line) !== '');
        $delimiter = $this->detectDelimiter((string) $firstLine);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($data);

                continue;
            }

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $rows[] = $this->combineRow($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function rowsFromXlsx(string $path): array
    {
        try {
            if (class_exists(ZipArchive::class)) {
                $archive = new ZipArchive;
                if ($archive->open($path) !== true) {
                    return [];
                }
            } else {
                $archive = new PharData($path);
            }
        } catch (Throwable) {
            return [];
        }

        $sharedStrings = $this->sharedStrings($archive);
        $sheet = $this->archiveContent($archive, 'xl/worksheets/sheet1.xml');
        if ($archive instanceof ZipArchive) {
            $archive->close();
        }

        if ($sheet === false) {
            return [];
        }

        $xml = simplexml_load_string($sheet);
        if (! $xml) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $raw = (string) $cell->v;
                $values[] = match ($type) {
                    's' => $sharedStrings[(int) $raw] ?? '',
                    'inlineStr' => (string) $cell->is->t,
                    default => $raw,
                };
            }

            if (! $this->isEmptyRow($values)) {
                $rows[] = $values;
            }
        }

        if ($rows === []) {
            return [];
        }

        $headers = $this->normalizeHeaders(array_shift($rows));

        return array_map(fn (array $row) => $this->combineRow($headers, $row), $rows);
    }

    private function sharedStrings(ZipArchive|PharData $archive): array
    {
        $content = $this->archiveContent($archive, 'xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $item) {
            $strings[] = (string) $item->t;
        }

        return $strings;
    }

    private function archiveContent(ZipArchive|PharData $archive, string $name): string|false
    {
        if ($archive instanceof ZipArchive) {
            return $archive->getFromName($name);
        }

        return isset($archive[$name]) ? $archive[$name]->getContent() : false;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
    }

    private function combineRow(array $headers, array $data): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = trim((string) ($data[$index] ?? ''));
        }

        return $row;
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return strtolower(trim((string) $header));
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$excelRow;
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="usuarios" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    }

    private function zip(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        [$time, $date] = $this->dosDateTime();

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $nameLength = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, $time, $date, $crc, $size, $size, $nameLength, 0).$name;
            $local .= $localHeader.$content;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0,
                0,
                $time,
                $date,
                $crc,
                $size,
                $size,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $offset
            ).$name;

            $offset += strlen($localHeader) + $size;
        }

        $entries = count($files);
        $centralSize = strlen($central);
        $centralOffset = strlen($local);
        $end = pack('VvvvvVVv', 0x06054B50, 0, 0, $entries, $entries, $centralSize, $centralOffset, 0);

        return $local.$central.$end;
    }

    private function dosDateTime(): array
    {
        $now = getdate();
        $time = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
        $date = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];

        return [$time, $date];
    }
}
