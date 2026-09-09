<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Urbanizacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LotCsvImportService
{
    private const HEADERS = ['urbanizacion', 'manzano', 'lote', 'superficie_m2', 'precio_m2', 'precio_total', 'cuota_inicial_tipo', 'cuota_inicial_valor', 'estado', 'coord_x', 'coord_y', 'observaciones'];

    private const LEGACY_HEADERS = ['urbanizacion', 'manzano', 'lote', 'superficie_m2', 'precio_m2', 'precio_total', 'estado', 'coord_x', 'coord_y', 'observaciones'];

    private const OLD_HEADERS = ['manzano_codigo', 'lote_codigo', 'superficie', 'precio', 'cuota_inicial_tipo', 'cuota_inicial_valor', 'estado', 'fila', 'columna', 'coord_x', 'coord_y', 'observaciones'];

    public function parse(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $handle = is_string($path) ? @fopen($path, 'r') : false;

        if ($handle === false) {
            return ['rows' => [], 'errors' => ['No se pudo leer el archivo CSV.']];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false || trim(str_replace("\xEF\xBB\xBF", '', $firstLine)) === '') {
            fclose($handle);

            return ['rows' => [], 'errors' => ['El archivo CSV está vacío.']];
        }

        $delimiter = $this->detectDelimiter($firstLine);
        $headers = str_getcsv($firstLine, $delimiter);
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $rows = [];
        $errors = [];
        $seenRows = [];
        $line = 1;

        $acceptedHeaders = [self::HEADERS, self::LEGACY_HEADERS, self::OLD_HEADERS];
        $activeHeaders = in_array($headers, $acceptedHeaders, true) ? $headers : self::HEADERS;

        if (! in_array($headers, $acceptedHeaders, true)) {
            $errors[] = 'La cabecera CSV no coincide con el formato requerido.';
        }

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if ($this->isEmptyLine($data)) {
                continue;
            }

            $expectedColumns = count($activeHeaders);
            $foundColumns = count($data);
            if ($expectedColumns !== $foundColumns) {
                $errors[] = "Línea {$line}: estructura CSV inválida. Se esperaban {$expectedColumns} columnas y se encontraron {$foundColumns}. Revise separadores, comas y campos vacíos.";

                continue;
            }

            $combined = array_combine($activeHeaders, $data);
            if ($combined === false) {
                $errors[] = "Línea {$line}: no se pudo interpretar la estructura CSV.";

                continue;
            }

            $row = $this->normalizeRow($combined, $headers);
            $rowErrors = $this->validateRow($row, $line, $seenRows);

            if ($rowErrors) {
                $errors = [...$errors, ...$rowErrors];
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            $errors[] = 'El archivo CSV no contiene filas de datos para importar.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    public function import(array $rows): int
    {
        return DB::transaction(function () use ($rows): int {
            $count = 0;

            foreach ($rows as $row) {
                $urbanizacion = Urbanizacion::firstOrCreate(
                    ['nombre' => trim($row['urbanizacion'])],
                    ['estado' => 'activa']
                );
                $manzano = Manzano::firstOrCreate(
                    ['urbanizacion_id' => $urbanizacion->id, 'codigo' => trim($row['manzano'])],
                    ['nombre' => 'Manzano '.trim($row['manzano']), 'orden' => 0]
                );

                Lote::create([
                    'manzano_id' => $manzano->id,
                    'codigo' => trim($row['lote']),
                    'superficie' => (float) $row['superficie_m2'],
                    'precio' => (float) $row['precio_total'],
                    'cuota_inicial_tipo' => trim((string) ($row['cuota_inicial_tipo'] ?: 'monto')),
                    'cuota_inicial_valor' => (float) ($row['cuota_inicial_valor'] ?? 0),
                    'estado' => trim($row['estado']),
                    'fila' => (int) ($row['fila'] ?? 1),
                    'columna' => (int) ($row['columna'] ?? 1),
                    'coord_x' => (float) $row['coord_x'],
                    'coord_y' => (float) $row['coord_y'],
                    'observaciones' => $row['observaciones'],
                ]);
                $count++;
            }

            return $count;
        });
    }

    private function validateRow(array $row, int $line, array &$seenRows): array
    {
        $errors = [];
        foreach (['urbanizacion', 'manzano', 'lote'] as $field) {
            if (blank($row[$field])) {
                $errors[] = "Linea {$line}: {$field} es obligatorio.";
            }
        }

        foreach (['superficie_m2', 'precio_m2', 'precio_total'] as $field) {
            if (! is_numeric($row[$field]) || (float) $row[$field] < 0) {
                $errors[] = "Linea {$line}: {$field} debe ser numerico y mayor o igual a cero.";
            }
        }

        if (! in_array($row['cuota_inicial_tipo'], Lote::CUOTA_INICIAL_TIPOS, true)) {
            $errors[] = "Linea {$line}: cuota_inicial_tipo invalido.";
        }

        if (! is_numeric($row['cuota_inicial_valor']) || (float) $row['cuota_inicial_valor'] < 0) {
            $errors[] = "Linea {$line}: cuota_inicial_valor debe ser numerico y mayor o igual a cero.";
        }

        if ($row['cuota_inicial_tipo'] === 'porcentaje' && (float) $row['cuota_inicial_valor'] > 100) {
            $errors[] = "Linea {$line}: cuota_inicial_valor no puede ser mayor a 100 cuando el tipo es porcentaje.";
        }

        if (! in_array($row['estado'], Lote::ESTADOS, true)) {
            $errors[] = "Linea {$line}: estado invalido.";
        }

        foreach (['coord_x', 'coord_y'] as $field) {
            if (! is_numeric($row[$field]) || (float) $row[$field] < 0 || (float) $row[$field] > 100) {
                $errors[] = "Linea {$line}: {$field} debe estar entre 0 y 100.";
            }
        }

        $urbanizacionName = trim((string) $row['urbanizacion']);
        $manzanoCodigo = trim((string) $row['manzano']);
        $loteCodigo = trim((string) $row['lote']);

        if ($urbanizacionName !== '' && $manzanoCodigo !== '' && $loteCodigo !== '') {
            $key = strtolower($urbanizacionName.'|'.$manzanoCodigo.'|'.$loteCodigo);

            if (isset($seenRows[$key])) {
                $errors[] = "Linea {$line}: Ya existe un lote con ese código en este manzano dentro del CSV.";
            }

            $seenRows[$key] = $line;
        }

        $urbanizacion = Urbanizacion::where('nombre', $urbanizacionName)->first();
        $manzano = $urbanizacion
            ? Manzano::where('urbanizacion_id', $urbanizacion->id)->where('codigo', $manzanoCodigo)->first()
            : null;

        if ($manzano && Lote::where('manzano_id', $manzano->id)->where('codigo', $loteCodigo)->exists()) {
            $errors[] = "Linea {$line}: Ya existe un lote con ese código en este manzano.";
        }

        return $errors;
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function isEmptyLine(array $data): bool
    {
        return count($data) === 1 && trim((string) $data[0]) === '';
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
    }

    private function normalizeRow(array $row, array $headers): array
    {
        if ($headers === self::OLD_HEADERS) {
            $superficie = $this->normalizeNumber($row['superficie'] ?? 0);
            $precioTotal = $this->normalizeNumber($row['precio'] ?? 0);
            $precioM2 = (float) $superficie > 0 ? (float) $precioTotal / (float) $superficie : 0;

            $row = [
                'urbanizacion' => Urbanizacion::find(session('urbanizacion_id'))?->nombre,
                'manzano' => $row['manzano_codigo'] ?? null,
                'lote' => $row['lote_codigo'] ?? null,
                'superficie_m2' => $superficie,
                'precio_m2' => number_format($precioM2, 2, '.', ''),
                'precio_total' => $precioTotal,
                'cuota_inicial_tipo' => $row['cuota_inicial_tipo'] ?? 'monto',
                'cuota_inicial_valor' => $row['cuota_inicial_valor'] ?? 0,
                'estado' => $row['estado'] ?? null,
                'fila' => $row['fila'] ?? 1,
                'columna' => $row['columna'] ?? 1,
                'coord_x' => $row['coord_x'] ?? null,
                'coord_y' => $row['coord_y'] ?? null,
                'observaciones' => $row['observaciones'] ?? null,
            ];
        }

        $row['urbanizacion'] = trim((string) ($row['urbanizacion'] ?? '')) ?: Urbanizacion::find(session('urbanizacion_id'))?->nombre;
        $row['cuota_inicial_tipo'] = trim((string) ($row['cuota_inicial_tipo'] ?? 'monto')) ?: 'monto';
        $row['cuota_inicial_valor'] = $this->normalizeNumber($row['cuota_inicial_valor'] ?? 0);
        $row['fila'] = $this->normalizeNumber($row['fila'] ?? 1);
        $row['columna'] = $this->normalizeNumber($row['columna'] ?? 1);

        foreach (['superficie_m2', 'precio_m2', 'precio_total', 'coord_x', 'coord_y'] as $field) {
            $row[$field] = $this->normalizeNumber($row[$field] ?? null);
        }

        return $row;
    }

    private function normalizeNumber(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^\d,.\-]/', '', $value) ?? $value;

        if (str_contains($value, ',') && str_contains($value, '.')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        if (str_contains($value, ',')) {
            return str_replace(',', '.', $value);
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            return str_replace('.', '', $value);
        }

        return $value;
    }
}
