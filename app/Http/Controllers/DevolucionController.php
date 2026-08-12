<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\SaleRescissionService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DevolucionController extends Controller
{
    public function create(Request $request, Venta $venta): View
    {
        $this->authorizeAdmin($request, $venta);
        $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas', 'cashMovements');

        return view('ventas.rescindir', compact('venta'));
    }

    public function store(Request $request, Venta $venta, SaleRescissionService $service): RedirectResponse
    {
        $this->authorizeAdmin($request, $venta);
        $data = $request->validate([
            'monto_devuelto' => ['required', 'numeric', 'min:0'],
            'monto_retenido_empresa' => ['required', 'numeric', 'min:0'],
            'metodo_pago' => ['required', Rule::in(['efectivo', 'transferencia', 'QR', 'banco', 'otro'])],
            'referencia' => ['nullable', 'string', 'max:255'],
            'motivo' => ['required', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'confirmacion' => ['accepted'],
        ]);
        $service->rescind($venta, $data, $request->user());

        return redirect()->route('ventas.show', $venta)->with('status', 'Venta rescindida con trazabilidad financiera.');
    }

    private function authorizeAdmin(Request $request, Venta $venta): void
    {
        abort_unless($request->user()->hasRole('administrador'), 403);
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403);
    }
}
