<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\PublicLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicLeadController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'celular' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'urbanizacion_id' => ['nullable', Rule::exists('urbanizaciones', 'id')->where('estado', 'activa')],
            'lote_id' => ['nullable', 'integer'],
            'mensaje' => ['required', 'string', 'max:1500'],
        ]);

        if (! empty($data['lote_id'])) {
            $validLot = Lote::query()->whereKey($data['lote_id'])->where('estado', 'disponible')
                ->whereHas('manzano.urbanizacion', fn ($query) => $query->where('estado', 'activa')->when($data['urbanizacion_id'] ?? null, fn ($q, $id) => $q->whereKey($id)))->exists();
            abort_unless($validLot, 422);
        }

        PublicLead::create([...$data, 'origen' => 'web', 'estado' => 'nuevo']);

        return back()->with('public_status', 'Recibimos tu consulta. Nuestro equipo se comunicará contigo.');
    }
}
