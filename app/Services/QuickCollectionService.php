<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuickCollectionService
{
    public function __construct(private CashMovementService $cash, private PaymentAllocationService $allocation) {}

    public function collect(Cuota $cuota, array $data, User $user): CashMovement
    {
        return DB::transaction(function () use ($cuota, $data, $user): CashMovement {
            $cuota = Cuota::whereKey($cuota->id)->lockForUpdate()->firstOrFail();
            $type = $data['tipo_aplicacion'] ?? 'cuotas';
            $this->allocation->preview($cuota, $data['monto'], $type);
            $verified = $data['metodo_pago'] === 'efectivo' || ($data['verificacion'] ?? '') === 'confirmado';
            if (! $verified) {
                return $this->cash->solicitarPagoCuota($cuota, (float) $data['monto'], $data['metodo_pago'], $user, $data);
            }

            $movement = $this->cash->ingresoCuota($cuota, (float) $data['monto'], $data['metodo_pago'], $user, $data['referencia'] ?? null, $type === 'amortizacion' ? 'amortizacion' : 'cuota');
            $type === 'amortizacion'
                ? $this->allocation->amortize($movement, $cuota, $user)
                : $this->allocation->allocate($movement, $cuota, $user);

            return $movement->fresh('pagoAplicaciones.cuota');
        });
    }
}
