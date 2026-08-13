<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use App\Models\Urbanizacion;
use App\Models\Venta;
use App\Services\CashMovementService;
use App\Services\FinancialSettingsService;
use App\Services\PaymentAlertService;
use App\Services\SystemSettingsService;
use App\Support\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MiCuentaController extends Controller
{
    public function index(Request $request, PaymentAlertService $alerts, SystemSettingsService $settings): View
    {
        $cliente = $this->cliente($request);
        $cliente->load(['ventas' => fn ($query) => $query->where('estado', '!=', 'anulada')->with('lote.manzano.urbanizacion', 'cuotas', 'cashMovements')]);
        $urbanizaciones = $this->activeUrbanizations();
        $pendientes = $cliente->cashMovements()->where('estado', 'pendiente_verificacion')->count();
        $whatsappPhone = $settings->get('whatsapp') ?: $settings->get('celular');
        $helpWhatsappUrl = WhatsAppLink::urlWithMessage($whatsappPhone, "Hola, soy {$cliente->nombre}. Necesito ayuda con mi cuenta en ".($settings->get('system_name') ?: $settings->get('company_name')).'.');

        return view('clientes.mi-cuenta', compact('cliente', 'urbanizaciones', 'pendientes', 'whatsappPhone', 'helpWhatsappUrl') + ['alertas' => $alerts->forCliente($cliente)]);
    }

    public function urbanizaciones(Request $request, SystemSettingsService $settings): View
    {
        return $this->catalog($request, $settings, false);
    }

    public function profile(Request $request): View
    {
        return view('clientes.perfil', ['cliente' => $this->cliente($request)]);
    }

    public function visitas(Request $request, SystemSettingsService $settings): View
    {
        return $this->catalog($request, $settings, true);
    }

    public function show(Request $request, Venta $venta): View
    {
        $this->own($request, $venta);
        $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas.pagoAplicaciones.cashMovement', 'cashMovements');

        return view('clientes.terreno', compact('venta'));
    }

    public function pay(Request $request, Venta $venta, FinancialSettingsService $settings): View
    {
        $this->own($request, $venta);
        $venta->load('lote.manzano.urbanizacion', 'cuotas');
        $cuotas = $venta->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('saldo_pendiente', '>', 0)->sortBy('fecha_vencimiento');
        abort_if($cuotas->isEmpty(), 422, 'Este terreno no tiene deuda activa.');

        return view('clientes.pagar', ['venta' => $venta, 'cuotas' => $cuotas, 'instructions' => $settings->paymentInstructions()]);
    }

    public function storePayment(Request $request, Venta $venta, CashMovementService $service, FinancialSettingsService $settings): RedirectResponse
    {
        $this->own($request, $venta);
        $data = $request->validate([
            'cuota_id' => ['required', 'integer'], 'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'metodo_pago' => ['required', Rule::in(['QR', 'transferencia'])],
            'banco' => ['required', 'string', 'max:255'], 'referencia' => ['required', 'string', 'max:255'],
        ]);
        $instructions = $settings->paymentInstructions();
        abort_if($data['metodo_pago'] === 'QR' && ! $instructions['qr'], 422, 'El pago QR no esta habilitado.');
        abort_if($data['metodo_pago'] === 'transferencia' && ! $instructions['cuenta_bancaria'], 422, 'La transferencia no esta habilitada.');
        $cuota = Cuota::whereKey($data['cuota_id'])->where('venta_id', $venta->id)->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->firstOrFail();
        $service->solicitarPagoCuota($cuota, (float) $data['monto'], $data['metodo_pago'], $request->user(), $data);

        return redirect()->route('portal.terrenos.show', $venta)->with('status', 'Pago registrado y pendiente de verificacion. El saldo aun no fue modificado.');
    }

    public function documents(Request $request, Venta $venta): View
    {
        $this->own($request, $venta);
        $venta->load('lote.manzano.urbanizacion', 'cashMovements');

        return view('clientes.documentos', compact('venta'));
    }

    private function cliente(Request $request)
    {
        abort_unless($request->user()->cliente_id, 403, 'Tu usuario no esta vinculado a un cliente.');

        return $request->user()->cliente()->firstOrFail();
    }

    private function catalog(Request $request, SystemSettingsService $settings, bool $visitas): View
    {
        $cliente = $this->cliente($request);
        $whatsappPhone = $settings->get('whatsapp') ?: $settings->get('celular');

        return view('clientes.urbanizaciones', [
            'cliente' => $cliente,
            'urbanizaciones' => $this->activeUrbanizations(),
            'whatsappPhone' => $whatsappPhone,
            'visitas' => $visitas,
        ]);
    }

    private function activeUrbanizations()
    {
        return Urbanizacion::query()->where('estado', 'activa')->withLotStats()->orderBy('nombre')->get();
    }

    private function own(Request $request, Venta $venta): void
    {
        abort_unless($request->user()->hasRole('cliente') && (int) $request->user()->cliente_id === (int) $venta->cliente_id, 403);
    }
}
