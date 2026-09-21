<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;

class ClientCommercialProtectionService
{
    public function __construct(private CommercialSettingsService $settings) {}

    public function days(?int $urbanizacionId = null): int
    {
        return $this->settings->clientProtectionDays($urbanizacionId);
    }

    /** @return array{protected_at: Carbon, protection_expires_at: Carbon} */
    public function period(?int $urbanizacionId = null, ?CarbonInterface $startedAt = null): array
    {
        $protectedAt = Carbon::instance(($startedAt ?? now())->toDateTime());

        return [
            'protected_at' => $protectedAt,
            'protection_expires_at' => $protectedAt->copy()->addDays($this->days($urbanizacionId)),
        ];
    }

    /** @return array{status: string, label: string, active: bool, days_remaining: int, protected_at: ?CarbonInterface, expires_at: ?CarbonInterface, has_operations: bool} */
    public function getCommercialProtection(Cliente $cliente): array
    {
        $expiresAt = $cliente->protection_expires_at;
        $active = $expiresAt !== null && $expiresAt->isFuture();
        $hasSales = $cliente->relationLoaded('ventas') ? $cliente->ventas->isNotEmpty() : $cliente->ventas()->exists();
        $hasReservations = $cliente->relationLoaded('reservas') ? $cliente->reservas->isNotEmpty() : $cliente->reservas()->exists();

        return [
            'status' => $active ? 'protected' : 'expired',
            'label' => $active ? 'Protección vigente' : 'Protección vencida',
            'active' => $active,
            'days_remaining' => $active ? (int) max(0, now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)) : 0,
            'protected_at' => $cliente->protected_at,
            'expires_at' => $expiresAt,
            'has_operations' => $hasSales || $hasReservations,
        ];
    }
}
