<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClientMatchingService
{
    public function __construct(private ClientCommercialProtectionService $protection) {}

    public function normalizeDocument(?string $value): ?string
    {
        $normalized = preg_replace('/[^\pL\pN]/u', '', mb_strtoupper(trim((string) $value)));

        return $normalized !== '' ? $normalized : null;
    }

    public function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 11 && str_starts_with($digits, '591')) {
            $digits = substr($digits, 3);
        }

        return $digits !== '' ? $digits : null;
    }

    public function normalizeEmail(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    public function findExactClient(?string $document, ?int $excludeId = null): ?Cliente
    {
        $normalized = $this->normalizeDocument($document);
        if ($normalized === null) {
            return null;
        }

        $query = Cliente::query()->with($this->summaryRelations());
        if ($excludeId !== null) {
            $query->whereKeyNot($excludeId);
        }

        $match = (clone $query)->where('documento_normalizado', $normalized)->first();
        if ($match) {
            return $match;
        }

        // Solo recorre registros históricos ambiguos que la migración dejó expresamente sin normalizar.
        return $query->whereNull('documento_normalizado')
            ->whereNotNull('documento')
            ->get()
            ->first(fn (Cliente $cliente): bool => $this->normalizeDocument($cliente->documento) === $normalized);
    }

    /** @return Collection<int, Cliente> */
    public function findPotentialMatches(array $data, ?int $excludeId = null): Collection
    {
        $phone = $this->normalizePhone($data['telefono'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        if ($phone === null && $email === null) {
            return new Collection;
        }

        return Cliente::query()
            ->with($this->summaryRelations())
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->where(function ($query) use ($phone, $email): void {
                $query->when($phone !== null, fn ($nested) => $nested->orWhere('telefono_normalizado', $phone))
                    ->when($email !== null, fn ($nested) => $nested->orWhere('email_normalizado', $email));
            })
            ->limit(10)
            ->get()
            ->each(function (Cliente $cliente) use ($phone, $email): void {
                $types = [];
                if ($phone !== null && $cliente->telefono_normalizado === $phone) {
                    $types[] = 'phone';
                }
                if ($email !== null && $cliente->email_normalizado === $email) {
                    $types[] = 'email';
                }
                $cliente->setAttribute('commercial_match_types', $types);
            });
    }

    public function summary(Cliente $cliente, ?User $viewer = null): array
    {
        $cliente->loadMissing($this->summaryRelations());
        $canSeeFullDocument = $viewer?->hasAnyRole(['super administrador', 'administrador', 'gerente']) ?? false;
        $document = (string) $cliente->documento;
        $properties = $cliente->ventas->map(fn ($venta): array => [
            'urbanizacion' => $venta->lote?->manzano?->urbanizacion?->nombre,
            'manzano' => $venta->lote?->manzano?->codigo,
            'lote' => $venta->lote?->codigo,
            'modalidad' => $venta->tipo_operacion,
            'estado' => $venta->estado,
            'tipo' => 'Venta',
        ])->concat($cliente->reservas->map(fn ($reserva): array => [
            'urbanizacion' => $reserva->lote?->manzano?->urbanizacion?->nombre,
            'manzano' => $reserva->lote?->manzano?->codigo,
            'lote' => $reserva->lote?->codigo,
            'modalidad' => $reserva->tipo_operacion,
            'estado' => $reserva->estado,
            'tipo' => 'Reserva',
        ]))->values();

        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'documento' => $canSeeFullDocument ? $document : $this->maskDocument($document),
            'registered_by' => $cliente->createdBy?->name ?? 'Usuario no registrado',
            'responsible_advisor' => $cliente->responsibleAdvisor?->name ?? 'Sin asesor responsable',
            'registered_at' => $cliente->created_at?->format('d/m/Y H:i'),
            'registered_days_ago' => $cliente->created_at ? (int) $cliente->created_at->diffInDays(now()) : null,
            'protection' => $this->protection->getCommercialProtection($cliente),
            'properties' => $properties,
            'operations_count' => $properties->count(),
            'match_types' => $cliente->getAttribute('commercial_match_types') ?? [],
        ];
    }

    private function maskDocument(string $document): string
    {
        if (mb_strlen($document) <= 4) {
            return str_repeat('•', mb_strlen($document));
        }

        return mb_substr($document, 0, 2).str_repeat('•', max(2, mb_strlen($document) - 4)).mb_substr($document, -2);
    }

    private function summaryRelations(): array
    {
        return [
            'createdBy:id,name',
            'responsibleAdvisor:id,name',
            'ventas:id,cliente_id,lote_id,tipo_operacion,estado',
            'ventas.lote:id,manzano_id,codigo',
            'ventas.lote.manzano:id,urbanizacion_id,codigo',
            'ventas.lote.manzano.urbanizacion:id,nombre',
            'reservas:id,cliente_id,lote_id,tipo_operacion,estado',
            'reservas.lote:id,manzano_id,codigo',
            'reservas.lote.manzano:id,urbanizacion_id,codigo',
            'reservas.lote.manzano.urbanizacion:id,nombre',
        ];
    }
}
