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

class PrimerVencimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cuota_uno_usa_fecha_primer_vencimiento(): void
    {
        $venta = $this->createVenta(3, '2026-01-15', '2026-02-10');

        app(InstallmentService::class)->generateForSale($venta);

        $primera = $venta->cuotas()->orderBy('numero')->firstOrFail();
        $this->assertSame('2026-02-10', $primera->fecha_programada->format('Y-m-d'));
        $this->assertSame('2026-02-10', $primera->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_cuotas_siguientes_se_desplazan_un_mes_desde_primer_vencimiento(): void
    {
        $venta = $this->createVenta(3, '2026-01-15', '2026-02-10');

        app(InstallmentService::class)->generateForSale($venta);

        $fechas = $venta->cuotas()->orderBy('numero')->get()
            ->map(fn (Cuota $cuota) => $cuota->fecha_programada->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-02-10', '2026-03-10', '2026-04-10'], $fechas);
    }

    public function test_fin_de_mes_31_enero_genera_fechas_validas_sin_saltos(): void
    {
        $venta = $this->createVenta(3, '2026-01-20', '2026-01-31');

        app(InstallmentService::class)->generateForSale($venta);

        $fechas = $venta->cuotas()->orderBy('numero')->get()
            ->map(fn (Cuota $cuota) => $cuota->fecha_programada->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $fechas);
    }

    public function test_fin_de_mes_dia_30_mantiene_fin_de_mes(): void
    {
        $venta = $this->createVenta(2, '2026-01-20', '2026-04-30');

        app(InstallmentService::class)->generateForSale($venta);

        $fechas = $venta->cuotas()->orderBy('numero')->get()
            ->map(fn (Cuota $cuota) => $cuota->fecha_programada->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-04-30', '2026-05-31'], $fechas);
    }

    public function test_fin_de_mes_29_febrero_continua_con_fin_de_mes(): void
    {
        $venta = $this->createVenta(2, '2025-12-15', '2028-02-29');

        app(InstallmentService::class)->generateForSale($venta);

        $fechas = $venta->cuotas()->orderBy('numero')->get()
            ->map(fn (Cuota $cuota) => $cuota->fecha_programada->format('Y-m-d'))
            ->all();

        $this->assertSame(['2028-02-29', '2028-03-31'], $fechas);
    }

    public function test_sin_fecha_primer_vencimiento_conserva_fallback(): void
    {
        $venta = $this->createVenta(3, '2026-01-15', null);

        app(InstallmentService::class)->generateForSale($venta);

        $fechas = $venta->cuotas()->orderBy('numero')->get()
            ->map(fn (Cuota $cuota) => $cuota->fecha_programada->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-02-15', '2026-03-15', '2026-04-15'], $fechas);
    }

    public function test_sin_fecha_primer_vencimiento_conserva_fallback_fin_de_mes(): void
    {
        $venta = $this->createVenta(3, '2026-01-31', null);

        app(InstallmentService::class)->generateForSale($venta);

        $fechas = $venta->cuotas()->orderBy('numero')->get()
            ->map(fn (Cuota $cuota) => $cuota->fecha_programada->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-03-03', '2026-03-31', '2026-05-01'], $fechas);
    }

    private function createVenta(int $numeroCuotas, string $fechaVenta, ?string $fechaPrimerVencimiento): Venta
    {
        $urbanizacion = Urbanizacion::create(['nombre' => 'Impacto Test', 'estado' => 'activa']);
        $manzano = Manzano::create(['urbanizacion_id' => $urbanizacion->id, 'codigo' => uniqid('M'), 'orden' => 1]);
        $lote = Lote::create([
            'manzano_id' => $manzano->id,
            'codigo' => uniqid('L'),
            'superficie' => 300,
            'precio' => 12000,
            'estado' => 'vendido',
            'fila' => 1,
            'columna' => 1,
            'coord_x' => 50,
            'coord_y' => 50,
        ]);
        $cliente = Cliente::create(['nombre' => 'Cliente Primer Vencimiento']);

        return Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'fecha_venta' => $fechaVenta,
            'precio_final' => 12000,
            'cuota_inicial' => 0,
            'saldo_financiar' => 12000,
            'numero_cuotas' => $numeroCuotas,
            'fecha_primer_vencimiento' => $fechaPrimerVencimiento,
            'estado' => 'activa',
        ]);
    }
}
