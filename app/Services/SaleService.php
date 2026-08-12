<?php

namespace App\Services;

use App\Models\Lote;
use App\Models\User;
use App\Models\Venta;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private LotService $lotService,
        private InstallmentService $installmentService,
        private CashMovementService $cashMovementService,
        private AuditService $auditService,
        private LotPricingService $pricingService,
        private CommercialSettingsService $commercialSettings
    ) {}

    public function create(array $data, ?User $user): Venta
    {
        return DB::transaction(function () use ($data, $user): Venta {
            $lote = Lote::with('reservaActiva')->lockForUpdate()->findOrFail($data['lote_id']);
            $this->lotService->ensureCanSell($lote, (int) $data['cliente_id'], (bool) ($data['admin_confirma_reserva'] ?? false));

            $reserva = $lote->reservaActiva;
            if ($reserva && $reserva->cliente_id === (int) $data['cliente_id']) {
                $data['reserva_id'] = $reserva->id;
            }

            $data = $this->applyCommercialPricing($data, $lote, true, $user);
            $data = $this->normalizeFinancialTerms($data, $lote);
            $venta = Venta::create([
                ...$data,
                'user_id' => $user?->id,
            ]);
            $this->auditService->log($venta, 'crear_venta', 'Venta registrada.', null, $venta->toArray());

            if ($reserva && $venta->reserva_id === $reserva->id) {
                $reserva->update(['estado' => 'convertida']);
                $this->auditService->log($reserva, 'convertir_reserva', 'La reserva fue convertida en venta.', null, $reserva->fresh()->toArray());
            }

            $this->lotService->changeStatus($lote, 'vendido', 'lote_vendido', $user, 'Lote vendido al cliente #'.$venta->cliente_id);

            if ((int) $venta->numero_cuotas === 0) {
                $this->cashMovementService->ingresoVenta($venta, (float) $venta->precio_final, 'contado', $data['metodo_pago'] ?? 'efectivo', $user, $data['referencia'] ?? null);
            } else {
                $this->cashMovementService->ingresoVenta($venta, (float) $venta->cuota_inicial, 'anticipo', $data['metodo_pago'] ?? 'efectivo', $user, $data['referencia'] ?? null);
                $this->installmentService->generateForSale($venta);
            }

            return $venta;
        });
    }

    public function annul(Venta $venta, ?User $user, ?string $motivo = null): Venta
    {
        return DB::transaction(function () use ($venta, $user, $motivo): Venta {
            if (! $motivo) {
                throw ValidationException::withMessages(['motivo' => 'Debes indicar el motivo para anular la venta.']);
            }

            if ($venta->cuotas()->exists() || $venta->cashMovements()->exists()) {
                throw ValidationException::withMessages(['venta' => 'No se puede anular una venta con cuotas o movimientos de caja asociados.']);
            }

            $before = $venta->toArray();
            $venta->update(['estado' => 'anulada']);
            $this->lotService->changeStatus($venta->lote, 'disponible', 'venta_anulada', $user, 'Venta anulada.');
            $this->auditService->log($venta, 'anular_venta', $motivo, $before, $venta->fresh()->toArray());

            return $venta;
        });
    }

    public function update(Venta $venta, array $data, ?User $user, string $motivo): Venta
    {
        return DB::transaction(function () use ($venta, $data, $user, $motivo): Venta {
            if (trim($motivo) === '') {
                throw ValidationException::withMessages(['motivo_cambio' => 'Debes explicar el motivo del cambio de esta venta.']);
            }

            $venta = Venta::with('cuotas.cashMovements', 'cashMovements', 'lote')->lockForUpdate()->findOrFail($venta->id);
            $before = $venta->toArray();
            $before['cuotas_antes'] = $venta->cuotas->map(fn ($cuota): array => $cuota->toArray())->all();
            $oldLot = $venta->lote;
            $newLot = Lote::with('reservaActiva')->lockForUpdate()->findOrFail($data['lote_id']);

            if ($venta->estado !== $data['estado'] && ($venta->estado === 'anulada' || $data['estado'] === 'anulada')) {
                throw ValidationException::withMessages([
                    'estado' => 'La anulacion de una venta debe realizarse mediante la accion Anular.',
                ]);
            }

            $installmentFields = ['tipo_operacion', 'precio_final', 'cuota_inicial', 'numero_cuotas', 'fecha_venta', 'fecha_primer_vencimiento'];
            $changesInstallmentStructure = collect($installmentFields)->contains(
                fn (string $field): bool => (string) $venta->{$field} !== (string) ($data[$field] ?? $venta->{$field})
            );

            if ($newLot->id !== $oldLot->id) {
                $this->lotService->ensureCanSell($newLot, (int) $data['cliente_id'], (bool) ($data['admin_confirma_reserva'] ?? false));
            }

            $data = $this->applyCommercialPricing($data, $newLot, false, $user);
            $data = $this->normalizeFinancialTerms($data, $newLot);
            $venta->update(collect($data)->except(['metodo_pago', 'referencia', 'admin_confirma_reserva', 'motivo_cambio'])->all());
            $installmentChanges = $changesInstallmentStructure
                ? $this->installmentService->resyncForSale($venta->fresh())
                : [
                    'cuotas_conservadas' => [],
                    'cuotas_eliminadas' => [],
                    'cuotas_creadas' => [],
                    'total_pagado_cuotas' => (float) $venta->cuotas->sum('monto_pagado'),
                    'saldo_financiar' => (int) $venta->numero_cuotas === 0
                        ? 0
                        : max(0, (float) $venta->precio_final - (float) $venta->cuota_inicial - (float) $venta->cuotas->sum('monto_pagado')),
                ];
            $venta->update(['saldo_financiar' => $installmentChanges['saldo_financiar']]);
            $cashChanges = $this->cashMovementService->syncInitialSaleMovement(
                $venta->fresh(),
                $data['metodo_pago'] ?? 'efectivo',
                $user,
                $data['referencia'] ?? null,
                $motivo
            );

            if ($oldLot->id !== $newLot->id) {
                $this->lotService->syncStatusFromReservations($oldLot, 'venta_actualizada', $user, 'La venta fue trasladada a otro lote.');
            }

            if ($venta->estado === 'anulada') {
                $this->lotService->syncStatusFromReservations($newLot, 'venta_actualizada', $user, 'La venta fue marcada como anulada.');
            } else {
                $this->lotService->changeStatus($newLot, 'vendido', 'venta_actualizada', $user, 'Lote asociado a venta actualizada.');
            }

            $after = $venta->fresh()->toArray();
            $after['motivo_cambio'] = $motivo;
            $after['venta_id'] = $venta->id;
            $after['cuotas_conservadas'] = $installmentChanges['cuotas_conservadas'];
            $after['cuotas_eliminadas'] = $installmentChanges['cuotas_eliminadas'];
            $after['cuotas_creadas'] = $installmentChanges['cuotas_creadas'];
            $after['movimientos_caja_actualizados'] = $cashChanges;
            $this->auditService->log($venta, 'venta_actualizada', $motivo, $before, $after);

            return $venta->fresh();
        });
    }

    private function applyCommercialPricing(array $data, Lote $lote, bool $forceAmounts, ?User $user): array
    {
        $tipoOperacion = $data['tipo_operacion'] ?? ((int) ($data['numero_cuotas'] ?? 0) > 0 ? 'credito' : 'contado');
        $payload = $this->pricingService->payload($lote);
        $operationUsd = $this->pricingService->operationUsd($lote, $tipoOperacion);
        $initialUsd = $this->pricingService->initialUsd($lote, $operationUsd);

        $descuento = array_key_exists('descuento', $data) ? (float) $data['descuento'] : 0.0;

        if ($descuento < 0) {
            throw ValidationException::withMessages(['descuento' => 'El descuento no puede ser negativo.']);
        }

        if ($descuento > 0) {
            $autorizado = $user && ($user->hasRole('gerente') || $user->hasRole('administrador'));

            if (! $autorizado) {
                throw ValidationException::withMessages([
                    'descuento' => 'Solo un gerente o administrador puede autorizar descuentos en una venta.',
                ]);
            }

            $data['descuento_autorizado_por'] = $user->id;
            $data['descuento_autorizado_en'] = now();
        }

        if ($forceAmounts) {
            $precioFinal = round($operationUsd - $descuento, 2);

            if ($precioFinal < 0) {
                throw ValidationException::withMessages([
                    'descuento' => 'El descuento no puede superar el precio de la operacion.',
                ]);
            }

            $data['descuento'] = $descuento;
            $data['precio_final'] = $precioFinal;
            $data['cuota_inicial'] = array_key_exists('cuota_inicial', $data)
                ? (float) $data['cuota_inicial']
                : $initialUsd;
        } elseif (array_key_exists('descuento', $data)) {
            $data['descuento'] = $descuento;

            if ((float) ($data['precio_final'] ?? $operationUsd) < 0) {
                throw ValidationException::withMessages([
                    'precio_final' => 'El precio final no puede ser negativo.',
                ]);
            }

            if ($descuento == 0) {
                $data['descuento_autorizado_por'] = null;
                $data['descuento_autorizado_en'] = null;
            }
        }

        $data['tipo_operacion'] = $tipoOperacion;
        $data['precio_base_usd'] = $payload['base_usd'];
        $data['incremento_credito_tipo'] = $payload['incremento_credito_tipo'];
        $data['incremento_credito_valor'] = $payload['incremento_credito_valor'];
        $data['incremento_credito_aplicado'] = $tipoOperacion === 'credito' ? $payload['credit_increment_usd'] : 0;
        $data['precio_final_usd'] = (float) ($data['precio_final'] ?? $operationUsd);
        $data['precio_final_bs'] = $this->pricingService->bs((float) $data['precio_final_usd'], $lote);
        $data['tipo_cambio_usd_bs'] = $payload['tipo_cambio_usd_bs'];

        return $data;
    }

    private function normalizeFinancialTerms(array $data, Lote $lote): array
    {
        $tipo = (string) ($data['tipo_operacion'] ?? 'contado');

        if ($tipo === 'contado') {
            $data['cuota_inicial'] = 0;
            $data['numero_cuotas'] = 0;
            $data['fecha_primer_vencimiento'] = null;
            $data['saldo_financiar'] = 0;

            return $data;
        }

        if (! in_array($tipo, ['semicontado', 'credito'], true)) {
            throw ValidationException::withMessages(['tipo_operacion' => 'La modalidad financiera no es válida.']);
        }

        $precioCents = Money::toCents($data['precio_final'] ?? 0);
        $inicialCents = Money::toCents($data['cuota_inicial'] ?? 0);
        $numeroCuotas = (int) ($data['numero_cuotas'] ?? 0);
        $urbanizacionId = $lote->manzano?->urbanizacion_id ?? $lote->manzano()->value('urbanizacion_id');
        $maximo = $tipo === 'semicontado'
            ? $this->commercialSettings->maxCuotasSemicontado($urbanizacionId)
            : $this->commercialSettings->maxCuotasCredito($urbanizacionId);

        if ($inicialCents < 0 || $inicialCents > $precioCents) {
            throw ValidationException::withMessages(['cuota_inicial' => 'La cuota inicial no puede superar el precio final pactado.']);
        }
        if ($precioCents - $inicialCents <= 0) {
            throw ValidationException::withMessages(['cuota_inicial' => 'La modalidad financiada debe dejar un saldo mayor a cero.']);
        }
        if ($numeroCuotas < 1 || $numeroCuotas > $maximo) {
            throw ValidationException::withMessages(['numero_cuotas' => "El plazo debe estar entre 1 y {$maximo} cuotas para {$tipo}."]);
        }
        if (empty($data['fecha_primer_vencimiento'])) {
            throw ValidationException::withMessages(['fecha_primer_vencimiento' => 'La primera fecha de vencimiento es obligatoria para ventas financiadas.']);
        }

        $data['cuota_inicial'] = Money::fromCents($inicialCents);
        $data['saldo_financiar'] = Money::fromCents($precioCents - $inicialCents);

        return $data;
    }
}
