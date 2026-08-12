<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\CashMovementService;
use App\Services\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashMovementAnnulTest extends TestCase
{
    use RefreshDatabase;

    public function test_anular_pago_total_restaura_la_cuota_a_pendiente(): void
    {
        $cuota = $this->createPaidCuota(5000, 5000, now()->addMonth());

        app(CashMovementService::class)->annul($cuota->cashMovements()->first(), 'Pago duplicado.');

        $cuota->refresh();
        $this->assertSame(0.0, (float) $cuota->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('pendiente', $cuota->estado);
        $this->assertNull($cuota->fecha_pago);
    }

    public function test_anular_pago_parcial_disminuye_monto_pagado(): void
    {
        $cuota = $this->createCuota(5000, now()->addMonth());
        $movimiento = $this->payCuota($cuota, 1000, 'efectivo');
        $this->payCuota($cuota, 2000, 'efectivo');
        $this->assertSame('parcial', $cuota->fresh()->estado);

        app(CashMovementService::class)->annul($movimiento, 'Correccion administrativa.');

        $cuota->refresh();
        $this->assertSame(2000.0, (float) $cuota->monto_pagado);
        $this->assertSame(3000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('parcial', $cuota->estado);
    }

    public function test_anular_pago_total_sobre_parcial_deja_cuota_parcial(): void
    {
        $cuota = $this->createCuota(5000, now()->addMonth());
        $this->payCuota($cuota, 2000, 'efectivo');
        $segundoPago = $this->payCuota($cuota, 3000, 'transferencia');
        $this->assertSame('pagada', $cuota->fresh()->estado);

        app(CashMovementService::class)->annul($segundoPago, 'Reverso de pago.');

        $cuota->refresh();
        $this->assertSame(2000.0, (float) $cuota->monto_pagado);
        $this->assertSame(3000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('parcial', $cuota->estado);
    }

    public function test_anular_un_solo_pago_conserva_los_demas(): void
    {
        $cuota = $this->createCuota(5000, now()->addMonth());
        $primero = $this->payCuota($cuota, 1000, 'efectivo');
        $segundo = $this->payCuota($cuota, 1500, 'efectivo');

        app(CashMovementService::class)->annul($primero, 'Pago no aplica.');

        $cuota->refresh();
        $this->assertSame(1500.0, (float) $cuota->monto_pagado);
        $this->assertSame(3500.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('parcial', $cuota->estado);
        $this->assertSame('confirmado', $segundo->fresh()->estado);
        $this->assertSame('anulado', $primero->fresh()->estado);
    }

    public function test_saldo_no_queda_negativo_ni_superior_al_monto(): void
    {
        $cuota = $this->createCuota(5000, now()->addMonth());
        $movimiento = $this->payCuota($cuota, 1000, 'efectivo');

        app(CashMovementService::class)->annul($movimiento, 'Correccion.');

        $cuota->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $cuota->monto_pagado);
        $this->assertLessThanOrEqual(5000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame(0.0, (float) $cuota->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota->saldo_pendiente);
    }

    public function test_anular_cuota_vencida_restaura_estado_vencida(): void
    {
        $cuota = $this->createPaidCuota(5000, 5000, now()->subDays(10));

        app(CashMovementService::class)->annul($cuota->cashMovements()->first(), 'Pago reversado.');

        $cuota->refresh();
        $this->assertSame(0.0, (float) $cuota->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('vencida', $cuota->estado);
    }

    public function test_no_permite_anular_dos_veces(): void
    {
        $cuota = $this->createPaidCuota(5000, 5000, now()->addMonth());
        $movimiento = $cuota->cashMovements()->first();
        app(CashMovementService::class)->annul($movimiento, 'Primer reverso.');

        $this->expectException(ValidationException::class);

        app(CashMovementService::class)->annul($movimiento, 'Segundo reverso.');
    }

    public function test_anulacion_conserva_motivo_y_registra_auditoria(): void
    {
        $cuota = $this->createPaidCuota(5000, 5000, now()->addMonth());
        $movimiento = $cuota->cashMovements()->first();

        app(CashMovementService::class)->annul($movimiento, 'Motivo documentado de anulacion.');

        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'CashMovement',
            'modelo_id' => $movimiento->id,
            'accion' => 'anular_caja',
            'descripcion' => 'Motivo documentado de anulacion.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Cuota',
            'modelo_id' => $cuota->id,
            'accion' => 'cuota_restaurada_por_anulacion',
            'descripcion' => 'Motivo documentado de anulacion.',
        ]);

        $this->assertSame('anulado', $movimiento->fresh()->estado);
        $this->assertSame(1, AuditLog::where('modelo', 'CashMovement')->where('modelo_id', $movimiento->id)->where('accion', 'anular_caja')->count());
    }

    public function test_anular_movimiento_sin_cuota_no_rompe_nada(): void
    {
        $movimiento = CashMovement::create([
            'tipo' => 'ingreso',
            'concepto' => 'ajuste',
            'metodo_pago' => 'efectivo',
            'monto' => 100,
            'fecha' => now(),
            'estado' => 'confirmado',
        ]);

        app(CashMovementService::class)->annul($movimiento, 'Ajuste manual reversado.');

        $this->assertSame('anulado', $movimiento->fresh()->estado);
    }

    public function test_no_revierte_movimiento_que_supera_el_monto_pagado_de_la_cuota(): void
    {
        $cuota = $this->createCuota(5000, now()->addMonth());
        $this->payCuota($cuota, 1000, 'efectivo');
        $cuota->refresh();

        $movimientoInconsistente = CashMovement::create([
            'cliente_id' => $cuota->venta->cliente_id,
            'sale_id' => $cuota->venta_id,
            'installment_id' => $cuota->id,
            'tipo' => 'ingreso',
            'concepto' => 'cuota',
            'metodo_pago' => 'efectivo',
            'monto' => 5000,
            'fecha' => now(),
            'estado' => 'confirmado',
        ]);
        $estadoCuotaAntes = $cuota->fresh()->toArray();

        try {
            app(CashMovementService::class)->annul($movimientoInconsistente, 'Reverso invalido.');
            $this->fail('Se esperaba una ValidationException por inconsistencia financiera.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertSame('confirmado', $movimientoInconsistente->fresh()->estado);
        $this->assertSame($estadoCuotaAntes, $cuota->fresh()->toArray());
    }

    public function test_doble_anulacion_no_altera_la_cuota(): void
    {
        $cuota = $this->createPaidCuota(5000, 5000, now()->addMonth());
        $movimiento = $cuota->cashMovements()->first();

        app(CashMovementService::class)->annul($movimiento, 'Primer reverso.');
        $estadoCuota = $cuota->fresh()->toArray();

        try {
            app(CashMovementService::class)->annul($movimiento, 'Segundo reverso.');
            $this->fail('Se esperaba una ValidationException en el segundo intento.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertSame('anulado', $movimiento->fresh()->estado);
        $this->assertSame($estadoCuota, $cuota->fresh()->toArray());
    }

    private function createPaidCuota(float $monto, float $montoPagado, $fechaVencimiento): Cuota
    {
        $cuota = $this->createCuota($monto, $fechaVencimiento);

        return $this->payCuota($cuota, $montoPagado, 'efectivo')->cuota()->first();
    }

    private function createCuota(float $monto, $fechaVencimiento): Cuota
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
        $cliente = Cliente::create(['nombre' => 'Cliente Anulacion']);
        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'precio_final' => 12000,
            'cuota_inicial' => 0,
            'saldo_financiar' => 12000,
            'numero_cuotas' => 1,
            'estado' => 'activa',
        ]);

        return Cuota::create([
            'venta_id' => $venta->id,
            'numero' => 1,
            'fecha_programada' => $fechaVencimiento,
            'fecha_vencimiento' => $fechaVencimiento,
            'monto' => $monto,
            'monto_pagado' => 0,
            'saldo_pendiente' => $monto,
            'estado' => 'pendiente',
        ]);
    }

    private function payCuota(Cuota $cuota, float $monto, string $metodoPago): CashMovement
    {
        app(InstallmentService::class)->pay($cuota, $monto, $metodoPago, User::factory()->create());

        return $cuota->cashMovements()->orderByDesc('id')->first();
    }
}
