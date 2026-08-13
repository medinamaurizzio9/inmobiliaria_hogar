<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompradorUsuarioManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_listados_separan_usuarios_internos_y_compradores(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $clienteUser = User::role('cliente')->firstOrFail();

        $urbanizacion = Urbanizacion::firstOrFail();
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('admin.usuarios'))->assertOk()->assertDontSee($clienteUser->email);
        $this->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('admin.compradores'))->assertOk()->assertSee('Usuarios compradores')->assertSee($clienteUser->cliente->nombre);
    }

    public function test_reset_comprador_usa_ci_hasheado_obliga_cambio_e_invalida_sesion(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $user = User::role('cliente')->whereNotNull('cliente_id')->firstOrFail();
        $user->cliente->update(['documento' => '6398241']);
        $user->update(['must_change_password' => false]);
        DB::table('sessions')->insert(['id' => 'comprador-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs($admin)->withSession(['urbanizacion_id' => Urbanizacion::firstOrFail()->id])->post(route('admin.compradores.reset-password', $user->cliente))->assertRedirect();

        $this->assertTrue(Hash::check('6398241', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'resetear_password_comprador', 'modelo_id' => $user->cliente_id]);
    }

    public function test_reset_elimina_solo_sesiones_del_comprador_afectado(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $compradorA = User::role('cliente')->firstOrFail();
        $compradorA->cliente->update(['documento' => 'CI-A']);
        $clienteB = Cliente::whereKeyNot($compradorA->cliente_id)->has('ventas')->firstOrFail();
        $compradorB = User::factory()->create(['cliente_id' => $clienteB->id]);
        $compradorB->assignRole('cliente');

        foreach ([[$compradorA, 'session-a'], [$compradorB, 'session-b'], [$admin, 'session-admin']] as [$user, $id]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp]);
        }

        $this->actingAs($admin)->withSession(['urbanizacion_id' => Urbanizacion::firstOrFail()->id])->post(route('admin.compradores.reset-password', $compradorA->cliente))->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-b', 'user_id' => $compradorB->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'session-admin', 'user_id' => $admin->id]);
    }

    public function test_comprador_siempre_resuelve_cliente_foto_y_usuario_interno_su_propia_foto(): void
    {
        $comprador = User::role('cliente')->firstOrFail();
        $comprador->cliente->update(['foto' => 'clientes/cliente.jpg']);
        $comprador->forceFill(['foto' => 'usuarios/historica.jpg'])->save();
        $interno = User::whereNull('cliente_id')->firstOrFail();
        $interno->forceFill(['foto' => 'usuarios/interno.jpg'])->save();

        $this->assertSame('clientes/cliente.jpg', $comprador->fresh()->profilePhotoPath());
        $this->assertSame('usuarios/interno.jpg', $interno->fresh()->profilePhotoPath());
    }

    public function test_pantallas_de_comprador_renderizan_cliente_foto_aunque_user_foto_sea_distinta(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('clientes/oficial.jpg', 'foto');
        Storage::disk('public')->put('usuarios/historica.jpg', 'foto');
        $comprador = User::role('cliente')->firstOrFail();
        $comprador->cliente->update(['foto' => 'clientes/oficial.jpg']);
        $comprador->forceFill(['foto' => 'usuarios/historica.jpg'])->save();

        $this->actingAs($comprador)->get(route('clientes.mi-cuenta'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('clientes/oficial.jpg'), false)
            ->assertDontSee(Storage::disk('public')->url('usuarios/historica.jpg'), false);
    }

    public function test_foto_comprador_se_valida_almacena_y_elimina(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $cliente = User::role('cliente')->firstOrFail()->cliente;

        $this->actingAs($admin)->withSession(['urbanizacion_id' => Urbanizacion::firstOrFail()->id])->post(route('admin.compradores.foto.update', $cliente), ['foto' => UploadedFile::fake()->image('cliente.webp')])->assertRedirect();
        Storage::disk('public')->assertExists($cliente->fresh()->foto);

        $path = $cliente->fresh()->foto;
        $this->delete(route('admin.compradores.foto.destroy', $cliente))->assertRedirect();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($cliente->fresh()->foto);
    }

    public function test_no_administrador_no_gestiona_compradores(): void
    {
        $cliente = Cliente::has('ventas')->firstOrFail();
        $this->actingAs(User::role('vendedor')->firstOrFail())->withSession(['urbanizacion_id' => Urbanizacion::firstOrFail()->id])->post(route('admin.compradores.reset-password', $cliente))->assertForbidden();
    }
}
