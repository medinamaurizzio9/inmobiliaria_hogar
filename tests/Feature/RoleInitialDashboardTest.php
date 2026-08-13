<?php

namespace Tests\Feature;

use App\Models\Asesor;
use App\Models\CashMovement;
use App\Models\GrupoComercial;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleInitialDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_starts_in_urbanization_selection_and_supervisor_in_dashboard(): void
    {
        $this->seed();

        $this->post(route('login.store'), ['email' => 'vendedor@impacto.test', 'password' => 'password'])
            ->assertRedirect(route('urbanizaciones.select'));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => 'supervisor@impacto.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertNotNull(session('urbanizacion_id'));
    }

    public function test_supervisor_dashboard_only_shows_assigned_team_and_groups(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $urbanizacion = $supervisor->urbanizacionesAsignadas()->firstOrFail();
        $own = Asesor::where('supervisor_id', $supervisor->id)->firstOrFail();
        $own->update(['nombre' => 'Asesor Propio', 'apellido' => 'Visible']);

        $otherSupervisor = User::factory()->create(['name' => 'Supervisor Ajeno']);
        $otherSupervisor->assignRole('supervisor');
        $otherSupervisor->urbanizacionesAsignadas()->sync([$urbanizacion->id => ['activo' => true]]);
        $otherGroup = GrupoComercial::create(['nombre' => 'Grupo Ajeno Oculto', 'supervisor_id' => $otherSupervisor->id, 'activo' => true]);
        $otherUser = User::factory()->create(['name' => 'Asesor Ajeno Oculto']);
        $otherUser->assignRole('vendedor');
        Asesor::create(['user_id' => $otherUser->id, 'supervisor_id' => $otherSupervisor->id, 'grupo_comercial_id' => $otherGroup->id, 'nombre' => 'Asesor Ajeno', 'apellido' => 'Oculto', 'email' => $otherUser->email, 'activo' => true]);

        $this->actingAs($supervisor)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard comercial')
            ->assertSee('Asesor Propio Visible')
            ->assertDontSee('Asesor Ajeno Oculto')
            ->assertDontSee('Grupo Ajeno Oculto')
            ->assertDontSee('Cobranza')
            ->assertDontSee('Caja');
    }

    public function test_administrative_role_has_precedence_over_supervisor_role(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $admin->assignRole('supervisor');

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aquí tienes el estado de la operación.')
            ->assertDontSee('Dashboard comercial');
    }

    public function test_administrative_and_financial_roles_start_in_operations_center(): void
    {
        $this->seed();

        foreach (['admin@impacto.test', 'gerente@impacto.test', 'cajero@impacto.test'] as $email) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));
            $this->assertNotNull(session('urbanizacion_id'));
            $this->post(route('logout'));
        }
    }

    public function test_operations_center_shows_pending_payment_alert_only_when_needed(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $response = $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'));
        $response->assertOk()->assertSee('Aquí tienes el estado de la operación.');

        $movement = CashMovement::query()->whereNotNull('sale_id')->firstOrFail();
        $movement->update(['estado' => 'pendiente_verificacion']);

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('pago pendiente de verificación')
            ->assertSee(route('cobranza.index').'#pendientes', false);
    }

    public function test_access_cards_follow_permissions(): void
    {
        $this->seed();
        $cajero = User::where('email', 'cajero@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($cajero)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cobranza')
            ->assertSee('Caja')
            ->assertDontSee('Nueva venta')
            ->assertDontSee('Reportes');
    }
}
