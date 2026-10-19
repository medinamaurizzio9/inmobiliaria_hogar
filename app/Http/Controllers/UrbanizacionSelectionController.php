<?php

namespace App\Http\Controllers;

use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UrbanizacionSelectionController extends Controller
{
    public function index(Request $request): View
    {
        $urbanizaciones = UrbanizacionContext::accessibleUrbanizaciones($request->user())
            ->load('publicSetting')
            ->loadCount([
                'lotes as total_lotes',
                'lotes as disponibles_count' => fn ($query) => $query->where('estado', 'disponible'),
                'lotes as vendidos_count' => fn ($query) => $query->where('estado', 'vendido'),
                'lotes as reservados_count' => fn ($query) => $query->where('estado', 'reservado'),
                'lotes as bloqueados_count' => fn ($query) => $query->where('estado', 'bloqueado'),
            ]);

        return view('urbanizaciones.select', compact('urbanizaciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'urbanizacion_id' => ['required', 'exists:urbanizaciones,id'],
        ]);

        if (! UrbanizacionContext::userCanAccess($request->user(), (int) $data['urbanizacion_id'])) {
            return back()->withErrors(['urbanizacion' => 'No tienes acceso a esta urbanizacion']);
        }

        $request->session()->put('urbanizacion_id', (int) $data['urbanizacion_id']);

        return redirect()->route('dashboard')->with('status', 'Urbanizacion seleccionada.');
    }
}
