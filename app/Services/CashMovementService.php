<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashMovementService
{
    public function __construct(
        private AuditService $auditService,
        private PaymentAllocationService $allocationService
    ) {}

    public function ingresoReserva(Reserva $reserva, float $monto, string $metodoPago, ?User $user, ?string $referencia = null): ?CashMovement
    {
        if ($monto <= 0) {
            return null;
        }

        return $this->create([
            'user_id' => $user?->id,
            'cliente_id' => $reserva->cliente_id,
            'reservation_id' => $reserva->id,
            'tipo' => 'ingreso',
            'concepto' => 'reserva',
            'metodo_pago' => $metodoPago,
            'monto' => $monto,
            'fecha' => now(),
            'referencia' => $referencia,
            'estado' => 'confirmado',
        ]);
    }

    public function ingresoVenta(Venta $venta, float $monto, string $concepto, string $metodoPago, ?User $user, ?string $referencia = null): ?CashMovement
    {
        if ($monto <= 0) {
            return null;
        }

        return $this->create([
            'user_id' => $user?->id,
            'cliente_id' => $venta->cliente_id,
            'sale_id' => $venta->id,
            'tipo' => 'ingreso',
            'concepto' => $concepto,
            'metodo_pago' => $metodoPago,
            'monto' => $monto,
            'fecha' => now(),
            'referencia' => $referencia,
            'estado' => 'confirmado',
        ]);
    }

    public function solicitarPagoCuota(Cuota $cuota, float $monto, string $metodoPago, User $user, array $data): CashMovement
    {
        if (! in_array($metodoPago, ['QR', 'transferencia'], true)) {
            throw ValidationException::withMessages([
                'metodo_pago' => 'El metodo de pago debe ser QR o transferencia.',
            ]);
        }

        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto a pagar debe ser mayor a cero.',
            ]);
        }

        $referencia = trim((string) ($data['referencia'] ?? ''));
        if ($referencia === '') {
            throw ValidationException::withMessages([
                'referencia' => 'La referencia del pago es obligatoria.',
            ]);
        }

        $banco = trim((string) ($data['banco'] ?? ''));
        if ($banco === '') {
            throw ValidationException::withMessages([
                'banco' => 'El banco es obligatorio para pagos por '.$metodoPago.'.',
            ]);
        }

        $fecha = $data['fecha'] ?? now()->toDateString();

        $duplicada = CashMovement::query()
            ->where('referencia', $referencia)
            ->whereNotIn('estado', ['anulado', 'rechazado'])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'referencia' => 'La referencia ya esta registrada en otro pago activo.',
            ]);
        }

        $cuota->loadMissing('venta');

        $movement = $this->create([
            'user_id' => $user->id,
            'cliente_id' => $cuota->venta->cliente_id,
            'sale_id' => $cuota->venta_id,
            'installment_id' => $cuota->id,
            'tipo' => 'ingreso',
            'concepto' => 'cuota',
            'metodo_pago' => $metodoPago,
            'monto' => $monto,
            'fecha' => $fecha,
            'banco' => $banco,
            'referencia' => $referencia,
            'estado' => 'pendiente_verificacion',
        ]);

        $this->auditService->log($movement, 'pago_solicitado', 'Solicitud de pago registrada ('.$metodoPago.').', null, $movement->fresh()->toArray());

        return $movement;
    }

    public function confirm(CashMovement $movement, User $user): CashMovement
    {
        return DB::transaction(function () use ($movement, $user): CashMovement {
            $movement = CashMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if ($movement->estado !== 'pendiente_verificacion') {
                throw ValidationException::withMessages([
                    'movimiento' => 'Solo un pago pendiente de verificacion puede confirmarse.',
                ]);
            }

            $cuota = $movement->installment_id
                ? Cuota::query()->whereKey($movement->installment_id)->lockForUpdate()->first()
                : null;

            if (! $cuota) {
                throw ValidationException::withMessages([
                    'movimiento' => 'Este pago no esta asociado a una cuota y no puede confirmarse.',
                ]);
            }

            $antes = $movement->toArray();
            $movement->update([
                'estado' => 'confirmado',
                'confirmado_por' => $user->id,
                'confirmado_en' => now(),
            ]);

            $this->allocationService->allocate($movement, $cuota, $user);

            $this->auditService->log($movement, 'pago_confirmado', 'Pago verificado y confirmado.', $antes, $movement->fresh()->toArray());

            return $movement;
        });
    }

    public function reject(CashMovement $movement, string $motivo, User $user): CashMovement
    {
        return DB::transaction(function () use ($movement, $motivo): CashMovement {
            $movement = CashMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if ($movement->estado !== 'pendiente_verificacion') {
                throw ValidationException::withMessages([
                    'movimiento' => 'Solo un pago pendiente de verificacion puede rechazarse.',
                ]);
            }

            $motivo = trim($motivo);
            if ($motivo === '') {
                throw ValidationException::withMessages([
                    'motivo' => 'El motivo de rechazo es obligatorio.',
                ]);
            }

            $antes = $movement->toArray();
            $movement->update([
                'estado' => 'rechazado',
                'motivo_rechazo' => $motivo,
            ]);

            $this->auditService->log($movement, 'pago_rechazado', $motivo, $antes, $movement->fresh()->toArray());

            return $movement;
        });
    }

    public function ingresoCuota(Cuota $cuota, float $monto, string $metodoPago, ?User $user, ?string $referencia = null, string $concepto = 'cuota'): CashMovement
    {
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto a pagar debe ser mayor a cero.',
            ]);
        }

        $cuota->loadMissing('venta');

        $movement = $this->create([
            'user_id' => $user?->id,
            'cliente_id' => $cuota->venta->cliente_id,
            'sale_id' => $cuota->venta_id,
            'installment_id' => $cuota->id,
            'tipo' => 'ingreso',
            'concepto' => $concepto,
            'metodo_pago' => $metodoPago,
            'monto' => $monto,
            'fecha' => now(),
            'referencia' => $referencia,
            'estado' => 'confirmado',
            'confirmado_por' => $user?->id,
            'confirmado_en' => now(),
        ]);

        $this->auditService->log($movement, 'pago_oficina', 'Pago de oficina confirmado de inmediato.', null, $movement->fresh()->toArray());

        return $movement;
    }

    public function create(array $data): CashMovement
    {
        return CashMovement::create($data);
    }

    public function syncInitialSaleMovement(Venta $venta, string $metodoPago, ?User $user, ?string $referencia, string $motivo): array
    {
        $venta->load('cashMovements');
        $initialMovements = $venta->cashMovements
            ->whereNull('installment_id')
            ->whereIn('concepto', ['anticipo', 'contado']);
        $concepto = (int) $venta->numero_cuotas === 0 ? 'contado' : 'anticipo';
        $amount = (int) $venta->numero_cuotas === 0 ? (float) $venta->precio_final : (float) $venta->cuota_inicial;
        $primary = $initialMovements->firstWhere('concepto', $concepto) ?? $initialMovements->first();
        $changes = [];

        if ($amount > 0) {
            if ($primary) {
                $before = $primary->toArray();
                $primary->update([
                    'user_id' => $user?->id,
                    'cliente_id' => $venta->cliente_id,
                    'concepto' => $concepto,
                    'metodo_pago' => $metodoPago,
                    'monto' => $amount,
                    'referencia' => $referencia,
                    'estado' => 'confirmado',
                ]);
                $this->auditService->log($primary, 'movimiento_inicial_venta_actualizado', $motivo, $before, $primary->fresh()->toArray());
                $changes[] = ['accion' => 'actualizado', 'antes' => $before, 'despues' => $primary->fresh()->toArray()];
            } else {
                $primary = $this->ingresoVenta($venta, $amount, $concepto, $metodoPago, $user, $referencia);
                $this->auditService->log($primary, 'movimiento_inicial_venta_creado', $motivo, null, $primary?->toArray());
                $changes[] = ['accion' => 'creado', 'despues' => $primary?->toArray()];
            }
        }

        foreach ($initialMovements->reject(fn (CashMovement $movement): bool => $primary && $movement->id === $primary->id) as $extra) {
            if ($extra->estado !== 'anulado') {
                $before = $extra->toArray();
                $extra->update(['estado' => 'anulado']);
                $this->auditService->log($extra, 'movimiento_inicial_venta_anulado', $motivo, $before, $extra->fresh()->toArray());
                $changes[] = ['accion' => 'anulado', 'antes' => $before, 'despues' => $extra->fresh()->toArray()];
            }
        }

        if ($amount <= 0 && $primary && $primary->estado !== 'anulado') {
            $before = $primary->toArray();
            $primary->update(['estado' => 'anulado']);
            $this->auditService->log($primary, 'movimiento_inicial_venta_anulado', $motivo, $before, $primary->fresh()->toArray());
            $changes[] = ['accion' => 'anulado', 'antes' => $before, 'despues' => $primary->fresh()->toArray()];
        }

        return $changes;
    }

    public function annul(CashMovement $movement, ?string $motivo = null): CashMovement
    {
        return DB::transaction(function () use ($movement, $motivo): CashMovement {
            $movement = CashMovement::whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if ($movement->estado === 'anulado') {
                throw ValidationException::withMessages(['movimiento' => 'El movimiento de caja ya esta anulado.']);
            }

            if (! $motivo) {
                throw ValidationException::withMessages(['motivo' => 'Debes indicar el motivo de anulacion del movimiento de caja.']);
            }

            $before = $movement->toArray();

            $tieneAplicaciones = $movement->pagoAplicaciones()->exists();

            if ($tieneAplicaciones) {
                $this->allocationService->reverse($movement, $motivo);
            } else {
                $cuota = null;
                if ($movement->estado === 'confirmado' && $movement->installment_id) {
                    $cuota = Cuota::whereKey($movement->installment_id)->lockForUpdate()->first();
                }

                if ($cuota && Money::toCents($movement->monto) > Money::toCents($cuota->monto_pagado)) {
                    throw ValidationException::withMessages([
                        'movimiento' => 'El monto del movimiento supera el monto pagado de la cuota y no puede revertirse.',
                    ]);
                }

                if ($cuota) {
                    $this->restoreCuotaAfterAnnulment($cuota, (float) $movement->monto, $motivo);
                }
            }

            $movement->update(['estado' => 'anulado']);

            $this->auditService->log($movement, 'anular_caja', $motivo, $before, $movement->fresh()->toArray());

            return $movement;
        });
    }

    private function restoreCuotaAfterAnnulment(Cuota $cuota, float $amount, string $motivo): void
    {
        $before = $cuota->toArray();

        $montoCuotaCentavos = Money::toCents($cuota->monto);
        $montoPagadoCentavos = Money::toCents($cuota->monto_pagado);
        $montoMovimientoCentavos = Money::toCents($amount);

        $nuevoPagadoCentavos = max(0, $montoPagadoCentavos - $montoMovimientoCentavos);
        $saldoCentavos = max(0, $montoCuotaCentavos - $nuevoPagadoCentavos);

        $cuota->monto_pagado = Money::fromCents($nuevoPagadoCentavos);
        $cuota->saldo_pendiente = Money::fromCents($saldoCentavos);
        $cuota->fecha_pago = $nuevoPagadoCentavos <= 0 ? null : $cuota->fecha_pago;
        $cuota->estado = $this->allocationService->cuotaEstado($cuota);
        $cuota->save();

        $this->auditService->log($cuota, 'cuota_restaurada_por_anulacion', $motivo, $before, $cuota->fresh()->toArray());
    }
}
