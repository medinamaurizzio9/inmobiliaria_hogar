<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\PagoAplicacion;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function __construct(private AuditService $auditService) {}

    /**
     * Distribuir un pago confirmado sobre las cuotas de la misma venta.
     *
     * Orden de aplicacion: cuota seleccionada primero y luego las restantes
     * pendientes o parciales de la misma venta ordenadas por fecha de vencimiento
     * y por id. El excedente que no cabe en ninguna cuota no se aplica.
     *
     * @return Collection<int, PagoAplicacion>
     */
    public function allocate(CashMovement $movement, Cuota $primary, ?User $user = null): Collection
    {
        if ($movement->estado !== 'confirmado') {
            throw ValidationException::withMessages([
                'movimiento' => 'Solo pagos confirmados pueden aplicarse a cuotas.',
            ]);
        }

        $totalCents = Money::toCents($movement->monto);
        if ($totalCents <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto a aplicar debe ser mayor a cero.',
            ]);
        }

        $restante = $totalCents;
        $aplicadas = collect();

        $candidatas = Cuota::query()
            ->where('venta_id', $primary->venta_id)
            ->where('saldo_pendiente', '>', 0)
            ->get();

        $ordenadas = $candidatas->sort(function (Cuota $a, Cuota $b) use ($primary): int {
            $pa = $a->id === $primary->id ? 0 : 1;
            $pb = $b->id === $primary->id ? 0 : 1;

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            $fa = (string) ($a->fecha_vencimiento?->format('Y-m-d') ?? $a->fecha_programada?->format('Y-m-d') ?? '');
            $fb = (string) ($b->fecha_vencimiento?->format('Y-m-d') ?? $b->fecha_programada?->format('Y-m-d') ?? '');

            return $fa === $fb ? ($a->id <=> $b->id) : strcmp($fa, $fb);
        })->values();

        foreach ($ordenadas as $cuota) {
            if ($restante <= 0) {
                break;
            }

            $bloqueada = $this->lockCuota((int) $cuota->id);
            $saldoCents = Money::toCents($bloqueada->saldo_pendiente);
            if ($saldoCents <= 0) {
                continue;
            }

            $aplicarCents = min($restante, $saldoCents);
            $aplicar = Money::fromCents($aplicarCents);

            $antes = $bloqueada->toArray();
            $nuevoPagado = Money::toCents($bloqueada->monto_pagado) + $aplicarCents;
            $nuevoSaldo = max(0, Money::toCents($bloqueada->monto) - $nuevoPagado);

            $bloqueada->monto_pagado = Money::fromCents($nuevoPagado);
            $bloqueada->saldo_pendiente = Money::fromCents($nuevoSaldo);
            $bloqueada->fecha_pago = $nuevoSaldo <= 0 ? now() : $bloqueada->fecha_pago;
            $bloqueada->estado = $this->cuotaEstado($bloqueada);
            $bloqueada->save();

            $aplicacion = PagoAplicacion::create([
                'cash_movement_id' => $movement->id,
                'cuota_id' => $bloqueada->id,
                'monto_aplicado' => $aplicar,
            ]);

            $aplicadas->push($aplicacion->load('cuota'));
            $restante -= $aplicarCents;

            $this->auditService->log($bloqueada, 'cobrar_cuota', 'Pago aplicado a la cuota.', $antes, $bloqueada->fresh()->toArray());
        }

        $this->auditService->log($movement, 'pago_aplicado', 'Pago aplicado a cuota(s).', null, [
            'aplicaciones' => $aplicadas->map(fn (PagoAplicacion $aplicacion): array => [
                'cuota_id' => $aplicacion->cuota_id,
                'monto_aplicado' => $aplicacion->monto_aplicado,
            ])->values()->all(),
        ]);

        return $aplicadas->values();
    }

    /**
     * Aplicar una amortizacion extraordinaria desde la ultima cuota pendiente.
     * De esta forma se conserva la mensualidad pactada y se reduce el plazo.
     *
     * @return Collection<int, PagoAplicacion>
     */
    public function amortize(CashMovement $movement, Cuota $primary, ?User $user = null): Collection
    {
        if ($movement->estado !== 'confirmado') {
            throw ValidationException::withMessages([
                'movimiento' => 'Solo pagos confirmados pueden aplicarse a cuotas.',
            ]);
        }

        $totalCents = Money::toCents($movement->monto);
        $candidatas = Cuota::query()
            ->where('venta_id', $primary->venta_id)
            ->where('saldo_pendiente', '>', 0)
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('fecha_programada')
            ->orderByDesc('id')
            ->get();
        $saldoCents = $candidatas->sum(fn (Cuota $cuota): int => Money::toCents($cuota->saldo_pendiente));

        if ($totalCents <= 0 || $totalCents > $saldoCents) {
            throw ValidationException::withMessages([
                'monto_pagado' => 'La amortizacion debe ser mayor a cero y no superar el saldo pendiente de la venta.',
            ]);
        }

        $restante = $totalCents;
        $aplicadas = collect();

        foreach ($candidatas as $cuota) {
            if ($restante <= 0) {
                break;
            }

            $bloqueada = $this->lockCuota((int) $cuota->id);
            $saldoCuotaCents = Money::toCents($bloqueada->saldo_pendiente);
            if ($saldoCuotaCents <= 0) {
                continue;
            }

            $aplicarCents = min($restante, $saldoCuotaCents);
            $antes = $bloqueada->toArray();
            $nuevoPagado = Money::toCents($bloqueada->monto_pagado) + $aplicarCents;
            $nuevoSaldo = max(0, Money::toCents($bloqueada->monto) - $nuevoPagado);

            $bloqueada->monto_pagado = Money::fromCents($nuevoPagado);
            $bloqueada->saldo_pendiente = Money::fromCents($nuevoSaldo);
            $bloqueada->fecha_pago = $nuevoSaldo <= 0 ? now() : $bloqueada->fecha_pago;
            $bloqueada->estado = $this->cuotaEstado($bloqueada);
            $bloqueada->save();

            $aplicacion = PagoAplicacion::create([
                'cash_movement_id' => $movement->id,
                'cuota_id' => $bloqueada->id,
                'monto_aplicado' => Money::fromCents($aplicarCents),
            ]);
            $aplicadas->push($aplicacion->load('cuota'));
            $restante -= $aplicarCents;

            $this->auditService->log($bloqueada, 'amortizar_cuota', 'Amortizacion extraordinaria aplicada a la cuota.', $antes, $bloqueada->fresh()->toArray());
        }

        $this->auditService->log($movement, 'amortizacion_aplicada', 'Amortizacion extraordinaria aplicada desde las ultimas cuotas.', null, [
            'aplicaciones' => $aplicadas->map(fn (PagoAplicacion $aplicacion): array => [
                'cuota_id' => $aplicacion->cuota_id,
                'monto_aplicado' => $aplicacion->monto_aplicado,
            ])->values()->all(),
        ]);

        return $aplicadas->values();
    }

    /**
     * Revertir todas las aplicaciones de un movimiento al anular el pago.
     * Las aplicaciones no se eliminan: quedan como trazabilidad historica.
     *
     * @return array<int, array{cuota_id: int, monto_restaurado: string}>
     */
    public function reverse(CashMovement $movement, string $motivo): array
    {
        $revertidas = [];

        $aplicaciones = $movement->pagoAplicaciones()->with('cuota')->get();
        $agrupadas = $aplicaciones->groupBy('cuota_id');

        foreach ($agrupadas as $cuotaId => $grupo) {
            $cuota = Cuota::query()->whereKey((int) $cuotaId)->lockForUpdate()->first();
            if (! $cuota) {
                continue;
            }

            $restarCents = $grupo->sum(fn (PagoAplicacion $aplicacion): int => Money::toCents($aplicacion->monto_aplicado));
            if ($restarCents <= 0) {
                continue;
            }

            $antes = $cuota->toArray();
            $nuevoPagado = max(0, Money::toCents($cuota->monto_pagado) - $restarCents);
            $nuevoSaldo = max(0, Money::toCents($cuota->monto) - $nuevoPagado);

            $cuota->monto_pagado = Money::fromCents($nuevoPagado);
            $cuota->saldo_pendiente = Money::fromCents($nuevoSaldo);
            $cuota->fecha_pago = $nuevoPagado <= 0 ? null : $cuota->fecha_pago;
            $cuota->estado = $this->cuotaEstado($cuota);
            $cuota->save();

            $this->auditService->log($cuota, 'cuota_restaurada_por_anulacion', $motivo, $antes, $cuota->fresh()->toArray());

            $revertidas[] = [
                'cuota_id' => (int) $cuotaId,
                'monto_restaurado' => Money::fromCents($restarCents),
            ];
        }

        return $revertidas;
    }

    public function cuotaEstado(Cuota $cuota): string
    {
        if (Money::toCents($cuota->saldo_pendiente) <= 0) {
            return 'pagada';
        }

        if (Money::toCents($cuota->monto_pagado) > 0) {
            return 'parcial';
        }

        $fechaVencimiento = $cuota->fecha_vencimiento ?? $cuota->fecha_programada;

        if ($fechaVencimiento && $fechaVencimiento->lt(now()->startOfDay())) {
            return 'vencida';
        }

        return 'pendiente';
    }

    private function lockCuota(int $id): Cuota
    {
        $query = Cuota::query();

        if (DB::transactionLevel() > 0) {
            return $query->lockForUpdate()->whereKey($id)->firstOrFail();
        }

        return $query->whereKey($id)->firstOrFail();
    }
}
