<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\Devolucion;
use App\Models\Lote;
use App\Models\User;
use App\Models\Venta;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleRescissionService
{
    public function __construct(private AuditService $auditService, private LotService $lotService) {}

    public function rescind(Venta $venta, array $data, User $user): Devolucion
    {
        abort_unless($user->hasRole('administrador'), 403, 'Solo un administrador puede rescindir una venta.');

        return DB::transaction(function () use ($venta, $data, $user): Devolucion {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);
            $lote = Lote::lockForUpdate()->findOrFail($venta->lote_id);
            if ($venta->estado === 'anulada' || $venta->devoluciones()->exists()) {
                throw ValidationException::withMessages(['venta' => 'La venta ya fue anulada o rescindida.']);
            }
            $pagado = CashMovement::where('sale_id', $venta->id)->where('tipo', 'ingreso')->where('estado', 'confirmado')->sum('monto');
            $pagadoCents = Money::toCents($pagado);
            $devueltoCents = Money::toCents($data['monto_devuelto'] ?? 0);
            $retenidoCents = Money::toCents($data['monto_retenido_empresa'] ?? 0);
            if ($devueltoCents < 0 || $retenidoCents < 0 || $devueltoCents + $retenidoCents > $pagadoCents) {
                throw ValidationException::withMessages(['monto_devuelto' => 'El monto devuelto mas el retenido no puede superar el total pagado.']);
            }

            $before = $venta->load('cuotas', 'cashMovements.pagoAplicaciones')->toArray();
            Cuota::where('venta_id', $venta->id)->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->lockForUpdate()->get()->each(function (Cuota $cuota): void {
                $cuota->update(['saldo_pendiente' => '0.00', 'estado' => 'anulada', 'observaciones' => trim(($cuota->observaciones ? $cuota->observaciones."\n" : '').'Anulada por rescision de venta.')]);
            });
            $devolucion = Devolucion::create([
                'venta_id' => $venta->id, 'cliente_id' => $venta->cliente_id,
                'monto_pagado' => Money::fromCents($pagadoCents), 'monto_devuelto' => Money::fromCents($devueltoCents),
                'monto_retenido_empresa' => Money::fromCents($retenidoCents), 'motivo' => $data['motivo'],
                'observaciones' => $data['observaciones'] ?? null, 'responsable_id' => $user->id,
                'fecha' => now()->toDateString(), 'estado' => 'confirmada',
            ]);
            if ($devueltoCents > 0) {
                CashMovement::create([
                    'user_id' => $user->id, 'cliente_id' => $venta->cliente_id, 'sale_id' => $venta->id,
                    'devolucion_id' => $devolucion->id, 'tipo' => 'egreso', 'concepto' => 'devolucion',
                    'metodo_pago' => $data['metodo_pago'], 'monto' => Money::fromCents($devueltoCents),
                    'fecha' => now()->toDateString(), 'referencia' => $data['referencia'] ?? null,
                    'observaciones' => $data['observaciones'] ?? null, 'estado' => 'confirmado',
                    'confirmado_por' => $user->id, 'confirmado_en' => now(),
                ]);
            }
            $venta->update(['estado' => 'anulada', 'saldo_financiar' => '0.00']);
            $this->lotService->syncStatusFromReservations($lote, 'venta_rescindida', $user, 'Venta rescindida; lote sincronizado con operaciones vigentes.');
            $this->auditService->log($venta, 'venta_rescindida', $data['motivo'], $before, $devolucion->load('cashMovements')->toArray());

            return $devolucion;
        });
    }
}
