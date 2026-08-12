<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Devolucion;
use App\Models\Lote;
use App\Models\Reestructuracion;
use App\Models\User;
use App\Models\Venta;
use App\Services\ManagementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_acceso_exclusivo_admin_y_gerente(): void
    {
        $sale = $this->sale();
        foreach (['administrador', 'gerente'] as $role) {
            $this->getAs($role, $sale)->assertOk();
        }
        foreach (['cajero', 'vendedor', 'supervisor', 'cliente'] as $role) {
            $this->getAs($role, $sale)->assertForbidden();
        }
    }

    public function test_kpi_vendido_cobrado_y_estados_excluyen_movimientos_no_confirmados(): void
    {
        $sale = $this->sale();
        $base = $this->data($sale);
        $cuota = $sale->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $pending = $this->movement($sale, $cuota, 91, 'pendiente_verificacion');
        $annulled = $this->movement($sale, $cuota, 92, 'anulado');
        $data = $this->data($sale);
        $this->assertSame($base['kpi']['vendido'], $data['kpi']['vendido']);
        $this->assertSame($base['kpi']['cobrado_bruto'], $data['kpi']['cobrado_bruto']);
        $this->assertFalse($data['payments']->contains($pending));
        $this->assertFalse($data['payments']->contains($annulled));
        $this->assertEqualsWithDelta($data['rows']->sum('saldo'), $data['kpi']['saldo'], .001);
    }

    public function test_cartera_vencida_proxima_anulada_y_dias_atraso(): void
    {
        $sale = $this->sale();
        $cuotas = $sale->cuotas()->where('saldo_pendiente', '>', 0)->take(3)->get();
        $cuotas[0]->update(['estado' => 'vencida', 'fecha_vencimiento' => today()->subDays(5), 'saldo_pendiente' => 40]);
        $cuotas[1]->update(['estado' => 'pendiente', 'fecha_vencimiento' => today()->addDays(2), 'saldo_pendiente' => 30]);
        $cuotas[2]->update(['estado' => 'anulada', 'saldo_pendiente' => 999]);
        $row = $this->data($sale)['rows']->firstWhere('venta_id', $sale->id);
        $this->assertSame('VENCIDA', $row['estado']);
        $this->assertSame(5, $row['dias_atraso']);
        $this->assertSame(40.0, $row['monto_vencido']);
        $this->assertNotSame(1069.0, $row['saldo']);
        $sale->update(['estado' => 'anulada']);
        $row = $this->data($sale)['rows']->firstWhere('venta_id', $sale->id);
        $this->assertSame('RESCINDIDA', $row['estado']);
        $this->assertSame(0.0, $row['saldo']);
    }

    public function test_devolucion_retenido_y_neto_separados(): void
    {
        $sale = $this->sale();
        $admin = $this->role('administrador');
        $before = $this->data($sale)['kpi'];
        Devolucion::create(['venta_id' => $sale->id, 'cliente_id' => $sale->cliente_id, 'monto_pagado' => 100, 'monto_devuelto' => 30, 'monto_retenido_empresa' => 20, 'motivo' => 'Prueba', 'responsable_id' => $admin->id, 'fecha' => today(), 'estado' => 'confirmada']);
        $kpi = $this->data($sale)['kpi'];
        $this->assertSame(30.0, $kpi['devuelto']);
        $this->assertSame(20.0, $kpi['retenido']);
        $this->assertSame($kpi['cobrado_bruto'] - 30, $kpi['cobrado_neto']);
        $this->assertSame($before['cobrado_bruto'], $kpi['cobrado_bruto']);
    }

    public function test_varios_terrenos_permanecen_separados(): void
    {
        $sale = $this->sale();
        $other = Venta::with('lote.manzano.urbanizacion', 'cuotas')->whereKeyNot($sale->id)->whereHas('cuotas')->firstOrFail();
        $other->update(['cliente_id' => $sale->cliente_id]);
        $rows = $this->data($sale, ['cliente_id' => $sale->cliente_id])['rows'];
        $this->assertGreaterThanOrEqual(2, $rows->count());
        $this->assertCount($rows->count(), $rows->pluck('venta_id')->unique());
    }

    public function test_filtros_fecha_cliente_vendedor_modalidad_metodo_y_combinados(): void
    {
        $sale = $this->sale();
        $movement = $sale->cashMovements()->where('estado', 'confirmado')->firstOrFail();
        $filters = ['desde' => $sale->fecha_venta->toDateString(), 'hasta' => $sale->fecha_venta->toDateString(), 'cliente_id' => $sale->cliente_id, 'vendedor_id' => $sale->user_id, 'modalidad' => $sale->tipo_operacion, 'metodo_pago' => $movement->metodo_pago, 'estado_venta' => $sale->estado];
        $data = $this->data($sale, $filters);
        $this->assertTrue($data['ventas']->contains('id', $sale->id));
        $this->assertTrue($data['payments']->every(fn ($p) => $p->metodo_pago === $movement->metodo_pago));
        $this->assertFalse($this->data($sale, ['modalidad' => 'modalidad-imposible'])['ventas']->contains('id', $sale->id));
    }

    public function test_agrupaciones_urbanizacion_vendedor_modalidad_metodo_y_caja_usuario(): void
    {
        $sale = $this->sale();
        $data = $this->data($sale);
        $this->assertTrue($data['byUrbanization']->contains('nombre', $sale->lote->manzano->urbanizacion->nombre));
        $this->assertTrue($data['bySeller']->contains('nombre', $sale->user?->name ?? 'Sin asignar'));
        $this->assertTrue($data['byModality']->contains('nombre', $sale->tipo_operacion));
        $this->assertNotEmpty($data['byMethod']);
        $this->assertNotEmpty($data['byCashier']);
    }

    public function test_devoluciones_reestructuraciones_y_proximos_cobros(): void
    {
        $sale = $this->sale();
        $admin = $this->role('administrador');
        $cuota = $sale->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $cuota->update(['estado' => 'pendiente', 'fecha_vencimiento' => today()->addDays(3)]);
        Reestructuracion::create(['venta_id' => $sale->id, 'saldo_antes' => 100, 'numero_cuotas_pendientes_antes' => 2, 'plazo_anterior' => 2, 'nuevo_plazo' => 3, 'fecha' => today(), 'fecha_primer_vencimiento' => today()->addMonth(), 'administrador_id' => $admin->id, 'motivo' => 'Prueba', 'snapshot_antes' => [], 'snapshot_despues' => []]);
        Devolucion::create(['venta_id' => $sale->id, 'cliente_id' => $sale->cliente_id, 'monto_pagado' => 10, 'monto_devuelto' => 0, 'monto_retenido_empresa' => 10, 'motivo' => 'Prueba', 'responsable_id' => $admin->id, 'fecha' => today(), 'estado' => 'confirmada']);
        $data = $this->data($sale, ['horizonte' => 7]);
        $this->assertTrue($data['restructures']->contains('venta_id', $sale->id));
        $this->assertTrue($data['refunds']->contains('venta_id', $sale->id));
        $this->assertTrue($data['upcoming']->contains(fn ($r) => $r['cuota'] === $cuota->numero));
    }

    public function test_csv_respeta_filtros_excluye_pendientes_y_otro_alcance(): void
    {
        $sale = $this->sale();
        $other = $sale->replicate();
        $other->lote_id = Lote::whereHas('manzano', fn ($q) => $q->where('urbanizacion_id', '!=', $sale->lote->manzano->urbanizacion_id))->firstOrFail()->id;
        $other->cliente_id = Cliente::whereKeyNot($sale->cliente_id)->firstOrFail()->id;
        $other->save();
        $other->load('cliente');
        $pending = $this->movement($sale, $sale->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail(), 777, 'pendiente_verificacion');
        $this->actingAs($this->role('administrador'))->withSession(['urbanizacion_id' => $sale->lote->manzano->urbanizacion_id])->get(route('reportes.gerencia.csv', ['cliente_id' => $sale->cliente_id]))->assertOk()->assertSee($sale->cliente->nombre)->assertDontSee($other->cliente->nombre)->assertDontSee($pending->referencia);
    }

    private function sale(): Venta
    {
        return Venta::with('cliente', 'user', 'lote.manzano.urbanizacion', 'cuotas', 'cashMovements')->whereHas('cuotas')->where('estado', '!=', 'anulada')->firstOrFail();
    }

    private function role(string $role): User
    {
        return User::role($role)->firstOrFail();
    }

    private function getAs(string $role, Venta $sale)
    {
        return $this->actingAs($this->role($role))->withSession(['urbanizacion_id' => $sale->lote->manzano->urbanizacion_id])->get(route('reportes.gerencia'));
    }

    private function data(Venta $sale, array $filters = []): array
    {
        return app(ManagementReportService::class)->data(['urbanizacion_id' => $sale->lote->manzano->urbanizacion_id, ...$filters]);
    }

    private function movement(Venta $sale, Cuota $cuota, float $amount, string $state): CashMovement
    {
        return CashMovement::create(['user_id' => $this->role('cajero')->id, 'cliente_id' => $sale->cliente_id, 'sale_id' => $sale->id, 'installment_id' => $cuota->id, 'tipo' => 'ingreso', 'concepto' => 'cuota', 'metodo_pago' => 'QR', 'monto' => $amount, 'fecha' => today(), 'referencia' => uniqid('GER-'), 'estado' => $state]);
    }
}
