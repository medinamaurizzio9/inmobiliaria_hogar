<?php

namespace Tests\Feature;

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
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PagoVerificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_qr_queda_pendiente_verificacion(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-QR-0001', $admin);

        $this->assertSame('pendiente_verificacion', $mov->fresh()->estado);
    }

    public function test_registro_transferencia_queda_pendiente_verificacion(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-TRF-0001', $admin);

        $this->assertSame('pendiente_verificacion', $mov->fresh()->estado);
    }

    public function test_registro_pendiente_no_modifica_monto_pagado_de_la_cuota(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $this->solicitar($cuota, 'QR', 'REF-QR-0002', $admin);

        $this->assertSame(0.0, (float) $cuota->fresh()->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame('pendiente', $cuota->fresh()->estado);
    }

    public function test_registro_pendiente_no_modifica_el_saldo_financiero_de_la_venta(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $saldoFinanciar = (float) $cuota->venta->saldo_financiar;

        $this->solicitar($cuota, 'transferencia', 'REF-TRF-0002', $admin);

        $this->assertSame($saldoFinanciar, (float) $cuota->venta->fresh()->saldo_financiar);
    }

    public function test_registro_exige_referencia(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();

        $this->expectException(ValidationException::class);
        app(CashMovementService::class)->solicitarPagoCuota($cuota, 2000, 'QR', $admin, ['banco' => 'Banco Nacional']);
    }

    public function test_registro_conserva_banco_y_referencia(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-QR-0003', $admin, ['banco' => 'Banco Mercantil']);

        $this->assertSame('Banco Mercantil', $mov->fresh()->banco);
        $this->assertSame('REF-QR-0003', $mov->fresh()->referencia);
    }

    public function test_registro_conserva_la_fecha_declarada(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-TRF-0003', $admin, ['fecha' => '2026-07-20']);

        $this->assertSame('2026-07-20', $mov->fresh()->fecha->format('Y-m-d'));
    }

    public function test_registro_exige_banco_para_qr_y_transferencia(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();

        $this->expectException(ValidationException::class);
        app(CashMovementService::class)->solicitarPagoCuota($cuota, 2000, 'transferencia', $admin, ['referencia' => 'REF-TRF-X']);
    }

    public function test_registro_rechaza_metodo_no_qr_ni_transferencia(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();

        $this->expectException(ValidationException::class);
        $this->solicitar($cuota, 'efectivo', 'REF-EFE-0001', $admin);
    }

    public function test_registro_no_duplica_referencia_activa(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $this->solicitar($cuota, 'QR', 'REF-DUP-0001', $admin);

        $this->expectException(ValidationException::class);
        $this->solicitar($cuota, 'transferencia', 'REF-DUP-0001', $admin);
    }

    public function test_referencia_reutilizable_tras_rechazo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REUSE-0001', $admin);
        app(CashMovementService::class)->reject($mov, 'Pago incorrecto.', $admin);

        $segundo = $this->solicitar($cuota, 'transferencia', 'REF-REUSE-0001', $admin);

        $this->assertSame('pendiente_verificacion', $segundo->fresh()->estado);
    }

    public function test_confirmar_pago_parcial_actualiza_cuota(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CONF-0001', $admin);

        app(CashMovementService::class)->confirm($mov, $admin);

        $cuota->refresh();
        $this->assertSame(2000.0, (float) $cuota->monto_pagado);
        $this->assertSame(3000.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('parcial', $cuota->estado);
    }

    public function test_confirmar_pago_total_marca_cuota_pagada(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-CONF-0002', $admin, ['monto' => 5000]);

        app(CashMovementService::class)->confirm($mov, $admin);

        $cuota->refresh();
        $this->assertSame(5000.0, (float) $cuota->monto_pagado);
        $this->assertSame(0.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('pagada', $cuota->estado);
        $this->assertNotNull($cuota->fecha_pago);
    }

    public function test_confirmar_pago_aplica_excedente_hasta_el_saldo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CONF-0003', $admin, ['monto' => 6000]);

        app(CashMovementService::class)->confirm($mov, $admin);

        $cuota->refresh();
        $this->assertSame(5000.0, (float) $cuota->monto_pagado);
        $this->assertSame(0.0, (float) $cuota->saldo_pendiente);
        $this->assertSame('pagada', $cuota->estado);
        $this->assertSame('confirmado', $mov->fresh()->estado);
    }

    public function test_excedente_sin_cuota_disp_solo_paga_hasta_el_saldo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CONF-0004', $admin, ['monto' => 6000]);

        app(CashMovementService::class)->confirm($mov, $admin);

        $this->assertSame(5000.0, (float) $cuota->fresh()->monto_pagado);
        $this->assertSame(0.0, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame('pagada', $cuota->fresh()->estado);
        $this->assertSame('confirmado', $mov->fresh()->estado);
        $this->assertSame(1, $mov->fresh()->pagoAplicaciones()->count());
        $this->assertSame(5000.0, (float) $mov->fresh()->pagoAplicaciones()->first()->monto_aplicado);
    }

    public function test_no_permite_doble_confirmacion(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CONF-0005', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->expectException(ValidationException::class);
        app(CashMovementService::class)->confirm($mov, $admin);
    }

    public function test_confirmar_guarda_confirmador_y_fecha(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CONF-0006', $admin);

        app(CashMovementService::class)->confirm($mov, $admin);

        $mov = $mov->fresh();
        $this->assertSame('confirmado', $mov->estado);
        $this->assertSame($admin->id, $mov->confirmado_por);
        $this->assertNotNull($mov->confirmado_en);
    }

    public function test_confirmar_registra_auditoria(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-CONF-0007', $admin);

        app(CashMovementService::class)->confirm($mov, $admin);

        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'CashMovement',
            'modelo_id' => $mov->id,
            'accion' => 'pago_confirmado',
        ]);
    }

    public function test_admin_confirma_pago_via_http(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-HTTP-CONF-0001', $admin);

        $this->postConfirm($admin, $mov, $urb)
            ->assertRedirect(route('caja.index'))
            ->assertSessionHas('status');

        $this->assertSame('confirmado', $mov->fresh()->estado);
        $this->assertSame($admin->id, $mov->fresh()->confirmado_por);
    }

    public function test_gerente_confirma_pago_via_http(): void
    {
        $this->seed();
        $gerente = User::where('email', 'gerente@impacto.test')->firstOrFail();
        $urb = Urbanizacion::orderBy('id')->firstOrFail();
        $cuota = $this->crearCuotaEn($urb);
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-HTTP-CONF-0002', $gerente);

        $this->postConfirm($gerente, $mov, $urb)
            ->assertRedirect(route('caja.index'))
            ->assertSessionHas('status');

        $this->assertSame('confirmado', $mov->fresh()->estado);
        $this->assertSame($gerente->id, $mov->fresh()->confirmado_por);
    }

    public function test_supervisor_no_puede_confirmar_via_http(): void
    {
        $this->seed();
        $supervisor = User::where('email', 'supervisor@impacto.test')->firstOrFail();
        $urb = Urbanizacion::orderBy('id')->firstOrFail();
        $cuota = $this->crearCuotaEn($urb);
        $mov = $this->solicitar($cuota, 'QR', 'REF-HTTP-CONF-0003', $supervisor);

        $this->postConfirm($supervisor, $mov, $urb)->assertForbidden();

        $this->assertSame('pendiente_verificacion', $mov->fresh()->estado);
    }

    public function test_vendedor_no_puede_confirmar_via_http(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urb = Urbanizacion::orderBy('id')->firstOrFail();
        $cuota = $this->crearCuotaEn($urb);
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-HTTP-CONF-0004', $vendedor);

        $this->postConfirm($vendedor, $mov, $urb)->assertForbidden();

        $this->assertSame('pendiente_verificacion', $mov->fresh()->estado);
    }

    public function test_rechazar_pago_pendiente_queda_rechazado(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REJ-0001', $admin);

        app(CashMovementService::class)->reject($mov, 'Referencia no coincide con la entidad bancaria.', $admin);

        $mov = $mov->fresh();
        $this->assertSame('rechazado', $mov->estado);
        $this->assertSame('Referencia no coincide con la entidad bancaria.', $mov->motivo_rechazo);
    }

    public function test_rechazo_no_modifica_la_cuota(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REJ-0002', $admin);

        app(CashMovementService::class)->reject($mov, 'Pago duplicado.', $admin);

        $this->assertSame(0.0, (float) $cuota->fresh()->monto_pagado);
        $this->assertSame(5000.0, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame('pendiente', $cuota->fresh()->estado);
    }

    public function test_rechazo_exige_motivo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REJ-0003', $admin);

        $this->expectException(ValidationException::class);
        app(CashMovementService::class)->reject($mov, '   ', $admin);
    }

    public function test_no_permite_rechazar_pago_confirmado(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REJ-0004', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->expectException(ValidationException::class);
        app(CashMovementService::class)->reject($mov, 'Rechazo tardio.', $admin);
    }

    public function test_no_permite_confirmar_pago_rechazado(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REJ-0005', $admin);
        app(CashMovementService::class)->reject($mov, 'Pago no verificable.', $admin);

        $this->expectException(ValidationException::class);
        app(CashMovementService::class)->confirm($mov, $admin);
    }

    public function test_rechazo_registra_auditoria(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-REJ-0006', $admin);

        app(CashMovementService::class)->reject($mov, 'Monto incorrecto.', $admin);

        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'CashMovement',
            'modelo_id' => $mov->id,
            'accion' => 'pago_rechazado',
        ]);
    }

    public function test_admin_rechaza_pago_via_http(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-HTTP-REJ-0001', $admin);

        $this->postReject($admin, $mov, $urb, ['motivo' => 'Referencia invalida.'])
            ->assertRedirect(route('caja.index'))
            ->assertSessionHas('status');

        $this->assertSame('rechazado', $mov->fresh()->estado);
        $this->assertSame('Referencia invalida.', $mov->fresh()->motivo_rechazo);
    }

    public function test_rechazo_http_exige_motivo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-HTTP-REJ-0002', $admin);

        $this->postReject($admin, $mov, $urb, ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame('pendiente_verificacion', $mov->fresh()->estado);
    }

    public function test_vendedor_no_puede_rechazar_via_http(): void
    {
        $this->seed();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urb = Urbanizacion::orderBy('id')->firstOrFail();
        $cuota = $this->crearCuotaEn($urb);
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-HTTP-REJ-0003', $vendedor);

        $this->postReject($vendedor, $mov, $urb, ['motivo' => 'Razon.'])->assertForbidden();

        $this->assertSame('pendiente_verificacion', $mov->fresh()->estado);
    }

    public function test_pago_oficina_queda_confirmado_de_inmediato(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        app(InstallmentService::class)->pay($cuota, 2000, 'efectivo', $admin);

        $mov = $cuota->cashMovements()->orderByDesc('id')->first();
        $this->assertSame('confirmado', $mov->estado);
        $this->assertSame($admin->id, $mov->confirmado_por);
        $this->assertNotNull($mov->confirmado_en);
    }

    public function test_pago_oficina_aplica_a_la_cuota(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        app(InstallmentService::class)->pay($cuota, 2000, 'efectivo', $admin);

        $this->assertSame(2000.0, (float) $cuota->fresh()->monto_pagado);
        $this->assertSame(3000.0, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame('parcial', $cuota->fresh()->estado);
    }

    public function test_pago_oficina_aplica_excedente_hasta_el_saldo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();

        app(InstallmentService::class)->pay($cuota, 6000, 'efectivo', $admin);

        $this->assertSame(5000.0, (float) $cuota->fresh()->monto_pagado);
        $this->assertSame(0.0, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame('pagada', $cuota->fresh()->estado);
    }

    public function test_detalle_muestra_datos_del_pago(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-DET-0001', $admin, ['banco' => 'Banco Nacional']);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('caja.show', $mov))
            ->assertOk()
            ->assertSee('REF-DET-0001')
            ->assertSee('Banco Nacional')
            ->assertSee('Pendiente de verificacion');
    }

    public function test_detalle_muestra_motivo_de_rechazo(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-DET-0002', $admin);
        app(CashMovementService::class)->reject($mov, 'Fondos no acreditados.', $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('caja.show', $mov))
            ->assertOk()
            ->assertSee('Fondos no acreditados.')
            ->assertSee('Rechazado');
    }

    public function test_pago_pendiente_no_genera_recibo_pdf(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-PDF-0001', $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('pdf.recibo', $mov))
            ->assertStatus(422);
    }

    public function test_pago_rechazado_no_genera_recibo_pdf(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-PDF-0002', $admin);
        app(CashMovementService::class)->reject($mov, 'Pago invalido.', $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('pdf.recibo', $mov))
            ->assertStatus(422);
    }

    public function test_pago_confirmado_genera_recibo_pdf(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-PDF-0003', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('pdf.recibo', $mov))
            ->assertOk();
    }

    public function test_tarjeta_publica_muestra_pago_en_verificacion(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-PUB-0001', $admin);

        $this->get(route('recibos.verificar', ['numero' => str_pad((string) $mov->id, 8, '0', STR_PAD_LEFT)]))
            ->assertOk()
            ->assertSee('PAGO EN VERIFICACIÓN')
            ->assertDontSee('RECIBO VÁLIDO');
    }

    public function test_tarjeta_publica_muestra_pago_rechazado(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'transferencia', 'REF-PUB-0002', $admin);
        app(CashMovementService::class)->reject($mov, 'Sin acreditacion.', $admin);

        $this->get(route('recibos.verificar', ['numero' => str_pad((string) $mov->id, 8, '0', STR_PAD_LEFT)]))
            ->assertOk()
            ->assertSee('PAGO RECHAZADO')
            ->assertDontSee('RECIBO VÁLIDO');
    }

    public function test_tarjeta_publica_muestra_recibo_valido_solo_cuando_es_confirmado(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $mov = $this->solicitar($cuota, 'QR', 'REF-PUB-0003', $admin);
        app(CashMovementService::class)->confirm($mov, $admin);

        $this->get(route('recibos.verificar', ['numero' => str_pad((string) $mov->id, 8, '0', STR_PAD_LEFT)]))
            ->assertOk()
            ->assertSee('RECIBO VÁLIDO');
    }

    public function test_caja_muestra_boton_confirmar_solo_para_pendientes(): void
    {
        [$admin, $urb, $cuota] = $this->contexto();
        $pendiente = $this->solicitar($cuota, 'QR', 'REF-LISTA-0001', $admin);
        $confirmado = $this->solicitar($cuota, 'transferencia', 'REF-LISTA-0002', $admin);
        app(CashMovementService::class)->confirm($confirmado, $admin);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->get(route('caja.index'))
            ->assertOk()
            ->assertSee(route('caja.confirm', $pendiente))
            ->assertDontSee(route('caja.confirm', $confirmado));
    }

    private function postConfirm(User $user, CashMovement $mov, Urbanizacion $urb): TestResponse
    {
        return $this->actingAs($user)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->post(route('caja.confirm', $mov));
    }

    private function postReject(User $user, CashMovement $mov, Urbanizacion $urb, array $data = []): TestResponse
    {
        return $this->actingAs($user)
            ->withSession(['urbanizacion_id' => $urb->id])
            ->post(route('caja.reject', $mov), $data);
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
            'nombre' => 'Cliente Verificacion',
            'documento' => 'CI-99999999',
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
