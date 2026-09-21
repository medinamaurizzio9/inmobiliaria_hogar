<?php

namespace App\Services;

use App\Models\ClientCommercialEvent;
use App\Models\Cliente;
use App\Models\User;

class ClientCommercialEventService
{
    public function registerCommercialEvent(
        Cliente $cliente,
        string $eventType,
        ?User $actor = null,
        ?int $urbanizacionId = null,
        ?string $matchType = null,
        array $metadata = [],
        ?int $involvedAdvisorId = null,
    ): ClientCommercialEvent {
        return ClientCommercialEvent::create([
            'cliente_id' => $cliente->id,
            'urbanizacion_id' => $urbanizacionId,
            'actor_user_id' => $actor?->id,
            'original_advisor_id' => $cliente->created_by,
            'involved_advisor_id' => $involvedAdvisorId ?? $actor?->id,
            'event_type' => $eventType,
            'match_type' => $matchType,
            'metadata' => $metadata ?: null,
        ]);
    }
}
