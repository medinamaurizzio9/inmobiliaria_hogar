<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\CommercialSettingsService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommercialSettingController extends Controller
{
    public function edit(CommercialSettingsService $settings): View
    {
        abort_unless(request()->user()?->hasAnyRole(['administrador', 'gerente']), 403);

        $urbanizacion = UrbanizacionContext::current();
        $urbanizacionId = UrbanizacionContext::currentId();

        return view('admin.configuracion-comercial', [
            'urbanizacion' => $urbanizacion,
            'reservaDiasHabilesAsesor' => $settings->reservaDiasHabilesAsesor($urbanizacionId),
            'proteccionClienteDias' => $settings->clientProtectionDays($urbanizacionId),
            'priceSettings' => $settings->priceSettings($urbanizacionId),
        ]);
    }

    public function update(Request $request, CommercialSettingsService $settings, AuditService $auditService): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['administrador', 'gerente']), 403);

        $urbanizacionId = UrbanizacionContext::currentId();
        $calculatorSettings = $settings->calculatorSettings($urbanizacionId);

        $request->merge([
            'proteccion_cliente_dias' => $request->input('proteccion_cliente_dias', $settings->clientProtectionDays($urbanizacionId)),
            'tipo_cambio_usd_bs' => $request->input('tipo_cambio_usd_bs', $settings->tipoCambioUsdBs($urbanizacionId)),
            'incremento_credito_tipo' => $request->input('incremento_credito_tipo', $settings->incrementoCreditoTipo($urbanizacionId)),
            'incremento_credito_valor' => $request->input('incremento_credito_valor', $settings->incrementoCreditoValor($urbanizacionId)),
            'inicial_minima_usd' => $request->input('inicial_minima_usd', $calculatorSettings['inicial_minima_usd']),
            'plazo_12_habilitado' => $request->has('plazo_12_habilitado') ? $request->boolean('plazo_12_habilitado') : $calculatorSettings['plazo_12_habilitado'],
            'plazo_24_habilitado' => $request->has('plazo_24_habilitado') ? $request->boolean('plazo_24_habilitado') : $calculatorSettings['plazo_24_habilitado'],
            'plazo_36_habilitado' => $request->has('plazo_36_habilitado') ? $request->boolean('plazo_36_habilitado') : $calculatorSettings['plazo_36_habilitado'],
            'descuento_contado_activo' => $request->has('descuento_contado_activo') ? $request->boolean('descuento_contado_activo') : $calculatorSettings['descuento_contado_activo'],
            'descuento_contado_tipo' => $request->input('descuento_contado_tipo', $calculatorSettings['descuento_contado_tipo']),
            'descuento_contado_valor' => $request->input('descuento_contado_valor', $calculatorSettings['descuento_contado_valor']),
            'descuento_promo_activo' => $request->has('descuento_promo_activo') ? $request->boolean('descuento_promo_activo') : $calculatorSettings['descuento_promo_activo'],
            'descuento_promo_tipo' => $request->input('descuento_promo_tipo', $calculatorSettings['descuento_promo_tipo']),
            'descuento_promo_valor' => $request->input('descuento_promo_valor', $calculatorSettings['descuento_promo_valor']),
            'descuento_promo_nombre' => $request->input('descuento_promo_nombre', $calculatorSettings['descuento_promo_nombre']),
            'descuento_promo_descripcion' => $request->input('descuento_promo_descripcion', $calculatorSettings['descuento_promo_descripcion']),
        ]);

        $data = $request->validate([
            'reserva_dias_habiles_asesor' => ['required', 'integer', 'min:1', 'max:30'],
            'proteccion_cliente_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'tipo_cambio_usd_bs' => ['required', 'numeric', 'min:0'],
            'incremento_credito_tipo' => ['required', 'in:monto,porcentaje'],
            'incremento_credito_valor' => ['required', 'numeric', 'min:0'],
            'inicial_minima_usd' => ['required', 'numeric', 'min:0'],
            'plazo_12_habilitado' => ['boolean'],
            'plazo_24_habilitado' => ['boolean'],
            'plazo_36_habilitado' => ['boolean'],
            'descuento_contado_activo' => ['boolean'],
            'descuento_contado_tipo' => ['required', 'in:monto,porcentaje'],
            'descuento_contado_valor' => ['required', 'numeric', 'min:0'],
            'descuento_promo_activo' => ['boolean'],
            'descuento_promo_tipo' => ['required', 'in:monto,porcentaje'],
            'descuento_promo_valor' => ['required', 'numeric', 'min:0'],
            'descuento_promo_nombre' => ['nullable', 'string', 'max:255'],
            'descuento_promo_descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = [
            'reserva_dias_habiles_asesor' => $settings->reservaDiasHabilesAsesor($urbanizacionId),
            'proteccion_cliente_dias' => $settings->clientProtectionDays($urbanizacionId),
            ...$settings->priceSettings($urbanizacionId),
        ];
        $setting = $settings->updateForUrbanizacion($urbanizacionId, $data);
        $auditService->log($setting, 'actualizar_configuracion_comercial', 'Usuario autorizado actualizo configuracion comercial.', $before, $data, $request);

        return back()->with('status', 'Configuracion comercial actualizada.');
    }
}
