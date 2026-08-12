<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StabilityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_no_puede_anular_caja(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $movimiento = CashMovement::firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $vendedor->urbanizacionesAsignadas()->firstOrFail()->id])
            ->post(route('caja.annul', $movimiento))
            ->assertForbidden();
    }

    public function test_cliente_no_puede_ver_pdf_de_otro_cliente(): void
    {
        $this->seed();

        $clienteUser = User::where('email', 'cliente@impacto.test')->firstOrFail();
        $ventaAjena = Venta::where('cliente_id', '!=', $clienteUser->cliente_id)->firstOrFail();

        $this->actingAs($clienteUser)
            ->get(route('pdf.plan', $ventaAjena))
            ->assertForbidden();
    }

    public function test_roles_comerciales_no_acceden_directamente_a_deuda_ventas_o_documentos_financieros(): void
    {
        $this->seed();

        $venta = Venta::whereHas('cuotas')->firstOrFail();

        foreach (['vendedor@impacto.test', 'supervisor@impacto.test'] as $email) {
            $usuario = User::where('email', $email)->firstOrFail();
            $session = ['urbanizacion_id' => $usuario->urbanizacionesAsignadas()->firstOrFail()->id];

            foreach ([
                route('ventas.index'),
                route('ventas.show', $venta),
                route('pdf.plan', $venta),
                route('pdf.contrato', $venta),
            ] as $url) {
                $this->actingAs($usuario)->withSession($session)->get($url)->assertForbidden();
            }
        }
    }

    public function test_cajero_ve_acciones_de_verificacion_que_backend_le_permite(): void
    {
        $this->seed();

        $cajero = User::where('email', 'cajero@impacto.test')->firstOrFail();
        $movimiento = CashMovement::whereNotNull('sale_id')->firstOrFail();
        $movimiento->forceFill(['estado' => 'pendiente_verificacion'])->save();
        $urbanizacionId = $movimiento->venta?->lote->manzano->urbanizacion_id
            ?? $movimiento->cuota?->venta->lote->manzano->urbanizacion_id;

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->get(route('caja.show', $movimiento))
            ->assertOk()
            ->assertSee('Confirmar pago')
            ->assertSee('Rechazar pago');
    }

    public function test_vendedor_no_puede_editar_cuota_pagada(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacionId = $vendedor->urbanizacionesAsignadas()->firstOrFail()->id;
        $cuotaPagada = Cuota::where('estado', 'pagada')
            ->whereHas('venta.lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacionId))
            ->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->put(route('cuotas.update', $cuotaPagada), [
                'monto_pagado' => 1,
                'metodo_pago' => 'efectivo',
            ])
            ->assertForbidden();
    }

    public function test_gerente_no_puede_editar_cuota_pagada(): void
    {
        $this->seed();

        $gerente = User::where('email', 'gerente@impacto.test')->firstOrFail();
        $urbanizacionId = Urbanizacion::orderBy('id')->firstOrFail()->id;
        $cuotaPagada = Cuota::where('estado', 'pagada')
            ->whereHas('venta.lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacionId))
            ->firstOrFail();

        $this->actingAs($gerente)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->put(route('cuotas.update', $cuotaPagada), [
                'monto_pagado' => 1,
                'metodo_pago' => 'efectivo',
            ])
            ->assertForbidden();
    }

    public function test_cajero_no_puede_editar_cuota_pagada(): void
    {
        $this->seed();

        $cajero = User::where('email', 'cajero@impacto.test')->firstOrFail();
        $urbanizacionId = Urbanizacion::orderBy('id')->firstOrFail()->id;
        $cuotaPagada = Cuota::where('estado', 'pagada')
            ->whereHas('venta.lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacionId))
            ->firstOrFail();

        $this->actingAs($cajero)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->put(route('cuotas.update', $cuotaPagada), [
                'monto_pagado' => 1,
                'metodo_pago' => 'efectivo',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_no_puede_editar_cuota_pagada(): void
    {
        $this->seed();

        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $urbanizacionId = $supervisor->urbanizacionesAsignadas()->firstOrFail()->id;
        $cuotaPagada = Cuota::where('estado', 'pagada')
            ->whereHas('venta.lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacionId))
            ->firstOrFail();

        $this->actingAs($supervisor)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->put(route('cuotas.update', $cuotaPagada), [
                'monto_pagado' => 1,
                'metodo_pago' => 'efectivo',
            ])
            ->assertForbidden();
    }

    public function test_administrador_puede_modificar_cuota_pagada(): void
    {
        $this->seed();

        $administrador = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacionId = Urbanizacion::orderBy('id')->firstOrFail()->id;
        $cuotaPagada = Cuota::where('estado', 'pagada')
            ->whereHas('venta.lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacionId))
            ->firstOrFail();

        $this->actingAs($administrador)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->put(route('cuotas.update', $cuotaPagada), [
                'monto_pagado' => 500,
                'metodo_pago' => 'efectivo',
                'referencia' => 'ADMIN-AJUSTE-01',
            ])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual((float) $cuotaPagada->monto_pagado, (float) $cuotaPagada->fresh()->monto_pagado);
    }

    public function test_reserva_vencida_libera_lote(): void
    {
        $this->seed();

        $reserva = Reserva::where('estado', 'activa')->firstOrFail();
        app(ReservationService::class)->expire($reserva, User::where('email', 'admin@impacto.test')->first());

        $this->assertSame('vencida', $reserva->fresh()->estado);
        $this->assertSame('disponible', $reserva->lote->fresh()->estado);
    }

    public function test_recibo_pdf_se_genera_correctamente(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $movimiento = CashMovement::firstOrFail();
        $urbanizacionId = $movimiento->venta?->lote->manzano->urbanizacion_id
            ?? $movimiento->reserva?->lote->manzano->urbanizacion_id
            ?? $movimiento->cuota?->venta->lote->manzano->urbanizacion_id;

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->get(route('pdf.recibo', $movimiento))
            ->assertOk();
    }

    public function test_estado_de_cuenta_calcula_saldo_correcto(): void
    {
        $this->seed();

        $cliente = Cliente::with('ventas.cuotas')->whereHas('ventas.cuotas')->firstOrFail();
        $esperado = (float) $cliente->ventas->flatMap->cuotas->sum('saldo_pendiente');

        $this->assertEquals($esperado, $cliente->saldoPendiente());
    }

    public function test_no_existen_registros_huerfanos_en_datos_semilla(): void
    {
        $this->seed();

        $this->assertSame(0, Venta::whereDoesntHave('cliente')->orWhereDoesntHave('lote')->orWhereDoesntHave('user')->count());
        $this->assertSame(0, Cuota::whereDoesntHave('venta')->count());
        $this->assertSame(0, CashMovement::whereNotNull('sale_id')->whereDoesntHave('venta')->count());
        $this->assertSame(0, CashMovement::whereNotNull('reservation_id')->whereDoesntHave('reserva')->count());
        $this->assertSame(0, CashMovement::whereNotNull('installment_id')->whereDoesntHave('cuota')->count());
        $this->assertSame(120, Lote::count());
    }
}
