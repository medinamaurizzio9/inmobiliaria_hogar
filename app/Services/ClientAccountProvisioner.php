<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientAccountProvisioner
{
    /** @return array{created: bool, temporary_password?: string, warning?: string, user?: User} */
    public function provision(Cliente $cliente): array
    {
        $existing = User::where('cliente_id', $cliente->id)->first();
        if ($existing) {
            return ['created' => false, 'user' => $existing];
        }

        $email = mb_strtolower(trim((string) $cliente->email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['created' => false, 'warning' => 'La venta fue registrada, pero el cliente no tiene un correo valido para crear su acceso.'];
        }
        if (User::where('email', $email)->exists()) {
            return ['created' => false, 'warning' => 'La venta fue registrada, pero el correo del cliente ya pertenece a otro usuario. Requiere revision administrativa.'];
        }

        $temporaryPassword = Str::password(16, true, true, true, false);
        $user = User::create([
            'cliente_id' => $cliente->id,
            'name' => $cliente->nombre,
            'email' => $email,
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'estado' => 'activo',
        ]);
        $user->assignRole('cliente');

        return ['created' => true, 'temporary_password' => $temporaryPassword, 'user' => $user];
    }
}
