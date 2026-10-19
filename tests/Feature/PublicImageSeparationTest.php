<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicImageSeparationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Urbanizacion $urbanizacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizacion = Urbanizacion::query()->where('estado', 'activa')->firstOrFail();
    }

    public function test_configured_global_banner_is_shown_and_can_be_removed(): void
    {
        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('admin.configuracion-general.update'), $this->settingsPayload([
                'landing_hero_image' => UploadedFile::fake()->image('banner.jpg', 1920, 900),
            ]))->assertRedirect();

        $path = SystemSetting::where('key', 'landing_hero_image')->value('value');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        auth()->logout();
        $this->get('/')->assertOk()->assertSee(Storage::disk('public')->url($path), false);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('admin.configuracion-general.update'), $this->settingsPayload([
                'landing_hero_image' => UploadedFile::fake()->image('banner-nuevo.png', 1920, 900),
            ]))->assertRedirect();

        $replacement = SystemSetting::where('key', 'landing_hero_image')->value('value');
        $this->assertNotSame($path, $replacement);
        Storage::disk('public')->assertMissing($path)->assertExists($replacement);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('admin.configuracion-general.update'), $this->settingsPayload([
                'remove_images' => ['landing_hero_image'],
            ]))->assertRedirect();

        Storage::disk('public')->assertMissing($replacement);
        Cache::forget('system_settings.all');
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('class="site-hero"', false)->assertDontSee('--hero-image:', false);
    }

    public function test_technical_plan_never_becomes_landing_or_project_cover(): void
    {
        Storage::disk('public')->put('planos/tecnico.webp', 'technical-plan');
        $this->urbanizacion->update(['plano_imagen' => 'planos/tecnico.webp']);
        Cache::forget('system_settings.all');

        $this->get('/')->assertOk()
            ->assertDontSee(Storage::disk('public')->url('planos/tecnico.webp'), false)
            ->assertSee('commercial-cover-placeholder', false)
            ->assertDontSee('--hero-image:', false);

        $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug))->assertOk()
            ->assertSee(Storage::disk('public')->url('planos/tecnico.webp'), false)
            ->assertSee('plan-map-image', false);
    }

    public function test_commercial_cover_is_managed_from_urbanization_form_and_used_by_cards(): void
    {
        Storage::disk('public')->put('planos/tecnico.webp', 'technical-plan');
        $this->urbanizacion->update(['plano_imagen' => 'planos/tecnico.webp']);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('urbanizaciones.update', $this->urbanizacion), $this->urbanizacionPayload([
                'commercial_image' => UploadedFile::fake()->image('portada.png', 1600, 900),
            ]))->assertRedirect(route('urbanizaciones.index'));

        $path = $this->urbanizacion->publicSetting()->firstOrFail()->hero_image;
        Storage::disk('public')->assertExists($path);
        auth()->logout();
        $this->get('/')->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false)
            ->assertDontSee(Storage::disk('public')->url('planos/tecnico.webp'), false);

        $this->get(route('disponibilidad.urbanizacion', $this->urbanizacion->slug))->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false)
            ->assertSee(Storage::disk('public')->url('planos/tecnico.webp'), false);
    }

    public function test_removing_commercial_cover_keeps_technical_plan_untouched(): void
    {
        Storage::disk('public')->put('planos/tecnico.webp', 'technical-plan');
        Storage::disk('public')->put('urbanizaciones/1/portada.webp', 'commercial');
        $this->urbanizacion->update(['plano_imagen' => 'planos/tecnico.webp']);
        $this->urbanizacion->publicSetting()->create(['hero_image' => 'urbanizaciones/1/portada.webp']);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('urbanizaciones.update', $this->urbanizacion), $this->urbanizacionPayload([
                'remove_commercial_image' => 1,
            ]))->assertRedirect(route('urbanizaciones.index'));

        $this->assertNull($this->urbanizacion->publicSetting()->firstOrFail()->hero_image);
        Storage::disk('public')->assertMissing('urbanizaciones/1/portada.webp');
        Storage::disk('public')->assertExists('planos/tecnico.webp');
    }

    public function test_invalid_commercial_image_is_rejected_without_replacing_existing_cover(): void
    {
        Storage::disk('public')->put('urbanizaciones/1/anterior.webp', 'commercial');
        $this->urbanizacion->publicSetting()->create(['hero_image' => 'urbanizaciones/1/anterior.webp']);

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('urbanizaciones.update', $this->urbanizacion), $this->urbanizacionPayload([
                'commercial_image' => UploadedFile::fake()->create('falsa.jpg', 10, 'image/jpeg'),
            ]))->assertSessionHasErrors('commercial_image');

        $this->assertSame('urbanizaciones/1/anterior.webp', $this->urbanizacion->publicSetting()->firstOrFail()->hero_image);
        Storage::disk('public')->assertExists('urbanizaciones/1/anterior.webp');
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'system_name' => 'HOGAR INMOBILIARIA',
            'system_subtitle' => 'Grupo Inmobiliario',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
        ], $overrides);
    }

    private function urbanizacionPayload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => $this->urbanizacion->nombre,
            'propietario' => $this->urbanizacion->propietario,
            'ubicacion' => $this->urbanizacion->ubicacion,
            'descripcion' => $this->urbanizacion->descripcion,
            'superficie_total' => $this->urbanizacion->superficie_total,
            'estado' => $this->urbanizacion->estado,
            'mostrar_precio_publico' => $this->urbanizacion->mostrar_precio_publico ? 1 : 0,
        ], $overrides);
    }
}
