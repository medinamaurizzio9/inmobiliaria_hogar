<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\User;
use App\Models\Venta;
use App\Services\CashMovementService;
use App\Services\FinancialSettingsService;
use App\Services\PaymentAllocationService;
use App\Services\QuickCollectionService;
use App\Support\UrbanizacionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CobranzaController extends Controller
{
    public function index(Request $request, FinancialSettingsService $settings): View
    {
        $this->authorizeAccess($request);
        $filters = $request->only(['q', 'estado', 'vencimiento_desde', 'vencimiento_hasta', 'asesor_id']);
        $search = trim((string) ($filters['q'] ?? ''));
        $query = UrbanizacionContext::ventas(Venta::with(['cliente', 'lote.manzano.urbanizacion', 'cuotas' => fn ($q) => $q->orderBy('numero'), 'cashMovements' => fn ($q) => $q->latest()->limit(8)]), UrbanizacionContext::currentId())
            ->whereHas('cuotas', fn (Builder $q) => $q->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('saldo_pendiente', '>', 0))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereKey(is_numeric($search) ? (int) $search : 0)
                        ->orWhereHas('cliente', fn (Builder $q) => $q->where('nombre', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%")->orWhere('telefono', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('lote', fn (Builder $q) => $q->where('codigo', 'like', "%{$search}%")->orWhereHas('manzano', fn (Builder $m) => $m->where('codigo', 'like', "%{$search}%")->orWhereHas('urbanizacion', fn (Builder $u) => $u->where('nombre', 'like', "%{$search}%"))))
                        ->orWhereHas('cashMovements', fn (Builder $q) => $q->where('referencia', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['asesor_id'] ?? '') !== '', fn (Builder $q) => $q->where('user_id', (int) $filters['asesor_id']))
            ->when(($filters['vencimiento_desde'] ?? '') !== '', fn (Builder $q) => $q->whereHas('cuotas', fn (Builder $c) => $c->whereDate('fecha_vencimiento', '>=', $filters['vencimiento_desde'])->where('saldo_pendiente', '>', 0)))
            ->when(($filters['vencimiento_hasta'] ?? '') !== '', fn (Builder $q) => $q->whereHas('cuotas', fn (Builder $c) => $c->whereDate('fecha_vencimiento', '<=', $filters['vencimiento_hasta'])->where('saldo_pendiente', '>', 0)))
            ->when(($filters['estado'] ?? '') === 'vencido', fn (Builder $q) => $q->whereHas('cuotas', fn (Builder $c) => $c->where('estado', 'vencida')->where('saldo_pendiente', '>', 0)))
            ->when(($filters['estado'] ?? '') === 'por_vencer', fn (Builder $q) => $q->whereHas('cuotas', fn (Builder $c) => $c->whereIn('estado', ['pendiente', 'parcial'])->whereBetween('fecha_vencimiento', [today(), today()->addDays(7)])->where('saldo_pendiente', '>', 0)))
            ->when(($filters['estado'] ?? '') === 'al_dia', fn (Builder $q) => $q->whereDoesntHave('cuotas', fn (Builder $c) => $c->where('estado', 'vencida')->where('saldo_pendiente', '>', 0)));

        $ventas = $query->orderByRaw("case when exists (select 1 from cuotas where cuotas.venta_id = ventas.id and cuotas.estado = 'vencida' and cuotas.saldo_pendiente > 0) then 0 else 1 end")
            ->orderBy('id')->paginate(20)->withQueryString();

        $today = UrbanizacionContext::cashMovements(CashMovement::query(), UrbanizacionContext::currentId())->whereDate('fecha', today());
        $confirmed = (clone $today)->where('estado', 'confirmado')->where('tipo', 'ingreso');
        $pending = UrbanizacionContext::cashMovements(CashMovement::with('cliente', 'venta.lote.manzano.urbanizacion', 'cuota.venta.lote.manzano.urbanizacion', 'user'), UrbanizacionContext::currentId())->where('estado', 'pendiente_verificacion')->latest()->limit(50)->get();
        $resultId = (int) session('quick_payment_result', 0);
        $result = $resultId ? UrbanizacionContext::cashMovements(CashMovement::with('venta.lote.manzano.urbanizacion', 'pagoAplicaciones.cuota'), UrbanizacionContext::currentId())->find($resultId) : null;

        $asesorIds = UrbanizacionContext::ventas(Venta::query(), UrbanizacionContext::currentId())->distinct()->pluck('user_id');

        return view('cobranza.index', ['ventas' => $ventas, 'search' => $search, 'filters' => $filters, 'asesores' => User::query()->whereIn('id', $asesorIds)->orderBy('name')->get(), 'pending' => $pending, 'result' => $result, 'instructions' => $settings->paymentInstructions(), 'summary' => [
            'total' => (clone $confirmed)->sum('monto'), 'efectivo' => (clone $confirmed)->where('metodo_pago', 'efectivo')->sum('monto'),
            'qr' => (clone $confirmed)->where('metodo_pago', 'QR')->sum('monto'), 'transferencia' => (clone $confirmed)->where('metodo_pago', 'transferencia')->sum('monto'),
            'operaciones' => (clone $confirmed)->count(), 'pendientes' => $pending->count(),
        ]]);
    }

    public function preview(Request $request, Venta $venta, PaymentAllocationService $allocation): JsonResponse
    {
        $this->authorizeSale($request, $venta);
        $data = $request->validate(['cuota_id' => ['required', 'integer'], 'monto' => ['required', 'numeric', 'gt:0'], 'tipo_aplicacion' => ['required', Rule::in(['cuotas', 'amortizacion'])]]);
        $cuota = Cuota::whereKey($data['cuota_id'])->where('venta_id', $venta->id)->firstOrFail();

        return response()->json($allocation->preview($cuota, $data['monto'], $data['tipo_aplicacion']));
    }

    public function store(Request $request, Venta $venta, QuickCollectionService $service): RedirectResponse
    {
        $this->authorizeSale($request, $venta);
        $data = $request->validate(['cuota_id' => ['required', 'integer'], 'monto' => ['required', 'numeric', 'gt:0'], 'metodo_pago' => ['required', Rule::in(['efectivo', 'QR', 'transferencia'])], 'verificacion' => ['nullable', Rule::in(['confirmado', 'pendiente_verificacion'])], 'tipo_aplicacion' => ['required', Rule::in(['cuotas', 'amortizacion'])], 'banco' => ['nullable', 'string', 'max:255'], 'referencia' => ['nullable', 'string', 'max:255'], 'fecha' => ['nullable', 'date']]);
        if ($data['metodo_pago'] !== 'efectivo') {
            validator($data, ['banco' => ['required'], 'referencia' => ['required'], 'verificacion' => ['required']])->validate();
        }
        $cuota = Cuota::whereKey($data['cuota_id'])->where('venta_id', $venta->id)->firstOrFail();
        $movement = $service->collect($cuota, $data, $request->user());

        return redirect()->route('cobranza.index', ['q' => $venta->id])->with('quick_payment_result', $movement->id);
    }

    public function confirm(Request $request, CashMovement $cashMovement, PaymentAllocationService $allocation, CashMovementService $service): RedirectResponse
    {
        $this->authorizeMovement($request, $cashMovement);
        abort_unless($cashMovement->estado === 'pendiente_verificacion' && $cashMovement->cuota, 422);
        $allocation->preview($cashMovement->cuota, $cashMovement->monto, $cashMovement->concepto === 'amortizacion' ? 'amortizacion' : 'cuotas');
        $service->confirm($cashMovement, $request->user());

        return redirect()->route('cobranza.index', ['q' => $cashMovement->sale_id])->with('quick_payment_result', $cashMovement->id);
    }

    public function reject(Request $request, CashMovement $cashMovement, CashMovementService $service): RedirectResponse
    {
        $this->authorizeMovement($request, $cashMovement);
        $data = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $service->reject($cashMovement, $data['motivo'], $request->user());

        return back()->with('status', 'Pago rechazado.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente', 'cajero']), 403);
    }

    private function authorizeSale(Request $request, Venta $venta): void
    {
        $this->authorizeAccess($request);
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403);
    }

    private function authorizeMovement(Request $request, CashMovement $movement): void
    {
        $this->authorizeAccess($request);
        abort_unless(UrbanizacionContext::cashMovementBelongsToCurrent($movement), 403);
    }
}
