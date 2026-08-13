<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_no_muestra_credenciales_precargadas(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('js/password-toggle.js', false)
            ->assertSee('value=""', false)
            ->assertDontSee('admin@impacto.test', false)
            ->assertDontSee('gerente@impacto.test', false)
            ->assertDontSee('vendedor@impacto.test', false)
            ->assertDontSee('cliente@impacto.test', false)
            ->assertDontSee('Impacto2026', false)
            ->assertDontSee('value="password"', false);

        $source = file_get_contents(resource_path('views/auth/login.blade.php'));
        $this->assertStringContainsString('value="{{ old(\'email\') }}"', $source);
        $this->assertStringNotContainsString("old('email',", $source);
        $this->assertStringNotContainsString('value="password"', $source);
    }

    public function test_toggle_de_contrasena_se_carga_en_vistas_con_password(): void
    {
        $this->seed();

        $admin = User::factory()->create([
            'email' => 'toggle.password@example.com',
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);
        $admin->assignRole('administrador');

        $this->actingAs($admin)
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('js/password-toggle.js', false)
            ->assertSee('type="password"', false);

        $script = file_get_contents(public_path('js/password-toggle.js'));

        $this->assertStringContainsString('Mostrar contraseña', $script);
        $this->assertStringContainsString('Ocultar contraseña', $script);
        $this->assertStringContainsString('input[type="password"]', $script);
    }

    public function test_login_rellena_solo_email_antiguo_despues_de_error(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'persona@example.com',
                'password' => 'clave-incorrecta',
            ])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('value="persona@example.com"', false)
            ->assertDontSee('value="clave-incorrecta"', false);
    }

    public function test_usuario_con_must_change_password_cambia_una_sola_vez(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'email' => 'cambio.seguro@example.com',
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);
        $user->assignRole('administrador');

        $this->post(route('login.store'), [
            'email' => 'cambio.seguro@example.com',
            'password' => 'temporal123',
        ])->assertRedirect(route('password.change'));

        $this->post(route('password.change.update'), [
            'current_password' => 'temporal123',
            'password' => 'nueva-clave-segura',
            'password_confirmation' => 'nueva-clave-segura',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'email' => 'cambio.seguro@example.com',
            'password' => 'nueva-clave-segura',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_usuario_sin_must_change_password_no_ve_formulario_de_cambio_obligatorio(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'email' => 'sin.cambio@example.com',
            'password' => Hash::make('clave-normal'),
            'must_change_password' => false,
        ]);
        $user->assignRole('administrador');

        $this->actingAs($user)
            ->get(route('password.change'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_logout_por_post_cierra_sesion_y_redirige_a_login(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@impacto.test')->firstOrFail();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_menu_no_usa_logout_por_get(): void
    {
        $source = file_get_contents(resource_path('views/layouts/partials/sidebar.blade.php'));

        $this->assertStringContainsString('method="POST"', $source);
        $this->assertStringContainsString("route('logout')", $source);
        $this->assertStringNotContainsString('href="{{ route(\'logout\') }}"', $source);
        $this->assertStringNotContainsString('href="/logout"', $source);
    }
}
