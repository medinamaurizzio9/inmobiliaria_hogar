<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\UrbanizacionPublicFeature;
use App\Models\UrbanizacionPublicSetting;
use App\Models\User;
use App\Support\YouTubeEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UrbanizacionPublicPageTest extends TestCase
{
    use RefreshDatabase;

    private Urbanizacion $urbanizacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->urbanizacion = Urbanizacion::query()->where('estado', 'activa')->firstOrFail();
    }

    public function test_slug_page_uses_urbanizacion_identity_and_professional_fallback(): void
    {
        $this->urbanizacion->update(['nombre' => 'Colinas del Norte Zona 1', 'ubicacion' => 'Warnes, Santa Cruz']);

        $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->fresh()->slug))
            ->assertOk()->assertSee('Colinas del Norte Zona 1')->assertSee('Warnes, Santa Cruz')
            ->assertSee('project-hero fallback', false)->assertSee('id="disponibilidad"', false);
    }

    public function test_valid_youtube_and_active_features_render_in_order(): void
    {
        UrbanizacionPublicSetting::create([
            'urbanizacion_id' => $this->urbanizacion->id,
            'youtube_url' => 'https://youtu.be/abcDEF12345',
            'titulo_descripcion' => 'Tu próximo terreno puede estar aquí',
            'descripcion_principal' => 'Descripción comercial extensa del proyecto.',
            'info_title' => 'Por qué elegirnos',
        ]);
        UrbanizacionPublicFeature::create(['urbanizacion_id' => $this->urbanizacion->id, 'titulo' => 'Segundo', 'descripcion' => 'B', 'orden' => 2, 'activo' => true]);
        UrbanizacionPublicFeature::create(['urbanizacion_id' => $this->urbanizacion->id, 'titulo' => 'Primero', 'descripcion' => 'A', 'orden' => 1, 'activo' => true]);
        UrbanizacionPublicFeature::create(['urbanizacion_id' => $this->urbanizacion->id, 'titulo' => 'Oculto', 'descripcion' => 'Privado', 'orden' => 0, 'activo' => false]);

        $response = $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug));
        $response->assertOk()->assertSee('youtube-nocookie.com/embed/abcDEF12345', false)
            ->assertSee('Tu próximo terreno puede estar aquí')->assertSee('Descripción comercial extensa del proyecto.')
            ->assertSee('Por qué elegirnos')->assertDontSee('Oculto');
        $this->assertLessThan(strpos($response->getContent(), 'Segundo'), strpos($response->getContent(), 'Primero'));
        $this->assertLessThan(strpos($response->getContent(), 'data-public-features'), strpos($response->getContent(), 'project-description-section'));
        $this->assertLessThan(strpos($response->getContent(), 'id="disponibilidad"'), strpos($response->getContent(), 'data-public-features'));
    }

    public function test_youtube_parser_rejects_non_youtube_domains(): void
    {
        $this->assertNull(YouTubeEmbed::url('https://example.com/watch?v=abcDEF12345'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/abcDEF12345', YouTubeEmbed::url('https://www.youtube.com/watch?v=abcDEF12345'));
    }

    public function test_admin_updates_content_and_more_than_ten_features_is_rejected(): void
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $session = ['urbanizacion_id' => $this->urbanizacion->id];
        $payload = ['youtube_url' => 'https://example.com/watch?v=abcDEF12345', 'info_title' => 'Título'];
        $this->actingAs($admin)->withSession($session)->put(route('urbanizaciones.public-page.update', $this->urbanizacion), $payload)->assertSessionHasErrors('youtube_url');

        $features = array_map(fn ($index) => ['titulo' => "Bloque {$index}", 'descripcion' => 'Descripción', 'orden' => $index, 'activo' => 1], range(1, 11));
        $this->actingAs($admin)->withSession($session)->put(route('urbanizaciones.public-page.update', $this->urbanizacion), ['features' => $features])->assertSessionHasErrors('features');

        $this->actingAs($admin)->withSession($session)->put(route('urbanizaciones.public-page.update', $this->urbanizacion), [
            'youtube_url' => 'https://youtu.be/abcDEF12345',
            'titulo_descripcion' => 'Título descriptivo',
            'descripcion_principal' => 'Descripción principal guardada desde administración.',
            'info_title' => 'Título',
            'features' => array_slice($features, 0, 10),
        ])->assertRedirect();
        $this->assertDatabaseCount('urbanizacion_public_features', 10);
        $this->assertDatabaseHas('urbanizacion_public_settings', ['urbanizacion_id' => $this->urbanizacion->id, 'titulo_descripcion' => 'Título descriptivo', 'descripcion_principal' => 'Descripción principal guardada desde administración.']);
    }

    public function test_gerente_and_unauthorized_user_cannot_modify_under_current_permissions(): void
    {
        foreach (['gerente@impacto.test', 'vendedor@impacto.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
                ->put(route('urbanizaciones.public-page.update', $this->urbanizacion), ['info_title' => 'No permitido'])
                ->assertForbidden();
        }
    }

    public function test_hero_upload_replacement_and_removal_are_safe(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $session = ['urbanizacion_id' => $this->urbanizacion->id];
        $this->actingAs($admin)->withSession($session)->put(route('urbanizaciones.public-page.update', $this->urbanizacion), ['hero_image' => UploadedFile::fake()->image('hero.jpg', 2200, 1200)])->assertRedirect();
        $first = $this->urbanizacion->publicSetting()->firstOrFail()->hero_image;
        Storage::disk('public')->assertExists($first);
        $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug))->assertSee(Storage::disk('public')->url($first), false);

        $this->actingAs($admin)->withSession($session)->put(route('urbanizaciones.public-page.update', $this->urbanizacion), ['hero_image' => UploadedFile::fake()->image('new.png', 1600, 900)])->assertRedirect();
        $second = $this->urbanizacion->publicSetting()->firstOrFail()->hero_image;
        Storage::disk('public')->assertMissing($first)->assertExists($second);
        $this->actingAs($admin)->withSession($session)->put(route('urbanizaciones.public-page.update', $this->urbanizacion), ['remove_hero' => 1])->assertRedirect();
        Storage::disk('public')->assertMissing($second);
    }

    public function test_whatsapp_uses_global_setting_and_lot_message_has_only_public_context(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('planos/public-test.jpg', 'image');
        $this->urbanizacion->update(['plano_imagen' => 'planos/public-test.jpg']);
        SystemSetting::updateOrCreate(['key' => 'whatsapp'], ['value' => '70000000']);
        Cache::flush();
        $this->urbanizacion->lotes()->where('estado', 'disponible')->firstOrFail()->update(['coord_x' => 10, 'coord_y' => 20]);
        $response = $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug));
        $response->assertOk()->assertSee('https://wa.me/59170000000', false)
            ->assertSee(rawurlencode('Urbanización: '.$this->urbanizacion->nombre), false)
            ->assertSee(rawurlencode('Manzano:'), false)->assertSee(rawurlencode('Lote:'), false)
            ->assertSee(rawurlencode('Superficie:'), false)
            ->assertDontSee('comprador')->assertDontSee('saldo_pendiente')->assertDontSee('cuotas');
    }

    public function test_public_map_has_no_inventory_list_and_only_available_lots_receive_whatsapp(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('planos/public-test.jpg', 'image');
        $this->urbanizacion->update(['plano_imagen' => 'planos/public-test.jpg']);
        SystemSetting::updateOrCreate(['key' => 'whatsapp'], ['value' => '70000000']);
        Cache::flush();

        foreach (['disponible', 'vendido', 'bloqueado', 'reservado'] as $index => $state) {
            $this->urbanizacion->lotes()->where('estado', $state)->firstOrFail()->update(['coord_x' => 10 + $index, 'coord_y' => 20 + $index]);
        }

        $content = $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug))->assertOk()
            ->assertDontSee('project-lot-list', false)->assertDontSee('<table', false)
            ->assertSee('Actualmente reservado')->assertSee('No disponible')->getContent();

        preg_match_all('/<button[^>]+data-public-lot[^>]*>/', $content, $buttons);
        $this->assertCount(4, $buttons[0]);
        $byState = collect($buttons[0])->keyBy(fn (string $button) => preg_match('/data-state="([^"]+)"/', $button, $match) ? $match[1] : '');
        $this->assertStringContainsString('data-whatsapp=', $byState['disponible']);
        $this->assertStringNotContainsString('data-whatsapp=', $byState['vendido']);
        $this->assertStringNotContainsString('data-whatsapp=', $byState['bloqueado']);
        $this->assertStringNotContainsString('data-whatsapp=', $byState['reservado']);
    }

    public function test_page_works_without_video_or_features_and_does_not_query_foreign_lots(): void
    {
        $other = Urbanizacion::create(['nombre' => 'Proyecto ajeno', 'ubicacion' => 'Otra zona', 'estado' => 'activa']);
        $response = $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug));
        $response->assertOk()->assertDontSee('youtube-nocookie.com')->assertDontSee($other->nombre);
    }
}
