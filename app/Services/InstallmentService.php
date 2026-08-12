<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\User;
use App\Models\Venta;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InstallmentService
{
    public function __construct(
        private CashMovementService $cashMovementService,
        private AuditService $auditService,
        private PaymentAllocationService $allocationService
    ) {}

    private function fechaCuota(Venta $venta, int $numero): string
    {
        if ($venta->fecha_primer_vencimiento) {
            $base = Carbon::parse($venta->fecha_primer_vencimiento);
            $fecha = $base->copy()->addMonthsNoOverflow($numero - 1);

            return $base->isLastOfMonth()
                ? $fecha->endOfMonth()->toDateString()
                : $fecha->toDateString();
        }

        return Carbon::parse($venta->fecha_venta)->addMonths($numero)->toDateString();
    }

    public function generateForSale(Venta $venta): void
    {
        $numeroCuotas = (int) $venta->numero_cuotas;

        if ($numeroCuotas < 1) {
            return;
        }

        $saldoCentavos = max(0, Money::toCents($venta->precio_final) - Money::toCents($venta->cuota_inicial));
        $montos = Money::distribute($saldoCentavos, $numeroCuotas);

        for ($i = 0; $i < $numeroCuotas; $i++) {
            $numero = $i + 1;
            $fecha = $this->fechaCuota($venta, $numero);

            Cuota::create([
                'venta_id' => $venta->id,
                'numero' => $numero,
                'fecha_programada' => $fecha,
                'fecha_vencimiento' => $fecha,
                'monto' => $montos[$i],
                'monto_pagado' => 0,
                'saldo_pendiente' => $montos[$i],
                'estado' => 'pendiente',
            ]);
        }
    }

    public function resyncForSale(Venta $venta): array
    {
        $venta->load('cuotas');
        $preserved = $venta->cuotas->filter(fn (Cuota $cuota): bool => (float) $cuota->monto_pagado > 0);

        if ((int) $venta->numero_cuotas < $preserved->count()) {
            throw ValidationException::withMessages([
                'numero_cuotas' => 'El numero de cuotas no puede ser menor a la cantidad de cuotas que ya tienen pagos.',
            ]);
        }

        $toDelete = $venta->cuotas->reject(fn (Cuota $cuota): bool => (float) $cuota->monto_pagado > 0);
        $deleted = $toDelete->map(fn (Cuota $cuota): array => $cuota->toArray())->values()->all();
        Cuota::whereIn('id', $toDelete->pluck('id'))->delete();

        $paidAmount = (float) $preserved->sum('monto_pagado');
        $availableAfterInitial = max(0, round((float) $venta->precio_final - (float) $venta->cuota_inicial, 2));
        if ($paidAmount > $availableAfterInitial) {
            throw ValidationException::withMessages([
                'precio_final' => 'El precio y la cuota inicial no pueden dejar un saldo menor al monto ya pagado en cuotas.',
            ]);
        }

        $remainingBalance = (int) $venta->numero_cuotas === 0
            ? 0.0
            : max(0, round($availableAfterInitial - $paidAmount, 2));
        $preservedOutstanding = (float) $preserved->sum('saldo_pendiente');
        if ($preservedOutstanding > $remainingBalance) {
            throw ValidationException::withMessages([
                'precio_final' => 'El nuevo saldo no puede ser menor al saldo pendiente de las cuotas que ya tienen pagos.',
            ]);
        }

        $balanceToGenerate = max(0, round($remainingBalance - $preservedOutstanding, 2));
        $pendingCount = max(0, (int) $venta->numero_cuotas - $preserved->count());
        $created = [];

        if ($pendingCount > 0 && $balanceToGenerate > 0) {
            $baseAmount = round($balanceToGenerate / $pendingCount, 2);
            $distributed = 0.0;
            $nextNumber = max(0, (int) $preserved->max('numero'));

            for ($i = 1; $i <= $pendingCount; $i++) {
                $number = $nextNumber + $i;
                $amount = $i === $pendingCount
                    ? round($balanceToGenerate - $distributed, 2)
                    : $baseAmount;
                $distributed += $amount;
                $fecha = $this->fechaCuota($venta, $number);

                $cuota = Cuota::create([
                    'venta_id' => $venta->id,
                    'numero' => $number,
                    'fecha_programada' => $fecha,
                    'fecha_vencimiento' => $fecha,
                    'monto' => $amount,
                    'monto_pagado' => 0,
                    'saldo_pendiente' => $amount,
                    'estado' => 'pendiente',
                ]);
                $created[] = $cuota->toArray();
            }
        }

        $venta->update(['saldo_financiar' => $remainingBalance]);

        return [
            'cuotas_conservadas' => $preserved->map(fn (Cuota $cuota): array => $cuota->toArray())->values()->all(),
            'cuotas_eliminadas' => $deleted,
            'cuotas_creadas' => $created,
            'total_pagado_cuotas' => $paidAmount,
            'saldo_financiar' => $remainingBalance,
        ];
    }

    public function pay(Cuota $cuota, float $monto, string $metodoPago, ?User $user, ?string $referencia = null): Cuota
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto_pagado' => 'El monto a pagar debe ser mayor a cero.']);
        }

        return DB::transaction(function () use ($cuota, $monto, $metodoPago, $user, $referencia): Cuota {
            $movement = $this->cashMovementService->ingresoCuota($cuota, $monto, $metodoPago, $user, $referencia);
            $this->allocationService->allocate($movement, $cuota, $user);

            return $cuota;
        });
    }

    public function markOverdue(): int
    {
        return Cuota::whereIn('estado', ['pendiente', 'parcial'])
            ->whereDate('fecha_programada', '<', now())
            ->update(['estado' => 'vencida']);
    }
}
