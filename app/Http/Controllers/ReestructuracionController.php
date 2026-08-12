<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\DebtRestructuringService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReestructuracionController extends Controller
{
    public function create(Request $request, Venta $venta): View
    {
        $this->authorizeAdmin($request, $venta);
        $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas');

        return view('ventas.reestructurar', compact('venta'));
    }

    public function store(Request $request, Venta $venta, DebtRestructuringService $service): RedirectResponse
    {
        $this->authorizeAdmin($request, $venta);
        $data = $request->validate([
            'nuevo_plazo' => ['required', 'integer', 'min:1'],
            'fecha_primer_vencimiento' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->restructure($venta, $data, $request->user());

        return redirect()->route('ventas.show', $venta)->with('status', 'Deuda reestructurada correctamente.');
    }

    private function authorizeAdmin(Request $request, Venta $venta): void
    {
        abort_unless($request->user()->hasRole('administrador'), 403);
        abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403);
    }
}
