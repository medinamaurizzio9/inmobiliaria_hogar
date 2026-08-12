<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\User;
use App\Models\Venta;
use App\Services\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuickCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_acceso_solo_financiero(): void
    {
        $venta = $this->sale();
        foreach (['administrador', 'gerente', 'cajero'] as $role) {
            $this->getAs($this->role($role), $venta)->assertOk();
        }
        foreach (['vendedor', 'supervisor', 'cliente'] as $role) {
            $this->getAs($this->role($role), $venta)->assertForbidden();
        }
    }

    public function test_buscador_unico_encuentra_campos_y_referencia(): void
    {
        $venta = $this->sale();
        $admin = $this->role('administrador');
        $terms = [$venta->cliente->nombre, $venta->cliente->documento, $venta->cliente->telefono, $venta->lote->codigo, $venta->lote->manzano->codigo, $venta->lote->manzano->urbanizacion->nombre, (string) $venta->id];
        foreach ($terms as $term) {
            $this->getAs($admin, $venta, $term)->assertSee($venta->lote->codigo);
        }
        $movement = $venta->cashMovements()->firstOrFail();
        $this->getAs($admin, $venta, $movement->referencia)->assertSee($venta->lote->codigo);
    }

    public function test_tarjetas_separan_terrenos_saldos_y_priorizan_vencida(): void
    {
        $venta = $this->sale();
        $other = Venta::with('lote.manzano.urbanizacion', 'cliente', 'cuotas')->whereKeyNot($venta->id)->whereHas('cuotas')->firstOrFail();
        $other->update(['cliente_id' => $venta->cliente_id]);
        $first = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $first->update(['estado' => 'vencida', 'fecha_vencimiento' => now()->subDay()]);
        $response = $this->getAs($this->role('administrador'), $venta, $venta->cliente->nombre);
        $response->assertSee($venta->lote->codigo)->assertSee($other->lote->codigo)->assertSee('VENCIDA');
        $this->assertNotSame((float) $venta->cuotas()->sum('saldo_pendiente'), (float) $other->cuotas()->sum('saldo_pendiente'));
    }

    public function test_preview_una_varias_y_amortizacion_no_escribe(): void
    {
        $venta = $this->sale();
        $cuotas = $venta->cuotas()->where('saldo_pendiente', '>', 0)->orderBy('numero')->get();
        $service = app(PaymentAllocationService::class);
        $before = $cuotas->map->only(['id', 'monto_pagado', 'saldo_pendiente', 'estado'])->all();
        $one = $service->preview($cuotas->first(), 10, 'cuotas');
        $many = $service->preview($cuotas->first(), (float) $cuotas->first()->saldo_pendiente + 10, 'cuotas');
        $amortization = $service->preview($cuotas->first(), 10, 'amortizacion');
        $this->assertCount(1, $one['aplicaciones']);
        $this->assertCount(2, $many['aplicaciones']);
        $this->assertSame($cuotas->last()->id, $amortization['aplicaciones'][0]['cuota_id']);
        $this->assertSame($before, $venta->cuotas()->whereIn('id', $cuotas->pluck('id'))->orderBy('numero')->get()->map->only(['id', 'monto_pagado', 'saldo_pendiente', 'estado'])->all());
    }

    public function test_preview_rechaza_monto_mayor_al_saldo(): void
    {
        $venta = $this->sale();
        $cuota = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $this->expectException(ValidationException::class);
        app(PaymentAllocationService::class)->preview($cuota, $venta->cuotas()->sum('saldo_pendiente') + 1);
    }

    public function test_efectivo_qr_verificado_y_transferencia_confirmados_crean_aplicaciones(): void
    {
        foreach (['efectivo', 'QR', 'transferencia'] as $method) {
            $venta = $this->sale();
            $cuota = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
            $response = $this->actingAs($this->role('cajero'))->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->post(route('cobranza.store', $venta), $this->paymentData($cuota, $method, 'confirmado', (float) $cuota->saldo_pendiente + 10));
            $response->assertRedirect();
            $movement = CashMovement::latest('id')->firstOrFail();
            $this->assertSame('confirmado', $movement->estado);
            $this->assertGreaterThanOrEqual(2, $movement->pagoAplicaciones()->count());
            $this->actingAs($this->role('cajero'))->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->get(route('pdf.recibo', $movement))->assertOk();
        }
    }

    public function test_qr_pendiente_no_cobra_y_aparece_en_verificacion(): void
    {
        $venta = $this->sale();
        $cuota = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $balance = $cuota->saldo_pendiente;
        $this->actingAs($this->role('cajero'))->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->post(route('cobranza.store', $venta), $this->paymentData($cuota, 'QR', 'pendiente_verificacion', 10))->assertRedirect();
        $movement = CashMovement::latest('id')->firstOrFail();
        $this->assertSame('pendiente_verificacion', $movement->estado);
        $this->assertSame((float) $balance, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame(0, $movement->pagoAplicaciones()->count());
        $this->getAs($this->role('cajero'), $venta)->assertSee($movement->referencia);
    }

    public function test_confirma_y_rechaza_pendientes_con_motivo_obligatorio(): void
    {
        $venta = $this->sale();
        $cuota = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $cajero = $this->role('cajero');
        $pending = $this->pending($venta, $cuota, 10);
        $this->actingAs($cajero)->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->post(route('cobranza.confirm', $pending))->assertRedirect();
        $this->assertSame('confirmado', $pending->fresh()->estado);
        $rejected = $this->pending($venta, $cuota, 10);
        $this->actingAs($cajero)->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->post(route('cobranza.reject', $rejected), [])->assertSessionHasErrors('motivo');
        $this->post(route('cobranza.reject', $rejected), ['motivo' => 'Referencia inválida'])->assertRedirect();
        $this->assertSame('rechazado', $rejected->fresh()->estado);
    }

    public function test_endpoints_rechazan_rol_no_financiero_y_otra_urbanizacion(): void
    {
        $venta = $this->sale();
        $cuota = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        foreach (['vendedor', 'cliente'] as $role) {
            $this->actingAs($this->role($role))->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->post(route('cobranza.store', $venta), $this->paymentData($cuota))->assertForbidden();
        }
        $other = $venta->replicate();
        $other->lote_id = Lote::whereHas('manzano', fn ($q) => $q->where('urbanizacion_id', '!=', $venta->lote->manzano->urbanizacion_id))->firstOrFail()->id;
        $other->save();
        $otherCuota = $cuota->replicate();
        $otherCuota->venta_id = $other->id;
        $otherCuota->save();
        $this->actingAs($this->role('cajero'))->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->post(route('cobranza.store', $other), $this->paymentData($otherCuota))->assertForbidden();
    }

    public function test_caja_hoy_suma_solo_confirmados(): void
    {
        $venta = $this->sale();
        $cuota = $venta->cuotas()->where('saldo_pendiente', '>', 0)->firstOrFail();
        $confirmed = $this->pending($venta, $cuota, 11);
        $confirmed->update(['estado' => 'confirmado']);
        $pending = $this->pending($venta, $cuota, 99);
        $response = $this->getAs($this->role('administrador'), $venta);
        $response->assertSee('Cobrado hoy')->assertSee('11.00')->assertSee($pending->referencia);
    }

    private function sale(): Venta
    {
        return Venta::with('cliente', 'lote.manzano.urbanizacion', 'cuotas', 'cashMovements')->whereHas('cuotas', fn ($q) => $q->where('saldo_pendiente', '>', 20))->inRandomOrder()->firstOrFail();
    }

    private function role(string $role): User
    {
        return User::role($role)->firstOrFail();
    }

    private function getAs(User $user, Venta $venta, string $q = '')
    {
        return $this->actingAs($user)->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])->get(route('cobranza.index', ['q' => $q]));
    }

    private function paymentData(Cuota $cuota, string $method = 'efectivo', string $verification = 'confirmado', float $amount = 10): array
    {
        return ['cuota_id' => $cuota->id, 'monto' => $amount, 'metodo_pago' => $method, 'verificacion' => $verification, 'tipo_aplicacion' => 'cuotas', 'banco' => $method === 'efectivo' ? null : 'Banco', 'referencia' => $method === 'efectivo' ? null : uniqid('COB-'), 'fecha' => now()->toDateString()];
    }

    private function pending(Venta $venta, Cuota $cuota, float $amount): CashMovement
    {
        return CashMovement::create(['user_id' => $this->role('cliente')->id, 'cliente_id' => $venta->cliente_id, 'sale_id' => $venta->id, 'installment_id' => $cuota->id, 'tipo' => 'ingreso', 'concepto' => 'cuota', 'metodo_pago' => 'QR', 'monto' => $amount, 'fecha' => now(), 'banco' => 'Banco', 'referencia' => uniqid('PEND-'), 'estado' => 'pendiente_verificacion']);
    }
}
