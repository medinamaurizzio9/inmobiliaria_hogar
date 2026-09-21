<?php

namespace Tests\Feature;

use App\Models\ClientCommercialEvent;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\UrbanizacionCommercialSetting;
use App\Models\User;
use App\Models\Venta;
use App\Services\ClientAccountProvisioner;
use App\Services\ClientCommercialEventService;
use App\Services\ClientCommercialProtectionService;
use App\Services\CommercialSettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientCommercialProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_new_document_creates_one_protected_client_with_original_and_responsible_advisor(): void
    {
        [$admin, $urbanizacion] = $this->context();

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Persona única',
                'documento' => '7.519.283-A',
                'telefono' => '+591 700 12345',
                'email' => 'PERSONA@EXAMPLE.COM',
            ])->assertRedirect(route('clientes.index'));

        $cliente = Cliente::where('documento_normalizado', '7519283A')->firstOrFail();
        $this->assertSame($admin->id, $cliente->created_by);
        $this->assertSame($admin->id, $cliente->responsible_user_id);
        $this->assertSame('70012345', $cliente->telefono_normalizado);
        $this->assertSame('persona@example.com', $cliente->email_normalizado);
        $this->assertSame(30, (int) $cliente->protected_at->diffInDays($cliente->protection_expires_at));
    }

    public function test_exact_document_in_another_format_is_blocked_by_backend_and_audited(): void
    {
        [$admin, $urbanizacion] = $this->context();
        $existing = Cliente::whereNotNull('documento')->firstOrFail();
        $formatted = implode('.', str_split((string) $existing->documento, 2));
        $count = Cliente::count();

        $this->from(route('clientes.create'))->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), ['nombre' => 'No duplicar', 'documento' => $formatted])
            ->assertRedirect(route('clientes.create'))
            ->assertSessionHas('duplicate_cliente_id', $existing->id);

        $this->assertSame($count, Cliente::count());
        $this->assertDatabaseHas('client_commercial_events', [
            'cliente_id' => $existing->id,
            'event_type' => ClientCommercialEvent::DUPLICATE_DOCUMENT,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_exact_match_endpoint_returns_real_client_commercial_summary(): void
    {
        [$admin, $urbanizacion] = $this->context();
        $cliente = Cliente::whereNotNull('documento')->with('createdBy')->firstOrFail();

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->getJson(route('clientes.matches', ['documento' => $cliente->documento]))
            ->assertOk()
            ->assertJsonPath('exact.id', $cliente->id)
            ->assertJsonPath('exact.registered_by', $cliente->createdBy?->name)
            ->assertJsonStructure(['exact' => ['protection', 'properties', 'operations_count']]);
    }

    public function test_phone_match_warns_without_merging_and_confirmation_allows_different_person(): void
    {
        [$admin, $urbanizacion] = $this->context();
        $existing = Cliente::firstOrFail();
        $existing->update(['telefono' => '+591 700 77777']);
        $count = Cliente::count();

        $payload = ['nombre' => 'Familiar diferente', 'documento' => 'FAM-9001', 'telefono' => '70077777'];
        $this->from(route('clientes.create'))->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), $payload)
            ->assertRedirect(route('clientes.create'))
            ->assertSessionHas('potential_client_matches');
        $this->assertSame($count, Cliente::count());
        $this->assertDatabaseHas('client_commercial_events', ['cliente_id' => $existing->id, 'event_type' => ClientCommercialEvent::PHONE_MATCH]);

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [...$payload, 'confirm_potential_match' => 1])
            ->assertRedirect(route('clientes.index'));
        $this->assertSame($count + 1, Cliente::count());
    }

    public function test_email_match_is_a_non_blocking_warning(): void
    {
        [$admin, $urbanizacion] = $this->context();
        $existing = Cliente::firstOrFail();
        $existing->update(['email' => 'familia@example.com']);

        $this->from(route('clientes.create'))->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), ['nombre' => 'Otra persona', 'documento' => 'EMAIL-2', 'email' => 'FAMILIA@example.com'])
            ->assertSessionHas('potential_client_matches');

        $this->assertDatabaseHas('client_commercial_events', ['cliente_id' => $existing->id, 'event_type' => ClientCommercialEvent::EMAIL_MATCH]);
    }

    public function test_protection_uses_45_days_after_configuration_change(): void
    {
        [$admin, $urbanizacion] = $this->context();
        UrbanizacionCommercialSetting::updateOrCreate(['urbanizacion_id' => $urbanizacion->id], ['proteccion_cliente_dias' => 45]);
        $this->assertSame(45, app(CommercialSettingsService::class)->clientProtectionDays($urbanizacion->id));

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), ['nombre' => 'Protección 45', 'documento' => 'PROT-45'])
            ->assertRedirect(route('clientes.index'));

        $cliente = Cliente::where('documento_normalizado', 'PROT45')->firstOrFail();
        $this->assertSame(45, (int) $cliente->protected_at->diffInDays($cliente->protection_expires_at));
    }

    public function test_expired_protection_is_calculated_without_automatic_renewal(): void
    {
        $cliente = Cliente::firstOrFail();
        $cliente->update(['protected_at' => now()->subDays(40), 'protection_expires_at' => now()->subDays(10)]);

        $status = app(ClientCommercialProtectionService::class)->getCommercialProtection($cliente->fresh());

        $this->assertFalse($status['active']);
        $this->assertSame('expired', $status['status']);
        $this->assertSame(0, $status['days_remaining']);
    }

    public function test_one_client_can_have_sales_in_two_urbanizations_and_one_portal_account(): void
    {
        $sale = Venta::with('cliente', 'lote.manzano')->firstOrFail();
        $cliente = $sale->cliente;
        $otherLot = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', '!=', $sale->lote->manzano->urbanizacion_id))
            ->where('estado', 'disponible')->firstOrFail();
        $clientsBefore = Cliente::count();
        $salesBefore = $cliente->ventas()->count();

        Venta::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $otherLot->id,
            'user_id' => $sale->user_id,
            'tipo_operacion' => 'credito',
            'fecha_venta' => now(),
            'precio_final' => 25000,
            'cuota_inicial' => 5000,
            'saldo_financiar' => 20000,
            'numero_cuotas' => 2,
            'estado' => 'activa',
        ]);

        $provisioner = app(ClientAccountProvisioner::class);
        $provisioner->provision($cliente);
        $provisioner->provision($cliente);

        $this->assertSame($clientsBefore, Cliente::count());
        $this->assertSame($salesBefore + 1, $cliente->ventas()->count());
        $this->assertSame(1, User::where('cliente_id', $cliente->id)->count());
        $this->assertSame(2, $cliente->ventas()->whereHas('lote.manzano', fn ($query) => $query->whereIn('urbanizacion_id', [$sale->lote->manzano->urbanizacion_id, $otherLot->manzano->urbanizacion_id]))->distinct('lote_id')->count('lote_id'));
    }

    public function test_credit_installments_remain_independent_per_sale(): void
    {
        $cliente = Cliente::firstOrFail();
        $sales = Venta::where('cliente_id', $cliente->id)->limit(2)->get();
        if ($sales->count() < 2) {
            $base = Venta::firstOrFail();
            $lot = Lote::whereKeyNot($base->lote_id)->where('estado', 'disponible')->firstOrFail();
            $sales->push(Venta::create([
                'cliente_id' => $cliente->id, 'lote_id' => $lot->id, 'user_id' => $base->user_id,
                'tipo_operacion' => 'credito', 'fecha_venta' => now(), 'precio_final' => 2000,
                'cuota_inicial' => 0, 'saldo_financiar' => 2000, 'numero_cuotas' => 1, 'estado' => 'activa',
            ]));
        }

        Cuota::create(['venta_id' => $sales[0]->id, 'numero' => 90, 'fecha_programada' => now()->addMonth(), 'fecha_vencimiento' => now()->addMonth(), 'monto' => 100, 'saldo_pendiente' => 100, 'estado' => 'pendiente']);
        Cuota::create(['venta_id' => $sales[1]->id, 'numero' => 91, 'fecha_programada' => now()->addMonth(), 'fecha_vencimiento' => now()->addMonth(), 'monto' => 250, 'saldo_pendiente' => 250, 'estado' => 'pendiente']);

        $this->assertSame(100.0, (float) Cuota::where('venta_id', $sales[0]->id)->where('numero', 90)->value('saldo_pendiente'));
        $this->assertSame(250.0, (float) Cuota::where('venta_id', $sales[1]->id)->where('numero', 91)->value('saldo_pendiente'));
    }

    public function test_new_reservation_on_protected_client_is_allowed_and_audited(): void
    {
        $seller = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $seller->urbanizacionesAsignadas()->firstOrFail();
        $cliente = Cliente::create([
            'urbanizacion_id' => $urbanizacion->id,
            'created_by' => $seller->id,
            'responsible_user_id' => $seller->id,
            'nombre' => 'Cliente protegido asesor',
            'documento' => 'ASESOR-PROT-1',
            'protected_at' => now(),
            'protection_expires_at' => now()->addDays(30),
        ]);
        $lot = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->where('estado', 'disponible')->firstOrFail();

        $this->actingAs($seller)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('reservas.store'), [
                'cliente_id' => $cliente->id,
                'lote_id' => $lot->id,
                'fecha_reserva' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays(5)->toDateString(),
                'monto_reserva' => 0,
                'tipo_operacion' => 'contado',
            ])->assertRedirect(route('reservas.index'));

        $this->assertDatabaseHas('client_commercial_events', [
            'cliente_id' => $cliente->id,
            'event_type' => ClientCommercialEvent::PROTECTED_OPERATION,
            'actor_user_id' => $seller->id,
        ]);
    }

    public function test_vendor_client_view_does_not_expose_financial_totals(): void
    {
        $seller = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $seller->urbanizacionesAsignadas()->firstOrFail();
        $cliente = Cliente::create([
            'urbanizacion_id' => $urbanizacion->id,
            'created_by' => $seller->id,
            'responsible_user_id' => $seller->id,
            'nombre' => 'Cliente visible del asesor',
            'documento' => 'ASESOR-VIEW-1',
            'protected_at' => now(),
            'protection_expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($seller)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertSee('Control comercial')
            ->assertSee('Propiedades / compras')
            ->assertDontSee('Total vendido')
            ->assertDontSee('Saldo pendiente')
            ->assertDontSee('PDF estado de cuenta');

        $this->get(route('clientes.estado-cuenta.pdf', $cliente))->assertForbidden();
        $this->get(route('clientes.pdf', $cliente))->assertForbidden();
    }

    public function test_manager_can_view_report_and_supervisor_only_sees_team_events(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $manager = User::where('email', 'gerente@impacto.test')->firstOrFail();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $seller = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $supervisor->urbanizacionesAsignadas()->firstOrFail();
        $teamClient = Cliente::create([
            'urbanizacion_id' => $urbanizacion->id,
            'created_by' => $seller->id,
            'responsible_user_id' => $seller->id,
            'nombre' => 'Cliente exclusivo del equipo',
            'documento' => 'TEAM-REPORT-1',
            'protected_at' => now(),
            'protection_expires_at' => now()->addDays(30),
        ]);
        $outsideClient = Cliente::where('created_by', $admin->id)->firstOrFail();
        $events = app(ClientCommercialEventService::class);
        $events->registerCommercialEvent($teamClient, ClientCommercialEvent::PHONE_MATCH, $seller, $urbanizacion->id, 'phone');
        $events->registerCommercialEvent($outsideClient, ClientCommercialEvent::EMAIL_MATCH, $admin, $urbanizacion->id, 'email');

        $this->actingAs($manager)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.control-comercial-clientes'))->assertOk()->assertSee($teamClient->nombre)->assertSee($outsideClient->nombre);

        $this->actingAs($supervisor)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.control-comercial-clientes'))->assertOk()->assertSee($teamClient->nombre)->assertDontSee($outsideClient->nombre);
    }

    public function test_administrator_report_works_when_optional_supervisor_role_does_not_exist(): void
    {
        [$admin, $urbanizacion] = $this->context();
        Role::where('name', 'supervisor')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.control-comercial-clientes'))
            ->assertOk()
            ->assertSee('Control comercial de clientes');
    }

    public function test_database_unique_normalized_document_blocks_a_race_duplicate(): void
    {
        [$admin, $urbanizacion] = $this->context();
        Cliente::create(['urbanizacion_id' => $urbanizacion->id, 'created_by' => $admin->id, 'nombre' => 'Primera', 'documento' => 'RACE-100']);

        $this->expectException(QueryException::class);
        Cliente::create(['urbanizacion_id' => $urbanizacion->id, 'created_by' => $admin->id, 'nombre' => 'Segunda', 'documento' => 'R A C E 100']);
    }

    public function test_form_has_responsive_backend_backed_match_structure(): void
    {
        $view = file_get_contents(resource_path('views/clientes/form.blade.php'));
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('data-client-match-form', $view);
        $this->assertStringContainsString("route('clientes.matches')", $view);
        $this->assertStringContainsString('confirm_potential_match', $view);
        $this->assertStringContainsString('@media(max-width:600px)', $css);
        $this->assertStringContainsString('.commercial-match-card', $css);
    }

    /** @return array{User, Urbanizacion} */
    private function context(): array
    {
        return [
            User::where('email', 'admin@impacto.test')->firstOrFail(),
            Urbanizacion::firstOrFail(),
        ];
    }
}
