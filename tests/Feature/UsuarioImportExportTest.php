<?php

namespace Tests\Feature;

use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_descarga_plantilla(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion()->id])
            ->get(route('admin.usuarios.template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_administrador_importa_usuarios_y_quedan_con_cambio_obligatorio(): void
    {
        $admin = $this->admin();
        $urbanizacion = $this->urbanizacion();

        $file = $this->csv([
            'nombre,email,password,rol,estado,telefono,ci,urbanizacion,supervisor',
            "Maria Lopez,maria.importada@example.com,12345678,supervisor,activo,77722222,2345678,{$urbanizacion->nombre},",
            "Juan Perez,juan.importado@example.com,12345678,vendedor,activo,77711111,1234567,{$urbanizacion->nombre},Maria Lopez",
        ]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('admin.usuarios.import.store'), ['archivo' => $file])
            ->assertRedirect(route('admin.usuarios.import'))
            ->assertSessionHas('status');

        $juan = User::where('email', 'juan.importado@example.com')->firstOrFail();
        $maria = User::where('email', 'maria.importada@example.com')->firstOrFail();

        $this->assertTrue($juan->must_change_password);
        $this->assertTrue($maria->must_change_password);
        $this->assertSame('activo', $maria->estado);
        $this->assertSame('juan.importado@example.com', $juan->email);
        $this->assertTrue(Hash::check('12345678', $juan->password));
        $this->assertTrue($juan->hasRole('vendedor'));
        $this->assertTrue($maria->hasRole('supervisor'));
        $this->assertDatabaseHas('supervisor_profiles', [
            'user_id' => $maria->id,
            'nombre' => 'Maria Lopez',
            'celular' => '77722222',
            'ci' => '2345678',
        ]);
        $this->assertDatabaseHas('asesores', [
            'user_id' => $juan->id,
            'supervisor_id' => $maria->id,
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'celular' => '77711111',
            'ci' => '1234567',
        ]);
        $this->assertTrue($juan->urbanizacionesAsignadas()->whereKey($urbanizacion->id)->exists());
        $this->assertTrue($maria->urbanizacionesAsignadas()->whereKey($urbanizacion->id)->exists());
    }

    public function test_importacion_valida_urbanizacion_y_supervisor(): void
    {
        $admin = $this->admin();

        $file = $this->csv([
            'nombre,email,password,rol,estado,telefono,ci,urbanizacion,supervisor',
            'Sin Urb,sin.urb@example.com,12345678,vendedor,activo,77711111,1234567,No Existe,',
            'Sin Supervisor,sin.supervisor@example.com,12345678,vendedor,activo,77711111,1234567,,Supervisor Fantasma',
        ]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion()->id])
            ->post(route('admin.usuarios.import.store'), ['archivo' => $file])
            ->assertRedirect(route('admin.usuarios.import'))
            ->assertSessionHas('import_errors', fn ($errors) => in_array('Fila 2: Urbanización no encontrada.', $errors, true)
                && in_array('Fila 3: Supervisor no encontrado.', $errors, true));
    }

    public function test_usuario_creado_desde_formulario_queda_con_cambio_obligatorio(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion()->id])
            ->post(route('admin.usuarios.store'), [
                'name' => 'Nuevo Usuario',
                'email' => 'nuevo.usuario@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => 'vendedor',
            ])
            ->assertRedirect(route('admin.usuarios'));

        $this->assertTrue(User::where('email', 'nuevo.usuario@example.com')->firstOrFail()->must_change_password);
    }

    public function test_primer_login_redirige_y_bloquea_sistema_hasta_cambiar_password(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'email' => 'primer.login@example.com',
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);
        $user->assignRole('administrador');

        $this->post(route('login.store'), [
            'email' => 'primer.login@example.com',
            'password' => 'temporal123',
        ])->assertRedirect(route('password.change'));

        $this->get(route('urbanizaciones.select'))
            ->assertRedirect(route('password.change'));
    }

    public function test_usuario_puede_logout_aunque_tenga_cambio_obligatorio(): void
    {
        $user = $this->admin();
        $user->update(['must_change_password' => true]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_cambiar_password_marca_flag_en_false(): void
    {
        $user = $this->admin();
        $user->update([
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->post(route('password.change.update'), [
                'current_password' => 'temporal123',
                'password' => 'nueva-clave-segura',
                'password_confirmation' => 'nueva-clave-segura',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_email_duplicado_muestra_error_amigable(): void
    {
        $admin = $this->admin();

        $file = $this->csv([
            'nombre,email,password,rol,estado',
            'Duplicado,admin@impacto.test,123456,vendedor,activo',
        ]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion()->id])
            ->post(route('admin.usuarios.import.store'), ['archivo' => $file])
            ->assertRedirect(route('admin.usuarios.import'))
            ->assertSessionHas('import_errors', fn ($errors) => in_array('Fila 2: El email ya existe.', $errors, true));
    }

    public function test_rol_invalido_muestra_error_amigable(): void
    {
        $admin = $this->admin();

        $file = $this->csv([
            'nombre,email,password,rol,estado',
            'Rol Malo,rol.malo@example.com,123456,inventado,activo',
        ]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion()->id])
            ->post(route('admin.usuarios.import.store'), ['archivo' => $file])
            ->assertRedirect(route('admin.usuarios.import'))
            ->assertSessionHas('import_errors', fn ($errors) => in_array('Fila 2: Rol no válido.', $errors, true));
    }

    public function test_exportacion_no_incluye_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion()->id])
            ->get(route('admin.usuarios.export'))
            ->assertOk()
            ->assertDontSee('Impacto2026')
            ->assertDontSee('$2y$');
    }

    public function test_no_administrador_recibe_403_al_importar_exportar_y_plantilla(): void
    {
        $this->seed();
        $user = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacionId = $user->urbanizacionesAsignadas()->firstOrFail()->id;

        $this->actingAs($user)->withSession(['urbanizacion_id' => $urbanizacionId])->get(route('admin.usuarios.import'))->assertForbidden();
        $this->actingAs($user)->withSession(['urbanizacion_id' => $urbanizacionId])->post(route('admin.usuarios.import.store'), ['archivo' => $this->csv(['nombre,email,password,rol,estado'])])->assertForbidden();
        $this->actingAs($user)->withSession(['urbanizacion_id' => $urbanizacionId])->get(route('admin.usuarios.export'))->assertForbidden();
        $this->actingAs($user)->withSession(['urbanizacion_id' => $urbanizacionId])->get(route('admin.usuarios.template'))->assertForbidden();
    }

    private function admin(): User
    {
        $this->seed();

        return User::where('email', 'admin@impacto.test')->firstOrFail();
    }

    private function urbanizacion(): Urbanizacion
    {
        return Urbanizacion::where('estado', 'activa')->firstOrFail();
    }

    private function csv(array $lines): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'usuarios-test-');
        file_put_contents($path, implode("\n", $lines));

        return new UploadedFile($path, 'usuarios.csv', 'text/csv', null, true);
    }
}
