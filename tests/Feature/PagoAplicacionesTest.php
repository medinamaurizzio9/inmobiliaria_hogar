<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\PagoAplicacion;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\CashMovementService;
use App\Services\InstallmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PagoAplicacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pago_12000_se_distribuye_en_tres_cuotas(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $cuota1 = $venta->cuotas->get(0);
        $mov = $this->solicitar($cuota1, 'QR', 'REF-MULTI-0001', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $aplicaciones = PagoAplicacion::query()->where('cash_movement_id', $mov->id)->orderBy('cuota_id')->get();
        $this->assertCount(3, $aplicaciones);

        $valueMap = $aplicaciones->mapWithKeys(fn (PagoAplicacion $a): array => [$a->cuota->numero => (float) $a->monto_aplicado]);
        $this->assertSame(5000.0, $valueMap[1]);
        $this->assertSame(5000.0, $valueMap[2]);
        $this->assertSame(2000.0, $valueMap[3]);

        $venta->cuotas->each(function (Cuota $cuota): void {
            $cuota->refresh();
        });
        $this->assertSame('pagada', $venta->cuotas->get(0)->estado);
        $this->assertSame('pagada', $venta->cuotas->get(1)->estado);
        $this->assertSame('parcial', $venta->cuotas->get(2)->estado);
        $this->assertSame(0.0, (float) $venta->cuotas->get(0)->saldo_pendiente);
        $this->assertSame(3000.0, (float) $venta->cuotas->get(2)->saldo_pendiente);
    }

    public function test_excedente_usa_cuotas_siguientes_en_orden_de_vencimiento(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $cuota3 = $venta->cuotas->get(2);
        $cuota2 = $venta->cuotas->get(1);

        // Pagar primera cuota (3000) que deberia ir a la cuota seleccionada
        $mov = $this->solicitar($cuota2, 'transferencia', 'REF-MULTI-0002', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $aplicaciones = PagoAplicacion::query()->where('cash_movement_id', $mov->id)->orderBy('cuota_id')->get();
        $this->assertCount(3, $aplicaciones);

        // La cuota seleccionada (2) se paga primero, luego 1 y 3 en orden de vencimiento
        $map = $aplicaciones->mapWithKeys(fn (PagoAplicacion $a): array => [$a->cuota_id => (float) $a->monto_aplicado]);
        $this->assertSame(5000.0, $map[$cuota2->id]);
        $this->assertSame(5000.0, $map[$venta->cuotas->get(0)->id]);
        $this->assertSame(2000.0, $map[$cuota3->id]);
    }

    public function test_solo_aplica_a_cuotas_de_la_misma_venta(): void
    {
        [$admin, $urb, $ventaA] = $this->ventaConCuotas(2, 5000);

        $clienteB = Cliente::create([
            'nombre' => 'Cliente Multicuota B',
            'documento' => 'CI-9999999',
            'urbanizacion_id' => $urb->id,
        ]);
        $ventaB = $this->crearVenta($urb, $clienteB, 16000, 0, now());
        $this->crearCuotas($ventaB, 2, 8000, now(), 1);

        $cuotaB1 = $ventaB->cuotas->get(0);
        $mov = $this->solicitar($cuotaB1, 'QR', 'REF-MULTI-0003', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $aplicaciones = PagoAplicacion::query()->where('cash_movement_id', $mov->id)->get();
        $this->assertCount(2, $aplicaciones);
        $this->assertSame(0, $aplicaciones->where('cuota_id', $ventaA->cuotas->get(0)->id)->count());
        $this->assertSame(0, $aplicaciones->where('cuota_id', $ventaA->cuotas->get(1)->id)->count());

        $totales = $aplicaciones->sum(fn (PagoAplicacion $a): float => (float) $a->monto_aplicado);
        $this->assertSame(12000.0, $totales);
    }

    public function test_pago_anticipado_aplica_a_cuotas_futuras(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $cuota3 = $venta->cuotas->get(2);

        app(InstallmentService::class)->pay($cuota3, 5000, 'efectivo', $admin);

        $cuota3->refresh();
        $this->assertSame(5000.0, (float) $cuota3->monto_pagado);
        $this->assertSame(0.0, (float) $cuota3->saldo_pendiente);
        $this->assertSame('pagada', $cuota3->estado);
        $this->assertSame(0.0, (float) $venta->cuotas->get(0)->fresh()->monto_pagado);
        $this->assertSame(0.0, (float) $venta->cuotas->get(1)->fresh()->monto_pagado);
    }

    public function test_anulacion_revierte_todas_las_aplicaciones(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $mov = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0004', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->assertCount(3, PagoAplicacion::query()->where('cash_movement_id', $mov->id)->get());

        app(CashMovementService::class)->annul($mov, 'Reverso total del pago.');

        $venta->cuotas->each(function (Cuota $cuota): void {
            $cuota->refresh();
        });
        $this->assertSame(0.0, (float) $venta->cuotas->get(0)->monto_pagado);
        $this->assertSame(0.0, (float) $venta->cuotas->get(1)->monto_pagado);
        $this->assertSame(0.0, (float) $venta->cuotas->get(2)->monto_pagado);
        $this->assertSame(5000.0, (float) $venta->cuotas->get(0)->saldo_pendiente);
        $this->assertSame(5000.0, (float) $venta->cuotas->get(1)->saldo_pendiente);
        $this->assertSame(5000.0, (float) $venta->cuotas->get(2)->saldo_pendiente);
        $this->assertSame('pendiente', $venta->cuotas->get(0)->estado);
        $this->assertSame('anulado', $mov->fresh()->estado);

        // Trazabilidad: las aplicaciones se conservan
        $this->assertCount(3, PagoAplicacion::query()->where('cash_movement_id', $mov->id)->get());
    }

    public function test_anulacion_parcial_revierte_solo_sus_aplicaciones(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $primero = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0005', $admin, ['monto' => 6000]);
        app(CashMovementService::class)->confirm($primero, $admin);

        $segundo = $this->solicitar($venta->cuotas->get(1), 'transferencia', 'REF-MULTI-0006', $admin, ['monto' => 4000]);
        app(CashMovementService::class)->confirm($segundo, $admin);

        app(CashMovementService::class)->annul($primero, 'Reverso de primer pago.');

        $venta->cuotas->each(function (Cuota $cuota): void {
            $cuota->refresh();
        });
        $this->assertSame(0.0, (float) $venta->cuotas->get(0)->monto_pagado);
        $this->assertSame(4000.0, (float) $venta->cuotas->get(1)->monto_pagado);
        $this->assertSame(0.0, (float) $venta->cuotas->get(2)->monto_pagado);
        $this->assertSame('parcial', $venta->cuotas->get(1)->estado);
    }

    public function test_anulacion_multi_cuota_restaura_estado_vencida(): void
    {
        [$admin, $urb, $cliente] = $this->clienteContexto();
        $venta = $this->crearVenta($urb, $cliente, 12000, 0, now()->subMonths(3));
        $this->crearCuotas($venta, 3, 5000, now()->subMonths(3), 0);
        $cuota1 = $venta->cuotas->get(0);
        $this->assertSame('vencida', $cuota1->estado);

        $mov = $this->solicitar($cuota1, 'QR', 'REF-MULTI-0007', $admin, ['monto' => 5000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $cuota1->refresh();
        $this->assertSame('pagada', $cuota1->estado);

        app(CashMovementService::class)->annul($mov, 'Reverso.');

        $cuota1->refresh();
        $this->assertSame('vencida', $cuota1->estado);
        $this->assertSame(0.0, (float) $cuota1->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota1->saldo_pendiente);
    }

    public function test_compatibilidad_historica_movimiento_sin_aplicaciones(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(1, 5000);
        $cuota = $venta->cuotas->get(0);

        // Movimiento historico: solo installment_id, sin pago_aplicaciones
        $hist = CashMovement::create([
            'user_id' => $admin->id,
            'cliente_id' => $venta->cliente_id,
            'sale_id' => $venta->id,
            'installment_id' => $cuota->id,
            'tipo' => 'ingreso',
            'concepto' => 'cuota',
            'metodo_pago' => 'efectivo',
            'monto' => 2000,
            'fecha' => now(),
            'estado' => 'confirmado',
        ]);

        // Simular que ese pago historico quedo reflejado en la cuota
        $cuota->update(['monto_pagado' => 2000, 'saldo_pendiente' => 3000, 'estado' => 'parcial']);

        app(CashMovementService::class)->annul($hist, 'Reverso historico.');

        $cuota->refresh();
        $this->assertSame(0.0, (float) $cuota->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('pendiente', $cuota->estado);
        $this->assertSame('anulado', $hist->fresh()->estado);
        $this->assertSame(0, PagoAplicacion::query()->where('cash_movement_id', $hist->id)->count());
    }

    public function test_recibo_multi_cuota_genera_pdf(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $mov = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0008', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('pdf.recibo', $mov))
            ->assertOk();
    }

    public function test_cajero_ve_caja(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CAJ-0001', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $cajero = $this->crearCajero($urb);

        $this->getCaja($cajero, $urb)
            ->assertOk()
            ->assertSee($mov->referencia);
    }

    public function test_cajero_ve_detalle_y_recibo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CAJ-0002', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('caja.show', $mov))
            ->assertOk()
            ->assertSee($mov->referencia);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('pdf.recibo', $mov))
            ->assertOk();
    }

    public function test_cajero_puede_cobrar_cuota_en_oficina(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->put(route('cuotas.update', $cuota), [
                'monto_pagado' => 2000,
                'metodo_pago' => 'efectivo',
                'referencia' => 'OFICINA-CAJ-01',
            ])
            ->assertRedirect();

        $this->assertSame(2000.0, (float) $cuota->fresh()->monto_pagado);
        $this->assertSame('parcial', $cuota->fresh()->estado);
    }

    public function test_cajero_no_puede_editar_ventas(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('ventas.edit', $cuota->venta))
            ->assertForbidden();
    }

    public function test_cajero_no_puede_anular_ventas(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->delete(route('ventas.destroy', $cuota->venta))
            ->assertForbidden();
    }

    public function test_cajero_no_puede_anular_caja(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CAJ-0003', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->post(route('caja.annul', $mov), ['motivo' => 'Intento de anulacion.'])
            ->assertForbidden();
    }

    public function test_cajero_no_accede_a_usuarios(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('admin.usuarios'))
            ->assertForbidden();
    }

    public function test_vendedor_sin_cobrar_cuotas(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urb = Urbanizacion::orderBy('id')->firstOrFail();
        $cuota = $this->crearCuotaEn($urb);

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('cuotas.index'))
            ->assertForbidden();
    }

    public function test_supervisor_sin_cobrar_cuotas(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $urb = Urbanizacion::orderBy('id')->firstOrFail();

        $this->actingAs($supervisor)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('caja.index'))
            ->assertForbidden();
    }

    public function test_comercial_permisos_vendedor_conservados(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();

        $vendedor->refresh();
        $this->assertTrue($vendedor->can('crear reservas'));
        $this->assertTrue($vendedor->can('ver reservas'));
        $this->assertTrue($vendedor->can('crear clientes'));
        $this->assertFalse($vendedor->can('cobrar cuotas'));
    }

    public function test_cajero_permisos_iniciales(): void
    {
        $this->seed();
        $cajero = $this->crearCajero(Urbanizacion::orderBy('id')->firstOrFail());

        $cajero->refresh();
        $this->assertTrue($cajero->can('cobrar cuotas'));
        $this->assertTrue($cajero->can('ver dashboard'));
        $this->assertTrue($cajero->can('ver clientes'));
        $this->assertFalse($cajero->can('editar ventas'));
        $this->assertFalse($cajero->can('anular ventas'));
        $this->assertFalse($cajero->can('administrar usuarios'));
        $this->assertFalse($cajero->can('anular caja'));
        $this->assertTrue($cajero->hasRole('cajero'));
    }

    public function test_pago_oficina_multi_cuota_en_cuotas(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->put(route('cuotas.update', $venta->cuotas->get(0)), [
                'monto_pagado' => 12000,
                'metodo_pago' => 'banco',
                'referencia' => 'OFICINA-MULTI-01',
            ])
            ->assertRedirect();

        $aplicaciones = PagoAplicacion::query()->count();
        $this->assertSame(3, $aplicaciones);

        $venta->cuotas->each(function (Cuota $cuota): void {
            $cuota->refresh();
        });
        $this->assertSame('pagada', $venta->cuotas->get(0)->estado);
        $this->assertSame('pagada', $venta->cuotas->get(1)->estado);
        $this->assertSame('parcial', $venta->cuotas->get(2)->estado);
        $this->assertSame(2000.0, (float) $venta->cuotas->get(2)->monto_pagado);
    }

    public function test_suma_aplicaciones_nunca_supera_el_monto(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(2, 5000);
        $mov = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0009', $admin, ['monto' => 6000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $aplicaciones = PagoAplicacion::query()->where('cash_movement_id', $mov->id)->get();
        $total = $aplicaciones->sum(fn (PagoAplicacion $a): float => (float) $a->monto_aplicado);
        $this->assertLessThanOrEqual(6000.0, $total);
        $this->assertSame(6000.0, $total);
    }

    public function test_no_supera_saldo_de_cada_cuota(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $mov = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0010', $admin, ['monto' => 20000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $venta->cuotas->each(function (Cuota $cuota): void {
            $cuota->refresh();
            $this->assertLessThanOrEqual((float) $cuota->monto, (float) $cuota->monto_pagado);
            $this->assertSame(0.0, (float) $cuota->saldo_pendiente);
        });
    }

    public function test_pago_multi_cuota_genera_movimientos_y_auditoria(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $mov = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0011', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->assertDatabaseHas('cash_movements', ['id' => $mov->id, 'estado' => 'confirmado']);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'CashMovement',
            'modelo_id' => $mov->id,
            'accion' => 'pago_confirmado',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'CashMovement',
            'modelo_id' => $mov->id,
            'accion' => 'pago_aplicado',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Cuota',
            'accion' => 'cobrar_cuota',
        ]);
    }

    public function test_detalle_caja_muestra_aplicaciones(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $mov = $this->solicitar($venta->cuotas->get(0), 'QR', 'REF-MULTI-0012', $admin, ['monto' => 12000]);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('caja.show', $mov))
            ->assertOk()
            ->assertSee('Aplicacion del pago')
            ->assertSee('Cuota Nro 1')
            ->assertSee('Cuota Nro 3');
    }

    public function test_amortizacion_extraordinaria_reduce_cuotas_desde_el_final(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(4, 5000);

        app(InstallmentService::class)->amortize($venta->cuotas->first(), 12000, 'efectivo', $admin, 'AMORT-001');

        $cuotas = $venta->cuotas()->orderBy('numero')->get();
        $this->assertSame(0.0, (float) $cuotas[0]->monto_pagado);
        $this->assertSame(2000.0, (float) $cuotas[1]->monto_pagado);
        $this->assertSame(5000.0, (float) $cuotas[2]->monto_pagado);
        $this->assertSame(5000.0, (float) $cuotas[3]->monto_pagado);
        $this->assertSame(3000.0, (float) $cuotas[1]->saldo_pendiente);
        $this->assertSame(5000.0, (float) $cuotas[0]->monto);
        $this->assertSame(5000.0, (float) $cuotas[1]->monto);
        $this->assertSame(1, $cuotas->where('saldo_pendiente', '>', 0)->where('saldo_pendiente', '<', 5000)->count());

        $movimiento = CashMovement::query()->latest('id')->firstOrFail();
        $this->assertSame('amortizacion', $movimiento->concepto);
        $this->assertCount(3, $movimiento->pagoAplicaciones);
    }

    public function test_anular_amortizacion_restaura_el_plan(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        app(InstallmentService::class)->amortize($venta->cuotas->first(), 7000, 'efectivo', $admin);
        $movimiento = CashMovement::query()->latest('id')->firstOrFail();

        app(CashMovementService::class)->annul($movimiento, 'Anulacion de prueba.');

        foreach ($venta->cuotas()->get() as $cuota) {
            $this->assertSame(0.0, (float) $cuota->monto_pagado);
            $this->assertSame(5000.0, (float) $cuota->saldo_pendiente);
        }
        $this->assertSame('anulado', $movimiento->fresh()->estado);
    }

    public function test_cajero_puede_elegir_amortizar_desde_cuotas(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(3, 5000);
        $cajero = $this->crearCajero($urb);

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->put(route('cuotas.update', $venta->cuotas->first()), [
                'monto_pagado' => 7000,
                'metodo_pago' => 'efectivo',
                'referencia' => 'AMORT-UI-001',
                'tipo_aplicacion' => 'amortizacion',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cash_movements', [
            'concepto' => 'amortizacion',
            'monto' => 7000,
            'estado' => 'confirmado',
        ]);
        $this->assertSame(5000.0, (float) $venta->cuotas->last()->fresh()->monto_pagado);
    }

    public function test_amortizacion_no_puede_superar_el_saldo_de_la_venta(): void
    {
        [$admin, $urb, $venta] = $this->ventaConCuotas(2, 5000);

        try {
            app(InstallmentService::class)->amortize($venta->cuotas->first(), 10001, 'efectivo', $admin, 'AMORT-EXCESO');
            $this->fail('La amortizacion mayor al saldo debio rechazarse.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('monto_pagado', $exception->errors());
        }

        $this->assertDatabaseMissing('cash_movements', ['referencia' => 'AMORT-EXCESO']);
    }

    private function getCaja(User $user, Urbanizacion $urbanizacion): TestResponse
    {
        return $this->actingAs($user)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('caja.index'));
    }

    private function crearCajero(Urbanizacion $urbanizacion): User
    {
        $cajero = User::factory()->create(['name' => 'Cajero Prueba', 'email' => 'cajero.prueba@impacto.test']);
        $cajero->assignRole('cajero');
        $cajero->urbanizacionesAsignadas()->syncWithoutDetaching([$urbanizacion->id => ['activo' => true]]);

        return $cajero;
    }

    private function solicitar(Cuota $cuota, string $metodo, string $referencia, User $user, array $extra = []): CashMovement
    {
        return app(CashMovementService::class)->solicitarPagoCuota(
            $cuota,
            (float) ($extra['monto'] ?? 2000),
            $metodo,
            $user,
            [
                'referencia' => $referencia,
                'banco' => $extra['banco'] ?? 'Banco Nacional',
                'fecha' => $extra['fecha'] ?? now()->toDateString(),
            ],
        );
    }

    private function contexto(): array
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::orderBy('id')->firstOrFail();
        $cuota = $this->crearCuotaEn($urbanizacion);

        return [$admin, $urbanizacion, $cuota];
    }

    private function clienteContexto(): array
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::orderBy('id')->firstOrFail();
        $cliente = Cliente::create([
            'nombre' => 'Cliente Multicuota',
            'documento' => 'CI-7777777',
            'urbanizacion_id' => $urbanizacion->id,
        ]);

        return [$admin, $urbanizacion, $cliente];
    }

    private function ventaConCuotas(int $cantidad, float $monto): array
    {
        [$admin, $urb, $cliente] = $this->clienteContexto();
        $venta = $this->crearVenta($urb, $cliente, $monto * $cantidad, 0, now());
        $this->crearCuotas($venta, $cantidad, $monto, now(), 1);

        return [$admin, $urb, $venta];
    }

    private function crearVenta(
        Urbanizacion $urbanizacion,
        Cliente $cliente,
        float $precioFinal,
        float $cuotaInicial,
        $fechaVenta
    ): Venta {
        $manzano = Manzano::create([
            'urbanizacion_id' => $urbanizacion->id,
            'codigo' => uniqid('M'),
            'orden' => 1,
        ]);
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

        return Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'fecha_venta' => $fechaVenta instanceof \DateTimeInterface ? $fechaVenta->format('Y-m-d') : $fechaVenta,
            'precio_final' => $precioFinal,
            'cuota_inicial' => $cuotaInicial,
            'saldo_financiar' => $precioFinal - $cuotaInicial,
            'numero_cuotas' => 0,
            'estado' => 'activa',
        ]);
    }

    private function crearCuotas(Venta $venta, int $cantidad, float $monto, $baseFecha, int $mesInicio): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $fecha = $baseFecha instanceof \DateTimeInterface
                ? $baseFecha->copy()->addMonths($mesInicio + $i)
                : Carbon::parse($baseFecha)->addMonths($mesInicio + $i);

            Cuota::create([
                'venta_id' => $venta->id,
                'numero' => $i + 1,
                'fecha_programada' => $fecha,
                'fecha_vencimiento' => $fecha,
                'monto' => $monto,
                'monto_pagado' => 0,
                'saldo_pendiente' => $monto,
                'estado' => $fecha->lt(now()->startOfDay()) ? 'vencida' : 'pendiente',
            ]);
        }
    }

    private function crearCuotaEn(Urbanizacion $urbanizacion, float $monto = 5000): Cuota
    {
        $manzano = Manzano::create([
            'urbanizacion_id' => $urbanizacion->id,
            'codigo' => uniqid('M'),
            'orden' => 1,
        ]);
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
        $cliente = Cliente::create([
            'nombre' => 'Cliente Cajero',
            'documento' => 'CI-8888888',
            'urbanizacion_id' => $urbanizacion->id,
        ]);
        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'precio_final' => 12000,
            'cuota_inicial' => 0,
            'saldo_financiar' => 5000,
            'numero_cuotas' => 1,
            'estado' => 'activa',
        ]);

        return Cuota::create([
            'venta_id' => $venta->id,
            'numero' => 1,
            'fecha_programada' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonth(),
            'monto' => $monto,
            'monto_pagado' => 0,
            'saldo_pendiente' => $monto,
            'estado' => 'pendiente',
        ]);
    }
}
