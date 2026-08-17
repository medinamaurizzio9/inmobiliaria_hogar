<?php

namespace Tests\Feature;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\GrupoComercial;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestSellerReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $sellerA;

    private User $sellerB;

    private GrupoComercial $groupA;

    private Urbanizacion $urbanizationA;

    private Urbanizacion $urbanizationB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->supervisor = User::factory()->create(['name' => 'Supervisor Ranking', 'estado' => 'activo']);
        $this->supervisor->assignRole('supervisor');
        $this->sellerA = User::factory()->create(['name' => 'Asesor Alfa', 'estado' => 'activo']);
        $this->sellerA->assignRole('vendedor');
        $this->sellerB = User::factory()->create(['name' => 'Asesor Beta', 'estado' => 'activo']);
        $this->sellerB->assignRole('vendedor');
        $this->groupA = GrupoComercial::create(['nombre' => 'Grupo Ranking A', 'supervisor_id' => $this->supervisor->id, 'activo' => true]);
        Asesor::create(['user_id' => $this->sellerA->id, 'supervisor_id' => $this->supervisor->id, 'grupo_comercial_id' => $this->groupA->id, 'nombre' => 'Asesor', 'apellido' => 'Alfa', 'ci' => 'RANK-A', 'email' => $this->sellerA->email, 'activo' => true]);
        Asesor::create(['user_id' => $this->sellerB->id, 'nombre' => 'Asesor', 'apellido' => 'Beta', 'ci' => 'RANK-B', 'email' => $this->sellerB->email, 'activo' => true]);
        [$this->urbanizationA, $this->urbanizationB] = Urbanizacion::query()->where('estado', 'activa')->take(2)->get()->all();
    }

    public function test_converted_sales_belong_to_reservation_seller_and_sum_final_price(): void
    {
        $this->sale($this->urbanizationA, $this->sellerA, 1200, now(), 'activa', true);
        $this->sale($this->urbanizationA, $this->sellerA, 2300, now(), 'completada', true);
        $this->sale($this->urbanizationA, $this->sellerA, 7000, now(), 'anulada');
        $this->sale($this->urbanizationA, $this->sellerA, 8000, now(), 'rescindida');
        $this->sale($this->urbanizationA, $this->sellerB, 9000);

        $response = $this->report(['usuario_id' => $this->sellerA->id]);
        $response->assertOk()->assertSee('<td>Asesor Alfa</td>', false)->assertSee('<td>2</td>', false)->assertSee('3,500.00');
        $response->assertDontSee('<td>Asesor Beta</td>', false);
        $response->assertDontSee('18,500.00');
    }

    public function test_urbanization_scope_and_global_scope_use_real_lot_relationship(): void
    {
        $this->sale($this->urbanizationA, $this->sellerA, 1100);
        $this->sale($this->urbanizationB, $this->sellerA, 2200);

        $this->report(['ambito' => 'urbanizacion'], $this->urbanizationA)
            ->assertSee($this->urbanizationA->nombre)->assertSee('1,100.00')->assertDontSee('3,300.00');
        $this->report(['ambito' => 'urbanizacion'], $this->urbanizationB)
            ->assertSee($this->urbanizationB->nombre)->assertSee('2,200.00')->assertDontSee('3,300.00');
        $this->report(['ambito' => 'global'], $this->urbanizationA)
            ->assertSee('Todas las urbanizaciones')->assertSee('<td>2</td>', false)->assertSee('3,300.00');
    }

    public function test_month_year_and_seller_filters_are_respected(): void
    {
        $this->sale($this->urbanizationA, $this->sellerA, 1010, now()->setDate(2026, 8, 5));
        $this->sale($this->urbanizationA, $this->sellerA, 2020, now()->setDate(2026, 7, 5));
        $this->sale($this->urbanizationA, $this->sellerB, 3030, now()->setDate(2025, 8, 5));

        $this->report(['mes' => 8, 'anio' => 2026, 'usuario_id' => $this->sellerA->id])
            ->assertSee('1,010.00')->assertDontSee('2,020.00')->assertDontSee('<td>Asesor Beta</td>', false);
    }

    public function test_supervisor_group_and_ranking_order_are_respected(): void
    {
        $this->sale($this->urbanizationA, $this->sellerA, 1000);
        $this->sale($this->urbanizationA, $this->sellerA, 1200);
        $this->sale($this->urbanizationA, $this->sellerB, 9000);

        $response = $this->report(['supervisor_id' => $this->supervisor->id, 'grupo_comercial_id' => $this->groupA->id]);
        $response->assertSee('<td>Asesor Alfa</td>', false)->assertDontSee('<td>Asesor Beta</td>', false);
        $content = $this->report()->getContent();
        $this->assertLessThan(strpos($content, 'Asesor Beta'), strpos($content, 'Asesor Alfa'));
    }

    public function test_exports_share_urbanization_and_global_scope(): void
    {
        $this->sale($this->urbanizationA, $this->sellerA, 1400);
        $this->sale($this->urbanizationB, $this->sellerA, 1600);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationA->id])
            ->get(route('reportes.mejor-vendedor.excel', ['ambito' => 'urbanizacion']))
            ->assertOk()->assertSee($this->urbanizationA->nombre)->assertSee('1400')->assertDontSee('3000');
        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationA->id])
            ->get(route('reportes.mejor-vendedor.excel', ['ambito' => 'global']))
            ->assertOk()->assertSee('Todas las urbanizaciones')->assertSee('3000');
        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationA->id])
            ->get(route('reportes.mejor-vendedor.pdf', ['ambito' => 'global']))
            ->assertOk();
    }

    private function report(array $filters = [], ?Urbanizacion $context = null)
    {
        return $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => ($context ?? $this->urbanizationA)->id])
            ->get(route('reportes.mejor-vendedor', $filters));
    }

    private function sale(Urbanizacion $urbanization, User $seller, float $price, $date = null, string $status = 'activa', bool $converted = false): Venta
    {
        $lot = Lote::query()->whereDoesntHave('venta')->whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanization->id))->firstOrFail();
        $client = Cliente::query()->where('urbanizacion_id', $urbanization->id)->first()
            ?? Cliente::create(['urbanizacion_id' => $urbanization->id, 'nombre' => 'Cliente '.$urbanization->id, 'documento' => 'RANK-'.$urbanization->id]);
        $reservation = $converted ? Reserva::create(['cliente_id' => $client->id, 'lote_id' => $lot->id, 'usuario_id' => $seller->id, 'fecha_reserva' => $date ?? now(), 'fecha_vencimiento' => ($date ?? now())->copy()->addDays(5), 'monto_reserva' => 100, 'estado' => 'convertida', 'tipo_operacion' => 'contado']) : null;

        return Venta::create(['lote_id' => $lot->id, 'cliente_id' => $client->id, 'user_id' => $converted ? $this->admin->id : $seller->id, 'reserva_id' => $reservation?->id, 'tipo_operacion' => 'contado', 'fecha_venta' => $date ?? now(), 'precio_final' => $price, 'cuota_inicial' => 0, 'numero_cuotas' => 0, 'estado' => $status]);
    }
}
