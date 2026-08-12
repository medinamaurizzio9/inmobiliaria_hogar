<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\GrupoComercial;
use App\Models\SupervisorProfile;
use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemConfigurationAndCommercialStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_supervisor_con_usuario(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('supervisores.store'), [
                'nombre' => 'Supervisor Norte',
                'ci' => 'SUP-900',
                'celular' => '70009000',
                'email' => 'supervisor.norte@test.local',
                'direccion' => 'Zona Norte',
                'activo' => 1,
            ])
            ->assertRedirect(route('supervisores.index'));

        $profile = SupervisorProfile::where('ci', 'SUP-900')->firstOrFail();
        $this->assertTrue($profile->user->hasRole('supervisor'));
        $this->assertTrue(Hash::check('SUP-900', $profile->user->password));
        $this->assertTrue($profile->user->must_change_password);
    }

    public function test_admin_ve_boton_eliminar_supervisor(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('supervisores.index'))
            ->assertOk()
            ->assertSee('Eliminar');
    }

    public function test_administrador_puede_eliminar_supervisor_sin_historial(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('supervisores.store'), [
                'nombre' => 'Supervisor Eliminar',
                'ci' => 'SUP-DEL',
                'celular' => '70009001',
                'email' => 'supervisor.eliminar@test.local',
                'direccion' => 'Zona Norte',
                'activo' => 1,
            ])
            ->assertRedirect(route('supervisores.index'));

        $profile = SupervisorProfile::where('ci', 'SUP-DEL')->firstOrFail();
        $userId = $profile->user_id;

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->delete(route('supervisores.destroy', $profile))
            ->assertRedirect()
            ->assertSessionHas('status', 'Usuario eliminado correctamente.');

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('supervisor_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'User',
            'modelo_id' => $userId,
            'accion' => 'eliminar_supervisor',
        ]);
    }

    public function test_administrador_no_elimina_supervisor_con_equipo_y_muestra_detalle(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();
        $profile = SupervisorProfile::whereHas('user', fn ($query) => $query->where('email', 'supervisor@impacto.test'))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->delete(route('supervisores.destroy', $profile))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $profile->user_id]);
        $this->assertDatabaseHas('supervisor_profiles', ['id' => $profile->id, 'activo' => true]);
        $this->assertStringContainsString('asesores asignados', session('errors')->first('delete'));
    }

    public function test_usuario_no_admin_recibe_403_al_eliminar_supervisor(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $supervisor = SupervisorProfile::firstOrFail();
        $urbanizacionId = $vendedor->urbanizacionesAsignadas()->firstOrFail()->id;

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->delete(route('supervisores.destroy', $supervisor))
            ->assertForbidden();
    }

    public function test_admin_puede_crear_grupo_comercial(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('grupos-comerciales.store'), [
                'nombre' => 'Grupo Comercial Test',
                'descripcion' => 'Grupo para pruebas',
                'supervisor_id' => $supervisor->id,
                'activo' => 1,
            ])
            ->assertRedirect(route('grupos-comerciales.index'));

        $this->assertDatabaseHas('grupos_comerciales', ['nombre' => 'Grupo Comercial Test', 'supervisor_id' => $supervisor->id]);
    }

    public function test_asigna_asesor_a_grupo(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $grupo = GrupoComercial::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('asesores.store'), [
                'nombre' => 'Asesor',
                'apellido' => 'Grupo',
                'ci' => 'ASE-900',
                'celular' => '70009111',
                'email' => 'asesor.grupo@test.local',
                'direccion' => 'Zona comercial',
                'supervisor_id' => $supervisor->id,
                'grupo_comercial_id' => $grupo->id,
                'urbanizaciones' => [$urbanizacion->id],
                'activo' => 1,
            ])
            ->assertRedirect(route('asesores.index'));

        $this->assertDatabaseHas('asesores', ['ci' => 'ASE-900', 'grupo_comercial_id' => $grupo->id]);
    }

    public function test_supervisor_solo_ve_su_grupo(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $otroSupervisor = User::factory()->create(['name' => 'Otro Supervisor', 'email' => 'otro.supervisor@test.local']);
        $otroSupervisor->assignRole('supervisor');
        GrupoComercial::create(['nombre' => 'Grupo Oculto', 'supervisor_id' => $otroSupervisor->id, 'activo' => true]);

        $this->actingAs($supervisor)
            ->get(route('grupos-comerciales.index'))
            ->assertOk()
            ->assertSee('Grupo Norte')
            ->assertDontSee('Grupo Oculto');
    }

    public function test_nombre_y_logo_del_sistema_son_configurables(): void
    {
        Storage::fake('public');
        [$admin, $urbanizacion] = $this->adminContext();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->put(route('admin.configuracion-general.update'), [
                'system_name' => 'URBANIZACIONES DEMO',
                'system_subtitle' => 'Panel comercial',
                'company_name' => 'Empresa Demo',
                'primary_color' => '#123456',
                'secondary_color' => '#654321',
                'logo_main' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'system_name', 'value' => 'URBANIZACIONES DEMO']);
        $this->assertNotEmpty(SystemSetting::where('key', 'logo_main')->value('value'));

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('URBANIZACIONES DEMO')
            ->assertSee('Panel comercial')
            ->assertSee(Storage::disk('public')->url(SystemSetting::where('key', 'logo_main')->value('value')), false)
            ->assertDontSee('/storage/storage/', false);
    }

    public function test_fondo_del_login_es_configurable_y_se_muestra_en_el_acceso(): void
    {
        Storage::fake('public');
        [$admin, $urbanizacion] = $this->adminContext();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->put(route('admin.configuracion-general.update'), [
                'system_name' => 'INMOLIDER VL CRM',
                'system_subtitle' => 'Sistema Integral de Terrenos',
                'primary_color' => '#123456',
                'secondary_color' => '#654321',
                'logo_main' => UploadedFile::fake()->image('principal.png'),
                'logo_login' => UploadedFile::fake()->image('login.png'),
                'login_background' => UploadedFile::fake()->image('fondo-login.webp', 1600, 900),
            ])
            ->assertRedirect();

        $background = SystemSetting::where('key', 'login_background')->value('value');

        $this->assertNotEmpty($background);
        Storage::disk('public')->assertExists($background);

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('login-page has-background', false)
            ->assertSee('storage/'.$background, false)
            ->assertSee('login-card', false)
            ->assertSee('login-logo', false);
    }

    public function test_logo_inexistente_no_renderiza_imagen_rota_y_conserva_branding(): void
    {
        Storage::fake('public');
        [$admin, $urbanizacion] = $this->adminContext();
        SystemSetting::updateOrCreate(['key' => 'system_name'], ['value' => 'HOGAR INMOBILIARIA']);
        SystemSetting::updateOrCreate(['key' => 'system_subtitle'], ['value' => 'Tu terreno, tu hogar']);
        SystemSetting::updateOrCreate(['key' => 'logo_main'], ['value' => 'storage/logos/inexistente.png']);
        SystemSetting::updateOrCreate(['key' => 'logo_login'], ['value' => 'logos/inexistente.png']);
        Cache::forget('system_settings.all');

        auth()->logout();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('HOGAR INMOBILIARIA')
            ->assertSee('Tu terreno, tu hogar')
            ->assertDontSee('login-logo', false)
            ->assertDontSee('/storage/storage/', false);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('HOGAR INMOBILIARIA')
            ->assertDontSee('storage/logos/inexistente.png', false);
    }

    public function test_recibo_pdf_usa_datos_configurados(): void
    {
        [$admin, $urbanizacion] = $this->adminContext();
        SystemSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Empresa Recibo Test']);
        SystemSetting::updateOrCreate(['key' => 'footer_text'], ['value' => 'Pie configurado para recibos']);
        $movimiento = CashMovement::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('pdf.recibo', $movimiento))
            ->assertOk();
    }

    private function adminContext(): array
    {
        $this->seed();

        return [
            User::where('email', 'admin@impacto.test')->firstOrFail(),
            Urbanizacion::firstOrFail(),
        ];
    }
}
