<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Reestructuracion;
use App\Models\User;
use App\Models\Venta;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtRestructuringService
{
    public function __construct(private AuditService $auditService) {}

    public function restructure(Venta $venta, array $data, User $user): Reestructuracion
    {
        $this->authorize($user);

        return DB::transaction(function () use ($venta, $data, $user): Reestructuracion {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);
            if (! in_array($venta->tipo_operacion, ['semicontado', 'credito'], true) || $venta->estado === 'anulada') {
                throw ValidationException::withMessages(['venta' => 'Solo se puede reestructurar una venta financiada activa.']);
            }

            $cuotas = Cuota::where('venta_id', $venta->id)->lockForUpdate()->orderBy('numero')->get();
            $activas = $cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida']);
            $saldoCents = $activas->sum(fn (Cuota $cuota) => Money::toCents($cuota->saldo_pendiente));
            $plazo = (int) ($data['nuevo_plazo'] ?? 0);
            if ($saldoCents <= 0) {
                throw ValidationException::withMessages(['venta' => 'La venta no tiene saldo pendiente para reestructurar.']);
            }
            if ($plazo < 1) {
                throw ValidationException::withMessages(['nuevo_plazo' => 'El nuevo plazo debe ser mayor a cero.']);
            }

            $antes = ['saldo' => Money::fromCents($saldoCents), 'cuotas' => $cuotas->map->toArray()->all()];
            foreach ($activas as $cuota) {
                $cuota->update([
                    'saldo_pendiente' => '0.00',
                    'estado' => 'anulada',
                    'observaciones' => trim(($cuota->observaciones ? $cuota->observaciones."\n" : '').'Anulada por reestructuracion de deuda.'),
                ]);
            }

            $montos = Money::distribute($saldoCents, $plazo);
            $base = Carbon::parse($data['fecha_primer_vencimiento']);
            $nextNumber = (int) $cuotas->max('numero');
            $nuevas = [];
            foreach ($montos as $index => $monto) {
                $fecha = $base->copy()->addMonthsNoOverflow($index);
                if ($base->isLastOfMonth()) {
                    $fecha->endOfMonth();
                }
                $nuevas[] = Cuota::create([
                    'venta_id' => $venta->id,
                    'numero' => $nextNumber + $index + 1,
                    'fecha_programada' => $fecha->toDateString(),
                    'fecha_vencimiento' => $fecha->toDateString(),
                    'monto' => $monto,
                    'monto_pagado' => '0.00',
                    'saldo_pendiente' => $monto,
                    'estado' => 'pendiente',
                    'observaciones' => 'Cuota generada por reestructuracion.',
                ])->toArray();
            }

            $venta->update(['saldo_financiar' => Money::fromCents($saldoCents), 'numero_cuotas' => $plazo, 'fecha_primer_vencimiento' => $base->toDateString()]);
            $despues = ['saldo' => Money::fromCents($saldoCents), 'cuotas' => $nuevas];
            $reestructuracion = Reestructuracion::create([
                'venta_id' => $venta->id, 'saldo_antes' => Money::fromCents($saldoCents),
                'numero_cuotas_pendientes_antes' => $activas->count(), 'plazo_anterior' => $activas->count(),
                'nuevo_plazo' => $plazo, 'cuota_referencia' => $montos[0] ?? null,
                'fecha' => now()->toDateString(), 'fecha_primer_vencimiento' => $base->toDateString(),
                'administrador_id' => $user->id, 'motivo' => $data['motivo'],
                'observaciones' => $data['observaciones'] ?? null, 'snapshot_antes' => $antes, 'snapshot_despues' => $despues,
            ]);
            $this->auditService->log($venta, 'reestructuracion_creada', $data['motivo'], $antes, $despues);

            return $reestructuracion;
        });
    }

    private function authorize(User $user): void
    {
        abort_unless($user->hasRole('administrador'), 403, 'Solo un administrador puede reestructurar una deuda.');
    }
}
