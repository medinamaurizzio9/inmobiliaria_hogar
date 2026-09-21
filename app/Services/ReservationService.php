<?php

namespace App\Services;

use App\Models\ClientCommercialEvent;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        private LotService $lotService,
        private CashMovementService $cashMovementService,
        private AuditService $auditService,
        private ClientCommercialProtectionService $clientProtection,
        private ClientCommercialEventService $clientEvents,
    ) {}

    public function create(array $data, ?User $user): Reserva
    {
        return DB::transaction(function () use ($data, $user): Reserva {
            $lote = Lote::lockForUpdate()->findOrFail($data['lote_id']);

            if ($lote->estado === 'vendido') {
                throw ValidationException::withMessages(['lote_id' => 'Este lote ya se encuentra vendido.']);
            }

            if ($lote->estado === 'bloqueado') {
                throw ValidationException::withMessages(['lote_id' => 'No se puede reservar un lote bloqueado.']);
            }

            if ($lote->estado === 'reservado') {
                throw ValidationException::withMessages(['lote_id' => 'Este lote ya tiene una reserva activa.']);
            }

            $reserva = Reserva::create([
                ...$data,
                'usuario_id' => $user?->id,
                'estado' => 'activa',
            ]);
            $this->auditService->log($reserva, 'crear_reserva', 'Reserva creada.', null, $reserva->toArray());

            $this->lotService->syncStatusFromReservations($lote, 'reserva_creada', $user, 'Reserva creada para cliente #'.$reserva->cliente_id);
            $this->cashMovementService->ingresoReserva($reserva, (float) $reserva->monto_reserva, $data['metodo_pago'] ?? 'efectivo', $user, $data['referencia'] ?? null);

            if ($this->clientProtection->getCommercialProtection($reserva->cliente)['active']) {
                $this->clientEvents->registerCommercialEvent(
                    $reserva->cliente,
                    ClientCommercialEvent::PROTECTED_OPERATION,
                    $user,
                    (int) $lote->manzano()->value('urbanizacion_id'),
                    null,
                    ['operation_type' => 'reservation', 'operation_id' => $reserva->id],
                );
            }

            return $reserva;
        });
    }

    public function cancel(Reserva $reserva, ?User $user, ?string $motivo = null): Reserva
    {
        return DB::transaction(function () use ($reserva, $user, $motivo): Reserva {
            if (! $motivo) {
                throw ValidationException::withMessages(['motivo' => 'Debes indicar el motivo para cancelar la reserva.']);
            }

            if ($reserva->estado !== 'activa') {
                throw ValidationException::withMessages(['reserva' => 'Solo se pueden cancelar reservas activas.']);
            }

            $before = $reserva->toArray();
            $reserva->update(['estado' => 'cancelada']);
            $this->lotService->syncStatusFromReservations($reserva->lote, 'reserva_cancelada', $user, 'Reserva cancelada.');
            $this->auditService->log($reserva, 'cancelar_reserva', $motivo, $before, $reserva->fresh()->toArray());

            return $reserva;
        });
    }

    public function expire(Reserva $reserva, ?User $user): Reserva
    {
        return DB::transaction(function () use ($reserva, $user): Reserva {
            if ($reserva->estado !== 'activa') {
                return $reserva;
            }

            $reserva->update(['estado' => 'vencida']);
            $this->lotService->syncStatusFromReservations($reserva->lote, 'reserva_vencida', $user, 'Reserva vencida.');
            $this->auditService->log($reserva, 'vencer_reserva', 'Reserva vencida.', null, $reserva->fresh()->toArray());

            return $reserva;
        });
    }
}
