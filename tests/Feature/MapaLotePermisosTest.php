<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MapaLotePermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_ve_reservar_lote_y_no_vender_ni_editar(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))
            ->where('estado', 'disponible')
            ->firstOrFail();
        $lote->update(['coord_x' => 40, 'coord_y' => 40]);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('data-modal-link="reservar"', false)
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertDontSee('data-can-reservar=', false)
            ->assertDontSee('data-can-vender=', false)
            ->assertDontSee('data-can-editar=', false);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('permisos.reservar', true)
            ->assertJsonPath('permisos.vender', false)
            ->assertJsonPath('permisos.editar', false);
    }

    public function test_vendedor_no_puede_acceder_a_crear_venta_por_url(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('ventas.create'))
            ->assertForbidden();
    }

    public function test_vendedor_no_puede_editar_lote_por_url(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('lotes.edit', $lote))
            ->assertForbidden();
    }

    public function test_vendedor_puede_crear_reserva_de_lote_disponible_asignado(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $cliente = Cliente::firstOrFail();
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))
            ->where('estado', 'disponible')
            ->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('reservas.store'), [
                'cliente_id' => $cliente->id,
                'lote_id' => $lote->id,
                'fecha_reserva' => now()->toDateString(),
                'fecha_vencimiento' => now()->addDays(90)->toDateString(),
                'monto_reserva' => 100,
                'tipo_operacion' => 'contado',
                'metodo_pago' => 'efectivo',
            ])
            ->assertRedirect(route('reservas.index'));

        $reserva = Reserva::where('lote_id', $lote->id)->latest()->firstOrFail();
        $this->assertSame($vendedor->id, $reserva->usuario_id);
        $this->assertTrue($reserva->fecha_vencimiento->isSameDay(now()->addWeekdays(5)));
        $this->assertSame('reservado', $lote->fresh()->estado);
    }

    public function test_vendedor_no_puede_reservar_lote_de_urbanizacion_no_asignada(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacionAsignada = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $otraUrbanizacion = Urbanizacion::create([
            'nombre' => 'Urbanizacion no asignada',
            'ubicacion' => 'Santa Cruz',
            'descripcion' => 'Sin asignacion para vendedor',
            'superficie_total' => 10000,
            'estado' => 'activa',
        ]);
        $manzano = Manzano::create([
            'urbanizacion_id' => $otraUrbanizacion->id,
            'codigo' => 'NA',
            'nombre' => 'No asignado',
            'orden' => 1,
        ]);
        $lote = Lote::create([
            'manzano_id' => $manzano->id,
            'codigo' => '01',
            'superficie' => 300,
            'precio' => 20000,
            'estado' => 'disponible',
            'fila' => 1,
            'columna' => 1,
        ]);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacionAsignada->id])
            ->post(route('reservas.store'), [
                'cliente_id' => Cliente::firstOrFail()->id,
                'lote_id' => $lote->id,
                'fecha_reserva' => now()->toDateString(),
                'monto_reserva' => 100,
                'tipo_operacion' => 'contado',
                'metodo_pago' => 'efectivo',
            ])
            ->assertForbidden();
    }

    public function test_administrador_ve_vender_editar_y_reservar(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))
            ->where('estado', 'disponible')
            ->firstOrFail();
        $lote->update(['coord_x' => 50, 'coord_y' => 50]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('data-modal-link="reservar"', false)
            ->assertSee('data-modal-link="vender"', false)
            ->assertSee('data-modal-link="editar"', false)
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertDontSee('data-can-reservar=', false)
            ->assertDontSee('data-can-vender=', false)
            ->assertDontSee('data-can-editar=', false);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('permisos.reservar', true)
            ->assertJsonPath('permisos.vender', true)
            ->assertJsonPath('permisos.editar', true);
    }

    public function test_modal_renderiza_urls_correctas_para_admin(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))
            ->where('estado', 'disponible')
            ->firstOrFail();
        $lote->update(['coord_x' => 50, 'coord_y' => 50]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertDontSee('data-detail-url=', false)
            ->assertDontSee('data-reserva-url=', false)
            ->assertDontSee('data-venta-url=', false)
            ->assertDontSee('data-edit-url=', false)
            ->assertSee('data-modal-link="detalle"', false)
            ->assertSee('id="lotModalClose"', false)
            ->assertSee('id="lotModalOverlay"', false);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('urls.detalle', route('lotes.show', $lote))
            ->assertJsonPath('urls.reservar', route('reservas.create', ['lote_id' => $lote->id]))
            ->assertJsonPath('urls.vender', route('ventas.create', ['lote_id' => $lote->id]))
            ->assertJsonPath('urls.editar', route('lotes.edit', $lote));
    }

    public function test_admin_puede_acceder_a_crear_venta_y_editar_lote(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('ventas.create'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('lotes.edit', $lote))
            ->assertOk();
    }
}
