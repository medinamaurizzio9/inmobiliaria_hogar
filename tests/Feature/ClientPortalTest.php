<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Venta;
use App\Services\ClientAccountProvisioner;
use App\Services\FinancialSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_cierre_crea_cuenta_cliente_segura_y_muestra_credencial_una_vez(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = $this->newLot();
        $cliente = Cliente::create(['urbanizacion_id' => $lote->manzano->urbanizacion_id, 'nombre' => 'Portal Nuevo', 'documento' => 'PORT-1', 'email' => 'portal.nuevo@example.com']);
        $response = $this->actingAs($admin)->withSession(['urbanizacion_id' => $lote->manzano->urbanizacion_id])->post(route('ventas.store'), $this->saleData($cliente, $lote));
        $response->assertRedirect(route('ventas.index'));
        $user = User::where('cliente_id', $cliente->id)->firstOrFail();
        $credential = session('client_temporary_credential');
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertTrue($user->must_change_password);
        $this->assertNotSame($cliente->documento, $credential['password']);
        $this->assertTrue(Hash::check($credential['password'], $user->password));
        $this->get(route('ventas.index'))->assertSee('visible una sola vez');
        $this->get(route('ventas.index'))->assertDontSee('visible una sola vez');
    }

    public function test_cuenta_existente_se_reutiliza_para_varios_terrenos(): void
    {
        $cliente = Cliente::firstOrFail();
        $before = User::where('cliente_id', $cliente->id)->count();
        $service = app(ClientAccountProvisioner::class);
        $service->provision($cliente);
        $service->provision($cliente);
        $this->assertSame($before ?: 1, User::where('cliente_id', $cliente->id)->count());
    }

    public function test_sin_correo_no_inventa_usuario_y_venta_se_conserva(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = $this->newLot();
        $cliente = Cliente::create(['urbanizacion_id' => $lote->manzano->urbanizacion_id, 'nombre' => 'Sin Correo', 'documento' => 'PORT-2', 'email' => null]);
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $lote->manzano->urbanizacion_id])->post(route('ventas.store'), $this->saleData($cliente, $lote))->assertRedirect();
        $this->assertDatabaseHas('ventas', ['cliente_id' => $cliente->id, 'lote_id' => $lote->id]);
        $this->assertDatabaseMissing('users', ['cliente_id' => $cliente->id]);
        $this->assertNotEmpty(session('warning'));
    }

    public function test_primer_ingreso_obliga_cambio_de_password(): void
    {
        $user = User::where('email', 'cliente@impacto.test')->firstOrFail();
        $user->update(['must_change_password' => true]);
        $this->actingAs($user)->get(route('clientes.mi-cuenta'))->assertRedirect(route('password.change'));
    }

    public function test_destino_inicial_del_cliente_siempre_es_mi_cuenta(): void
    {
        $user = $this->clientUser();
        $user->update(['password' => Hash::make('Password-segura-123'), 'estado' => 'activo']);
        $this->assertGreaterThan(0, Venta::where('cliente_id', $user->cliente_id)->count());

        $this->post(route('logout'));
        $this->flushSession();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Password-segura-123'])
            ->assertRedirect(route('clientes.mi-cuenta'));
    }

    public function test_dashboard_muestra_solo_terrenos_y_saldos_del_cliente(): void
    {
        $user = $this->clientUser();
        $own = Venta::where('cliente_id', $user->cliente_id)->firstOrFail();
        $foreign = Venta::where('cliente_id', '!=', $user->cliente_id)->firstOrFail();
        $this->actingAs($user)->get(route('clientes.mi-cuenta'))->assertOk()->assertSee($own->lote->codigo)->assertDontSee($foreign->lote->codigo)->assertSee('Saldo total informativo')->assertSee(route('portal.terrenos.show', $own), false);
    }

    public function test_perfil_es_pagina_separada_y_estado_activo_correcto(): void
    {
        $user = $this->clientUser();

        $this->actingAs($user)->get(route('clientes.mi-cuenta'))
            ->assertOk()
            ->assertDontSee('class="active" href="'.route('portal.perfil'), false);

        $this->get(route('portal.perfil'))
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee($user->cliente->nombre)
            ->assertSee($user->cliente->documento)
            ->assertSee('class="active" href="'.route('portal.perfil'), false)
            ->assertDontSee('Saldo total informativo');
    }

    public function test_detalle_es_explicito_y_regresa_a_mi_cuenta(): void
    {
        $user = $this->clientUser();
        $venta = Venta::where('cliente_id', $user->cliente_id)->firstOrFail();

        $this->actingAs($user)->get(route('portal.terrenos.show', $venta))
            ->assertOk()
            ->assertSee('Detalle de mi terreno')
            ->assertSee('Volver a mi cuenta')
            ->assertSee(route('clientes.mi-cuenta').'#mis-terrenos', false);
    }

    public function test_dashboard_muestra_metricas_reales_y_navegacion_simplificada(): void
    {
        $user = $this->clientUser();
        $venta = Venta::with('cuotas')->whereHas('cuotas')->firstOrFail();
        $venta->update(['cliente_id' => $user->cliente_id, 'estado' => 'activa']);
        $cuota = $venta->cuotas->firstOrFail();
        $cuota->update(['estado' => 'vencida', 'fecha_vencimiento' => now()->subDay(), 'saldo_pendiente' => 321]);
        $this->movement($user, $venta, $cuota, 'pendiente_verificacion');

        $this->actingAs($user)->get(route('clientes.mi-cuenta'))
            ->assertOk()
            ->assertSee('Saldo total informativo')
            ->assertSee('Pagos por verificar')
            ->assertSee('Cuotas vencidas')
            ->assertSee('Mi perfil')
            ->assertSee('Mis terrenos')
            ->assertSee('Reserva de visitas')
            ->assertSee('Ver urbanizaciones')
            ->assertDontSee('Usuarios del sistema')
            ->assertDontSee('Configuracion financiera');
    }

    public function test_reserva_visita_usa_nombre_y_urbanizacion_sin_documento(): void
    {
        $user = $this->clientUser();
        $urbanizacion = $this->newLot()->manzano->urbanizacion;
        SystemSetting::updateOrCreate(['key' => 'whatsapp'], ['value' => '70000000']);
        Cache::flush();

        $response = $this->actingAs($user)->get(route('portal.visitas'));
        $response->assertOk()
            ->assertSee($urbanizacion->nombre)
            ->assertSee('https://wa.me/59170000000', false)
            ->assertSee(rawurlencode("Hola, soy {$user->cliente->nombre}. Quisiera reservar una visita para conocer la urbanización {$urbanizacion->nombre}."), false)
            ->assertDontSee($user->cliente->documento);
    }

    public function test_catalogo_muestra_agregados_y_disponibilidad_publica(): void
    {
        $user = $this->clientUser();
        $urbanizacion = $this->newLot()->manzano->urbanizacion;

        $this->actingAs($user)->get(route('portal.urbanizaciones'))
            ->assertOk()
            ->assertSee($urbanizacion->nombre)
            ->assertSee('Lotes')
            ->assertSee('Disponibles')
            ->assertSee(route('disponibilidad.urbanizacion', $urbanizacion->slug), false);
    }

    public function test_cliente_no_accede_venta_pago_documentos_o_pdf_ajenos(): void
    {
        $user = $this->clientUser();
        $foreign = Venta::where('cliente_id', '!=', $user->cliente_id)->firstOrFail();
        $this->actingAs($user)->get(route('portal.terrenos.show', $foreign))->assertForbidden();
        $this->get(route('portal.pagar', $foreign))->assertForbidden();
        $this->get(route('portal.documentos', $foreign))->assertForbidden();
        $this->get(route('portal.estado-cuenta.pdf', $foreign))->assertForbidden();
        $this->get(route('portal.contrato', $foreign))->assertForbidden();
    }

    public function test_alertas_son_por_cuota_y_excluyen_pagadas_y_anuladas(): void
    {
        $user = $this->clientUser();
        $venta = Venta::whereHas('cuotas')->firstOrFail();
        $venta->update(['cliente_id' => $user->cliente_id, 'estado' => 'activa']);
        $cuotas = $venta->cuotas()->take(3)->get();
        $cuotas[0]->update(['estado' => 'vencida', 'fecha_vencimiento' => now()->subDay(), 'saldo_pendiente' => 50]);
        $cuotas[1]->update(['estado' => 'pendiente', 'fecha_vencimiento' => now()->addDays(2), 'saldo_pendiente' => 60]);
        $cuotas[2]->update(['estado' => 'pagada', 'fecha_vencimiento' => now(), 'saldo_pendiente' => 0]);
        $this->actingAs($user)->get(route('clientes.mi-cuenta'))->assertSee('TIENES CUOTAS VENCIDAS')->assertSee('Próximo pago')->assertSee('50.00')->assertSee('60.00');
    }

    public function test_cliente_registra_qr_pendiente_sin_afectar_saldo_ni_aplicaciones(): void
    {
        [$user,$venta,$cuota] = $this->payableSale();
        $this->enablePayments();
        $saldo = $cuota->saldo_pendiente;
        $applications = $cuota->pagoAplicaciones()->count();
        $this->actingAs($user)->post(route('portal.pagar.store', $venta), $this->paymentData($cuota, 'QR'))->assertRedirect(route('portal.terrenos.show', $venta));
        $this->assertDatabaseHas('cash_movements', ['cliente_id' => $user->cliente_id, 'sale_id' => $venta->id, 'installment_id' => $cuota->id, 'metodo_pago' => 'QR', 'estado' => 'pendiente_verificacion']);
        $this->assertSame((float) $saldo, (float) $cuota->fresh()->saldo_pendiente);
        $this->assertSame($applications, $cuota->pagoAplicaciones()->count());
    }

    public function test_transferencia_requiere_referencia_y_no_admite_venta_ajena(): void
    {
        [$user,$venta,$cuota] = $this->payableSale();
        $this->enablePayments();
        $data = $this->paymentData($cuota, 'transferencia');
        unset($data['referencia']);
        $this->actingAs($user)->post(route('portal.pagar.store', $venta), $data)->assertSessionHasErrors('referencia');
        $foreign = Venta::where('cliente_id', '!=', $user->cliente_id)->firstOrFail();
        $this->post(route('portal.pagar.store', $foreign), $this->paymentData($cuota, 'transferencia'))->assertForbidden();
    }

    public function test_portal_muestra_estados_y_solo_recibos_confirmados(): void
    {
        [$user,$venta,$cuota] = $this->payableSale();
        $pending = $this->movement($user, $venta, $cuota, 'pendiente_verificacion');
        $rejected = $this->movement($user, $venta, $cuota, 'rechazado', ['motivo_rechazo' => 'Referencia incorrecta']);
        $confirmed = $this->movement($user, $venta, $cuota, 'confirmado');
        $this->actingAs($user)->get(route('portal.terrenos.show', $venta))->assertSee('PENDIENTE DE VERIFICACION')->assertSee('RECHAZADO')->assertSee('Referencia incorrecta')->assertSee(route('portal.recibo', $confirmed), false);
        $this->get(route('portal.recibo', $pending))->assertStatus(422);
        $this->get(route('portal.recibo', $rejected))->assertStatus(422);
    }

    public function test_documentos_propios_y_estado_cuenta_pdf_disponibles(): void
    {
        [$user,$venta] = $this->payableSale();
        $this->actingAs($user)->get(route('portal.documentos', $venta))->assertOk()->assertSee('Contrato')->assertSee('Plan de pagos')->assertSee('Estado de cuenta');
        $this->get(route('portal.estado-cuenta.pdf', $venta))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('portal.contrato', $venta))->assertOk();
    }

    public function test_medios_inactivos_no_aparecen_y_datos_publicos_son_limitados(): void
    {
        [$user,$venta] = $this->payableSale();
        SystemSetting::whereIn('key', ['qr_institucional_activo', 'banco_activo'])->update(['value' => '0']);
        Cache::flush();
        $this->actingAs($user)->get(route('portal.pagar', $venta))->assertOk()->assertSee('No hay medios de pago remotos activos')->assertDontSee('QR institucional')->assertDontSee('Transferencia');
        $this->assertSame(['qr', 'cuenta_bancaria'], array_keys(app(FinancialSettingsService::class)->paymentInstructions()));
    }

    private function clientUser(): User
    {
        $user = User::where('email', 'cliente@impacto.test')->firstOrFail();
        $user->update(['must_change_password' => false]);

        return $user;
    }

    private function payableSale(): array
    {
        $user = $this->clientUser();
        $venta = Venta::with('cuotas')->whereHas('cuotas', fn ($q) => $q->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('saldo_pendiente', '>', 0))->firstOrFail();
        $venta->update(['cliente_id' => $user->cliente_id, 'estado' => 'activa']);

        return [$user, $venta, $venta->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->first()];
    }

    private function enablePayments(): void
    {
        Storage::disk('public')->put('qr/test.png', 'qr');
        foreach (['qr_institucional_activo' => '1', 'qr_institucional_imagen' => 'qr/test.png', 'qr_institucional_nombre' => 'QR Hogar', 'banco_activo' => '1', 'banco_nombre' => 'Banco', 'banco_titular' => 'Hogar', 'banco_numero_cuenta' => '123', 'banco_tipo_cuenta' => 'Corriente', 'banco_moneda' => 'BOB', 'banco_instrucciones' => 'Transferir'] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        } Cache::flush();
    }

    private function paymentData(Cuota $cuota, string $method): array
    {
        return ['cuota_id' => $cuota->id, 'monto' => 123.45, 'fecha' => now()->toDateString(), 'metodo_pago' => $method, 'banco' => 'Banco Test', 'referencia' => uniqid('PORT-')];
    }

    private function movement(User $user, Venta $venta, Cuota $cuota, string $state, array $extra = []): CashMovement
    {
        return CashMovement::create([...['user_id' => $user->id, 'cliente_id' => $user->cliente_id, 'sale_id' => $venta->id, 'installment_id' => $cuota->id, 'tipo' => 'ingreso', 'concepto' => 'cuota', 'metodo_pago' => 'QR', 'monto' => 10, 'fecha' => now(), 'referencia' => uniqid(), 'estado' => $state], ...$extra]);
    }

    private function newLot(): Lote
    {
        $base = Lote::with('manzano')->firstOrFail();

        return Lote::create(['manzano_id' => $base->manzano_id, 'codigo' => uniqid('PORT-'), 'superficie' => 300, 'precio' => 10000, 'cuota_inicial_tipo' => 'monto', 'cuota_inicial_valor' => 0, 'estado' => 'disponible', 'fila' => 1, 'columna' => 1]);
    }

    private function saleData(Cliente $cliente, Lote $lote): array
    {
        return ['cliente_id' => $cliente->id, 'lote_id' => $lote->id, 'tipo_operacion' => 'credito', 'fecha_venta' => now()->toDateString(), 'precio_final' => 10000, 'descuento' => 0, 'cuota_inicial' => 1000, 'numero_cuotas' => 3, 'fecha_primer_vencimiento' => now()->addMonth()->toDateString(), 'estado' => 'activa', 'metodo_pago' => 'efectivo'];
    }
}
