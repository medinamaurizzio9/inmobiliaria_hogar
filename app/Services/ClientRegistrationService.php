<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ClientRegistrationService
{
    public function __construct(
        private ClientMatchingService $matching,
        private ClientCommercialProtectionService $protection,
    ) {}

    /** @return array{client: Cliente, created: bool} */
    public function register(array $data, User $actor, int $urbanizacionId, ?int $responsibleUserId = null): array
    {
        $normalizedDocument = $this->matching->normalizeDocument($data['documento'] ?? null);
        $attributes = [
            ...$data,
            'urbanizacion_id' => $urbanizacionId,
            'created_by' => $actor->id,
            'responsible_user_id' => $responsibleUserId ?? $actor->id,
            'documento_normalizado' => $normalizedDocument,
            'telefono_normalizado' => $this->matching->normalizePhone($data['telefono'] ?? null),
            'email_normalizado' => $this->matching->normalizeEmail($data['email'] ?? null),
            ...$this->protection->period($urbanizacionId),
        ];

        try {
            return DB::transaction(function () use ($attributes, $normalizedDocument): array {
                if ($normalizedDocument !== null) {
                    $existing = Cliente::query()
                        ->where('documento_normalizado', $normalizedDocument)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return ['client' => $existing, 'created' => false];
                    }
                }

                return ['client' => Cliente::create($attributes), 'created' => true];
            });
        } catch (QueryException $exception) {
            if ($normalizedDocument === null || ! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            $existing = Cliente::where('documento_normalizado', $normalizedDocument)->first();
            if (! $existing) {
                throw $exception;
            }

            return ['client' => $existing, 'created' => false];
        }
    }
}
