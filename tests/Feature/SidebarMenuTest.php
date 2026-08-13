<?php

namespace Tests\Feature;

use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_no_ve_ventas_caja_reportes_ni_administracion(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('Mapa de disponibilidad')
            ->assertSee('Lotes disponibles')
            ->assertSee('Mis reservas')
            ->assertDontSee(route('ventas.index'), false)
            ->assertDontSee(route('caja.index'), false)
            ->assertDontSee(route('reportes.index'), false)
            ->assertDontSee(route('admin.usuarios'), false);
    }

    public function test_administrador_ve_administracion(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee('Usuarios')
            ->assertSee('Roles y permisos')
            ->assertSee('data-menu-toggle', false)
            ->assertSee('type="button"', false);
    }

    public function test_finanzas_muestra_cobranza_y_caja_sin_modulo_visual_de_cuotas(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('cobranza.index'))
            ->assertOk()
            ->assertSee(route('cobranza.index'), false)
            ->assertSee(route('caja.index'), false)
            ->assertDontSee(route('cuotas.index'), false);
    }

    public function test_url_historica_de_cuotas_redirige_a_cobranza(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get('/cuotas')
            ->assertRedirect(route('cobranza.index'));
    }

    public function test_ruta_configuracion_comercial_deja_administracion_abierto(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('admin.configuracion'))
            ->assertOk()
            ->assertSee('data-menu-key="administracion"', false)
            ->assertSee('class="sidebar-group open active" data-menu-key="administracion"', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('Configuracion comercial');
    }

    public function test_ruta_usuarios_deja_administracion_abierto(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('admin.usuarios'))
            ->assertOk()
            ->assertSee('class="sidebar-group open active" data-menu-key="administracion"', false)
            ->assertSee('Usuarios');
    }

    public function test_menu_reportes_abre_en_rutas_reportes(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.cuotas'))
            ->assertOk()
            ->assertSee('class="sidebar-group open active" data-menu-key="reportes"', false)
            ->assertSee('Cuotas pendientes/vencidas');
    }

    public function test_supervisor_ve_equipo_comercial_limitado(): void
    {
        $this->seed();

        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $urbanizacion = $supervisor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($supervisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('asesores.index'))
            ->assertOk()
            ->assertSee('Equipo comercial')
            ->assertSee('Asesores de mi equipo')
            ->assertDontSee('Supervisores')
            ->assertDontSee('Grupos comerciales')
            ->assertDontSee('Administracion');
    }
}
