<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\UrbanizacionCommercialSetting;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemicontadoVentaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Urbanizacion $urbanizacion;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizacion = Urbanizacion::orderBy('id')->firstOrFail();
        $this->cliente = Cliente::where('urbanizacion_id', $this->urbanizacion->id)->firstOrFail();
        UrbanizacionCommercialSetting::updateOrCreate(['urbanizacion_id' => $this->urbanizacion->id], [
            'max_cuotas_semicontado' => 6,
            'max_cuotas_credito' => 36,
        ]);
    }

    public function test_semicontado_genera_cuotas_y_no_se_trata_como_contado(): void
    {
        $venta = $this->crearVenta('semicontado', 10000, 2000, 4);

        $this->assertSame('semicontado', $venta->tipo_operacion);
        $this->assertCount(4, $venta->cuotas);
        $this->assertDatabaseHas('cash_movements', ['sale_id' => $venta->id, 'concepto' => 'anticipo', 'monto' => 2000]);
        $this->assertDatabaseMissing('cash_movements', ['sale_id' => $venta->id, 'concepto' => 'contado']);
    }

    public function test_cuota_inicial_se_descuenta_y_saldo_es_correcto(): void
    {
        $venta = $this->crearVenta('semicontado', 10000, 2500, 3);

        $this->assertSame(2500.0, (float) $venta->cuota_inicial);
        $this->assertSame(7500.0, (float) $venta->saldo_financiar);
        $this->assertSame(7500.0, (float) $venta->cuotas->sum('monto'));
    }

    public function test_mensualidad_semicontado_es_correcta(): void
    {
        $venta = $this->crearVenta('semicontado', 10000, 2000, 4);

        $this->assertSame([2000.0, 2000.0, 2000.0, 2000.0], $venta->cuotas->pluck('monto')->map(fn ($monto) => (float) $monto)->all());
    }

    public function test_residuo_de_redondeo_queda_en_ultima_cuota(): void
    {
        $venta = $this->crearVenta('semicontado', 10000, 0, 3);

        $this->assertSame([3333.33, 3333.33, 3333.34], $venta->cuotas->pluck('monto')->map(fn ($monto) => (float) $monto)->all());
    }

    public function test_primera_fecha_de_vencimiento_se_respeta(): void
    {
        $venta = $this->crearVenta('semicontado', 10000, 1000, 3, '2026-09-30');

        $this->assertSame(['2026-09-30', '2026-10-31', '2026-11-30'], $venta->cuotas->pluck('fecha_vencimiento')->map->format('Y-m-d')->all());
    }

    public function test_maximo_de_cuotas_semicontado_es_aceptado(): void
    {
        $venta = $this->crearVenta('semicontado', 12000, 0, 6);

        $this->assertCount(6, $venta->cuotas);
    }

    public function test_exceder_maximo_semicontado_es_rechazado(): void
    {
        $this->postVenta($this->nuevoLote(10000), 'semicontado', 0, 7)
            ->assertSessionHasErrors('numero_cuotas');
        $this->assertDatabaseMissing('ventas', ['tipo_operacion' => 'semicontado']);
    }

    public function test_cuota_inicial_mayor_al_precio_es_rechazada(): void
    {
        $this->postVenta($this->nuevoLote(10000), 'semicontado', 10001, 3)
            ->assertSessionHasErrors('cuota_inicial');
    }

    public function test_saldo_cero_en_semicontado_es_rechazado(): void
    {
        $this->postVenta($this->nuevoLote(10000), 'semicontado', 10000, 3)
            ->assertSessionHasErrors('cuota_inicial');
    }

    public function test_fecha_de_primer_vencimiento_es_obligatoria(): void
    {
        $lote = $this->nuevoLote(10000);
        $data = $this->datosVenta($lote, 'semicontado', 1000, 3);
        unset($data['fecha_primer_vencimiento']);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('ventas.store'), $data)
            ->assertSessionHasErrors('fecha_primer_vencimiento');
    }

    public function test_contado_sigue_sin_cuotas_y_registra_pago_total(): void
    {
        $venta = $this->crearVenta('contado', 10000, 5000, 3);

        $this->assertCount(0, $venta->cuotas);
        $this->assertSame(0, (int) $venta->numero_cuotas);
        $this->assertSame(0.0, (float) $venta->saldo_financiar);
        $this->assertDatabaseHas('cash_movements', ['sale_id' => $venta->id, 'concepto' => 'contado', 'monto' => 10000]);
    }

    public function test_credito_existente_sigue_generando_cuotas(): void
    {
        $venta = $this->crearVenta('credito', 10000, 1000, 12);

        $this->assertSame('credito', $venta->tipo_operacion);
        $this->assertCount(12, $venta->cuotas);
        $this->assertSame(9000.0, (float) $venta->saldo_financiar);
    }

    public function test_descuento_autorizado_modifica_precio_pactado(): void
    {
        $venta = $this->crearVenta('semicontado', 10000, 1000, 3, '2026-09-15', 500);

        $this->assertSame(9500.0, (float) $venta->precio_final);
        $this->assertSame(8500.0, (float) $venta->saldo_financiar);
        $this->assertSame($this->admin->id, $venta->descuento_autorizado_por);
    }

    public function test_vendedor_no_puede_registrar_venta_ni_alterar_precio(): void
    {
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $lote = $this->nuevoLote(10000);

        $this->actingAs($vendedor)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('ventas.store'), $this->datosVenta($lote, 'semicontado', 1000, 3, descuento: 500))
            ->assertForbidden();
        $this->assertDatabaseMissing('ventas', ['lote_id' => $lote->id]);
    }

    public function test_ui_muestra_modalidad_y_controla_campos_financieros(): void
    {
        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('ventas.create'))
            ->assertOk()
            ->assertSee('value="semicontado"', false)
            ->assertSee('Saldo financiado')
            ->assertSee('Cuota mensual estimada')
            ->assertSee("['semicontado', 'credito'].includes", false)
            ->assertSee("field.style.display = financed ? '' : 'none'", false)
            ->assertSee('"semicontado":6', false);
    }

    private function crearVenta(string $tipo, float $precio, float $inicial, int $cuotas, string $fecha = '2026-09-15', float $descuento = 0): Venta
    {
        $lote = $this->nuevoLote($precio);
        $this->postVenta($lote, $tipo, $inicial, $cuotas, $fecha, $descuento)->assertRedirect(route('ventas.index'));

        return Venta::with('cuotas')->where('lote_id', $lote->id)->firstOrFail();
    }

    private function postVenta(Lote $lote, string $tipo, float $inicial, int $cuotas, string $fecha = '2026-09-15', float $descuento = 0)
    {
        return $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('ventas.store'), $this->datosVenta($lote, $tipo, $inicial, $cuotas, $fecha, $descuento));
    }

    private function datosVenta(Lote $lote, string $tipo, float $inicial, int $cuotas, string $fecha = '2026-09-15', float $descuento = 0): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'lote_id' => $lote->id,
            'tipo_operacion' => $tipo,
            'fecha_venta' => '2026-08-12',
            'precio_final' => $lote->precio,
            'descuento' => $descuento,
            'cuota_inicial' => $inicial,
            'numero_cuotas' => $cuotas,
            'fecha_primer_vencimiento' => $fecha,
            'estado' => 'activa',
            'metodo_pago' => 'efectivo',
        ];
    }

    private function nuevoLote(float $precio): Lote
    {
        return Lote::create([
            'manzano_id' => $this->urbanizacion->manzanos()->firstOrFail()->id,
            'codigo' => uniqid('SC-'),
            'superficie' => 300,
            'precio' => $precio,
            'cuota_inicial_tipo' => 'monto',
            'cuota_inicial_valor' => 0,
            'estado' => 'disponible',
            'fila' => 1,
            'columna' => 1,
        ]);
    }
}
