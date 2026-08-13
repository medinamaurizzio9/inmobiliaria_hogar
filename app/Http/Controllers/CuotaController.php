<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayCuotaRequest;
use App\Models\Cuota;
use App\Models\User;
use App\Services\InstallmentService;
use App\Support\UrbanizacionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuotaController extends Controller
{
    public function index(Request $request, InstallmentService $installmentService): View
    {
        $installmentService->markOverdue();

        $filters = $request->only(['q', 'estado', 'fecha_desde', 'fecha_hasta']);
        $query = UrbanizacionContext::cuotas(Cuota::with('venta.cliente', 'venta.lote.manzano', 'venta.cuotas'))->orderBy('fecha_programada')
            ->when(($filters['q'] ?? '') !== '', fn (Builder $q) => $q->whereHas('venta', fn (Builder $v) => $v->whereHas('cliente', fn (Builder $c) => $c->where('nombre', 'like', '%'.$filters['q'].'%')->orWhere('documento', 'like', '%'.$filters['q'].'%'))->orWhereHas('lote', fn (Builder $l) => $l->where('codigo', 'like', '%'.$filters['q'].'%')->orWhereHas('manzano', fn (Builder $m) => $m->where('codigo', 'like', '%'.$filters['q'].'%')))))
            ->when(($filters['estado'] ?? '') !== '', fn (Builder $q) => $q->where('estado', $filters['estado'] === 'vencidas' ? 'vencida' : $filters['estado']))
            ->when(($filters['fecha_desde'] ?? '') !== '', fn (Builder $q) => $q->whereDate('fecha_programada', '>=', $filters['fecha_desde']))
            ->when(($filters['fecha_hasta'] ?? '') !== '', fn (Builder $q) => $q->whereDate('fecha_programada', '<=', $filters['fecha_hasta']));
        $summaryQuery = clone $query;

        return view('cuotas.index', ['cuotas' => $query->paginate(25)->withQueryString(), 'filters' => $filters, 'summary' => [
            'pendientes' => (clone $summaryQuery)->whereIn('estado', ['pendiente', 'parcial'])->count(),
            'vencidas' => (clone $summaryQuery)->where('estado', 'vencida')->count(),
            'pagadas_mes' => (clone $summaryQuery)->where('estado', 'pagada')->whereBetween('fecha_pago', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'saldo' => (clone $summaryQuery)->sum('saldo_pendiente'),
        ]]);
    }

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
