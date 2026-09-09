<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CommercialSetting;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommercialPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Urbanizacion $urbanizacion;

    private Manzano $manzano;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $this->urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);
        $this->manzano = $this->urbanizacion->manzanos()->firstOrFail();
        $this->cliente = Cliente::where('urbanizacion_id', $this->urbanizacion->id)->firstOrFail();

        CommercialSetting::updateOrCreate(['key' => 'tipo_cambio_usd_bs'], ['value' => '6.96']);
        CommercialSetting::updateOrCreate(['key' => 'incremento_credito_tipo'], ['value' => 'porcentaje']);
        CommercialSetting::updateOrCreate(['key' => 'incremento_credito_valor'], ['value' => '10']);
    }

    public function test_mapa_siempre_muestra_precio_credito_y_cuota_inicial_en_bs(): void
    {
        $lote = $this->createLote('MAPA-PRECIO', 20000, 'monto', 5000, ['coord_x' => 40, 'coord_y' => 50]);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('mapa'))
            ->assertOk()
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertDontSee('data-precio=', false)
            ->assertDontSee('data-cuota-inicial=', false)
            ->assertSee($lote->codigo);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->getJson(route('mapa.lotes.show-json', $lote))
            ->assertOk()
            ->assertJsonPath('precio', '$us 22,000.00')
            ->assertJsonPath('precio_bs', 'Bs 153,120.00')
            ->assertJsonPath('cuota_inicial', '$us 5,000.00')
            ->assertJsonPath('cuota_inicial_bs', 'Bs 34,800.00');
    }

    public function test_reserva_contado_y_semicontado_muestran_precio_base(): void
    {
        $lote = $this->createLote('RES-BASE', 20000, 'porcentaje', 20);

        $response = $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('reservas.create', ['lote_id' => $lote->id]));

        $response->assertOk()
            ->assertSee('data-base-usd="20000"', false)
            ->assertSee('data-base-bs="139200"', false)
            ->assertSee('data-initial-base-usd="4000"', false)
            ->assertSee('Precio operacion');
    }

    public function test_reserva_credito_muestra_precio_base_mas_incremento(): void
    {
        $lote = $this->createLote('RES-CREDITO', 20000, 'porcentaje', 20);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('reservas.create', ['lote_id' => $lote->id]))
            ->assertOk()
            ->assertSee('data-credit-usd="22000"', false)
            ->assertSee('data-credit-bs="153120"', false)
            ->assertSee('data-initial-credit-usd="4400"', false)
            ->assertSee('data-initial-credit-bs="30624"', false);
    }

    public function test_venta_credito_respeta_tipo_operacion_y_guarda_snapshot(): void
    {
        $lote = $this->createLote('VENTA-CREDITO', 20000, 'porcentaje', 20);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->post(route('ventas.store'), [
                'cliente_id' => $this->cliente->id,
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
            'tipo_operacion' => 'credito',
            'precio_base_usd' => 20000,
            'incremento_credito_aplicado' => 2000,
            'precio_final' => 22000,
            'precio_final_usd' => 22000,
            'precio_final_bs' => 153120,
            'cuota_inicial' => 1,
            'tipo_cambio_usd_bs' => 6.96,
        ]);
    }

    private function createLote(string $codigo, float $precio, string $cuotaTipo, float $cuotaValor, array $extra = []): Lote
    {
        return Lote::create([
            'manzano_id' => $this->manzano->id,
            'codigo' => $codigo,
            'superficie' => 328,
            'precio' => $precio,
            'cuota_inicial_tipo' => $cuotaTipo,
            'cuota_inicial_valor' => $cuotaValor,
            'estado' => 'disponible',
            'fila' => 1,
            'columna' => 1,
            'coord_x' => null,
            'coord_y' => null,
            ...$extra,
        ]);
    }
}
