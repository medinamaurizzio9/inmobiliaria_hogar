<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\InstallmentService;
use App\Services\ReservationService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_vende_lote_vendido(): void
    {
        $lote = $this->createLot(['estado' => 'vendido']);
        $cliente = Cliente::create(['nombre' => 'Cliente Uno']);

        $this->expectException(ValidationException::class);

        app(SaleService::class)->create($this->saleData($lote, $cliente), User::factory()->create());
    }

    public function test_genera_cuotas_al_crear_venta_a_credito(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Credito']);

        $venta = app(SaleService::class)->create($this->saleData($lote, $cliente, ['numero_cuotas' => 4]), User::factory()->create());

        $this->assertCount(4, $venta->cuotas()->get());
        $this->assertDatabaseHas('lotes', ['id' => $lote->id, 'estado' => 'vendido']);
    }

    public function test_convierte_reserva_en_venta(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Reserva']);
        $user = User::factory()->create();

        $reserva = app(ReservationService::class)->create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'fecha_reserva' => now()->format('Y-m-d'),
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'monto_reserva' => 500,
            'metodo_pago' => 'efectivo',
        ], $user);

        app(SaleService::class)->create($this->saleData($lote->fresh(), $cliente), $user);

        $this->assertSame('convertida', $reserva->fresh()->estado);
        $this->assertSame('vendido', $lote->fresh()->estado);
    }

    public function test_reserva_activa_deja_lote_reservado(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Activo']);

        app(ReservationService::class)->create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'fecha_reserva' => now()->format('Y-m-d'),
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'monto_reserva' => 100,
            'metodo_pago' => 'efectivo',
        ], User::factory()->create());

        $this->assertSame('reservado', $lote->fresh()->estado);
    }

    public function test_reserva_cancelada_deja_lote_disponible(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Cancela']);
        $user = User::factory()->create();
        $reserva = app(ReservationService::class)->create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'fecha_reserva' => now()->format('Y-m-d'),
            'fecha_vencimiento' => now()->addDays(5)->format('Y-m-d'),
            'monto_reserva' => 100,
            'metodo_pago' => 'efectivo',
        ], $user);

        app(ReservationService::class)->cancel($reserva, $user, 'Cliente desistio.');

        $this->assertSame('cancelada', $reserva->fresh()->estado);
        $this->assertSame('disponible', $lote->fresh()->estado);
    }

    public function test_reserva_vencida_deja_lote_disponible(): void
    {
        $lote = $this->createLot();
        $cliente = Cliente::create(['nombre' => 'Cliente Vence']);
        $user = User::factory()->create();
        $reserva = app(ReservationService::class)->create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'fecha_reserva' => now()->subDays(8)->format('Y-m-d'),
            'fecha_vencimiento' => now()->subDay()->format('Y-m-d'),
            'monto_reserva' => 100,
            'metodo_pago' => 'efectivo',
        ], $user);

        app(ReservationService::class)->expire($reserva, $user);

        $this->assertSame('vencida', $reserva->fresh()->estado);
        $this->assertSame('disponible', $lote->fresh()->estado);
    }

    public function test_lote_vendido_no_cambia_a_disponible_por_reserva(): void
    {
        $lote = $this->createLot(['estado' => 'vendido']);
        $cliente = Cliente::create(['nombre' => 'Cliente Vendido']);
        $user = User::factory()->create();
        $reserva = Reserva::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'usuario_id' => $user->id,
            'fecha_reserva' => now(),
            'fecha_vencimiento' => now()->addDays(5),
            'monto_reserva' => 100,
            'estado' => 'activa',
        ]);
        \App\Models\Venta::create($this->saleData($lote, $cliente, ['estado' => 'activa']));

        app(ReservationService::class)->expire($reserva, $user);

        $this->assertSame('vencida', $reserva->fresh()->estado);
        $this->assertSame('vendido', $lote->fresh()->estado);
    }

    public function test_multiples_reservas_mantienen_consistencia_del_lote(): void
    {
        $lote = $this->createLot(['estado' => 'reservado']);
        $user = User::factory()->create();
        $primera = Reserva::create([
            'cliente_id' => Cliente::create(['nombre' => 'Cliente Uno Reserva'])->id,
            'lote_id' => $lote->id,
            'usuario_id' => $user->id,
            'fecha_reserva' => now(),
            'fecha_vencimiento' => now()->addDays(5),
            'monto_reserva' => 100,
            'estado' => 'activa',
        ]);
        Reserva::create([
            'cliente_id' => Cliente::create(['nombre' => 'Cliente Dos Reserva'])->id,
            'lote_id' => $lote->id,
            'usuario_id' => $user->id,
            'fecha_reserva' => now(),
            'fecha_vencimiento' => now()->addDays(5),
            'monto_reserva' => 100,
            'estado' => 'activa',
        ]);

        app(ReservationService::class)->expire($primera, $user);

        $this->assertSame('reservado', $lote->fresh()->estado);
    }

    public function test_registra_pago_parcial(): void
    {
        $cuota = $this->createInstallment();

        app(InstallmentService::class)->pay($cuota, 100, 'efectivo', User::factory()->create());

        $this->assertSame('parcial', $cuota->fresh()->estado);
        $this->assertEquals(100, (float) $cuota->fresh()->monto_pagado);
        $this->assertEquals(200, (float) $cuota->fresh()->saldo_pendiente);
    }

    public function test_genera_movimiento_de_caja_al_pagar_cuota(): void
    {
        $cuota = $this->createInstallment();

        app(InstallmentService::class)->pay($cuota, 300, 'transferencia', User::factory()->create(), 'TX-1');

        $this->assertDatabaseHas('cash_movements', [
            'installment_id' => $cuota->id,
            'concepto' => 'cuota',
            'monto' => 300,
            'referencia' => 'TX-1',
        ]);
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
                'fecha_primer_vencimiento' => now()->addMonth()->format('Y-m-d'),
                'estado' => 'activa',
                'metodo_pago' => 'efectivo',
            ],
            ...$overrides,
        ];
    }

    private function createInstallment(): Cuota
    {
        $lote = $this->createLot(['estado' => 'vendido']);
        $cliente = Cliente::create(['nombre' => 'Cliente Cuota']);
        $venta = \App\Models\Venta::create($this->saleData($lote, $cliente, ['numero_cuotas' => 1]));

        return Cuota::create([
            'venta_id' => $venta->id,
            'numero' => 1,
            'fecha_programada' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonth(),
            'monto' => 300,
            'monto_pagado' => 0,
            'saldo_pendiente' => 300,
            'estado' => 'pendiente',
        ]);
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
