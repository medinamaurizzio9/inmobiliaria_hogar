<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\FinancialSettingsService;
use App\Services\PaymentAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinancialSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Urbanizacion $urbanizacion;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed();
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizacion = Urbanizacion::orderBy('id')->firstOrFail();
    }

    public function test_admin_puede_guardar_configuracion_financiera(): void
    {
        $this->updateAs($this->admin, $this->payload(['dias_aviso_vencimiento' => 7]))->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'dias_aviso_vencimiento', 'value' => '7']);
    }

    public function test_gerente_puede_ver_configuracion_financiera(): void
    {
        $this->getAs($this->roleUser('gerente'))->assertOk()->assertSee('Vista de solo lectura para gerencia.');
    }

    public function test_gerente_no_puede_modificar_configuracion_financiera(): void
    {
        $this->updateAs($this->roleUser('gerente'), $this->payload())->assertForbidden();
    }

    public function test_cajero_no_puede_modificar_configuracion_financiera(): void
    {
        $this->updateAs($this->roleUser('cajero'), $this->payload())->assertForbidden();
    }

    public function test_vendedor_no_puede_modificar_configuracion_financiera(): void
    {
        $this->updateAs($this->roleUser('vendedor'), $this->payload())->assertForbidden();
    }

    public function test_supervisor_no_puede_modificar_configuracion_financiera(): void
    {
        $this->updateAs($this->roleUser('supervisor'), $this->payload())->assertForbidden();
    }

    public function test_qr_valido_se_guarda_en_storage_publico(): void
    {
        Storage::fake('public');
        $this->updateAs($this->admin, $this->payload([
            'qr_institucional_imagen' => UploadedFile::fake()->image('qr.png', 500, 500),
            'qr_institucional_activo' => 1,
        ]))->assertRedirect();

        $path = SystemSetting::where('key', 'qr_institucional_imagen')->value('value');
        Storage::disk('public')->assertExists($path);
    }

    public function test_archivo_qr_invalido_se_rechaza(): void
    {
        Storage::fake('public');
        $this->updateAs($this->admin, $this->payload([
            'qr_institucional_imagen' => UploadedFile::fake()->create('qr.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('qr_institucional_imagen');
    }

    public function test_qr_desactivado_no_aparece_en_instrucciones_de_pago(): void
    {
        $this->setSettings(['qr_institucional_imagen' => 'qr/prueba.png', 'qr_institucional_activo' => '0']);

        $this->assertNull(app(FinancialSettingsService::class)->paymentInstructions()['qr']);
    }

    public function test_datos_bancarios_se_guardan(): void
    {
        $this->updateAs($this->admin, $this->payload([
            'banco_nombre' => 'Banco Hogar',
            'banco_titular' => 'Hogar Inmobiliaria SRL',
            'banco_numero_cuenta' => '123-456',
            'banco_activo' => 1,
        ]))->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'banco_numero_cuenta', 'value' => '123-456']);
    }

    public function test_cuenta_desactivada_no_aparece_en_instrucciones(): void
    {
        $this->setSettings(['banco_nombre' => 'Banco Hogar', 'banco_activo' => '0']);

        $this->assertNull(app(FinancialSettingsService::class)->paymentInstructions()['cuenta_bancaria']);
    }

    public function test_dias_aviso_predeterminado_es_tres(): void
    {
        $this->assertSame(3, app(FinancialSettingsService::class)->diasAvisoVencimiento());
    }

    public function test_cuota_a_tres_dias_aparece_proxima(): void
    {
        [$cliente] = $this->clienteConCuota(now()->addDays(3), 'pendiente', 5000, 5000);

        $alerta = app(PaymentAlertService::class)->forCliente($cliente)->first();
        $this->assertSame('proxima_vencer', $alerta['indicador']);
        $this->assertSame(3, $alerta['dias']);
    }

    public function test_cuota_fuera_del_rango_no_aparece(): void
    {
        [$cliente] = $this->clienteConCuota(now()->addDays(4), 'pendiente', 5000, 5000);

        $this->assertTrue(app(PaymentAlertService::class)->forCliente($cliente)->isEmpty());
    }

    public function test_cuota_vencida_se_identifica_por_fecha(): void
    {
        [$cliente] = $this->clienteConCuota(now()->subDay(), 'pendiente', 5000, 5000);

        $this->assertSame('vencida', app(PaymentAlertService::class)->forCliente($cliente)->first()['indicador']);
    }

    public function test_cuota_pagada_no_genera_alerta(): void
    {
        [$cliente] = $this->clienteConCuota(now()->addDay(), 'pagada', 5000, 0);

        $this->assertTrue(app(PaymentAlertService::class)->forCliente($cliente)->isEmpty());
    }

    public function test_cuota_parcial_proxima_conserva_saldo_real(): void
    {
        [$cliente] = $this->clienteConCuota(now()->addDays(2), 'parcial', 5000, 1800);

        $alerta = app(PaymentAlertService::class)->forCliente($cliente)->first();
        $this->assertSame('proxima_vencer', $alerta['indicador']);
        $this->assertSame(1800.0, $alerta['saldo']);
    }

    public function test_cuota_parcial_vencida_conserva_saldo_vencido(): void
    {
        [$cliente] = $this->clienteConCuota(now()->subDays(2), 'parcial', 5000, 1250);

        $alerta = app(PaymentAlertService::class)->forCliente($cliente)->first();
        $this->assertSame('vencida', $alerta['indicador']);
        $this->assertSame(1250.0, $alerta['saldo']);
    }

    public function test_mora_esta_deshabilitada_por_defecto(): void
    {
        $this->assertFalse(app(FinancialSettingsService::class)->all($this->urbanizacion->id)['mora_habilitada']);
    }

    public function test_mora_deshabilitada_no_altera_cuota(): void
    {
        [$cliente, $cuota] = $this->clienteConCuota(now()->subDay(), 'vencida', 5000, 5000);
        app(FinancialSettingsService::class)->all($this->urbanizacion->id);

        $this->assertSame(5000.0, (float) $cuota->fresh()->monto);
        $this->assertSame(5000.0, (float) $cuota->fresh()->saldo_pendiente);
    }

    public function test_configuracion_de_mora_se_guarda_sin_aplicar_recargo(): void
    {
        [$cliente, $cuota] = $this->clienteConCuota(now()->subDay(), 'vencida', 5000, 5000);
        $this->updateAs($this->admin, $this->payload([
            'mora_habilitada' => 1,
            'tipo_mora' => 'porcentaje',
            'valor_mora' => 2.5,
            'dias_gracia' => 5,
        ]))->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'mora_habilitada', 'value' => '1']);
        $this->assertSame(5000.0, (float) $cuota->fresh()->saldo_pendiente);
    }

    public function test_no_se_inventa_recargo_automatico_aunque_mora_este_habilitada(): void
    {
        [$cliente, $cuota] = $this->clienteConCuota(now()->subDays(10), 'vencida', 5000, 3200);
        $this->setSettings(['mora_habilitada' => '1', 'tipo_mora' => 'porcentaje', 'valor_mora' => '10']);

        app(PaymentAlertService::class)->forCliente($cliente);

        $this->assertSame(5000.0, (float) $cuota->fresh()->monto);
        $this->assertSame(3200.0, (float) $cuota->fresh()->saldo_pendiente);
    }

    public function test_cambio_financiero_genera_auditoria(): void
    {
        $this->updateAs($this->admin, $this->payload(['dias_aviso_vencimiento' => 9]))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['accion' => 'actualizar_configuracion_financiera', 'user_id' => $this->admin->id]);
    }

    public function test_limite_semicontado_se_actualiza_en_configuracion_existente(): void
    {
        $this->updateAs($this->admin, $this->payload(['max_cuotas_semicontado' => 18]))->assertRedirect();

        $this->assertSame(18, app(FinancialSettingsService::class)->all($this->urbanizacion->id)['max_cuotas_semicontado']);
    }

    public function test_limite_credito_se_actualiza_en_configuracion_existente(): void
    {
        $this->updateAs($this->admin, $this->payload(['max_cuotas_credito' => 48]))->assertRedirect();

        $this->assertSame(48, app(FinancialSettingsService::class)->all($this->urbanizacion->id)['max_cuotas_credito']);
    }

    public function test_payment_instructions_solo_expone_informacion_publica(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('qr/publico.png', 'qr');
        $this->setSettings([
            'qr_institucional_imagen' => 'qr/publico.png',
            'qr_institucional_activo' => '1',
            'banco_nombre' => 'Banco Público',
            'banco_activo' => '1',
            'mora_habilitada' => '1',
            'valor_mora' => '99',
            'dias_aviso_vencimiento' => '15',
        ]);

        $instructions = app(FinancialSettingsService::class)->paymentInstructions();
        $this->assertSame(['qr', 'cuenta_bancaria'], array_keys($instructions));
        $this->assertSame(Storage::disk('public')->url('qr/publico.png'), $instructions['qr']['url']);
        $this->assertArrayNotHasKey('mora_habilitada', $instructions);
        $this->assertArrayNotHasKey('dias_aviso_vencimiento', $instructions);
    }

    private function getAs(User $user)
    {
        return $this->actingAs($user)->withSession(['urbanizacion_id' => $this->urbanizacion->id])->get(route('admin.configuracion-financiera'));
    }

    private function updateAs(User $user, array $data)
    {
        return $this->actingAs($user)->withSession(['urbanizacion_id' => $this->urbanizacion->id])->put(route('admin.configuracion-financiera.update'), $data);
    }

    private function roleUser(string $role): User
    {
        $user = User::role($role)->first() ?? User::factory()->create();
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
        $user->urbanizacionesAsignadas()->syncWithoutDetaching([$this->urbanizacion->id => ['activo' => true]]);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'qr_institucional_nombre' => 'QR Hogar',
            'qr_institucional_activo' => 0,
            'banco_nombre' => '',
            'banco_titular' => '',
            'banco_numero_cuenta' => '',
            'banco_tipo_cuenta' => '',
            'banco_moneda' => 'BOB',
            'banco_instrucciones' => '',
            'banco_activo' => 0,
            'dias_aviso_vencimiento' => 3,
            'mora_habilitada' => 0,
            'tipo_mora' => null,
            'valor_mora' => 0,
            'dias_gracia' => 0,
            'max_cuotas_semicontado' => 36,
            'max_cuotas_credito' => 36,
            ...$overrides,
        ];
    }

    private function setSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('financial_settings.global');
    }

    private function clienteConCuota($fecha, string $estado, float $monto, float $saldo): array
    {
        $cliente = Cliente::create(['urbanizacion_id' => $this->urbanizacion->id, 'nombre' => uniqid('Cliente '), 'documento' => uniqid('CI-')]);
        $manzano = Manzano::create(['urbanizacion_id' => $this->urbanizacion->id, 'codigo' => uniqid('M-'), 'orden' => 1]);
        $lote = Lote::create(['manzano_id' => $manzano->id, 'codigo' => uniqid('L-'), 'superficie' => 300, 'precio' => $monto, 'estado' => 'vendido', 'fila' => 1, 'columna' => 1]);
        $venta = Venta::create(['lote_id' => $lote->id, 'cliente_id' => $cliente->id, 'fecha_venta' => now(), 'precio_final' => $monto, 'cuota_inicial' => 0, 'saldo_financiar' => $saldo, 'numero_cuotas' => 1, 'estado' => 'activa']);
        $cuota = Cuota::create([
            'venta_id' => $venta->id,
            'numero' => 1,
            'fecha_programada' => $fecha,
            'fecha_vencimiento' => $fecha,
            'monto' => $monto,
            'monto_pagado' => $monto - $saldo,
            'saldo_pendiente' => $saldo,
            'estado' => $estado,
        ]);

        return [$cliente, $cuota];
    }
}
