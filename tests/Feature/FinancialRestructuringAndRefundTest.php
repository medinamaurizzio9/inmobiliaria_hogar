<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\PagoAplicacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\DebtRestructuringService;
use App\Services\SaleRescissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialRestructuringAndRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reestructura_credito_preservando_historia_y_generando_plan_exacto(): void
    {
        [$admin, $venta] = $this->sale('credito');
        $cuotas = $venta->cuotas()->orderBy('numero')->get();
        $pagada = $cuotas->first();
        $pagada->update(['monto_pagado' => $pagada->monto, 'saldo_pendiente' => 0, 'estado' => 'pagada', 'fecha_pago' => now()]);
        $parcial = $cuotas->get(1);
        $parcial->update(['monto_pagado' => 10, 'saldo_pendiente' => (float) $parcial->monto - 10, 'estado' => 'parcial']);
        $saldo = $venta->cuotas()->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->sum('saldo_pendiente');

        $record = app(DebtRestructuringService::class)->restructure($venta, $this->restructureData(7), $admin);

        $this->assertDatabaseHas('cuotas', ['id' => $pagada->id, 'estado' => 'pagada', 'monto_pagado' => $pagada->monto]);
        $this->assertDatabaseHas('cuotas', ['id' => $parcial->id, 'estado' => 'anulada', 'monto_pagado' => 10]);
        $this->assertSame(7, $venta->cuotas()->where('estado', 'pendiente')->count());
        $this->assertEqualsWithDelta($saldo, $venta->cuotas()->where('estado', 'pendiente')->sum('monto'), .001);
        $this->assertSame('2030-02-28', $venta->cuotas()->where('estado', 'pendiente')->orderBy('numero')->first()->fecha_vencimiento->toDateString());
        $this->assertSame('2030-08-31', $venta->cuotas()->where('estado', 'pendiente')->orderByDesc('numero')->first()->fecha_vencimiento->toDateString());
        $this->assertNotEmpty($record->snapshot_antes['cuotas']);
        $this->assertCount(7, $record->snapshot_despues['cuotas']);
        $this->assertDatabaseHas('audit_logs', ['modelo_id' => $venta->id, 'accion' => 'reestructuracion_creada']);
    }

    public function test_admin_reestructura_semicontado_y_residuo_va_a_ultima_cuota(): void
    {
        [$admin, $venta] = $this->sale('semicontado');
        foreach ($venta->cuotas as $cuota) {
            $cuota->update(['monto' => '33.34', 'saldo_pendiente' => '33.34']);
        }
        $venta->cuotas()->skip(3)->take(PHP_INT_MAX)->update(['estado' => 'anulada', 'saldo_pendiente' => 0]);
        app(DebtRestructuringService::class)->restructure($venta, $this->restructureData(3), $admin);
        $new = $venta->cuotas()->where('estado', 'pendiente')->orderBy('numero')->pluck('monto')->map(fn ($v) => (float) $v)->all();
        $this->assertSame([22.22, 22.22, 22.24], $new);
    }

    public function test_reestructuracion_rechaza_contado_pagada_anulada_y_plazo_invalido(): void
    {
        [$admin, $venta] = $this->sale('credito');
        foreach (['contado', 'anulada'] as $invalid) {
            $copy = $venta->replicate();
            $copy->tipo_operacion = $invalid === 'contado' ? 'contado' : 'credito';
            $copy->estado = $invalid === 'anulada' ? 'anulada' : 'activa';
            $copy->save();
            try {
                app(DebtRestructuringService::class)->restructure($copy, $this->restructureData(2), $admin);
                $this->fail('Debio rechazarse');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $venta->cuotas()->update(['estado' => 'pagada', 'saldo_pendiente' => 0]);
        $this->expectException(ValidationException::class);
        app(DebtRestructuringService::class)->restructure($venta, $this->restructureData(0), $admin);
    }

    public function test_roles_no_administradores_no_reestructuran(): void
    {
        [, $venta] = $this->sale('credito');
        foreach (['gerente', 'cajero', 'vendedor', 'supervisor'] as $role) {
            $user = User::role($role)->firstOrFail();
            try {
                app(DebtRestructuringService::class)->restructure($venta, $this->restructureData(2), $user);
                $this->fail("{$role} no debe reestructurar");
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_historial_y_botones_solo_se_muestran_al_admin(): void
    {
        [$admin, $venta] = $this->sale('credito');
        app(DebtRestructuringService::class)->restructure($venta, $this->restructureData(2), $admin);
        $session = ['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id];
        $this->actingAs($admin)->withSession($session)->get(route('ventas.show', $venta))->assertOk()->assertSee('Historial de reestructuraciones')->assertSee('Reestructurar deuda')->assertSee('Rescindir venta');
        $this->actingAs(User::role('gerente')->firstOrFail())->withSession($session)->get(route('ventas.show', $venta))->assertOk()->assertDontSee('Reestructurar deuda')->assertDontSee('Rescindir venta')->assertSee('Historial de reestructuraciones');
    }

    public function test_admin_rescinde_preserva_pagos_aplicaciones_y_cuotas_creando_egreso(): void
    {
        [$admin, $venta] = $this->sale('credito');
        $cuota = $venta->cuotas()->first();
        $movement = CashMovement::create(['user_id' => $admin->id, 'cliente_id' => $venta->cliente_id, 'sale_id' => $venta->id, 'installment_id' => $cuota->id, 'tipo' => 'ingreso', 'concepto' => 'cuota', 'metodo_pago' => 'efectivo', 'monto' => '100.00', 'fecha' => now(), 'estado' => 'confirmado']);
        $application = PagoAplicacion::create(['cash_movement_id' => $movement->id, 'cuota_id' => $cuota->id, 'monto_aplicado' => '100.00', 'fecha_aplicacion' => now(), 'orden_aplicacion' => 1]);
        $paidTotal = CashMovement::where('sale_id', $venta->id)->where('tipo', 'ingreso')->where('estado', 'confirmado')->sum('monto');
        $count = $venta->cuotas()->count();
        $refund = app(SaleRescissionService::class)->rescind($venta, $this->refundData(60, 40), $admin);

        $this->assertDatabaseHas('cash_movements', ['devolucion_id' => $refund->id, 'tipo' => 'egreso', 'concepto' => 'devolucion', 'monto' => 60, 'estado' => 'confirmado']);
        $this->assertDatabaseHas('cash_movements', ['id' => $movement->id, 'tipo' => 'ingreso', 'estado' => 'confirmado']);
        $this->assertDatabaseHas('pago_aplicaciones', ['id' => $application->id, 'monto_aplicado' => 100]);
        $this->assertSame($count, $venta->cuotas()->count());
        $this->assertSame(0.0, (float) $venta->cuotas()->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->sum('saldo_pendiente'));
        $this->assertSame('anulada', $venta->fresh()->estado);
        $this->assertSame('disponible', $venta->lote->fresh()->estado);
        $this->assertSame((float) $paidTotal, (float) $refund->monto_pagado);
        $this->assertSame(40.0, (float) $refund->monto_retenido_empresa);
        $this->assertDatabaseHas('audit_logs', ['modelo_id' => $venta->id, 'accion' => 'venta_rescindida']);
    }

    public function test_retenido_no_genera_egreso_y_aparece_en_reporte(): void
    {
        [$admin, $venta] = $this->sale('credito');
        CashMovement::create(['user_id' => $admin->id, 'cliente_id' => $venta->cliente_id, 'sale_id' => $venta->id, 'tipo' => 'ingreso', 'concepto' => 'anticipo', 'metodo_pago' => 'efectivo', 'monto' => '100.00', 'fecha' => now(), 'estado' => 'confirmado']);
        app(SaleRescissionService::class)->rescind($venta, $this->refundData(0, 100), $admin);
        $this->assertSame(0, CashMovement::where('concepto', 'devolucion')->count());
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->get(route('reportes.index'))->assertOk()->assertSee('Monto retenido')->assertSee('100.00');
    }

    public function test_rescision_rechaza_suma_superior_a_pagado(): void
    {
        [$admin, $venta] = $this->sale('credito');
        $this->expectException(ValidationException::class);
        app(SaleRescissionService::class)->rescind($venta, $this->refundData(999999, 1), $admin);
    }

    public function test_gerente_no_rescinde(): void
    {
        [, $venta] = $this->sale('credito');
        $this->expectException(HttpException::class);
        app(SaleRescissionService::class)->rescind($venta, $this->refundData(0, 0), User::role('gerente')->firstOrFail());
    }

    public function test_rescision_con_reserva_activa_deja_lote_reservado(): void
    {
        [$admin, $venta] = $this->sale('credito');
        $venta->lote->reservas()->create(['cliente_id' => $venta->cliente_id, 'usuario_id' => $admin->id, 'fecha_reserva' => now(), 'fecha_vencimiento' => now()->addDay(), 'monto_reserva' => 0, 'tipo_operacion' => 'credito', 'estado' => 'activa']);
        app(SaleRescissionService::class)->rescind($venta, $this->refundData(0, 0), $admin);
        $this->assertSame('reservado', $venta->lote->fresh()->estado);
    }

    private function sale(string $type): array
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $venta = Venta::with('lote.manzano', 'cuotas')->where('tipo_operacion', $type)->whereHas('cuotas')->first();
        if (! $venta) {
            $venta = Venta::with('lote.manzano', 'cuotas')->whereHas('cuotas')->firstOrFail();
            $venta->update(['tipo_operacion' => $type, 'estado' => 'activa']);
        }

        return [$admin, $venta->fresh(['lote.manzano', 'cuotas'])];
    }

    private function restructureData(int $term): array
    {
        return ['nuevo_plazo' => $term, 'fecha_primer_vencimiento' => '2030-02-28', 'motivo' => 'Acuerdo administrativo documentado.', 'observaciones' => 'Prueba'];
    }

    private function refundData(float $refund, float $retained): array
    {
        return ['monto_devuelto' => $refund, 'monto_retenido_empresa' => $retained, 'metodo_pago' => 'transferencia', 'referencia' => 'DEV-1', 'motivo' => 'Rescision acordada.', 'observaciones' => 'Prueba'];
    }
}
