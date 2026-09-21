<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaEdicionBuscadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_puede_editar_reserva(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $reserva = Reserva::with('lote.manzano')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $reserva->lote->manzano->urbanizacion_id])
            ->get(route('reservas.edit', $reserva))
            ->assertOk()
            ->assertSee('Editar reserva', false);
    }

    public function test_vendedor_no_puede_editar_reserva(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $reserva = Reserva::whereHas('lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.edit', $reserva))
            ->assertForbidden()
            ->assertSee('No tienes permiso para editar esta reserva.', false);
    }

    public function test_vendedor_no_ve_boton_editar_reserva_y_admin_si(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.index'))
            ->assertOk()
            ->assertDontSee('>Editar</a>', false);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.index'))
            ->assertOk()
            ->assertSee('>Editar</a>', false);
    }

    public function test_endpoint_buscar_retorna_clientes_por_documento_y_nombre(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->getJson(route('clientes.buscar', ['q' => $cliente->documento]))
            ->assertOk()
            ->assertJsonFragment(['id' => $cliente->id, 'documento' => $cliente->documento]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->getJson(route('clientes.buscar', ['q' => explode(' ', $cliente->nombre)[0]]))
            ->assertOk()
            ->assertJsonFragment(['id' => $cliente->id, 'nombre' => $cliente->nombre]);
    }

    public function test_endpoint_reutiliza_ci_global_y_respeta_minimo_dos_caracteres(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $primera = Urbanizacion::firstOrFail();
        $segunda = Urbanizacion::whereKeyNot($primera->id)->firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $primera->id)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $segunda->id])
            ->getJson(route('clientes.buscar', ['q' => $cliente->documento]))
            ->assertOk()
            ->assertJsonFragment(['id' => $cliente->id]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $primera->id])
            ->getJson(route('clientes.buscar', ['q' => 'A']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_venta_create_muestra_buscador_para_admin_y_vendedor_no_accede(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('Buscar cliente por nombre, carnet o celular', false)
            ->assertSee('data-cliente-search', false);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $vendedor->urbanizacionesAsignadas()->firstOrFail()->id])
            ->get(route('ventas.create'))
            ->assertForbidden();
    }
}
