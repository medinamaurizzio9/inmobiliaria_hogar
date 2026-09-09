<?php

namespace Tests\Feature;

use App\Models\CommercialSetting;
use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LotePrecioVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    private Urbanizacion $urbanizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->urbanizacion = Urbanizacion::firstOrFail();

        CommercialSetting::updateOrCreate(['key' => 'incremento_credito_tipo'], ['value' => 'monto']);
        CommercialSetting::updateOrCreate(['key' => 'incremento_credito_valor'], ['value' => '3000']);
    }

    public function test_administrador_ve_precio_oportunidad_y_precio_real(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('lotes.index'))
            ->assertOk()
            ->assertSee('Precio Oportunidad')
            ->assertSee('Precio Real')
            ->assertSee('Precio oportunidad desde')
            ->assertSee('Precio oportunidad hasta');
    }

    public function test_vendedor_solo_ve_precio_real_en_lotes(): void
    {
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('lotes.index'))
            ->assertOk()
            ->assertDontSee('Precio Oportunidad')
            ->assertDontSee('Precio oportunidad desde')
            ->assertDontSee('Precio oportunidad hasta')
            ->assertSee('Precio Real')
            ->assertSee('Precio real desde')
            ->assertSee('Precio real hasta');
    }

    public function test_exportacion_de_vendedor_no_contiene_precio_oportunidad(): void
    {
        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $vendedor->givePermissionTo('exportar reportes');
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();

        $response = $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('export.csv', 'lotes'))
            ->assertOk();

        $content = $response->getContent();

        $this->assertStringNotContainsString('precio_oportunidad', $content);
        $this->assertStringContainsString('precio_real', $content);
    }

    public function test_exportacion_de_administrador_contiene_ambos_precios(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();

        $response = $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->get(route('export.csv', 'lotes'))
            ->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('precio_oportunidad', $content);
        $this->assertStringContainsString('precio_real', $content);
    }

    public function test_disponibilidad_publica_no_muestra_precio_oportunidad(): void
    {
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $this->urbanizacion->id))
            ->where('estado', 'disponible')
            ->firstOrFail();
        Storage::fake('public');
        Storage::disk('public')->put('planos/precio-publico.jpg', 'image');
        $this->urbanizacion->update(['plano_imagen' => 'planos/precio-publico.jpg', 'mostrar_precio_publico' => true]);
        $lote->update(['precio' => 20000, 'coord_x' => 10, 'coord_y' => 20]);

        $this->get(route('disponibilidad.publica', ['urbanizacion_id' => $this->urbanizacion->id]))
            ->assertOk()
            ->assertDontSee('Precio Oportunidad')
            ->assertSee('data-price="$us 23,000.00"', false)
            ->assertDontSee('project-lot-list', false);

        $this->urbanizacion->update(['mostrar_precio_publico' => false]);
        $this->get(route('disponibilidad.publica', ['urbanizacion_id' => $this->urbanizacion->id]))
            ->assertOk()->assertDontSee('data-price=', false)->assertDontSee('$us 23,000.00');
    }
}
