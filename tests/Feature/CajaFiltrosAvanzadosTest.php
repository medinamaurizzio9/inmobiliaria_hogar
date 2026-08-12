<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CajaFiltrosAvanzadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_nombre_cliente(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $m1 = $this->crearMovimiento($urb, ['referencia' => 'REF-CLI-A'], 'contado');
        $this->crearMovimiento($urb, ['referencia' => 'REF-CLI-B', 'cliente_nombre' => 'Otro Cliente'], 'contado');

        $this->getCaja($admin, $urb, ['cliente' => 'Cliente Filtro'])
            ->assertOk()
            ->assertSee($m1->referencia)
            ->assertDontSee('REF-CLI-B');
    }

    public function test_filtra_por_documento_cliente(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-DOC-A', 'cliente_documento' => 'CI-111111']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-DOC-B', 'cliente_documento' => 'CI-222222']);

        $this->getCaja($admin, $urb, ['documento' => 'CI-111111'])
            ->assertOk()
            ->assertSee('REF-DOC-A')
            ->assertDontSee('REF-DOC-B');
    }

    public function test_filtra_por_referencia(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-UNICA-99']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-OTRA-88']);

        $this->getCaja($admin, $urb, ['referencia' => 'REF-UNICA-99'])
            ->assertOk()
            ->assertSee('REF-UNICA-99')
            ->assertDontSee('REF-OTRA-88');
    }

    public function test_filtra_por_urbanizacion(): void
    {
        [$admin, $urbA] = $this->nuevoContexto();
        $this->crearMovimiento($urbA, ['referencia' => 'REF-URB-A']);

        $urbB = Urbanizacion::create(['nombre' => 'URB-FILTROS-B', 'estado' => 'activa']);
        $this->crearMovimiento($urbB, ['referencia' => 'REF-URB-B']);

        $this->getCaja($admin, $urbA)
            ->assertOk()
            ->assertSee('REF-URB-A')
            ->assertDontSee('REF-URB-B');

        $this->getCaja($admin, $urbA, ['urbanizacion_id' => $urbB->id])
            ->assertOk()
            ->assertSee('REF-URB-B')
            ->assertDontSee('REF-URB-A');
    }

    public function test_filtra_por_manzano_o_lote(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-LOTE-1', 'lote_codigo' => 'L-FIL-1', 'manzano_codigo' => 'M-FIL-1']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-LOTE-2', 'lote_codigo' => 'L-FIL-2', 'manzano_codigo' => 'M-FIL-2']);

        $this->getCaja($admin, $urb, ['lote' => 'L-FIL-1'])
            ->assertOk()
            ->assertSee('REF-LOTE-1')
            ->assertDontSee('REF-LOTE-2');

        $this->getCaja($admin, $urb, ['lote' => 'M-FIL'])
            ->assertOk()
            ->assertSee('REF-LOTE-1')
            ->assertSee('REF-LOTE-2');
    }

    public function test_filtra_por_modalidad(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-MOD-CREDITO'], 'credito');
        $this->crearMovimiento($urb, ['referencia' => 'REF-MOD-CONTADO'], 'contado');

        $this->getCaja($admin, $urb, ['modalidad' => 'credito'])
            ->assertOk()
            ->assertSee('REF-MOD-CREDITO')
            ->assertDontSee('REF-MOD-CONTADO');
    }

    public function test_selects_estado_y_metodo_incluyen_todos_los_valores(): void
    {
        [$admin, $urb] = $this->nuevoContexto();

        $this->getCaja($admin, $urb)
            ->assertOk()
            ->assertSee('Pendiente de verificacion')
            ->assertSee('Confirmado')
            ->assertSee('Rechazado')
            ->assertSee('Anulado')
            ->assertSee('Devolucion')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia')
            ->assertSee('Banco');
    }

    public function test_filtra_por_estado(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-EST-REC', 'estado' => 'rechazado']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-EST-CON', 'estado' => 'confirmado']);

        $this->getCaja($admin, $urb, ['estado' => 'rechazado'])
            ->assertOk()
            ->assertSee('REF-EST-REC')
            ->assertDontSee('REF-EST-CON');
    }

    public function test_filtra_por_monto_minimo_y_maximo(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-MONTO-BAJO', 'monto' => 2500]);
        $this->crearMovimiento($urb, ['referencia' => 'REF-MONTO-ALTO', 'monto' => 9000]);

        $this->getCaja($admin, $urb, ['monto_min' => 3000, 'monto_max' => 10000])
            ->assertOk()
            ->assertSee('REF-MONTO-ALTO')
            ->assertDontSee('REF-MONTO-BAJO');
    }

    public function test_filtra_por_usuario(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $cajeroA = User::factory()->create(['name' => 'Cajero A']);
        $cajeroB = User::factory()->create(['name' => 'Cajero B']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-USR-A', 'user_id' => $cajeroA->id]);
        $this->crearMovimiento($urb, ['referencia' => 'REF-USR-B', 'user_id' => $cajeroB->id]);

        $this->getCaja($admin, $urb, ['usuario_id' => $cajeroA->id])
            ->assertOk()
            ->assertSee('REF-USR-A')
            ->assertDontSee('REF-USR-B');
    }

    public function test_filtros_combinados(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-COMBO-SI', 'estado' => 'confirmado'], 'credito');
        $this->crearMovimiento($urb, ['referencia' => 'REF-COMBO-NO-MODALIDAD', 'estado' => 'confirmado'], 'contado');
        $this->crearMovimiento($urb, ['referencia' => 'REF-COMBO-NO-ESTADO', 'estado' => 'rechazado'], 'credito');

        $this->getCaja($admin, $urb, ['modalidad' => 'credito', 'estado' => 'confirmado', 'cliente' => 'Cliente Filtro'])
            ->assertOk()
            ->assertSee('REF-COMBO-SI')
            ->assertDontSee('REF-COMBO-NO-MODALIDAD')
            ->assertDontSee('REF-COMBO-NO-ESTADO');
    }

    public function test_paginacion_conserva_filtros(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-PAG-1', 'estado' => 'confirmado']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-PAG-2', 'estado' => 'confirmado']);
        $this->crearMovimiento($urb, ['referencia' => 'REF-PAG-3', 'estado' => 'confirmado']);

        $this->getCaja($admin, $urb, ['estado' => 'confirmado', 'per_page' => 2])
            ->assertOk()
            ->assertSee('page=2')
            ->assertSee('estado=confirmado');
    }

    public function test_limpiar_filtros_apunta_al_listado_sin_query(): void
    {
        [$admin, $urb] = $this->nuevoContexto();

        $this->getCaja($admin, $urb, ['estado' => 'rechazado'])
            ->assertOk()
            ->assertSee('Limpiar')
            ->assertSee('href="'.route('caja.index').'"', false);
    }

    public function test_exportacion_respeta_filtros_avanzados(): void
    {
        [$admin, $urb] = $this->nuevoContexto();
        $this->crearMovimiento($urb, ['referencia' => 'REF-EXP-CREDITO'], 'credito');
        $this->crearMovimiento($urb, ['referencia' => 'REF-EXP-CONTADO'], 'contado');

        $response = $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('export.csv', ['tipo' => 'caja', 'modalidad' => 'credito']));

        $response->assertOk();
        $response->assertSee('REF-EXP-CREDITO');
        $response->assertDontSee('REF-EXP-CONTADO');
    }

    public function test_vendedor_sin_acceso_financiero(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $vendedor->urbanizacionesAsignadas()->firstOrFail()->id])
            ->get(route('caja.index'))
            ->assertForbidden();
    }

    private function getCaja(User $admin, Urbanizacion $urbanizacion, array $params = []): TestResponse
    {
        return $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('caja.index', $params));
    }

    private function nuevoContexto(): array
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::create(['nombre' => 'URB-FILTROS-A', 'estado' => 'activa']);

        return [$admin, $urbanizacion];
    }

    private function crearMovimiento(Urbanizacion $urbanizacion, array $overrides = [], string $tipoOperacion = 'credito'): CashMovement
    {
        $manzano = Manzano::create([
            'urbanizacion_id' => $urbanizacion->id,
            'codigo' => $overrides['manzano_codigo'] ?? 'M-'.uniqid(),
            'orden' => 1,
        ]);
        $lote = Lote::create([
            'manzano_id' => $manzano->id,
            'codigo' => $overrides['lote_codigo'] ?? 'L-'.uniqid(),
            'superficie' => 300,
            'precio' => 12000,
            'estado' => 'vendido',
            'fila' => 1,
            'columna' => 1,
            'coord_x' => 50,
            'coord_y' => 50,
        ]);
        $cliente = Cliente::create([
            'nombre' => $overrides['cliente_nombre'] ?? 'Cliente Filtro',
            'documento' => $overrides['cliente_documento'] ?? 'CI-12345',
        ]);
        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'tipo_operacion' => $tipoOperacion,
            'precio_final' => 12000,
            'cuota_inicial' => 0,
            'saldo_financiar' => 12000,
            'numero_cuotas' => 1,
            'estado' => 'activa',
        ]);

        $base = [
            'user_id' => User::factory()->create(['name' => 'Cajero Prueba'])->id,
            'cliente_id' => $cliente->id,
            'sale_id' => $venta->id,
            'tipo' => 'ingreso',
            'concepto' => 'anticipo',
            'metodo_pago' => 'transferencia',
            'monto' => 2500,
            'fecha' => now()->subDay(),
            'referencia' => 'REF-FILTRO-1',
            'estado' => 'confirmado',
        ];

        return CashMovement::create([...$base, ...$overrides]);
    }
}
