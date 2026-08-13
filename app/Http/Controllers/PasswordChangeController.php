<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\InitialDestinationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(Request $request, InitialDestinationService $destination): View|RedirectResponse
    {
        if (! $request->user()?->must_change_password) {
            return $this->redirectAfterPasswordChange($request, $destination);
        }

        return view('auth.change-password');
    }

    public function update(Request $request, AuditService $auditService, InitialDestinationService $destination): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'La contrasena actual no es correcta.']);
        }

        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'La nueva contrasena debe ser diferente a la actual.']);
        }

        if ($user->cliente?->documento && hash_equals((string) $user->cliente->documento, $data['password'])) {
            throw ValidationException::withMessages(['password' => 'La nueva contraseña no puede ser igual al CI temporal.']);
        }

        $before = ['must_change_password' => $user->must_change_password];

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        $auditService->log($user, 'cambio_password_obligatorio', 'Cambio obligatorio de contrasena.', $before, ['must_change_password' => false], $request);

        return $this->redirectAfterPasswordChange($request, $destination)->with('status', 'Contrasena actualizada correctamente.');
    }

    private function redirectAfterPasswordChange(Request $request, InitialDestinationService $destination): RedirectResponse
    {
        return redirect()->intended(route($destination->routeName($request->user(), $request)));
    }
}
