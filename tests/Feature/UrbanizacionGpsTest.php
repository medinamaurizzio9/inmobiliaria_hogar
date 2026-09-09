<?php

namespace Tests\Feature;

use App\Models\Urbanizacion;
use App\Models\UrbanizacionReferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UrbanizacionGpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_puede_crear_editar_y_eliminar_punto_gps(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.urbanizacion-gps.index'))
            ->assertOk()
            ->assertSee('Configuracion Urbanizacion GPS')
            ->assertSee('Nuevo Punto de Referencia');

        $this->actingAs($admin)
            ->post(route('admin.urbanizacion-gps.store'), [
                'urbanizacion_id' => $urbanizacion->id,
                'nombre' => 'Rotonda de Ingreso',
                'tipo_referencia' => 'ingreso',
                'latitud' => -16.50012345,
                'longitud' => -68.15012345,
                'plano_x' => 42.125,
                'plano_y' => 58.875,
                'descripcion' => 'Referencia principal',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.urbanizacion-gps.index', ['urbanizacion_id' => $urbanizacion->id]));

        $referencia = UrbanizacionReferencia::firstOrFail();
        $this->assertDatabaseHas('urbanizacion_referencias', [
            'id' => $referencia->id,
            'urbanizacion_id' => $urbanizacion->id,
            'nombre' => 'Rotonda de Ingreso',
            'tipo_referencia' => 'ingreso',
            'plano_x' => 42.125,
            'plano_y' => 58.875,
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.urbanizacion-gps.update', $referencia), [
                'urbanizacion_id' => $urbanizacion->id,
                'nombre' => 'Casa Grande',
                'tipo_referencia' => 'construccion',
                'latitud' => -16.50100000,
                'longitud' => -68.15100000,
                'plano_x' => 12.5,
                'plano_y' => 90.25,
                'descripcion' => null,
            ])
            ->assertRedirect(route('admin.urbanizacion-gps.index', ['urbanizacion_id' => $urbanizacion->id]));

        $this->assertDatabaseHas('urbanizacion_referencias', [
            'id' => $referencia->id,
            'nombre' => 'Casa Grande',
            'tipo_referencia' => 'construccion',
            'plano_x' => 12.5,
            'plano_y' => 90.25,
            'activo' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.urbanizacion-gps.destroy', $referencia))
            ->assertRedirect(route('admin.urbanizacion-gps.index', ['urbanizacion_id' => $urbanizacion->id]));

        $this->assertDatabaseMissing('urbanizacion_referencias', ['id' => $referencia->id]);
    }

    public function test_gerente_puede_ver_configuracion_gps_y_vendedor_no(): void
    {
        $this->seed();

        $gerente = User::where('email', 'gerente@impacto.test')->firstOrFail();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();

        $this->actingAs($gerente)
            ->get(route('admin.urbanizacion-gps.index'))
            ->assertOk();

        $this->actingAs($vendedor)
            ->get(route('admin.urbanizacion-gps.index'))
            ->assertForbidden();
    }

    public function test_mapa_muestra_boton_y_referencias_gps_configuradas(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        UrbanizacionReferencia::create([
            'urbanizacion_id' => $urbanizacion->id,
            'nombre' => 'Parada de Buses',
            'tipo_referencia' => 'transporte',
            'latitud' => -16.51000000,
            'longitud' => -68.16000000,
            'plano_x' => 33.333,
            'plano_y' => 44.444,
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('Puntos GPS')
            ->assertSee('Mi ubicacion')
            ->assertSee('Parada de Buses')
            ->assertSee('Transporte')
            ->assertSee('-16.51000000')
            ->assertSee('-68.16000000')
            ->assertSee('left: 33.333%', false)
            ->assertSee('top: 44.444%', false);
    }

    public function test_mi_ubicacion_requiere_autenticacion(): void
    {
        $this->seed();

        $this->postJson(route('mapa.mi-ubicacion'), [
            'latitud' => -16.5,
            'longitud' => -68.15,
            'accuracy' => 7,
        ])->assertUnauthorized();
    }

    public function test_mi_ubicacion_requiere_minimo_cuatro_referencias_calibradas(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $this->crearReferencia($urbanizacion, 'A', -16.0, -68.0, 10, 10);
        $this->crearReferencia($urbanizacion, 'B', -16.0, -67.0, 90, 10);
        $this->crearReferencia($urbanizacion, 'C', -15.0, -68.0, 10, 90);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->postJson(route('mapa.mi-ubicacion'), [
                'latitud' => -15.5,
                'longitud' => -67.5,
                'accuracy' => 8,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'La urbanizacion requiere al menos 4 referencias GPS calibradas.');
    }

    public function test_mi_ubicacion_devuelve_xy_validos_y_no_guarda_ubicacion(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $this->crearReferencia($urbanizacion, 'A', -16.0, -68.0, 10, 10);
        $this->crearReferencia($urbanizacion, 'B', -16.0, -67.0, 90, 10);
        $this->crearReferencia($urbanizacion, 'C', -15.0, -68.0, 10, 90);
        $this->crearReferencia($urbanizacion, 'D', -15.0, -67.0, 90, 90);

        $referenciasAntes = UrbanizacionReferencia::count();

        $response = $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->postJson(route('mapa.mi-ubicacion'), [
                'latitud' => -15.5,
                'longitud' => -67.5,
                'accuracy' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('accuracy', 7);

        $this->assertGreaterThanOrEqual(0, $response->json('x'));
        $this->assertLessThanOrEqual(100, $response->json('x'));
        $this->assertGreaterThanOrEqual(0, $response->json('y'));
        $this->assertLessThanOrEqual(100, $response->json('y'));
        $this->assertEqualsWithDelta(50, $response->json('x'), 0.2);
        $this->assertEqualsWithDelta(50, $response->json('y'), 0.2);
        $this->assertSame($referenciasAntes, UrbanizacionReferencia::count());
    }

    private function crearReferencia(Urbanizacion $urbanizacion, string $nombre, float $latitud, float $longitud, float $planoX, float $planoY): UrbanizacionReferencia
    {
        return UrbanizacionReferencia::create([
            'urbanizacion_id' => $urbanizacion->id,
            'nombre' => $nombre,
            'tipo_referencia' => 'sector',
            'latitud' => $latitud,
            'longitud' => $longitud,
            'plano_x' => $planoX,
            'plano_y' => $planoY,
            'activo' => true,
        ]);
    }
}
