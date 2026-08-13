<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayCuotaRequest;
use App\Models\Cuota;
use App\Models\User;
use App\Services\InstallmentService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;

class CuotaController extends Controller
{
    public function update(PayCuotaRequest $request, Cuota $cuota, InstallmentService $installmentService): RedirectResponse
    {
        abort_unless(UrbanizacionContext::cuotaBelongsToCurrent($cuota), 403, 'No tienes acceso a esta urbanizacion');

        $this->authorizeManualModification($request->user(), $cuota);

        $arguments = [
            $cuota,
            (float) $request->validated('monto_pagado'),
            $request->validated('metodo_pago'),
            $request->user(),
            $request->validated('referencia'),
        ];

        if ($request->validated('tipo_aplicacion') === 'amortizacion') {
            $installmentService->amortize(...$arguments);

            return back()->with('status', 'Amortizacion extraordinaria registrada; se redujo el plazo pendiente.');
        }

        $installmentService->pay(...$arguments);

        return back()->with('status', 'Pago registrado y movimiento de caja generado.');
    }

    private function authorizeManualModification(User $user, Cuota $cuota): void
    {
        $hasPayments = (float) $cuota->monto_pagado > 0 || $cuota->estado === 'pagada';

        if (! $hasPayments) {
            return;
        }

        abort_unless($user->can('modificar cuotas'), 403, 'Solo un administrador puede modificar cuotas con pagos o pagadas.');
    }
}
