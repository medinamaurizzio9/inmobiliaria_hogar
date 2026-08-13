<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\AuditService;
use App\Services\ClientAccountProvisioner;
use App\Services\ManagedImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CompradorUsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);
        $search = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', '');

        $clientes = Cliente::query()
            ->with(['user.roles', 'urbanizacion'])
            ->withCount('ventas')
            ->has('ventas')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")->orWhere('documento', 'like', "%{$search}%")->orWhere('telefono', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($estado === 'sin_cuenta', fn ($query) => $query->doesntHave('user'))
            ->when($estado === 'activo', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('estado', 'activo')->where('must_change_password', false)))
            ->when($estado === 'bloqueado', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('estado', 'inactivo')))
            ->when($estado === 'cambio_password', fn ($query) => $query->whereHas('user', fn ($q) => $q->where('must_change_password', true)))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('administracion.compradores.index', compact('clientes', 'search', 'estado'));
    }

    public function createAccess(Request $request, Cliente $cliente, ClientAccountProvisioner $provisioner, AuditService $auditService): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($cliente->ventas()->exists(), 422, 'El cliente no tiene ventas asociadas.');
        $result = $provisioner->provision($cliente);
        if (! ($result['created'] ?? false)) {
            return back()->withErrors(['access' => $result['warning'] ?? 'El comprador ya tiene una cuenta.']);
        }
        $auditService->log($cliente, 'crear_acceso_comprador', 'Acceso de comprador creado.', null, ['user_id' => $result['user']->id], $request);

        return back()->with('status', 'Acceso creado. El comprador deberá cambiar su contraseña temporal al ingresar.');
    }

    public function resetPassword(Request $request, Cliente $cliente, AuditService $auditService): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_if(blank($cliente->documento), 422, 'El comprador no tiene CI/documento registrado.');
        $user = $cliente->user()->whereHas('roles', fn ($q) => $q->where('name', 'cliente'))->firstOrFail();
        DB::transaction(function () use ($request, $cliente, $user, $auditService): void {
            $user->update(['password' => Hash::make($cliente->documento), 'must_change_password' => true]);
            // La invalidación administrativa usa el SESSION_DRIVER=database vigente.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
            $auditService->log($cliente, 'resetear_password_comprador', 'Contraseña temporal del comprador restablecida.', ['must_change_password' => false], ['must_change_password' => true], $request);
        });

        return back()->with('status', 'Contraseña restablecida. El comprador deberá utilizar su CI como contraseña temporal y cambiarla en el próximo inicio de sesión.');
    }

    public function toggleAccess(Request $request, Cliente $cliente, AuditService $auditService): RedirectResponse
    {
        $this->authorizeAccess($request);
        $user = $cliente->user()->whereHas('roles', fn ($q) => $q->where('name', 'cliente'))->firstOrFail();
        $before = $user->estado;
        $user->update(['estado' => $before === 'activo' ? 'inactivo' : 'activo']);
        if ($user->estado === 'inactivo') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $auditService->log($cliente, 'cambiar_acceso_comprador', 'Estado de acceso del comprador actualizado.', ['estado' => $before], ['estado' => $user->estado], $request);

        return back()->with('status', 'Estado de acceso actualizado.');
    }

    public function updatePhoto(Request $request, Cliente $cliente, ManagedImageService $images, AuditService $auditService): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $before = $cliente->foto;
        $cliente->update(['foto' => $images->replace($before, $data['foto'], 'clientes')]);
        $auditService->log($cliente, 'cambiar_foto_comprador', 'Fotografía del comprador actualizada.', ['foto' => $before], ['foto' => $cliente->foto], $request);

        return back()->with('status', 'Fotografía actualizada.');
    }

    public function deletePhoto(Request $request, Cliente $cliente, ManagedImageService $images, AuditService $auditService): RedirectResponse
    {
        $this->authorizeAccess($request);
        $before = $cliente->foto;
        $images->delete($before, 'clientes');
        $cliente->update(['foto' => null]);
        $auditService->log($cliente, 'quitar_foto_comprador', 'Fotografía del comprador eliminada.', ['foto' => $before], ['foto' => null], $request);

        return back()->with('status', 'Fotografía eliminada.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->hasRole('administrador') && $request->user()->can('administrar usuarios'), 403);
    }
}
