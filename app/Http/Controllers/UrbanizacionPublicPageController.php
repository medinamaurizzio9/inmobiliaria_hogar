<?php

namespace App\Http\Controllers;

use App\Models\Urbanizacion;
use App\Services\AuditService;
use App\Services\ManagedImageService;
use App\Support\YouTubeEmbed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UrbanizacionPublicPageController extends Controller
{
    public function edit(Urbanizacion $urbanizacion): View
    {
        $urbanizacion->load(['publicSetting', 'publicFeatures']);

        return view('urbanizaciones.public-page', compact('urbanizacion'));
    }

    public function update(Request $request, Urbanizacion $urbanizacion, ManagedImageService $images, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_hero' => ['nullable', 'boolean'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'titulo_descripcion' => ['nullable', 'string', 'max:255'],
            'descripcion_principal' => ['nullable', 'string', 'max:5000'],
            'info_title' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'array', 'max:10'],
            'features.*.titulo' => ['required_with:features.*.descripcion', 'nullable', 'string', 'max:255'],
            'features.*.descripcion' => ['required_with:features.*.titulo', 'nullable', 'string', 'max:2000'],
            'features.*.orden' => ['nullable', 'integer', 'min:0', 'max:255'],
            'features.*.activo' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['youtube_url']) && YouTubeEmbed::id($data['youtube_url']) === null) {
            throw ValidationException::withMessages(['youtube_url' => 'Ingrese una URL válida de YouTube.']);
        }

        $setting = $urbanizacion->publicSetting()->firstOrNew();
        $before = ['setting' => $setting->toArray(), 'features' => $urbanizacion->publicFeatures()->get()->toArray()];
        $oldHero = $setting->hero_image;
        $newHero = null;

        if ($request->hasFile('hero_image')) {
            try {
                $newHero = $images->storeOptimized($request->file('hero_image'), "urbanizaciones/{$urbanizacion->id}", [
                    'max_width' => 1920,
                    'max_height' => 1080,
                    'quality' => 82,
                ])['path'];
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['hero_image' => $exception->getMessage()]);
            }
        }

        try {
            DB::transaction(function () use ($data, $request, $urbanizacion, $setting, $newHero): void {
                $setting->fill([
                    'youtube_url' => $data['youtube_url'] ?? null,
                    'titulo_descripcion' => $data['titulo_descripcion'] ?? null,
                    'descripcion_principal' => $data['descripcion_principal'] ?? null,
                    'info_title' => $data['info_title'] ?? null,
                ]);
                if ($newHero) {
                    $setting->hero_image = $newHero;
                } elseif ($request->boolean('remove_hero')) {
                    $setting->hero_image = null;
                }
                $urbanizacion->publicSetting()->save($setting);

                $urbanizacion->publicFeatures()->delete();
                foreach (array_values($data['features'] ?? []) as $index => $feature) {
                    if (blank($feature['titulo'] ?? null) && blank($feature['descripcion'] ?? null)) {
                        continue;
                    }
                    $urbanizacion->publicFeatures()->create([
                        'titulo' => $feature['titulo'],
                        'descripcion' => $feature['descripcion'],
                        'orden' => $feature['orden'] ?? $index,
                        'activo' => (bool) ($feature['activo'] ?? false),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            if ($newHero) {
                $images->delete($newHero, "urbanizaciones/{$urbanizacion->id}");
            }
            throw $exception;
        }

        if ($oldHero && ($newHero || $request->boolean('remove_hero'))) {
            $images->delete($oldHero, "urbanizaciones/{$urbanizacion->id}");
        }

        $urbanizacion->load(['publicSetting', 'publicFeatures']);
        $audit->log($urbanizacion, 'pagina_publica_actualizada', 'Configuración de página pública actualizada.', $before, [
            'setting' => $urbanizacion->publicSetting?->toArray(),
            'features' => $urbanizacion->publicFeatures->toArray(),
        ], $request);

        return back()->with('status', 'Página pública actualizada.');
    }
}
