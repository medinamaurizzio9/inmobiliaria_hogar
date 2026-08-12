<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Urbanizacion;
use App\Models\Venta;
use App\Services\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_exacta_genera_cuotas_iguales(): void
    {
        $venta = $this->createVenta(12000, 3000, 3);

        app(InstallmentService::class)->generateForSale($venta);

        $cuotas = $venta->cuotas()->orderBy('numero')->get();
        $this->assertCount(3, $cuotas);
        $this->assertEquals([3000.0, 3000.0, 3000.0], $cuotas->map(fn (Cuota $cuota) => (float) $cuota->monto)->all());
    }

    public function test_division_con_residuo_la_ultima_cuota_absorbe_la_diferencia(): void
    {
        $venta = $this->createVenta(12000, 2000, 3);

        app(InstallmentService::class)->generateForSale($venta);

        $cuotas = $venta->cuotas()->orderBy('numero')->get();
        $this->assertCount(3, $cuotas);
        $this->assertEquals([3333.33, 3333.33, 3333.34], $cuotas->map(fn (Cuota $cuota) => (float) $cuota->monto)->all());
    }

    public function test_genera_el_numero_correcto_de_cuotas(): void
    {
        $venta = $this->createVenta(10000, 0, 12);

        app(InstallmentService::class)->generateForSale($venta);

        $this->assertCount(12, $venta->cuotas()->get());
        $this->assertSame(range(1, 12), $venta->cuotas()->orderBy('numero')->pluck('numero')->all());
    }

    public function test_suma_de_cuotas_es_igual_al_saldo_financiado(): void
    {
        $venta = $this->createVenta(20000, 5000, 7);

        app(InstallmentService::class)->generateForSale($venta);

        $suma = (float) $venta->cuotas()->sum('monto');
        $this->assertSame(15000.0, round($suma, 2));
    }

    public function test_no_genera_cuotas_cuando_el_numero_es_cero(): void
    {
        $venta = $this->createVenta(10000, 10000, 0);

        app(InstallmentService::class)->generateForSale($venta);

        $this->assertCount(0, $venta->cuotas()->get());
    }

    public function test_10000_entre_3_con_residuo_en_la_ultima(): void
    {
        $venta = $this->createVenta(10000, 0, 3);

        app(InstallmentService::class)->generateForSale($venta);

        $montos = $venta->cuotas()->orderBy('numero')->get()->map(fn (Cuota $cuota) => (float) $cuota->monto)->all();
        $this->assertSame([3333.33, 3333.33, 3333.34], $montos);
    }

    public function test_100_entre_6_con_residuo_en_la_ultima(): void
    {
        $venta = $this->createVenta(100, 0, 6);

        app(InstallmentService::class)->generateForSale($venta);

        $montos = $venta->cuotas()->orderBy('numero')->get()->map(fn (Cuota $cuota) => (float) $cuota->monto)->all();
        $this->assertSame([16.66, 16.66, 16.66, 16.66, 16.66, 16.70], $montos);
    }

    public function test_monto_con_centavos_12345_67_entre_7(): void
    {
        $venta = $this->createVenta(12345.67, 0, 7);

        app(InstallmentService::class)->generateForSale($venta);

        $montos = $venta->cuotas()->orderBy('numero')->get()->map(fn (Cuota $cuota) => (float) $cuota->monto)->all();
        $this->assertSame([1763.66, 1763.66, 1763.66, 1763.66, 1763.66, 1763.66, 1763.71], $montos);
        $this->assertSame(12345.67, round((float) $venta->cuotas()->sum('monto'), 2));
    }

    public function test_suma_exacta_de_cuotas_igual_al_saldo_financiado(): void
    {
        $venta = $this->createVenta(12345.67, 345.67, 7);

        app(InstallmentService::class)->generateForSale($venta);

        $suma = round((float) $venta->cuotas()->sum('monto'), 2);
        $this->assertSame(12000.0, $suma);
    }

    public function test_la_ultima_cuota_absorbe_solamente_el_residuo(): void
    {
        $venta = $this->createVenta(100, 0, 6);

        app(InstallmentService::class)->generateForSale($venta);

        $cuotas = $venta->cuotas()->orderBy('numero')->get();
        $primeras = $cuotas->take(5);
        $ultima = $cuotas->last();

        foreach ($primeras as $cuota) {
            $this->assertSame(16.66, (float) $cuota->monto);
        }
        $this->assertSame(16.70, (float) $ultima->monto);
    }

    public function test_no_altera_ventas_existentes(): void
    {
        $ventaA = $this->createVenta(12000, 2000, 3);
        $ventaB = $this->createVenta(30000, 0, 6);

        app(InstallmentService::class)->generateForSale($ventaA);
        $cuotasA = $ventaA->cuotas()->orderBy('id')->pluck('id')->all();
        $montosA = $ventaA->cuotas()->orderBy('id')->pluck('monto')->all();

        app(InstallmentService::class)->generateForSale($ventaB);

        $this->assertCount(3, $ventaA->cuotas()->get());
        $this->assertCount(6, $ventaB->cuotas()->get());
        $this->assertSame($cuotasA, $ventaA->cuotas()->orderBy('id')->pluck('id')->all());
        $this->assertSame($montosA, $ventaA->cuotas()->orderBy('id')->pluck('monto')->all());
    }

    private function createVenta(float $precioFinal, float $cuotaInicial, int $numeroCuotas): Venta
    {
        $urbanizacion = Urbanizacion::create(['nombre' => 'Impacto Test', 'estado' => 'activa']);
        $manzano = Manzano::create(['urbanizacion_id' => $urbanizacion->id, 'codigo' => uniqid('M'), 'orden' => 1]);
        $lote = Lote::create([
            'manzano_id' => $manzano->id,
            'codigo' => uniqid('L'),
            'superficie' => 300,
            'precio' => $precioFinal,
            'estado' => 'vendido',
            'fila' => 1,
            'columna' => 1,
            'coord_x' => 50,
            'coord_y' => 50,
        ]);
        $cliente = Cliente::create(['nombre' => 'Cliente Cuotas']);

        return Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'precio_final' => $precioFinal,
            'cuota_inicial' => $cuotaInicial,
            'saldo_financiar' => $precioFinal - $cuotaInicial,
            'numero_cuotas' => $numeroCuotas,
            'estado' => 'activa',
        ]);
    }
}
