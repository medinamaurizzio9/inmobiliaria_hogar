<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\LotHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LotService
{
    public function ensureCanSell(Lote $lote, int $clienteId, bool $adminConfirma = false): void
    {
        $lote->loadMissing('reservaActiva');

        if ($lote->estado === 'vendido') {
            throw ValidationException::withMessages(['lote_id' => 'Este lote ya se encuentra vendido.']);
        }

        if ($lote->estado === 'bloqueado') {
            throw ValidationException::withMessages(['lote_id' => 'Este lote esta bloqueado y no puede venderse.']);
        }

        if ($lote->estado === 'reservado' && $lote->reservaActiva?->cliente_id !== $clienteId && ! $adminConfirma) {
            throw ValidationException::withMessages([
                'lote_id' => 'Este lote esta reservado para otro cliente. Solicita confirmacion administrativa para continuar.',
            ]);
        }
    }

    public function changeStatus(Lote $lote, string $estadoNuevo, string $accion, ?User $user = null, ?string $descripcion = null): void
    {
        $estadoAnterior = $lote->estado;
        $lote->update(['estado' => $estadoNuevo]);

        if ($estadoAnterior !== $estadoNuevo || $descripcion) {
            $this->recordHistory($lote, $accion, $user, $descripcion, $estadoAnterior, $estadoNuevo);
        }
    }

    public function syncStatusFromReservations(Lote $lote, string $accion, ?User $user = null, ?string $descripcion = null): void
    {
        $lote->refresh();

        if ($this->hasActiveSale($lote)) {
            if ($lote->estado !== 'vendido') {
                $this->changeStatus($lote, 'vendido', $accion, $user, $descripcion ?? 'Lote con venta activa.');
            }

            return;
        }

        $hasActiveReservation = $lote->reservas()->where('estado', 'activa')->exists();
        $target = $hasActiveReservation ? 'reservado' : 'disponible';

        if ($lote->estado !== $target) {
            $this->changeStatus($lote, $target, $accion, $user, $descripcion);
        }
    }

    public function hasActiveSale(Lote $lote): bool
    {
        return $lote->venta()
            ->whereIn('estado', ['activa', 'completada'])
            ->exists();
    }

    public function recordHistory(
        Lote $lote,
        string $accion,
        ?User $user = null,
        ?string $descripcion = null,
        ?string $estadoAnterior = null,
        ?string $estadoNuevo = null
    ): LotHistory {
        return LotHistory::create([
            'lote_id' => $lote->id,
            'user_id' => $user?->id,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
        ]);
    }

    public function trackManualChanges(Lote $lote, array $before, ?User $user): void
    {
        if (array_key_exists('precio', $before) && (float) $before['precio'] !== (float) $lote->precio) {
            $this->recordHistory($lote, 'cambio_precio', $user, 'Cambio manual de precio: '.$before['precio'].' a '.$lote->precio, $before['estado'] ?? null, $lote->estado);
        }

        if (($before['estado'] ?? null) !== $lote->estado) {
            $this->recordHistory($lote, 'cambio_estado_manual', $user, 'Cambio manual de estado del lote.', $before['estado'] ?? null, $lote->estado);
        }

        $tipoAnterior = $before['cuota_inicial_tipo'] ?? null;
        $valorAnterior = array_key_exists('cuota_inicial_valor', $before) ? (float) $before['cuota_inicial_valor'] : null;
        if ($tipoAnterior !== $lote->cuota_inicial_tipo || $valorAnterior !== (float) $lote->cuota_inicial_valor) {
            $this->recordHistory(
                $lote,
                'cambio_cuota_inicial',
                $user,
                'Cambio manual de cuota inicial: '.($tipoAnterior ?? 'sin tipo').' '.$valorAnterior.' a '.$lote->cuota_inicial_tipo.' '.$lote->cuota_inicial_valor,
                $before['estado'] ?? null,
                $lote->estado
            );
        }
    }
}
