<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVentaRequest;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Venta;
use App\Services\CommercialSettingsService;
use App\Services\SaleService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', '');
        $fechaDesde = (string) $request->query('fecha_desde', '');
        $fechaHasta = (string) $request->query('fecha_hasta', '');
        $sort = $this->sort($request);

        $ventas = UrbanizacionContext::ventas(Venta::with('cliente', 'lote.manzano.urbanizacion', 'cuotas'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->whereHas('cliente', function ($clienteQuery) use ($search): void {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('documento', 'like', "%{$search}%")
                            ->orWhere('telefono', 'like', "%{$search}%");
                    })->orWhereHas('lote', function ($loteQuery) use ($search): void {
                        $loteQuery->where('codigo', 'like', "%{$search}%")
                            ->orWhereHas('manzano', fn ($manzanoQuery) => $manzanoQuery->where('codigo', 'like', "%{$search}%"));
                    });
                });
            })
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($fechaDesde !== '', fn ($query) => $query->whereDate('fecha_venta', '>=', $fechaDesde))
            ->when($fechaHasta !== '', fn ($query) => $query->whereDate('fecha_venta', '<=', $fechaHasta))
            ->tap(fn ($query) => $this->applySorting($query, $sort['field'], $sort['direction']))
            ->paginate($perPage)
            ->appends($request->query());

        return view('ventas.index', [
            'ventas' => $ventas,
            'filters' => [
                'q' => $search,
                'estado' => $estado,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Venta $venta): View
    {
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403, 'No tienes acceso a esta urbanizacion');

        $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas.pagoAplicaciones', 'cashMovements', 'descuentoAutorizador', 'reestructuraciones.administrador', 'devoluciones.responsable', 'devoluciones.cashMovements');

        return view('ventas.show', [
            'venta' => $venta,
        ]);
    }

    public function create(Request $request): View
    {
        abort_if($request->user()->hasAnyRole(['vendedor', 'supervisor']), 403, 'Los asesores solo pueden crear reservas. No tienen permiso para registrar ventas.');

        $venta = new Venta(['fecha_venta' => now(), 'numero_cuotas' => 12, 'estado' => 'activa', 'tipo_operacion' => 'credito']);
        if ($request->filled('cliente_id')) {
            $cliente = Cliente::findOrFail($request->integer('cliente_id'));
            abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');
            $venta->cliente_id = $cliente->id;
        }
        if ($request->filled('lote_id')) {
            $venta->lote_id = $request->integer('lote_id');
        }

        return view('ventas.form', $this->formData($venta));
    }

    public function store(StoreVentaRequest $request, SaleService $saleService): RedirectResponse
    {
        abort_if($request->user()->hasAnyRole(['vendedor', 'supervisor']), 403, 'Los asesores solo pueden crear reservas. No tienen permiso para registrar ventas.');

        $lote = Lote::with('manzano')->findOrFail($request->validated('lote_id'));
        $cliente = Cliente::findOrFail($request->validated('cliente_id'));
        abort_unless(UrbanizacionContext::loteBelongsToCurrent($lote), 403, 'No tienes acceso a esta urbanizacion');
        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');

        $venta = $saleService->create($request->validated(), $request->user());

        $access = $venta->getAttribute('client_access_result');
        if (($access['created'] ?? false) === true) {
            session()->flash('client_temporary_credential', [
                'email' => $access['user']->email,
                'password' => $access['temporary_password'],
            ]);
        } elseif (! empty($access['warning'])) {
            session()->flash('warning', $access['warning']);
        }

        return redirect()->route('ventas.index')->with('status', 'Operacion realizada correctamente.');
    }

    public function edit(Request $request, Venta $venta): View
    {
        $this->authorizeEdit($request, $venta);
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403, 'No tienes acceso a esta urbanizacion');

        return view('ventas.form', $this->formData($venta));
    }

    public function update(StoreVentaRequest $request, Venta $venta, SaleService $saleService): RedirectResponse
    {
        $this->authorizeEdit($request, $venta);
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403, 'No tienes acceso a esta urbanizacion');
        $lote = Lote::with('manzano')->findOrFail($request->validated('lote_id'));
        $cliente = Cliente::findOrFail($request->validated('cliente_id'));
        abort_unless(UrbanizacionContext::loteBelongsToCurrent($lote), 403, 'No tienes acceso a esta urbanizacion');
        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');

        $saleService->update($venta, $request->validated(), $request->user(), $request->string('motivo_cambio')->toString());

        return redirect()->route('ventas.index')->with('status', 'Operacion realizada correctamente.');
    }

    public function destroy(Request $request, Venta $venta, SaleService $saleService): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente']), 403, 'No tienes permiso para anular ventas.');
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403, 'No tienes acceso a esta urbanizacion');

        $data = $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $saleService->annul($venta, $request->user(), $data['motivo']);

        return back()->with('status', 'Operacion realizada correctamente.');
    }

    private function formData(Venta $venta): array
    {
        $venta->loadMissing('cashMovements');
        $settings = app(CommercialSettingsService::class);
        $urbanizacionId = UrbanizacionContext::currentId();

        return [
            'venta' => $venta,
            'initialMovement' => $venta->cashMovements
                ->whereNull('installment_id')
                ->whereIn('concepto', ['anticipo', 'contado'])
                ->where('estado', 'confirmado')
                ->first(),
            'clientes' => UrbanizacionContext::clientes(Cliente::query())->orderBy('nombre')->get(),
            'lotes' => UrbanizacionContext::lotes(Lote::with('manzano.urbanizacion', 'reservaActiva.cliente'))
                ->where(fn ($query) => $query->whereIn('estado', ['disponible', 'reservado'])->orWhere('id', $venta->lote_id))
                ->orderBy('codigo')
                ->get(),
            'financingLimits' => [
                'semicontado' => $settings->maxCuotasSemicontado($urbanizacionId),
                'credito' => $settings->maxCuotasCredito($urbanizacionId),
            ],
        ];
    }

    private function authorizeEdit(Request $request, Venta $venta): void
    {
        abort_unless(
            $request->user()->hasRole('administrador') && $request->user()->can('editar ventas'),
            403,
            'No tienes permiso para editar esta venta.'
        );

        abort_if(
            $venta->estado === 'anulada' && ! $request->user()->can('editar ventas anuladas'),
            403,
            'No tienes permiso especial para editar una venta anulada.'
        );
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 50);

        return in_array($perPage, [15, 30, 50, 100], true) ? $perPage : 50;
    }

    private function sort(Request $request): array
    {
        $allowedSorts = ['fecha', 'cliente', 'lote', 'tipo', 'monto', 'estado'];
        $field = $request->query('sort', 'fecha');

        return [
            'field' => in_array($field, $allowedSorts, true) ? $field : 'fecha',
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function applySorting($query, string $field, string $direction): void
    {
        match ($field) {
            'fecha' => $query->orderBy('fecha_venta', $direction),
            'cliente' => $query->orderBy(Cliente::select('nombre')->whereColumn('clientes.id', 'ventas.cliente_id'), $direction),
            'lote' => $query->orderBy(Lote::select('codigo')->whereColumn('lotes.id', 'ventas.lote_id'), $direction),
            'tipo' => $query->orderBy('tipo_operacion', $direction),
            'monto' => $query->orderBy('precio_final_usd', $direction),
            'estado' => $query->orderBy('estado', $direction),
        };
    }
}
