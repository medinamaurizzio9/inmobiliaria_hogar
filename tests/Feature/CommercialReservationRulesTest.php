<?php

namespace Tests\Feature;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommercialReservationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_asesor_crea_reserva_con_vencimiento_en_cinco_dias_habiles(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();
        $lote = $this->loteDisponible($urbanizacion);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('reservas.store'), [
                'cliente_id' => $cliente->id,
                'lote_id' => $lote->id,
                'fecha_reserva' => '2026-05-29',
                'fecha_vencimiento' => '2026-12-31',
                'monto_reserva' => 100,
                'tipo_operacion' => 'credito',
                'metodo_pago' => 'efectivo',
            ])
            ->assertRedirect(route('reservas.index'));

        $reserva = Reserva::where('lote_id', $lote->id)->firstOrFail();
        $this->assertTrue($reserva->fecha_vencimiento->isSameDay(Carbon::parse('2026-06-05')));
        $this->assertSame('credito', $reserva->tipo_operacion);
    }

    public function test_admin_puede_cambiar_dias_habiles_de_reserva(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();
        $lote = $this->loteDisponible($urbanizacion);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->put(route('admin.configuracion.update'), ['reserva_dias_habiles_asesor' => 3])
            ->assertRedirect();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('reservas.store'), [
                'cliente_id' => $cliente->id,
                'lote_id' => $lote->id,
                'fecha_reserva' => '2026-05-29',
                'fecha_vencimiento' => '2026-12-31',
                'monto_reserva' => 100,
                'tipo_operacion' => 'contado',
                'metodo_pago' => 'efectivo',
            ])
            ->assertRedirect(route('reservas.index'));

        $this->assertTrue(Reserva::where('lote_id', $lote->id)->firstOrFail()->fecha_vencimiento->isSameDay(Carbon::parse('2026-06-03')));
    }

    public function test_tipo_operacion_es_obligatorio(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();
        $lote = $this->loteDisponible($urbanizacion);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('reservas.store'), [
                'cliente_id' => $cliente->id,
                'lote_id' => $lote->id,
                'fecha_reserva' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays(7)->toDateString(),
                'monto_reserva' => 100,
                'metodo_pago' => 'efectivo',
            ])
            ->assertSessionHasErrors('tipo_operacion');
    }

    public function test_asesor_solo_ve_sus_reservas(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();
        $propia = $this->crearReserva($urbanizacion, $cliente, $vendedor, 'PROPIA-ASESOR');
        $otra = $this->crearReserva($urbanizacion, $cliente, User::where('email', 'admin@impacto.test')->firstOrFail(), 'NO-VISIBLE');

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.index'))
            ->assertOk()
            ->assertSee($propia->lote->codigo)
            ->assertDontSee($otra->lote->codigo);
    }

    public function test_supervisor_solo_ve_reservas_de_su_equipo(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        Asesor::create(['user_id' => $vendedor->id, 'supervisor_id' => $supervisor->id, 'nombre' => 'Asesor', 'apellido' => 'Demo', 'ci' => 'SUP-1', 'email' => 'asesor-equipo@test.local', 'activo' => true]);
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();
        $equipo = $this->crearReserva($urbanizacion, $cliente, $vendedor, 'EQUIPO-SUP');
        $ajena = $this->crearReserva($urbanizacion, $cliente, User::where('email', 'admin@impacto.test')->firstOrFail(), 'AJENA-SUP');

        $response = $this->actingAs($supervisor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.index'));

        $response->assertOk();
        $this->assertStringContainsString($equipo->lote->codigo, $response->getContent());
        $this->assertStringNotContainsString($ajena->lote->codigo, $response->getContent());
    }

    public function test_reporte_reservas_y_exportaciones_cargan(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.reservas', ['tipo_operacion' => 'credito']))
            ->assertOk()
            ->assertSee('Reporte de reservas')
            ->assertSee('Tipo operacion');

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.reservas.excel'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="reporte-reservas-impacto.xls"');

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.reservas.pdf'))
            ->assertOk();
    }

    public function test_reporte_mejor_vendedor_calcula_ranking_y_exporta(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.mejor-vendedor'))
            ->assertOk()
            ->assertSee('Ranking comercial');

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.mejor-vendedor.excel'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="reporte-mejor-vendedor-impacto.xls"');

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reportes.mejor-vendedor.pdf'))
            ->assertOk();
    }

    public function test_menu_comercial_no_muestra_disponibilidad_y_vendedor_no_ve_ventas(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Disponibilidad')
            ->assertDontSee(route('ventas.index'), false);
    }

    private function loteDisponible(Urbanizacion $urbanizacion): Lote
    {
        return Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))
            ->where('estado', 'disponible')
            ->firstOrFail();
    }

    private function crearReserva(Urbanizacion $urbanizacion, Cliente $cliente, User $user, string $codigoLote): Reserva
    {
        $lote = $this->loteDisponible($urbanizacion);
        $lote->update(['codigo' => $codigoLote, 'estado' => 'reservado']);

        return Reserva::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'usuario_id' => $user->id,
            'fecha_reserva' => now(),
            'fecha_vencimiento' => now()->addDays(5),
            'monto_reserva' => 100,
            'estado' => 'activa',
            'tipo_operacion' => 'contado',
        ]);
    }
}
