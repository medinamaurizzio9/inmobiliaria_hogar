<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\User;
use App\Services\CashMovementService;
use App\Support\UrbanizacionContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashMovementController extends Controller
{
    private const FILTROS = [
        'q', 'cliente', 'documento', 'referencia', 'lote', 'modalidad',
        'tipo', 'concepto', 'metodo_pago', 'estado',
        'fecha_desde', 'fecha_hasta', 'monto_min', 'monto_max', 'usuario_id',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente', 'cajero']), 403, 'No tienes permiso para ver Caja.');

        $perPage = $this->perPage($request);
        $filtros = $request->only(self::FILTROS);
        $urbanizacionId = UrbanizacionContext::filtroUrbanizacion($request->user(), $request->query('urbanizacion_id', ''));

        $movimientos = UrbanizacionContext::cashMovements(
            CashMovement::with('cliente', 'venta', 'reserva', 'cuota', 'user'),
            $urbanizacionId
        )
            ->filtered($filtros)
            ->latest()
            ->paginate($perPage)
            ->appends($request->query());

        return view('caja.index', [
            'movimientos' => $movimientos,
            'filters' => [
                ...$filtros,
                'urbanizacion_id' => $urbanizacionId ? (string) $urbanizacionId : '',
                'per_page' => $perPage,
            ],
            'urbanizaciones' => UrbanizacionContext::accessibleUrbanizaciones($request->user()),
            'usuarios' => $this->usuariosCaja($urbanizacionId),
        ]);
    }

    public function annul(Request $request, CashMovement $cashMovement, CashMovementService $cashMovementService): RedirectResponse
    {
        abort_unless(UrbanizacionContext::cashMovementBelongsToCurrent($cashMovement), 403, 'No tienes acceso a esta urbanizacion');

        $data = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $cashMovementService->annul($cashMovement, $data['motivo']);

        return back()->with('status', 'Movimiento de caja anulado.');
    }

    public function show(Request $request, CashMovement $cashMovement): View
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente', 'cajero']), 403, 'No tienes permiso para ver Caja.');
        abort_unless(UrbanizacionContext::cashMovementBelongsToCurrent($cashMovement), 403, 'No tienes acceso a esta urbanizacion');

        $cashMovement->load([
            'cliente',
            'user',
            'confirmador',
            'reserva.lote.manzano.urbanizacion',
            'venta.lote.manzano.urbanizacion',
            'cuota.venta.lote.manzano.urbanizacion',
            'pagoAplicaciones.cuota',
        ]);

        return view('caja.show', ['movimiento' => $cashMovement]);
    }

    public function confirm(Request $request, CashMovement $cashMovement, CashMovementService $cashMovementService): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente']), 403, 'No tienes permiso para confirmar pagos.');
        abort_unless(UrbanizacionContext::cashMovementBelongsToCurrent($cashMovement), 403, 'No tienes acceso a esta urbanizacion');

        $cashMovementService->confirm($cashMovement, $request->user());

        return redirect()->route('caja.index', $request->query())->with('status', 'Pago verificado y confirmado.');
    }

    public function reject(Request $request, CashMovement $cashMovement, CashMovementService $cashMovementService): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente']), 403, 'No tienes permiso para rechazar pagos.');
        abort_unless(UrbanizacionContext::cashMovementBelongsToCurrent($cashMovement), 403, 'No tienes acceso a esta urbanizacion');

        $data = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $cashMovementService->reject($cashMovement, $data['motivo'], $request->user());

        return redirect()->route('caja.index', $request->query())->with('status', 'Pago rechazado.');
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 15);

        return in_array($perPage, [15, 30, 50, 100], true) ? $perPage : 15;
    }

    private function usuariosCaja(?int $urbanizacionId): Collection
    {
        $ids = UrbanizacionContext::cashMovements(CashMovement::query(), $urbanizacionId)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get();
    }
}
