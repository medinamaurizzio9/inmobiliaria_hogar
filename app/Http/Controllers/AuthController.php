<?php

namespace App\Http\Controllers;

use App\Services\InitialDestinationService;
use App\Services\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request, PublicPortalController $portal, SystemSettingsService $settings): View
    {
        return $portal($request, $settings);
    }

    public function showLoginForm(Request $request, PublicPortalController $portal, SystemSettingsService $settings): View
    {
        return $this->showLogin($request, $portal, $settings);
    }

    public function login(Request $request, InitialDestinationService $destination): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt([...$credentials, 'estado' => 'activo'], $request->boolean('remember'))) {
            $request->session()->regenerate();

            if ($request->user()->must_change_password) {
                return redirect()->route('password.change');
            }

            return redirect()->intended(route($destination->routeName($request->user(), $request)));
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
