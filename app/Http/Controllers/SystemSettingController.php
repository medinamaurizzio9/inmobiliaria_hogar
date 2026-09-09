<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\ManagedImageService;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(SystemSettingsService $settings): View
    {
        return view('admin.configuracion-general', [
            'settings' => $settings->all(),
        ]);
    }

    public function update(Request $request, SystemSettingsService $settings, AuditService $auditService, ManagedImageService $images): RedirectResponse
    {
        $request->merge([
            'public_base_url' => trim((string) $request->input('public_base_url', '')),
        ]);

        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:255'],
            'system_subtitle' => ['required', 'string', 'max:255'],
            'public_base_url' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/.+[^\/]$/'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:100'],
            'celular' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_main' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'logo_login' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'login_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'logo_pdf' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string', 'in:logo_main,logo_login,login_background,logo_pdf'],
        ]);

        $removeImages = array_values(array_unique($data['remove_images'] ?? []));
        unset($data['remove_images']);

        foreach (['logo_main', 'logo_login', 'logo_pdf'] as $logoKey) {
            if ($request->hasFile($logoKey)) {
                $oldPath = $settings->normalizedPublicPath($settings->all()[$logoKey] ?? null);
                $data[$logoKey] = $images->replaceOptimized($oldPath, $request->file($logoKey), 'logos', ['max_width' => 800, 'max_height' => 800, 'quality' => 85])['path'];
            }
        }

        $before = $settings->all();

        foreach ($removeImages as $key) {
            $data[$key] = null;
        }

        if ($request->hasFile('login_background')) {
            $oldBackground = $settings->normalizedPublicPath($before['login_background'] ?? null);
            $data['login_background'] = $images->replaceOptimized($oldBackground, $request->file('login_background'), 'login-backgrounds', ['max_width' => 1920, 'max_height' => 1920, 'quality' => 82])['path'];
        }

        $settings->setMany($data);

        foreach ($removeImages as $key) {
            $oldPath = $settings->normalizedPublicPath($before[$key] ?? null);
            if ($oldPath !== null && ! str_contains($oldPath, '..')) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $auditService->log(SystemSetting::query()->first(), 'cambiar_configuracion_sistema', 'Configuracion general del sistema actualizada.', $before, $settings->all(), $request);

        if (! file_exists(public_path('storage'))) {
            try {
                app('files')->link(storage_path('app/public'), public_path('storage'));
            } catch (\Throwable) {
                // El enlace puede existir o no estar permitido en algunos entornos Windows.
            }
        }

        return back()->with('status', 'Configuracion general guardada correctamente.');
    }
}
