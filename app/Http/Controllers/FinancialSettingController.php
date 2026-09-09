<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\FinancialSettingsService;
use App\Services\ManagedImageService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialSettingController extends Controller
{
    public function edit(Request $request, FinancialSettingsService $settings): View
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente', 'super administrador']), 403);

        return view('admin.configuracion-financiera', [
            'settings' => $settings->all(UrbanizacionContext::currentId()),
            'canEdit' => $request->user()->hasAnyRole(['administrador', 'super administrador']),
        ]);
    }

    public function update(Request $request, FinancialSettingsService $settings, AuditService $auditService, ManagedImageService $images): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'super administrador']), 403);

        $data = $request->validate([
            'qr_institucional_imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'qr_institucional_nombre' => ['nullable', 'string', 'max:255'],
            'qr_institucional_activo' => ['required', 'boolean'],
            'banco_nombre' => ['nullable', 'string', 'max:150'],
            'banco_titular' => ['nullable', 'string', 'max:200'],
            'banco_numero_cuenta' => ['nullable', 'string', 'max:100'],
            'banco_tipo_cuenta' => ['nullable', 'string', 'max:100'],
            'banco_moneda' => ['required', 'in:BOB,USD'],
            'banco_instrucciones' => ['nullable', 'string', 'max:1000'],
            'banco_activo' => ['required', 'boolean'],
            'dias_aviso_vencimiento' => ['required', 'integer', 'min:0', 'max:30'],
            'mora_habilitada' => ['required', 'boolean'],
            'tipo_mora' => ['nullable', 'in:monto_fijo,porcentaje'],
            'valor_mora' => ['nullable', 'numeric', 'min:0'],
            'dias_gracia' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_cuotas_semicontado' => ['required', 'integer', 'min:1', 'max:120'],
            'max_cuotas_credito' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $before = $settings->all(UrbanizacionContext::currentId());
        if ($request->hasFile('qr_institucional_imagen')) {
            $data['qr_institucional_imagen'] = $images->replaceOptimized($before['qr_institucional_imagen'], $request->file('qr_institucional_imagen'), 'configuracion-financiera/qr', ['max_width' => 2000, 'max_height' => 2000, 'quality' => 95, 'format' => 'original'])['path'];
        }

        $settings->update($data, UrbanizacionContext::currentId());
        $after = $settings->all(UrbanizacionContext::currentId());
        $auditService->log(SystemSetting::query()->first(), 'actualizar_configuracion_financiera', 'Configuracion financiera administrativa actualizada.', $before, $after, $request);

        return back()->with('status', 'Configuracion financiera guardada.');
    }
}
