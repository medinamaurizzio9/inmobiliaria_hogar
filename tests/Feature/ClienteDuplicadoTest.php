<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteDuplicadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_permite_crear_cliente_duplicado_en_misma_urbanizacion(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();

        $this->from(route('clientes.create'))
            ->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Otro nombre',
                'documento' => $cliente->documento,
                'telefono' => '70000000',
            ])
            ->assertRedirect(route('clientes.create'))
            ->assertSessionHas('duplicate_cliente_id', $cliente->id);

        $this->assertSame(1, Cliente::where('urbanizacion_id', $urbanizacion->id)->where('documento', $cliente->documento)->count());
    }

    public function test_no_duplica_la_misma_persona_en_otra_urbanizacion(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $primera = Urbanizacion::firstOrFail();
        $segunda = Urbanizacion::whereKeyNot($primera->id)->firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $primera->id)->firstOrFail();

        $countBefore = Cliente::count();

        $this->from(route('clientes.create'))->actingAs($admin)
            ->withSession(['urbanizacion_id' => $segunda->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Cliente otra urbanizacion',
                'documento' => $cliente->documento,
                'telefono' => '71111111',
            ])
            ->assertRedirect(route('clientes.create'))
            ->assertSessionHas('duplicate_cliente_id', $cliente->id);

        $this->assertSame($countBefore, Cliente::count());
    }

    public function test_muestra_datos_del_cliente_existente_y_quien_lo_registro(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->with('createdBy')->firstOrFail();

        $response = $this->followingRedirects()
            ->from(route('clientes.create'))
            ->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Intento duplicado',
                'documento' => $cliente->documento,
            ]);

        $response->assertOk()
            ->assertSee('Cliente ya registrado.', false)
            ->assertSee($cliente->nombre, false)
            ->assertSee($cliente->documento, false)
            ->assertSee($cliente->createdBy?->name ?? 'Usuario no registrado', false)
            ->assertSee($cliente->created_at->format('d/m/Y H:i'), false);
    }

    public function test_boton_usar_cliente_existente_apunta_a_reserva_si_hay_lote(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->where('estado', 'disponible')->firstOrFail();

        $this->followingRedirects()
            ->from(route('clientes.create', ['lote_id' => $lote->id]))
            ->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Duplicado con lote',
                'documento' => $cliente->documento,
                'lote_id' => $lote->id,
            ])
            ->assertOk()
            ->assertSee(e(route('reservas.create', ['cliente_id' => $cliente->id, 'lote_id' => $lote->id])), false);
    }

    public function test_boton_usar_cliente_existente_apunta_a_detalle_si_no_hay_lote(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();

        $this->followingRedirects()
            ->from(route('clientes.create'))
            ->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Duplicado sin lote',
                'documento' => $cliente->documento,
            ])
            ->assertOk()
            ->assertSee(route('clientes.show', $cliente), false);
    }

    public function test_vendedor_no_puede_ver_cliente_de_urbanizacion_no_asignada(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacionAsignada = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $otraUrbanizacion = Urbanizacion::whereKeyNot($urbanizacionAsignada->id)->firstOrFail();
        $cliente = Cliente::create([
            'urbanizacion_id' => $otraUrbanizacion->id,
            'created_by' => User::where('email', 'admin@impacto.test')->firstOrFail()->id,
            'nombre' => 'Cliente no asignado',
            'documento' => 'CI-NO-ASIGNADO',
        ]);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacionAsignada->id])
            ->get(route('clientes.show', $cliente))
            ->assertForbidden();
    }

    public function test_cliente_nuevo_guarda_created_by(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.store'), [
                'nombre' => 'Cliente creado por admin',
                'documento' => 'CI-CREADO-001',
            ])
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Cliente creado por admin',
            'urbanizacion_id' => $urbanizacion->id,
            'created_by' => $admin->id,
        ]);
    }
}
