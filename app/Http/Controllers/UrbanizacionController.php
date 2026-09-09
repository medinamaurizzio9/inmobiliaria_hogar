<?php

namespace App\Http\Controllers;

use App\Models\Urbanizacion;
use App\Services\PlanImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UrbanizacionController extends Controller
{
    public function index(): View
    {
        return view('urbanizaciones.index', [
            'urbanizaciones' => Urbanizacion::withLotStats()->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('urbanizaciones.form', ['urbanizacion' => new Urbanizacion]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $storedPlan = $data['_stored_plan'] ?? null;
        unset($data['_stored_plan']);

        try {
            Urbanizacion::create($data);
        } catch (\Throwable $exception) {
            $this->deleteStoredPlan($storedPlan);
            throw $exception;
        }

        return redirect()->route('urbanizaciones.index')->with('status', $this->successMessage($storedPlan));
    }

    public function edit(Urbanizacion $urbanizacion): View
    {
        return view('urbanizaciones.form', compact('urbanizacion'));
    }

    public function update(Request $request, Urbanizacion $urbanizacion): RedirectResponse
    {
        $oldImage = $urbanizacion->plano_imagen;
        $oldOriginal = $urbanizacion->plano_archivo_original;
        $data = $this->validated($request, $urbanizacion);
        $storedPlan = $data['_stored_plan'] ?? null;
        unset($data['_stored_plan']);

        try {
            $urbanizacion->update($data);
        } catch (\Throwable $exception) {
            $this->deleteStoredPlan($storedPlan);
            throw $exception;
        }

        if ($storedPlan && ($oldImage || $oldOriginal)) {
            app(PlanImageService::class)->delete($oldImage, $oldOriginal);
        }

        return redirect()->route('urbanizaciones.index')->with('status', $this->successMessage($storedPlan));
    }

    public function destroy(Urbanizacion $urbanizacion): RedirectResponse
    {
        abort_unless(request()->user()->hasAnyRole(['administrador', 'gerente']), 403, 'No tienes permiso para eliminar urbanizaciones.');

        $urbanizacion->delete();

        return back()->with('status', 'Urbanizacion eliminada.');
    }

    private function validated(Request $request, ?Urbanizacion $urbanizacion = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'propietario' => ['nullable', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'plano_imagen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'superficie_total' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:activa,pausada,cerrada'],
            'mostrar_precio_publico' => ['nullable', 'boolean'],
        ]);

        $data['mostrar_precio_publico'] = $request->boolean('mostrar_precio_publico');

        if ($request->hasFile('plano_imagen')) {
            $planImageService = app(PlanImageService::class);

            try {
                $storedPlan = $planImageService->store($request->file('plano_imagen'));
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages([
                    'plano_imagen' => $exception->getMessage(),
                ]);
            }

            $data['plano_imagen'] = $storedPlan['plano_imagen'];
            $data['plano_archivo_original'] = $storedPlan['plano_archivo_original'];
            $data['_stored_plan'] = $storedPlan;
        } else {
            unset($data['plano_imagen']);
            unset($data['plano_archivo_original']);
        }

        $data['slug'] = $this->uniqueSlug($data['nombre'], $urbanizacion);

        return $data;
    }

    private function deleteStoredPlan(?array $storedPlan): void
    {
        if ($storedPlan) {
            app(PlanImageService::class)->delete($storedPlan['plano_imagen'] ?? null, $storedPlan['plano_archivo_original'] ?? null);
        }
    }

    private function successMessage(?array $storedPlan): string
    {
        $optimization = $storedPlan['optimization'] ?? null;
        if (! is_array($optimization) || $optimization['target_bytes_reached'] === null) {
            return 'Operacion realizada correctamente.';
        }

        if ($optimization['target_bytes_reached']) {
            return 'Operacion realizada correctamente. El plano fue optimizado automáticamente sin cambiar sus dimensiones.';
        }

        return 'Operacion realizada correctamente. El plano fue optimizado sin cambiar sus dimensiones, pero continúa superando 2 MB para conservar una calidad segura.';
    }

    private function uniqueSlug(string $name, ?Urbanizacion $urbanizacion = null): string
    {
        $base = Str::slug($name) ?: 'urbanizacion';
        $slug = $base;
        $suffix = 2;

        while (Urbanizacion::where('slug', $slug)
            ->when($urbanizacion, fn ($query) => $query->whereKeyNot($urbanizacion->id))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
