<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaleDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'gerente', 'supervisor', 'vendedor'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    public function test_gerente_autoriza_descuento_y_guarda_precio_final_pactado(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Descuento Gerente']);
        $gerente = $this->user('gerente');

        $venta = app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 500]), $gerente);
        $venta->refresh();

        $this->assertEquals(500.0, (float) $venta->descuento);
        $this->assertEquals(11500.0, (float) $venta->precio_final);
        $this->assertEquals(11500.0, (float) $venta->precio_final_usd);
        $this->assertEquals(12000.0, (float) $venta->precio_base_usd);
        $this->assertSame($gerente->id, $venta->descuento_autorizado_por);
        $this->assertNotNull($venta->descuento_autorizado_en);
    }

    public function test_administrador_autoriza_descuento(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Descuento Admin']);
        $admin = $this->user('administrador');

        $venta = app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 300]), $admin);
        $venta->refresh();

        $this->assertEquals(300.0, (float) $venta->descuento);
        $this->assertEquals(11700.0, (float) $venta->precio_final);
        $this->assertSame($admin->id, $venta->descuento_autorizado_por);
    }

    public function test_vendedor_no_puede_autorizar_descuento(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Vendedor']);
        $vendedor = $this->user('vendedor');

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 100]), $vendedor);
    }

    public function test_supervisor_no_puede_autorizar_descuento(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Supervisor']);
        $supervisor = $this->user('supervisor');

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 100]), $supervisor);
    }

    public function test_usuario_sin_rol_no_puede_autorizar_descuento(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Sin Rol']);

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 100]), User::factory()->create());
    }

    public function test_descuento_negativo_es_rechazado(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Negativo']);
        $gerente = $this->user('gerente');

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => -100]), $gerente);
    }

    public function test_descuento_no_puede_superar_el_precio_de_la_operacion(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Descuento Excesivo']);
        $gerente = $this->user('gerente');

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 13000]), $gerente);
    }

    public function test_descuento_cero_no_requiere_autorizacion(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Sin Descuento']);

        $venta = app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 0]), User::factory()->create());
        $venta->refresh();

        $this->assertEquals(0.0, (float) $venta->descuento);
        $this->assertEquals(12000.0, (float) $venta->precio_final);
        $this->assertNull($venta->descuento_autorizado_por);
        $this->assertNull($venta->descuento_autorizado_en);
    }

    public function test_descuento_aplicado_en_venta_al_contado(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Contado Descuento']);
        $gerente = $this->user('gerente');

        $venta = app(SaleService::class)->create($this->saleData($lote, $cliente, [
            'descuento' => 200,
            'tipo_operacion' => 'contado',
            'numero_cuotas' => 0,
        ]), $gerente);
        $venta->refresh();

        $this->assertEquals(200.0, (float) $venta->descuento);
        $this->assertEquals(11800.0, (float) $venta->precio_final);
        $this->assertEquals(0, $venta->numero_cuotas);
    }

    public function test_update_sin_descuento_conserva_descuento_autorizado(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Update Descuento']);
        $gerente = $this->user('gerente');

        $venta = app(SaleService::class)->create($this->saleData($lote, $cliente, ['descuento' => 500]), $gerente);
        $venta->refresh();

        $updated = app(SaleService::class)->update($venta, $this->updatePayload($venta, $lote, $cliente), $gerente, 'Ajuste menor');
        $updated->refresh();

        $this->assertEquals(500.0, (float) $updated->descuento);
        $this->assertEquals(11500.0, (float) $updated->precio_final);
        $this->assertSame($gerente->id, $updated->descuento_autorizado_por);
    }

    private function user(string $rol): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function saleData(Lote $lote, Cliente $cliente, array $overrides = []): array
    {
        return [
            ...[
                'lote_id' => $lote->id,
                'cliente_id' => $cliente->id,
                'fecha_venta' => now()->format('Y-m-d'),
                'precio_final' => 12000,
                'cuota_inicial' => 2000,
                'numero_cuotas' => 3,
                'estado' => 'activa',
                'metodo_pago' => 'efectivo',
            ],
            ...$overrides,
        ];
    }

    private function updatePayload(Venta $venta, Lote $lote, Cliente $cliente): array
    {
        return [
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'tipo_operacion' => 'credito',
            'fecha_venta' => $venta->fecha_venta,
            'precio_final' => $venta->getRawOriginal('precio_final'),
            'cuota_inicial' => $venta->getRawOriginal('cuota_inicial'),
            'numero_cuotas' => $venta->numero_cuotas,
            'estado' => 'activa',
            'metodo_pago' => 'efectivo',
            'motivo_cambio' => 'Ajuste menor',
        ];
    }

    private function createLot(array $overrides = []): Lote
    {
        $urbanizacion = Urbanizacion::create(['nombre' => 'Impacto Test', 'estado' => 'activa']);
        $manzano = Manzano::create(['urbanizacion_id' => $urbanizacion->id, 'codigo' => uniqid('M'), 'orden' => 1]);

        return Lote::create([
            ...[
                'manzano_id' => $manzano->id,
                'codigo' => uniqid('L'),
                'superficie' => 300,
                'precio' => 12000,
                'estado' => 'disponible',
                'fila' => 1,
                'columna' => 1,
                'coord_x' => 50,
                'coord_y' => 50,
            ],
            ...$overrides,
        ]);
    }
}
