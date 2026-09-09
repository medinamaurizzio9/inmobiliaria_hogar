<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandLogoRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_uses_configured_logo_and_login_preference(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/main-wide.webp', 'wide-logo');
        Storage::disk('public')->put('branding/login-tall.png', 'tall-logo');
        foreach ([
            'system_name' => 'Hogar Inmobiliaria',
            'logo_main' => 'branding/main-wide.webp',
            'logo_login' => 'branding/login-tall.png',
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('system_settings.all');

        $this->blade('<x-brand-logo variant="public-header" />')
            ->assertSee(Storage::disk('public')->url('branding/main-wide.webp'), false)
            ->assertSee('brand-logo--public-header', false);
        $this->blade('<x-brand-logo variant="login" :prefer-login="true" />')
            ->assertSee(Storage::disk('public')->url('branding/login-tall.png'), false)
            ->assertSee('brand-logo--login', false);
    }

    public function test_missing_logo_has_accessible_initials_fallback(): void
    {
        SystemSetting::updateOrCreate(['key' => 'system_name'], ['value' => 'Hogar Inmobiliaria']);
        Cache::forget('system_settings.all');

        $this->blade('<x-brand-logo variant="sidebar" />')
            ->assertSee('brand-logo-fallback--sidebar', false)
            ->assertSee('role="img"', false)
            ->assertSee('aria-label="Hogar Inmobiliaria"', false)
            ->assertSee('>HI</span>', false)
            ->assertDontSee('<img', false);
    }

    public function test_every_supported_context_has_an_isolated_variant(): void
    {
        foreach (['sidebar', 'topbar', 'login', 'login-hero', 'public-header', 'footer', 'pwa'] as $variant) {
            $this->blade("<x-brand-logo variant=\"{$variant}\" src=\"/storage/branding/extreme-logo.png\" />")
                ->assertSee("brand-logo--{$variant}", false)
                ->assertSee('src="/storage/branding/extreme-logo.png"', false);
        }
    }

    public function test_css_bounds_horizontal_vertical_and_large_logos_without_deformation(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('.brand-logo{display:block;', $css);
        $this->assertStringContainsString('object-fit:contain', $css);
        $this->assertStringContainsString('.brand-logo--sidebar,.brand-logo-fallback--sidebar{width:44px;height:44px;max-width:44px;max-height:44px}', $css);
        $this->assertStringContainsString('.brand-logo--login,.brand-logo-fallback--login{width:180px;height:82px;', $css);
        $this->assertStringContainsString('.brand-logo--public-header,.brand-logo-fallback--public-header{width:132px;height:52px;', $css);
        $this->assertStringNotContainsString('width: 44px !important', $css);
    }

    public function test_views_use_the_component_without_inline_logo_dimensions(): void
    {
        $views = [
            resource_path('views/layouts/partials/sidebar.blade.php'),
            resource_path('views/layouts/partials/topbar.blade.php'),
            resource_path('views/auth/login.blade.php'),
            resource_path('views/public/portal.blade.php'),
            resource_path('views/disponibilidad/index.blade.php'),
            resource_path('views/public/recibos-verificar.blade.php'),
            resource_path('views/offline.blade.php'),
        ];

        foreach ($views as $view) {
            $markup = file_get_contents($view);
            $this->assertStringContainsString('<x-brand-logo', $markup, $view);
            $this->assertStringNotContainsString('style="max-width:72px', $markup, $view);
        }
    }

    public function test_public_login_project_and_offline_pages_render_logo_variants(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::query()->where('estado', 'activa')->firstOrFail();

        $this->get('/')->assertOk()
            ->assertSee('brand-logo-frame--public-header', false)
            ->assertSee('brand-logo-frame--login', false)
            ->assertSee('brand-logo-frame--footer', false);
        $this->get(route('disponibilidad.urbanizacion', $urbanizacion->slug))->assertOk()
            ->assertSee('brand-logo-frame--public-header', false);
        $this->get(route('pwa.offline'))->assertOk()
            ->assertSee('brand-logo--pwa', false)
            ->assertSee('pwa/icon-192.png', false);
    }

    public function test_authenticated_layout_renders_sidebar_and_mobile_topbar_variants(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('brand-logo-frame--sidebar', false)
            ->assertSee('brand-logo-frame--topbar', false);
    }

    public function test_rendering_public_brand_does_not_change_lot_coordinates(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::query()->where('estado', 'activa')->firstOrFail();
        $lote = $urbanizacion->lotes()->firstOrFail();
        $lote->update(['coord_x' => 37.25, 'coord_y' => 61.75]);

        $this->get(route('disponibilidad.urbanizacion', $urbanizacion->slug))->assertOk();

        $this->assertEquals(37.25, $lote->fresh()->coord_x);
        $this->assertEquals(61.75, $lote->fresh()->coord_y);
    }
}
