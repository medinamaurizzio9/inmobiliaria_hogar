<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlanoMapaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_subir_plano_a_urbanizacion(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('urbanizaciones.store'), [
                'nombre' => 'Plano Demo',
                'ubicacion' => 'Santa Cruz',
                'descripcion' => 'Urbanizacion con plano',
                'superficie_total' => 10000,
                'estado' => 'activa',
                'plano_imagen' => UploadedFile::fake()->image('plano.jpg', 1200, 800),
            ])
            ->assertRedirect(route('urbanizaciones.index'));

        $urbanizacion = Urbanizacion::where('nombre', 'Plano Demo')->firstOrFail();
        $this->assertNotNull($urbanizacion->plano_imagen);
        $this->assertNull($urbanizacion->plano_archivo_original);
        Storage::disk('public')->assertExists($urbanizacion->plano_imagen);
    }

    public function test_pdf_sin_conversor_muestra_error_amigable(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->from(route('urbanizaciones.edit', $urbanizacion))
            ->put(route('urbanizaciones.update', $urbanizacion), [
                'nombre' => $urbanizacion->nombre,
                'ubicacion' => $urbanizacion->ubicacion,
                'descripcion' => $urbanizacion->descripcion,
                'superficie_total' => $urbanizacion->superficie_total,
                'estado' => $urbanizacion->estado,
                'plano_imagen' => UploadedFile::fake()->create('plano.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('urbanizaciones.edit', $urbanizacion))
            ->assertSessionHasErrors([
                'plano_imagen' => 'No se pudo convertir el PDF. Suba una imagen JPG o PNG en alta resolución.',
            ]);
    }

    public function test_admin_puede_guardar_coordenadas_de_lote(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = Lote::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $lote->manzano->urbanizacion_id])
            ->patchJson(route('mapa.lotes.posicion', $lote), [
                'coord_x' => 42.5,
                'coord_y' => 68.25,
            ])
            ->assertOk()
            ->assertJson(['message' => 'Lote ubicado correctamente']);

        $this->assertEquals(42.5, (float) $lote->fresh()->coord_x);
        $this->assertEquals(68.25, (float) $lote->fresh()->coord_y);
    }

    public function test_admin_puede_quitar_ubicacion_de_lote(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = Lote::firstOrFail();
        $lote->update(['coord_x' => 42.5, 'coord_y' => 68.25]);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $lote->manzano->urbanizacion_id])
            ->deleteJson(route('mapa.lotes.posicion.clear', $lote))
            ->assertOk()
            ->assertJson(['message' => 'Ubicacion quitada correctamente']);

        $lote->refresh();
        $this->assertNull($lote->coord_x);
        $this->assertNull($lote->coord_y);
    }

    public function test_lote_sin_coordenadas_no_aparece_como_punto_en_mapa(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = Lote::firstOrFail();
        $lote->update(['coord_x' => null, 'coord_y' => null]);
        $urbanizacion = $lote->manzano->urbanizacion;
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->assertDontSee('data-lote-id="'.$lote->id.'"', false)
            ->assertSee('Sin ubicacion', false);
    }

    public function test_lote_con_coordenadas_se_renderiza_en_porcentaje(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = Lote::firstOrFail();
        $lote->update(['coord_x' => 35.25, 'coord_y' => 61.75]);
        $urbanizacion = $lote->manzano->urbanizacion;
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->assertSee('map-shell', false)
            ->assertSee('plan-map-viewport', false)
            ->assertSee('plan-map-layer', false)
            ->assertSee('plan-map-image', false)
            ->assertSee('lot-point', false)
            ->assertSee('data-zoom-value', false)
            ->assertSee('data-zoom-fullscreen', false)
            ->assertSee('data-lote-id="'.$lote->id.'"', false)
            ->assertSee('left: 35.25%; top: 61.75%;', false);
    }

    public function test_vendedor_no_puede_editar_coordenadas(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $lote = Lote::firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $vendedor->urbanizacionesAsignadas()->firstOrFail()->id])
            ->patchJson(route('mapa.lotes.posicion', $lote), [
                'coord_x' => 22,
                'coord_y' => 33,
            ])
            ->assertForbidden();
    }

    public function test_coordenadas_fuera_de_rango_son_rechazadas(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $lote = Lote::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $lote->manzano->urbanizacion_id])
            ->patchJson(route('mapa.lotes.posicion', $lote), [
                'coord_x' => 120,
                'coord_y' => -5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['coord_x', 'coord_y']);
    }

    public function test_mapa_muestra_plano_cargado(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->assertSee('Plano '.$urbanizacion->nombre, false)
            ->assertSee('planos/demo.jpg', false);
    }

    public function test_botones_del_mapa_tienen_tipo_explicito(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->assertSee('id="toggle-edit" type="button"', false)
            ->assertSee('id="next-unlocated" type="button"', false)
            ->assertSee('id="toggle-edit-lock" type="button"', false)
            ->assertSee('id="clear-position" type="button"', false)
            ->assertSee('type="button" data-zoom-in', false)
            ->assertSee('type="button" data-zoom-out', false)
            ->assertSee('type="button" data-zoom-reset', false)
            ->assertSee('type="button" data-zoom-fullscreen', false)
            ->assertSee('type="button" id="lotModalClose"', false);
    }

    public function test_modal_esta_fuera_del_viewport_del_mapa(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $html = $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('mapa', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->getContent();

        $viewportOpen = strpos($html, 'class="plan-map-viewport"');
        $viewportClose = strpos($html, '<div id="lotModalOverlay"');
        $modal = strpos($html, '<div id="lote-map-modal"');

        $this->assertNotFalse($viewportOpen);
        $this->assertNotFalse($viewportClose);
        $this->assertNotFalse($modal);
        $this->assertGreaterThan($viewportOpen, $viewportClose);
        $this->assertGreaterThan($viewportClose, $modal);
    }

    public function test_puntos_no_usan_escala_inversa(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.lot-point {', $css);
        $this->assertStringContainsString('position: absolute;', $css);
        $this->assertStringContainsString('transform: translate(-50%, -50%)', $css);
        $this->assertStringContainsString('inline-size: clamp(8px, 1.4vw, 22px)', $css);
        $this->assertStringContainsString('inline-size: clamp(6px, 2vw, 16px)', $css);
        $this->assertStringNotContainsString('--lot-point-size: 28px', $css);
        $this->assertStringNotContainsString('scale(calc', $css);
        $this->assertStringNotContainsString('scale(var(--', $css);

        $javascript = file_get_contents(public_path('js/map-zoom.js'));
        $this->assertStringContainsString('layer.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`', $javascript);
        $this->assertStringNotContainsString('point.style.transform', $javascript);
    }

    public function test_publico_puede_ver_mapa_responsive_sin_autenticacion(): void
    {
        $this->seed();

        $lote = Lote::firstOrFail();
        $lote->update(['coord_x' => 40, 'coord_y' => 55]);
        $urbanizacion = $lote->manzano->urbanizacion;
        Storage::disk('public')->put('planos/demo.jpg', 'fixture');
        $urbanizacion->update(['plano_imagen' => 'planos/demo.jpg']);

        $this->get(route('disponibilidad.publica', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->assertSee('plan-map-viewport', false)
            ->assertSee('plan-map-layer', false)
            ->assertSee('plan-map-image', false)
            ->assertSee('lot-point', false)
            ->assertSee('Zoom +', false)
            ->assertSee('data-zoom-out', false)
            ->assertSee('data-zoom-reset', false)
            ->assertSee('data-zoom-fullscreen', false)
            ->assertSee('left: 40%; top: 55%;', false);
    }
}
