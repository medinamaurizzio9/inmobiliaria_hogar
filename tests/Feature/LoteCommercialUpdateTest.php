<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CommercialSetting;
use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\LotPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoteCommercialUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Urbanizacion $urbanizacion;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizacion = Urbanizacion::firstOrFail();
        $this->cliente = Cliente::where('urbanizacion_id', $this->urbanizacion->id)->firstOrFail();

        CommercialSetting::updateOrCreate(['key' => 'tipo_cambio_usd_bs'], ['value' => '6.96']);
        CommercialSetting::updateOrCreate(['key' => 'incremento_credito_tipo'], ['value' => 'monto']);
        CommercialSetting::updateOrCreate(['key' => 'incremento_credito_valor'], ['value' => '3000']);
    }

    public function test_administrador_ve_controles_y_vendedor_no_los_ve(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('lotes.index'))
            ->assertOk()
            ->assertSee('Editar rapido')
            ->assertSee('Actualizacion Masiva')
            ->assertSee('precio_oportunidad_usd')
            ->assertSee('Seleccionar todos')
            ->assertDontSee('precio_real_usd')
            ->assertDontSee('Todos los lotes de la urbanizacion actual')
            ->assertDontSee('Solo lotes filtrados actualmente')
            ->assertDontSee('Solo manzano seleccionado');

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('lotes.index'))
            ->assertOk()
            ->assertDontSee('Editar rapido')
            ->assertDontSee('Actualizacion Masiva')
            ->assertDontSee('precio_oportunidad_usd');
    }

    public function test_admin_edita_precio_oportunidad_individual_y_recalcula_precio_real(): void
    {
        $lote = $this->loteDisponible();

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->patch(route('lotes.comercial-rapido', $lote), [
                'precio_oportunidad_usd' => 24000,
                'cuota_inicial_valor' => (float) $lote->cuota_inicial_valor,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Datos comerciales del lote actualizados correctamente.');

        $lote->refresh();

        $this->assertSame(24000.0, (float) $lote->precio);
        $this->assertSame(27000.0, app(LotPricingService::class)->creditUsd($lote));
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Lote',
            'modelo_id' => $lote->id,
            'accion' => 'edicion_rapida_precio_oportunidad',
        ]);
    }

    public function test_admin_edita_cuota_inicial_individual(): void
    {
        $lote = $this->loteDisponible();

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->patch(route('lotes.comercial-rapido', $lote), [
                'precio_oportunidad_usd' => (float) $lote->precio,
                'cuota_inicial_valor' => 5000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lotes', [
            'id' => $lote->id,
            'cuota_inicial_tipo' => 'monto',
            'cuota_inicial_valor' => 5000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Lote',
            'modelo_id' => $lote->id,
            'accion' => 'edicion_rapida_cuota_inicial',
        ]);
    }

    public function test_vendedor_no_puede_editar_actualizaciones_comerciales(): void
    {
        $lote = $this->loteDisponible();
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->patch(route('lotes.comercial-rapido', $lote), [
                'precio_oportunidad_usd' => 25000,
                'cuota_inicial_valor' => 5000,
            ])
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('lotes.comercial-masivo'), [
                'lote_ids' => [$lote->id],
                'operation' => 'reemplazar_cuota',
                'valor' => 5000,
            ])
            ->assertForbidden();
    }

    public function test_actualizacion_masiva_cambia_precio_oportunidad(): void
    {
        $lote = $this->loteDisponible();
        $precioOriginal = (float) $lote->precio;

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('lotes.comercial-masivo'), [
                'lote_ids' => [$lote->id],
                'operation' => 'incrementar_precio_oportunidad_monto',
                'valor' => 1000,
            ])
            ->assertRedirect();

        $lote->refresh();

        $this->assertSame($precioOriginal + 1000, (float) $lote->precio);
        $this->assertSame($precioOriginal + 4000, app(LotPricingService::class)->creditUsd($lote));
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Lote',
            'modelo_id' => $lote->id,
            'accion' => 'actualizacion_masiva_precio_oportunidad',
        ]);
    }

    public function test_actualizacion_masiva_cambia_cuota_inicial(): void
    {
        $lote = $this->loteDisponible();

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('lotes.comercial-masivo'), [
                'lote_ids' => [$lote->id],
                'operation' => 'reemplazar_cuota',
                'valor' => 4000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lotes', [
            'id' => $lote->id,
            'cuota_inicial_tipo' => 'monto',
            'cuota_inicial_valor' => 4000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Lote',
            'modelo_id' => $lote->id,
            'accion' => 'actualizacion_masiva_cuota_inicial',
        ]);
    }

    public function test_actualizacion_masiva_requiere_lotes_seleccionados(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->from(route('lotes.index'))
            ->post(route('lotes.comercial-masivo'), [
                'operation' => 'reemplazar_cuota',
                'valor' => 4000,
            ])
            ->assertRedirect(route('lotes.index'))
            ->assertSessionHasErrors(['lote_ids' => 'Seleccione al menos un lote.']);
    }

    public function test_mapa_muestra_precio_real_recalculado(): void
    {
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $this->urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);
        $lote = $this->loteDisponible();
        $lote->update(['coord_x' => 35, 'coord_y' => 40, 'precio' => 24000]);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertDontSee('data-precio=', false);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('precio', '$us 27,000.00');
    }

    public function test_credito_usa_precio_real_y_contado_semicontado_usan_precio_oportunidad(): void
    {
        $credito = $this->loteDisponible();
        $credito->update(['precio' => 20000, 'cuota_inicial_tipo' => 'monto', 'cuota_inicial_valor' => 3000]);

        $this->crearVenta($credito, 'credito', 12);
        $this->assertDatabaseHas('ventas', [
            'lote_id' => $credito->id,
            'tipo_operacion' => 'credito',
            'precio_final' => 23000,
            'incremento_credito_aplicado' => 3000,
        ]);

        $contado = $this->loteDisponible(exceptId: $credito->id);
        $contado->update(['precio' => 21000]);
        $this->crearVenta($contado, 'contado', 0);
        $this->assertDatabaseHas('ventas', ['lote_id' => $contado->id, 'tipo_operacion' => 'contado', 'precio_final' => 21000]);

        $semicontado = $this->loteDisponible(exceptId: $contado->id);
        $semicontado->update(['precio' => 22000]);
        $this->crearVenta($semicontado, 'semicontado', 1);
        $this->assertDatabaseHas('ventas', ['lote_id' => $semicontado->id, 'tipo_operacion' => 'semicontado', 'precio_final' => 22000]);
    }

    private function loteDisponible(?int $exceptId = null): Lote
    {
        return Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $this->urbanizacion->id))
            ->where('estado', 'disponible')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->firstOrFail();
    }

    private function crearVenta(Lote $lote, string $tipoOperacion, int $numeroCuotas): void
    {
        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('ventas.store'), [
                'cliente_id' => $this->cliente->id,
                'lote_id' => $lote->id,
                'tipo_operacion' => $tipoOperacion,
                'fecha_venta' => now()->toDateString(),
                'precio_final' => 1,
                'cuota_inicial' => 1,
                'numero_cuotas' => $numeroCuotas,
                'fecha_primer_vencimiento' => $numeroCuotas > 0 ? now()->addMonth()->toDateString() : null,
                'estado' => 'activa',
                'metodo_pago' => 'efectivo',
            ])
            ->assertRedirect(route('ventas.index'));
    }
}
