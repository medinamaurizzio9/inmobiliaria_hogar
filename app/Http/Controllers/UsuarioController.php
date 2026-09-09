<?php

namespace App\Http\Controllers;

use App\Models\Asesor;
use App\Models\SupervisorProfile;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ManagedImageService;
use App\Services\UserDeletionService;
use App\Services\UserSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    private array $allowedRoles = [
        'administrador',
        'gerente',
        'supervisor',
        'vendedor',
        'cliente',
    ];

    public function index(Request $request, UserDeletionService $deletionService): View
    {
        $this->authorizeManageUsers($request);
        $sort = $this->sort($request);

        $search = trim((string) $request->query('q', ''));
        $role = (string) $request->query('rol', '');
        $estado = (string) $request->query('estado', '');
        $users = User::with('roles')
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'cliente'))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($role !== '', fn ($query) => $query->role($role))
            ->when(in_array($estado, ['activo', 'inactivo'], true), fn ($query) => $query->where('estado', $estado))
            ->tap(fn ($query) => $this->applySorting($query, $sort['field'], $sort['direction']))
            ->paginate(50)
            ->appends($request->query());

        $users->getCollection()->each(
            fn (User $user) => $user->setAttribute('has_delete_history', $deletionService->hasHistoricalRecords($user))
        );

        return view('administracion.usuarios.index', ['users' => $users, 'roles' => $this->roles(), 'filters' => compact('search', 'role', 'estado')]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManageUsers($request);

        return view('administracion.usuarios.create', [
            'usuario' => new User,
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request, AuditService $auditService): RedirectResponse
    {
        $this->authorizeManageUsers($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', Rule::in($this->roles()->pluck('name')->all())],
        ]);

        $user = DB::transaction(function () use ($data, $request, $auditService): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'must_change_password' => true,
                'estado' => 'activo',
            ]);
            $user->syncRoles([$data['rol']]);

            $auditService->log($user, 'crear_usuario', 'Creacion de usuario del sistema.', null, [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $data['rol'],
            ], $request);

            return $user;
        });

        return redirect()
            ->route('admin.usuarios')
            ->with('status', 'Usuario creado correctamente.');
    }

    public function importForm(Request $request): View
    {
        $this->authorizeManageUsers($request);

        return view('administracion.usuarios.import');
    }

    public function import(Request $request, UserSpreadsheetService $spreadsheet, AuditService $auditService): RedirectResponse
    {
        $this->authorizeManageUsers($request);

        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt,xlsx'],
        ]);

        try {
            $rows = $spreadsheet->rowsFromUpload($data['archivo']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['archivo' => $exception->getMessage()]);
        }

        $validRoles = $this->roles()->pluck('name')->all();
        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $validRoles, &$created, &$skipped, &$errors): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $nombre = trim((string) ($row['nombre'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));
                $password = (string) ($row['password'] ?? '');
                $rol = trim((string) ($row['rol'] ?? ''));
                $estado = trim((string) ($row['estado'] ?? '')) ?: 'activo';
                $telefono = trim((string) ($row['telefono'] ?? ''));
                $ci = trim((string) ($row['ci'] ?? ''));
                $urbanizacionNombre = trim((string) ($row['urbanizacion'] ?? ''));
                $supervisorNombre = trim((string) ($row['supervisor'] ?? ''));
                $urbanizacion = null;
                $supervisor = null;

                $rowErrors = [];
                if ($nombre === '') {
                    $rowErrors[] = "Fila {$line}: Nombre requerido.";
                }
                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = "Fila {$line}: Email invalido.";
                } elseif (User::where('email', $email)->exists()) {
                    $rowErrors[] = "Fila {$line}: El email ya existe.";
                }
                if (trim($password) === '') {
                    $rowErrors[] = "Fila {$line}: Contrasena requerida.";
                } elseif (mb_strlen($password) < 6) {
                    $rowErrors[] = "Fila {$line}: La contrasena debe tener al menos 6 caracteres.";
                }
                if ($rol === '' || ! in_array($rol, $validRoles, true)) {
                    $rowErrors[] = "Fila {$line}: Rol no válido.";
                }
                if (! in_array($estado, ['activo', 'inactivo'], true)) {
                    $rowErrors[] = "Fila {$line}: Estado no valido.";
                }
                if ($urbanizacionNombre !== '') {
                    $urbanizacion = Urbanizacion::where('nombre', $urbanizacionNombre)->first();
                    if (! $urbanizacion) {
                        $rowErrors[] = "Fila {$line}: Urbanización no encontrada.";
                    }
                }
                if ($supervisorNombre !== '') {
                    $supervisor = User::role('supervisor')
                        ->where(function ($query) use ($supervisorNombre): void {
                            $query->where('name', $supervisorNombre)
                                ->orWhere('email', $supervisorNombre);
                        })
                        ->first();

                    if (! $supervisor) {
                        $rowErrors[] = "Fila {$line}: Supervisor no encontrado.";
                    }
                }

                if ($rowErrors !== []) {
                    $errors = array_merge($errors, $rowErrors);
                    $skipped++;

                    continue;
                }

                $user = User::create([
                    'name' => $nombre,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'must_change_password' => true,
                    'estado' => $estado,
                ]);
                $user->syncRoles([$rol]);
                if ($urbanizacion) {
                    $user->urbanizacionesAsignadas()->syncWithoutDetaching([$urbanizacion->id => ['activo' => $estado === 'activo']]);
                }
                $this->syncCommercialProfile($user, $rol, [
                    'nombre' => $nombre,
                    'email' => $email,
                    'telefono' => $telefono,
                    'ci' => $ci,
                    'activo' => $estado === 'activo',
                    'supervisor_id' => $supervisor?->id,
                ]);
                $created++;
            }
        });

        $auditService->log(null, 'importar_usuarios', 'Importacion de usuarios.', null, [
            'creados' => $created,
            'omitidos' => $skipped,
            'errores' => count($errors),
        ], $request);

        return redirect()
            ->route('admin.usuarios.import')
            ->with('status', "Usuarios creados: {$created}. Usuarios omitidos: {$skipped}. Errores: ".count($errors).'.')
            ->with('import_errors', $errors);
    }

    public function export(Request $request, UserSpreadsheetService $spreadsheet, AuditService $auditService): Response
    {
        $this->authorizeManageUsers($request);

        $rows = User::with(['roles', 'asesor.supervisor', 'supervisorProfile', 'urbanizacionesAsignadas'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->roles->pluck('name')->join(', '),
                $user->estado ?? 'activo',
                $this->commercialPhone($user),
                $this->commercialCi($user),
                $user->urbanizacionesAsignadas->pluck('nombre')->join(', '),
                $this->commercialSupervisor($user),
                $user->must_change_password ? 'si' : 'no',
                $user->created_at?->format('Y-m-d H:i:s'),
            ])
            ->all();

        $auditService->log(null, 'exportar_usuarios', 'Exportacion de usuarios.', null, [
            'cantidad' => count($rows),
        ], $request);

        return $this->xlsxResponse(
            $spreadsheet->xlsx(['nombre', 'email', 'rol', 'estado', 'telefono', 'ci', 'urbanizacion', 'supervisor', 'must_change_password', 'created_at'], $rows),
            'usuarios_'.now()->format('Y_m_d').'.xlsx'
        );
    }

    public function template(Request $request, UserSpreadsheetService $spreadsheet): Response
    {
        $this->authorizeManageUsers($request);

        return $this->xlsxResponse(
            $spreadsheet->xlsx(UserSpreadsheetService::HEADERS, [
                ['Maria Lopez', 'maria@empresa.com', '12345678', 'supervisor', 'activo', '77722222', '2345678', 'Colinas del Norte Zona 1', ''],
                ['Juan Perez', 'juan@empresa.com', '12345678', 'vendedor', 'activo', '77711111', '1234567', 'Colinas del Norte Zona 1', 'Maria Lopez'],
            ]),
            'plantilla_usuarios.xlsx'
        );
    }

    public function edit(Request $request, User $usuario): View
    {
        $this->authorizeManageUsers($request);

        $usuario->load('roles');

        return view('administracion.usuarios.edit', [
            'usuario' => $usuario,
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, User $usuario, AuditService $auditService): RedirectResponse
    {
        $this->authorizeManageUsers($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'rol' => ['required', Rule::in($this->roles()->pluck('name')->all())],
        ]);

        DB::transaction(function () use ($usuario, $data, $request, $auditService): void {
            $usuario->load('roles');
            $before = [
                'name' => $usuario->name,
                'email' => $usuario->email,
                'roles' => $usuario->roles->pluck('name')->values()->all(),
            ];

            $updates = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $updates['password'] = Hash::make($data['password']);
                $updates['must_change_password'] = true;
            }

            $usuario->update($updates);
            $usuario->syncRoles([$data['rol']]);

            $auditService->log($usuario, 'editar_usuario', 'Edicion de usuario del sistema.', $before, [
                'name' => $usuario->name,
                'email' => $usuario->email,
                'roles' => [$data['rol']],
                'password_changed' => ! empty($data['password']),
            ], $request);
        });

        return redirect()
            ->route('admin.usuarios')
            ->with('status', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $usuario, AuditService $auditService, UserDeletionService $deletionService): RedirectResponse
    {
        $result = $deletionService->deleteOrDeactivate($usuario, $request, $auditService, 'eliminar_usuario', 'desactivar_usuario');

        return redirect()
            ->route('admin.usuarios')
            ->with('status', $result === 'deleted'
                ? 'Usuario eliminado correctamente.'
                : 'El usuario tiene registros asociados, por seguridad fue desactivado.');
    }

    public function updatePhoto(Request $request, User $usuario, ManagedImageService $images, AuditService $auditService): RedirectResponse
    {
        $this->authorizeManageUsers($request);
        abort_if($usuario->hasRole('cliente'), 404);
        $data = $request->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        $before = $usuario->foto;
        $usuario->forceFill(['foto' => $images->replaceOptimized($before, $data['foto'], 'usuarios', ['max_width' => 600, 'max_height' => 600, 'quality' => 84, 'generate_thumbnail' => true, 'thumbnail_width' => 240, 'thumbnail_height' => 240])['path']])->save();
        $auditService->log($usuario, 'cambiar_foto_usuario', 'Fotografía del usuario actualizada.', ['foto' => $before], ['foto' => $usuario->foto], $request);

        return back()->with('status', 'Fotografía actualizada.');
    }

    public function deletePhoto(Request $request, User $usuario, ManagedImageService $images, AuditService $auditService): RedirectResponse
    {
        $this->authorizeManageUsers($request);
        abort_if($usuario->hasRole('cliente'), 404);
        $before = $usuario->foto;
        $images->delete($before, 'usuarios');
        $usuario->forceFill(['foto' => null])->save();
        $auditService->log($usuario, 'quitar_foto_usuario', 'Fotografía del usuario eliminada.', ['foto' => $before], ['foto' => null], $request);

        return back()->with('status', 'Fotografía eliminada.');
    }

    private function roles()
    {
        return Role::whereIn('name', $this->allowedRoles)
            ->orderBy('name')
            ->get();
    }

    private function authorizeManageUsers(Request $request): void
    {
        abort_unless($request->user()?->hasRole('administrador'), 403);
    }

    private function sort(Request $request): array
    {
        $allowedSorts = ['id', 'name', 'email', 'rol'];
        $field = $request->query('sort', 'id');

        return [
            'field' => in_array($field, $allowedSorts, true) ? $field : 'id',
            'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function applySorting($query, string $field, string $direction): void
    {
        if ($field !== 'rol') {
            $query->orderBy($field, $direction);

            return;
        }

        $query->orderBy(
            Role::select('name')
                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', User::class)
                ->limit(1),
            $direction
        );
    }

    private function syncCommercialProfile(User $user, string $rol, array $data): void
    {
        if ($rol === 'supervisor') {
            SupervisorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nombre' => $data['nombre'],
                    'ci' => $data['ci'] !== '' ? $data['ci'] : null,
                    'celular' => $data['telefono'] ?: null,
                    'email' => $data['email'],
                    'activo' => $data['activo'],
                ]
            );
        }

        if ($rol === 'vendedor') {
            [$nombre, $apellido] = $this->splitName($data['nombre']);
            Asesor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'supervisor_id' => $data['supervisor_id'],
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'ci' => $data['ci'] !== '' ? $data['ci'] : null,
                    'celular' => $data['telefono'] ?: null,
                    'email' => $data['email'],
                    'activo' => $data['activo'],
                ]
            );
        }
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?: $name, $parts[1] ?? ''];
    }

    private function commercialPhone(User $user): string
    {
        return $user->asesor?->celular
            ?? $user->supervisorProfile?->celular
            ?? '';
    }

    private function commercialCi(User $user): string
    {
        return $user->asesor?->ci
            ?? $user->supervisorProfile?->ci
            ?? '';
    }

    private function commercialSupervisor(User $user): string
    {
        return $user->asesor?->supervisor?->name ?? '';
    }

    private function xlsxResponse(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
