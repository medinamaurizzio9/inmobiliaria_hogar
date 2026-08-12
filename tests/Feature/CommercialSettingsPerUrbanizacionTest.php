<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\CommercialSettingsService;
use App\Services\LotPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommercialSettingsPerUrbanizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Urbanizacion $zona1;
    private Urbanizacion $zona2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizaciones = Urbanizacion::orderBy('id')->take(2)->get();
        $this->zona1 = $urbanizaciones[0];
        $this->zona2 = $urbanizaciones[1];
    }

    public function test_cada_urbanizacion_tiene_configuracion_comercial_independiente(): void
    {
        $settings = app(CommercialSettingsService::class);

        $settings->updateForUrbanizacion($this->zona1->id, $this->commercialData(['incremento_credito_valor' => 1000]));
        $settings->updateForUrbanizacion($this->zona2->id, $this->commercialData(['incremento_credito_valor' => 5000]));

        $this->assertSame(1000.0, $settings->incrementoCreditoValor($this->zona1->id));
        $this->assertSame(5000.0, $settings->incrementoCreditoValor($this->zona2->id));
    }

    public function test_editar_zona_uno_no_afecta_zona_dos(): void
    {
        app(CommercialSettingsService::class)->updateForUrbanizacion($this->zona2->id, $this->commercialData(['incremento_credito_valor' => 7000]));

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->put(route('admin.configuracion.update'), $this->commercialData(['incremento_credito_valor' => 1200]))
            ->assertRedirect();

        $this->assertDatabaseHas('urbanizacion_commercial_settings', [
            'urbanizacion_id' => $this->zona1->id,
            'incremento_credito_valor' => 1200,
        ]);
        $this->assertDatabaseHas('urbanizacion_commercial_settings', [
            'urbanizacion_id' => $this->zona2->id,
            'incremento_credito_valor' => 7000,
        ]);
    }

    public function test_precio_real_de_lote_usa_incremento_de_su_urbanizacion(): void
    {
        $pricing = app(LotPricingService::class);
        app(CommercialSettingsService::class)->updateForUrbanizacion($this->zona1->id, $this->commercialData(['incremento_credito_valor' => 1000]));
        app(CommercialSettingsService::class)->updateForUrbanizacion($this->zona2->id, $this->commercialData(['incremento_credito_valor' => 5000]));

        $loteZona1 = $this->lote($this->zona1, 'PRECIO-Z1', 20000);
        $loteZona2 = $this->lote($this->zona2, 'PRECIO-Z2', 20000);

        $this->assertSame(21000.0, $pricing->creditUsd($loteZona1));
        $this->assertSame(25000.0, $pricing->creditUsd($loteZona2));
    }

    public function test_mapa_usa_configuracion_de_urbanizacion_seleccionada(): void
    {
        $this->zona2->update(['plano_imagen' => 'planos/zona2.jpg']);
        app(CommercialSettingsService::class)->updateForUrbanizacion($this->zona2->id, $this->commercialData(['incremento_credito_valor' => 3000, 'tipo_cambio_usd_bs' => 7]));
        $lote = $this->lote($this->zona2, 'MAPA-Z2', 20000, ['coord_x' => 30, 'coord_y' => 40]);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona2->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee($lote->codigo)
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertDontSee('data-precio=', false);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona2->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('precio', '$us 23,000.00')
            ->assertJsonPath('precio_bs', 'Bs 161,000.00');
    }

    public function test_dias_reserva_por_lote(): void
    {
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        app(CommercialSettingsService::class)->updateForUrbanizacion($urbanizacion->id, $this->commercialData(['reserva_dias_habiles_asesor' => 2]));
        $lote = $this->lote($urbanizacion, 'RESERVA-ASESOR', 20000);
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('reservas.create', ['lote_id' => $lote->id]))
            ->assertOk()
            ->assertSee('value="2026-06-17"', false);

        Carbon::setTestNow();
    }

    public function test_ventas_guardan_snapshot_comercial_correcto(): void
    {
        app(CommercialSettingsService::class)->updateForUrbanizacion($this->zona2->id, $this->commercialData([
            'tipo_cambio_usd_bs' => 7,
            'incremento_credito_tipo' => 'porcentaje',
            'incremento_credito_valor' => 20,
        ]));
        $lote = $this->lote($this->zona2, 'VENTA-Z2', 20000, ['cuota_inicial_tipo' => 'porcentaje', 'cuota_inicial_valor' => 10]);
        $cliente = Cliente::create(['urbanizacion_id' => $this->zona2->id, 'nombre' => 'Cliente Venta Z2', 'documento' => 'VEN-Z2']);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona2->id])
            ->post(route('ventas.store'), [
                'cliente_id' => $cliente->id,
                'lote_id' => $lote->id,
                'tipo_operacion' => 'credito',
                'fecha_venta' => now()->toDateString(),
                'precio_final' => 1,
                'cuota_inicial' => 1,
                'numero_cuotas' => 12,
                'fecha_primer_vencimiento' => now()->addMonth()->toDateString(),
                'estado' => 'activa',
                'metodo_pago' => 'efectivo',
            ])
            ->assertRedirect(route('ventas.index'));

        $this->assertDatabaseHas('ventas', [
            'lote_id' => $lote->id,
            'precio_base_usd' => 20000,
            'incremento_credito_tipo' => 'porcentaje',
            'incremento_credito_valor' => 20,
            'incremento_credito_aplicado' => 4000,
            'precio_final_usd' => 24000,
            'precio_final_bs' => 168000,
            'tipo_cambio_usd_bs' => 7,
        ]);
    }

    public function test_usuario_no_administrador_no_puede_editar_configuracion_comercial(): void
    {
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->put(route('admin.configuracion.update'), $this->commercialData())
            ->assertForbidden();
    }

    public function test_admin_puede_guardar_configuracion_de_calculadora_comercial(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->put(route('admin.configuracion.update'), $this->commercialData([
                'inicial_minima_usd' => 10000,
                'plazo_12_habilitado' => true,
                'plazo_24_habilitado' => false,
                'plazo_36_habilitado' => true,
                'descuento_contado_activo' => true,
                'descuento_contado_tipo' => 'porcentaje',
                'descuento_contado_valor' => 8,
                'descuento_promo_activo' => true,
                'descuento_promo_tipo' => 'monto',
                'descuento_promo_valor' => 1500,
                'descuento_promo_nombre' => 'Promo feria',
                'descuento_promo_descripcion' => 'Descuento temporal',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('urbanizacion_commercial_settings', [
            'urbanizacion_id' => $this->zona1->id,
            'inicial_minima_usd' => 10000,
            'plazo_12_habilitado' => true,
            'plazo_24_habilitado' => false,
            'plazo_36_habilitado' => true,
            'descuento_contado_activo' => true,
            'descuento_contado_tipo' => 'porcentaje',
            'descuento_contado_valor' => 8,
            'descuento_promo_activo' => true,
            'descuento_promo_tipo' => 'monto',
            'descuento_promo_valor' => 1500,
            'descuento_promo_nombre' => 'Promo feria',
        ]);
    }

    public function test_gerente_puede_guardar_configuracion_comercial_y_asesor_no(): void
    {
        $gerente = User::where('email', 'gerente@impacto.test')->firstOrFail();
        $asesor = User::where('email', 'vendedor@impacto.test')->firstOrFail();

        $this->actingAs($gerente)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->put(route('admin.configuracion.update'), $this->commercialData(['inicial_minima_usd' => 7500]))
            ->assertRedirect();

        $this->assertDatabaseHas('urbanizacion_commercial_settings', [
            'urbanizacion_id' => $this->zona1->id,
            'inicial_minima_usd' => 7500,
        ]);

        $this->actingAs($asesor)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->put(route('admin.configuracion.update'), $this->commercialData(['inicial_minima_usd' => 3000]))
            ->assertForbidden();
    }

    public function test_mapa_expone_configuracion_comercial_para_calculadora(): void
    {
        $this->zona1->update(['plano_imagen' => 'planos/zona1.jpg']);
        app(CommercialSettingsService::class)->updateForUrbanizacion($this->zona1->id, $this->commercialData([
            'tipo_cambio_usd_bs' => 7,
            'inicial_minima_usd' => 10000,
            'plazo_12_habilitado' => true,
            'plazo_24_habilitado' => false,
            'plazo_36_habilitado' => true,
            'descuento_contado_activo' => true,
            'descuento_contado_tipo' => 'porcentaje',
            'descuento_contado_valor' => 5,
        ]));

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('window.commercialConfig', false)
            ->assertSee('"tipoCambio":7', false)
            ->assertSee('"inicialMinimaUsd":10000', false)
            ->assertSee('"plazos":[12,36]', false)
            ->assertDontSee('"plazos":[12,24,36]', false);
    }

    public function test_calculadora_aparece_en_modal_y_js_usa_tipo_de_cambio(): void
    {
        $this->zona1->update(['plano_imagen' => 'planos/zona1.jpg']);
        $lote = $this->lote($this->zona1, 'CALC-01', 20000, ['coord_x' => 30, 'coord_y' => 40]);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('data-modal-calculator', false)
            ->assertSee('commercialCalculatorModal', false)
            ->assertSee('La inicial minima para esta urbanizacion es', false);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->zona1->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('permisos.calculadora', true)
            ->assertJsonPath('precio_real_usd', 20000)
            ->assertJsonPath('tipo_cambio_usd_bs', 6.96);

        $javascript = file_get_contents(public_path('js/map-zoom.js'));
        $this->assertStringContainsString('window.ImpactoCommercialCalculator', $javascript);
        $this->assertStringContainsString('precioBs: this.money(price * exchange)', $javascript);
        $this->assertStringContainsString('enabledTerms.includes(months)', $javascript);
    }

    private function commercialData(array $overrides = []): array
    {
        return [
            'reserva_dias_habiles_asesor' => 5,
            'tipo_cambio_usd_bs' => 6.96,
            'incremento_credito_tipo' => 'monto',
            'incremento_credito_valor' => 0,
            'max_cuotas_semicontado' => 36,
            'max_cuotas_credito' => 36,
            'inicial_minima_usd' => 0,
            'plazo_12_habilitado' => true,
            'plazo_24_habilitado' => true,
            'plazo_36_habilitado' => true,
            'descuento_contado_activo' => false,
            'descuento_contado_tipo' => 'monto',
            'descuento_contado_valor' => 0,
            'descuento_promo_activo' => false,
            'descuento_promo_tipo' => 'monto',
            'descuento_promo_valor' => 0,
            'descuento_promo_nombre' => null,
            'descuento_promo_descripcion' => null,
            ...$overrides,
        ];
    }

    private function lote(Urbanizacion $urbanizacion, string $codigo, float $precio, array $overrides = []): Lote
    {
        return Lote::create([
            'manzano_id' => $urbanizacion->manzanos()->firstOrFail()->id,
            'codigo' => $codigo,
            'superficie' => 300,
            'precio' => $precio,
            'cuota_inicial_tipo' => 'monto',
            'cuota_inicial_valor' => 1000,
            'estado' => 'disponible',
            'fila' => 1,
            'columna' => 1,
            'coord_x' => null,
            'coord_y' => null,
            ...$overrides,
        ]);
    }
}
