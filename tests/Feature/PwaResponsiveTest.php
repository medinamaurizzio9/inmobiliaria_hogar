<?php

namespace Tests\Feature;

use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_public_valid_and_contains_installation_metadata(): void
    {
        $this->get(route('pwa.manifest'))->assertOk();
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Hogar Inmobiliaria', $manifest['name']);
        $this->assertSame('Hogar', $manifest['short_name']);
        $this->assertSame('/login', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertContains('192x192', array_column($manifest['icons'], 'sizes'));
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));
        $this->assertFileExists(public_path('pwa/icon-192.png'));
        $this->assertFileExists(public_path('pwa/icon-512.png'));
    }

    public function test_service_worker_and_safe_offline_page_are_public(): void
    {
        $this->get(route('pwa.service-worker'))->assertOk();
        $worker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString('fetch(request).catch(() => caches.match(OFFLINE_URL))', $worker);
        $this->assertStringNotContainsString("'/pagos'", $worker);
        $this->assertStringNotContainsString("'/ventas'", $worker);
        $this->assertStringNotContainsString("'/reservas'", $worker);

        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('Sin conexión')
            ->assertSee('Algunas funciones requieren internet.');
    }

    public function test_pwa_routes_do_not_change_laravel_authentication_flow(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('pwa.manifest'))->assertOk();
        $this->get(route('pwa.service-worker'))->assertOk();
        $this->get(route('login'))->assertOk()->assertSee('manifest.webmanifest', false);
    }

    public function test_mobile_login_remember_checkbox_keeps_compact_accessible_dimensions(): void
    {
        $portal = $this->get(route('login'))->assertOk();
        $css = file_get_contents(public_path('css/app.css'));
        $javascript = file_get_contents(public_path('js/public-portal.js'));

        $portal->assertSee('class="login-remember"', false)
            ->assertSee('type="checkbox" name="remember" value="1"', false);
        $this->assertStringContainsString('.mobile-login .login-remember input[type="checkbox"]', $css);
        $this->assertStringContainsString('width:18px', $css);
        $this->assertStringContainsString('min-height:18px', $css);
        $this->assertStringContainsString('.mobile-login input:not([type="checkbox"]):not([type="radio"])', $css);
        $this->assertStringContainsString("if (!menu?.classList.contains('open')", $javascript);
    }

    public function test_supervisor_and_advisor_dashboards_render_mobile_structure(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $advisor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $advisor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($supervisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard comercial')
            ->assertSee('responsive-table', false)
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('manifest.webmanifest', false);

        $this->actingAs($advisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('advisor-mobile-priorities', false)
            ->assertSee('Nueva reserva')
            ->assertSee('Ver lotes')
            ->assertSee('Clientes')
            ->assertSee('Mi perfil');
    }

    public function test_client_portal_does_not_render_internal_commercial_modules(): void
    {
        $this->seed();
        $client = User::where('email', 'cliente@impacto.test')->firstOrFail();

        $this->actingAs($client)
            ->get(route('clientes.mi-cuenta'))
            ->assertOk()
            ->assertDontSee(route('ventas.index'), false)
            ->assertDontSee(route('reservas.index'), false)
            ->assertDontSee(route('mapa'), false);
    }

    public function test_public_portal_and_urbanization_page_keep_pwa_and_touch_map_support(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::query()->whereNotNull('slug')->firstOrFail();
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $this->get('/')
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('public-portal.js', false);

        $this->get(route('disponibilidad.urbanizacion', $urbanizacion->slug))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('plan-map-viewport', false)
            ->assertSee('map-zoom.js', false)
            ->assertSee('data-lot-dialog', false);
    }

    public function test_commercial_lists_and_forms_expose_responsive_hooks(): void
    {
        $this->seed();
        $advisor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $advisor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($advisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('responsive-table commercial-list', false);

        $this->actingAs($advisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.index'))
            ->assertOk()
            ->assertSee('responsive-table commercial-list', false);

        $this->actingAs($advisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.create'))
            ->assertOk()
            ->assertSee('class="form card"', false);

        $clientList = file_get_contents(resource_path('views/clientes/index.blade.php'));
        $reservationList = file_get_contents(resource_path('views/reservas/index.blade.php'));
        $this->assertStringContainsString('data-label="Teléfono"', $clientList);
        $this->assertStringContainsString('data-label="Acciones"', $reservationList);
    }
}
