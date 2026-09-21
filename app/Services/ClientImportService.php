<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClientImportBatch;
use App\Models\ClientImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ClientImportService
{
    public const MAX_ROWS = 5000;

    public const HEADERS = ['nombre', 'ci', 'telefono', 'email', 'direccion', 'asesor_email'];

    private const HEADER_ALIASES = [
        'nombre' => 'nombre', 'nombres' => 'nombre', 'nombre completo' => 'nombre',
        'ci' => 'documento', 'c i' => 'documento', 'documento' => 'documento', 'carnet' => 'documento',
        'telefono' => 'telefono', 'celular' => 'telefono', 'whatsapp' => 'telefono',
        'email' => 'email', 'e mail' => 'email', 'correo' => 'email', 'correo electronico' => 'email',
        'direccion' => 'direccion', 'domicilio' => 'direccion',
        'asesor' => 'asesor_email', 'asesor email' => 'asesor_email', 'email asesor' => 'asesor_email', 'correo asesor' => 'asesor_email',
    ];

    public function __construct(
        private UserSpreadsheetService $spreadsheets,
        private ClientMatchingService $matching,
        private ClientRegistrationService $registration,
    ) {}

    public function preview(UploadedFile $file, User $actor, int $urbanizacionId): ClientImportBatch
    {
        ClientImportBatch::where('status', 'preview')->where('created_at', '<', now()->subDay())->delete();
        $rawRows = $this->spreadsheets->rowsFromUpload($file);
        if ($rawRows === []) {
            throw new RuntimeException('El archivo está vacío o no contiene filas válidas.');
        }
        if (count($rawRows) > self::MAX_ROWS) {
            throw new RuntimeException('El archivo supera el máximo de '.number_format(self::MAX_ROWS, 0, ',', '.').' filas.');
        }

        $rows = [];
        foreach ($rawRows as $index => $raw) {
            $rows[] = $this->analyzeRow($raw, $index + 2, $actor);
        }
        $this->applyInternalDuplicates($rows);

        return DB::transaction(function () use ($file, $actor, $urbanizacionId, $rows): ClientImportBatch {
            $batch = ClientImportBatch::create([
                'user_id' => $actor->id,
                'urbanizacion_id' => $urbanizacionId,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'status' => 'preview',
                'total_rows' => count($rows),
            ]);

            foreach ($rows as $row) {
                $batch->rows()->create($row);
            }
            $this->refreshSummary($batch);

            return $batch->fresh('rows.cliente');
        });
    }

    public function confirm(ClientImportBatch $batch, User $actor, array $acceptedMatches): array
    {
        if ($batch->status !== 'preview') {
            throw new RuntimeException('Este lote de importación ya fue procesado.');
        }

        $accepted = collect($acceptedMatches)->map(fn ($id) => (int) $id)->all();
        $matchTotal = $batch->match_rows;
        $created = [];
        foreach ($batch->rows()->get() as $row) {
            if ($row->status === ClientImportRow::MATCH && ! in_array($row->id, $accepted, true)) {
                continue;
            }
            if (! in_array($row->status, [ClientImportRow::READY, ClientImportRow::MATCH], true)) {
                continue;
            }

            try {
                $payload = $row->payload;
                $result = $this->registration->register([
                    'nombre' => $payload['nombre'],
                    'documento' => $payload['documento'],
                    'telefono' => $payload['telefono'] ?: null,
                    'email' => $payload['email'] ?: null,
                    'direccion' => $payload['direccion'] ?: null,
                ], $actor, (int) $batch->urbanizacion_id, $payload['responsible_user_id'] ?: null);

                if ($result['created']) {
                    $row->update(['status' => ClientImportRow::CREATED, 'cliente_id' => $result['client']->id, 'match_accepted' => $row->status === ClientImportRow::MATCH, 'detail' => 'Cliente creado correctamente.']);
                    $created[] = $result['client'];
                } else {
                    $row->update(['status' => ClientImportRow::EXISTING, 'cliente_id' => $result['client']->id, 'detail' => 'El CI ya existe; no se modificó el cliente.']);
                }
            } catch (Throwable) {
                $row->update(['status' => ClientImportRow::ERROR, 'detail' => 'No se pudo crear el cliente. Revise los datos e intente nuevamente.']);
            }
        }

        $batch->update(['status' => 'completed', 'completed_at' => now()]);
        $this->refreshSummary($batch);
        $batch->update(['match_rows' => $matchTotal]);

        return ['batch' => $batch->fresh('rows.cliente'), 'created' => $created];
    }

    public function cancel(ClientImportBatch $batch): void
    {
        if ($batch->status === 'preview') {
            $batch->delete();
        }
    }

    private function analyzeRow(array $raw, int $line, User $viewer): array
    {
        $mapped = [];
        foreach ($raw as $header => $value) {
            $canonical = self::HEADER_ALIASES[$this->normalizeHeader((string) $header)] ?? null;
            if ($canonical !== null && ! array_key_exists($canonical, $mapped)) {
                $mapped[$canonical] = trim((string) $value);
            }
        }
        $payload = [
            'nombre' => $mapped['nombre'] ?? '', 'documento' => $mapped['documento'] ?? '',
            'telefono' => $mapped['telefono'] ?? '', 'email' => $mapped['email'] ?? '',
            'direccion' => $mapped['direccion'] ?? '', 'asesor_email' => mb_strtolower($mapped['asesor_email'] ?? ''),
            'responsible_user_id' => null, 'normalized_document' => $this->matching->normalizeDocument($mapped['documento'] ?? null),
        ];
        $errors = [];
        if ($payload['nombre'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($payload['normalized_document'] === null) {
            $errors[] = 'El CI es obligatorio.';
        }
        if ($payload['email'] !== '' && ! filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo no tiene un formato válido.';
        }
        if ($payload['asesor_email'] !== '') {
            $advisor = User::query()->whereRaw('LOWER(email) = ?', [$payload['asesor_email']])
                ->whereHas('roles', fn ($query) => $query->where('name', 'vendedor')->where('guard_name', 'web'))->first();
            if (! $advisor) {
                $errors[] = 'El asesor indicado no existe o no tiene rol vendedor.';
            } else {
                $payload['responsible_user_id'] = $advisor->id;
            }
        }

        if ($errors !== []) {
            return $this->row($line, ClientImportRow::ERROR, implode(' ', $errors), $payload);
        }
        if ($existing = $this->matching->findExactClient($payload['documento'])) {
            return $this->row($line, ClientImportRow::EXISTING, 'El CI ya pertenece a '.$existing->nombre.'. No se modificará.', $payload, $existing->id);
        }
        $potential = $this->matching->findPotentialMatches(['telefono' => $payload['telefono'], 'email' => $payload['email']]);
        if ($potential->isNotEmpty()) {
            $types = $potential->flatMap(fn (Cliente $client) => $client->getAttribute('commercial_match_types'))->unique()->map(fn ($type) => $type === 'phone' ? 'teléfono' : 'email')->implode(' y ');

            return $this->row($line, ClientImportRow::MATCH, 'Coincidencia por '.$types.'. Confirme que es una persona diferente.', $payload);
        }

        return $this->row($line, ClientImportRow::READY, 'Lista para importar.', $payload);
    }

    private function applyInternalDuplicates(array &$rows): void
    {
        $groups = collect($rows)->groupBy(fn (array $row) => $row['payload']['normalized_document'] ?: 'line-'.$row['row_number'], preserveKeys: true);
        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }
            $indexes = $group->keys();
            $names = $group->map(fn ($row) => $this->normalizeText($row['payload']['nombre']))->unique();
            $phones = $group->pluck('payload.telefono')->filter()->map(fn ($v) => $this->matching->normalizePhone($v))->unique();
            $emails = $group->pluck('payload.email')->filter()->map(fn ($v) => $this->matching->normalizeEmail($v))->unique();
            if ($names->count() > 1 || $phones->count() > 1 || $emails->count() > 1) {
                foreach ($indexes as $index) {
                    $rows[$index]['status'] = ClientImportRow::ERROR;
                    $rows[$index]['detail'] = 'El mismo CI aparece con datos contradictorios dentro del archivo.';
                }

                continue;
            }
            foreach ($indexes->slice(1) as $index) {
                $rows[$index]['status'] = ClientImportRow::EXISTING;
                $rows[$index]['detail'] = 'CI repetido dentro del archivo; se importará únicamente la primera fila compatible.';
            }
        }
    }

    private function refreshSummary(ClientImportBatch $batch): void
    {
        $counts = $batch->rows()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $batch->update([
            'created_rows' => $counts[ClientImportRow::CREATED] ?? 0,
            'existing_rows' => $counts[ClientImportRow::EXISTING] ?? 0,
            'match_rows' => $counts[ClientImportRow::MATCH] ?? 0,
            'error_rows' => $counts[ClientImportRow::ERROR] ?? 0,
        ]);
    }

    private function row(int $line, string $status, string $detail, array $payload, ?int $clienteId = null): array
    {
        return ['row_number' => $line, 'status' => $status, 'detail' => $detail, 'payload' => $payload, 'cliente_id' => $clienteId];
    }

    private function normalizeHeader(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $value)))));
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower($value))));
    }
}
