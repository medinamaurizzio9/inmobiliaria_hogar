<?php

namespace App\Services;

use App\Models\User;
use App\Support\UrbanizacionContext;
use Illuminate\Http\Request;

class InitialDestinationService
{
    public function routeName(User $user, Request $request): string
    {
        if ($user->hasRole('cliente')) {
            return 'clientes.mi-cuenta';
        }

        if ($user->hasAnyRole(['super administrador', 'administrador', 'gerente', 'cajero']) && $user->can('ver dashboard')) {
            $this->ensureUrbanizacionContext($user, $request);

            return UrbanizacionContext::currentId() ? 'dashboard' : 'urbanizaciones.select';
        }

        if ($user->hasRole('supervisor') && $user->can('ver dashboard')) {
            $this->ensureUrbanizacionContext($user, $request);

            return UrbanizacionContext::currentId() ? 'dashboard' : 'urbanizaciones.select';
        }

        if ($user->hasRole('vendedor')) {
            return 'urbanizaciones.select';
        }

        return 'urbanizaciones.select';
    }

    private function ensureUrbanizacionContext(User $user, Request $request): void
    {
        $currentId = UrbanizacionContext::currentId();

        if ($currentId && UrbanizacionContext::userCanAccess($user, $currentId)) {
            return;
        }

        $request->session()->forget('urbanizacion_id');
        $urbanizacion = UrbanizacionContext::accessibleUrbanizaciones($user)->first();

        if ($urbanizacion) {
            $request->session()->put('urbanizacion_id', $urbanizacion->id);
        }
    }
}
