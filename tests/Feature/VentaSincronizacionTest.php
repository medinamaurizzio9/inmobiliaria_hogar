<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaSincronizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_actualiza_valores_cuotas_caja_listado_dashboard_y_auditoria(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $venta = Venta::with('lote.manzano', 'cuotas')->whereHas('cuotas')->whereDoesntHave('cuotas', fn ($query) => $query->where('monto_pagado', '>', 0))->firstOrFail();
        $oldPendingIds = $venta->cuotas->pluck('id')->all();
        $price = 30000;
        $initial = 6000;
        $installments = 8;

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->put(route('ventas.update', $venta), $this->payload($venta, [
                'precio_final' => $price,
                'cuota_inicial' => $initial,
                'numero_cuotas' => $installments,
                'metodo_pago' => 'transferencia',
                'referencia' => 'ACT-VENTA-01',
                'motivo_cambio' => 'Ajuste integral solicitado por el cliente.',
            ]))
            ->assertRedirect(route('ventas.index'));

        $venta->refresh();
        $this->assertSame(30000.0, (float) $venta->precio_final);
        $this->assertSame(6000.0, (float) $venta->cuota_inicial);
        $this->assertSame(24000.0, (float) $venta->saldo_financiar);
        $this->assertSame(8, $venta->cuotas()->count());
        $this->assertSame([], Cuota::whereIn('id', $oldPendingIds)->pluck('id')->all());

        $this->assertDatabaseHas('cash_movements', [
            'sale_id' => $venta->id,
            'concepto' => 'anticipo',
            'metodo_pago' => 'transferencia',
            'monto' => 6000,
            'referencia' => 'ACT-VENTA-01',
            'estado' => 'confirmado',
        ]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('30,000.00', false)
            ->assertSee('24,000.00', false);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(number_format(Venta::whereIn('estado', ['activa', 'completada'])->sum('precio_final'), 2), false);

        $audit = AuditLog::where('modelo', 'Venta')->where('modelo_id', $venta->id)->where('accion', 'venta_actualizada')->firstOrFail();
        $this->assertSame('Ajuste integral solicitado por el cliente.', $audit->descripcion);
        $this->assertCount(count($oldPendingIds), $audit->datos_nuevos['cuotas_eliminadas']);
        $this->assertCount(8, $audit->datos_nuevos['cuotas_creadas']);
        $this->assertNotEmpty($audit->datos_nuevos['movimientos_caja_actualizados']);
    }

    public function test_cuotas_con_pago_se_conservan_y_solo_pendientes_se_regeneran(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $venta = Venta::with('lote.manzano', 'cuotas')->whereHas('cuotas', fn ($query) => $query->where('monto_pagado', '>', 0))->firstOrFail();
        $paid = $venta->cuotas->where('monto_pagado', '>', 0);
        $pending = $venta->cuotas->where('monto_pagado', '<=', 0);
        $paidIds = $paid->pluck('id')->all();
        $pendingIds = $pending->pluck('id')->all();
        $paidAmount = (float) $paid->sum('monto_pagado');

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->put(route('ventas.update', $venta), $this->payload($venta, [
                'precio_final' => 28000,
                'cuota_inicial' => 4000,
                'numero_cuotas' => 7,
                'motivo_cambio' => 'Reprogramacion del saldo pendiente.',
            ]))
            ->assertRedirect(route('ventas.index'));

        $venta->refresh();
        $this->assertSame($paidIds, Cuota::whereIn('id', $paidIds)->orderBy('id')->pluck('id')->all());
        $this->assertSame([], Cuota::whereIn('id', $pendingIds)->pluck('id')->all());
        $this->assertSame(7, $venta->cuotas()->count());
        $this->assertSame(round(28000 - 4000 - $paidAmount, 2), (float) $venta->saldo_financiar);
    }

    public function test_venta_contado_queda_sin_cuotas_con_movimiento_contado_y_lote_vendido(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $venta = Venta::with('lote.manzano', 'cuotas')->whereHas('cuotas')->whereDoesntHave('cuotas', fn ($query) => $query->where('monto_pagado', '>', 0))->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->put(route('ventas.update', $venta), $this->payload($venta, [
                'tipo_operacion' => 'contado',
                'precio_final' => 25000,
                'cuota_inicial' => 0,
                'numero_cuotas' => 0,
                'estado' => 'completada',
                'metodo_pago' => 'QR',
                'motivo_cambio' => 'Cliente completo el pago al contado.',
            ]))
            ->assertRedirect(route('ventas.index'));

        $venta->refresh();
        $this->assertSame(0, $venta->cuotas()->count());
        $this->assertSame(0.0, (float) $venta->saldo_financiar);
        $this->assertSame('vendido', $venta->lote->fresh()->estado);
        $this->assertDatabaseHas('cash_movements', [
            'sale_id' => $venta->id,
            'concepto' => 'contado',
            'metodo_pago' => 'QR',
            'monto' => 25000,
            'estado' => 'confirmado',
        ]);
        $this->assertSame(1, CashMovement::where('sale_id', $venta->id)->whereNull('installment_id')->where('estado', 'confirmado')->count());
    }

    private function payload(Venta $venta, array $overrides = []): array
    {
        return [
            ...[
                'lote_id' => $venta->lote_id,
                'cliente_id' => $venta->cliente_id,
                'fecha_venta' => $venta->fecha_venta->format('Y-m-d'),
                'tipo_operacion' => $venta->tipo_operacion ?? ((int) $venta->numero_cuotas > 0 ? 'credito' : 'contado'),
                'precio_final' => $venta->precio_final,
                'cuota_inicial' => $venta->cuota_inicial,
                'numero_cuotas' => $venta->numero_cuotas,
                'fecha_primer_vencimiento' => $venta->fecha_primer_vencimiento?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d'),
                'estado' => $venta->estado,
                'observaciones' => $venta->observaciones,
                'metodo_pago' => 'efectivo',
                'motivo_cambio' => 'Actualizacion administrativa.',
            ],
            ...$overrides,
        ];
    }
}
