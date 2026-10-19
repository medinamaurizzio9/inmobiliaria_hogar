<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Noticia;
use App\Models\PublicLead;
use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPremiumPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_and_login_render_public_portal(): void
    {
        $this->seed();

        foreach (['/', '/login'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('Nuestros proyectos')
                ->assertSee('Consulta disponibilidad')
                ->assertSee('name="email"', false)
                ->assertSee('autocomplete="current-password"', false);
        }
    }

    public function test_public_home_does_not_render_legacy_hero_or_technical_footer_copy(): void
    {
        $this->seed();

        $this->get('/')->assertOk()
            ->assertSee('class="site-hero"', false)
            ->assertDontSee('class="portal-hero"', false)
            ->assertDontSee('Sistema Integral de Terrenos')
            ->assertDontSee('Version piloto - MVP funcional.');
    }

    public function test_public_information_pages_render_with_shared_navigation(): void
    {
        $this->seed();

        foreach (['/nosotros', '/urbanizaciones-publicas', '/testimonios', '/preguntas-frecuentes', '/contacto'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('Navegación principal')
                ->assertSee('Ingresar');
        }
    }

    public function test_landing_and_project_images_never_fall_back_to_technical_plan(): void
    {
        $this->seed();
        Storage::fake('public');
        Storage::disk('public')->put('branding/hero-configurado.webp', 'hero');
        Storage::disk('public')->put('planos/proyecto-configurado.webp', 'project');
        SystemSetting::updateOrCreate(['key' => 'landing_hero_image'], ['value' => 'branding/hero-configurado.webp']);
        Urbanizacion::query()->where('estado', 'activa')->firstOrFail()->update(['plano_imagen' => 'planos/proyecto-configurado.webp']);
        Cache::forget('system_settings.all');

        $this->get('/')->assertOk()
            ->assertSee('storage/branding/hero-configurado.webp', false)
            ->assertDontSee('storage/planos/proyecto-configurado.webp', false)
            ->assertSee('commercial-cover-placeholder', false);
    }

    public function test_authenticated_user_does_not_receive_public_login(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();

        $this->actingAs($admin)->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_only_published_news_is_public(): void
    {
        $this->seed();
        $published = Noticia::create(['titulo' => 'Noticia visible', 'resumen' => 'Resumen público', 'contenido' => 'Contenido', 'publicada' => true, 'fecha_publicacion' => now()]);
        $draft = Noticia::create(['titulo' => 'Borrador secreto', 'resumen' => 'No mostrar', 'contenido' => 'Privado', 'publicada' => false]);

        $this->get('/')->assertOk()->assertSee($published->titulo)->assertDontSee($draft->titulo);
        $this->get(route('public.noticias.show', $published->slug))->assertOk()->assertSee($published->contenido);
        $this->get('/noticias/'.$draft->slug)->assertNotFound();
    }

    public function test_public_contact_validates_and_creates_lead_not_client(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::firstOrFail();
        $clientsBefore = Cliente::count();

        $this->post(route('public.contacto.store'), [])->assertSessionHasErrors(['nombre', 'celular', 'mensaje']);
        $this->post(route('public.contacto.store'), [
            'nombre' => 'Persona interesada',
            'celular' => '70001122',
            'email' => 'lead@example.com',
            'urbanizacion_id' => $urbanizacion->id,
            'mensaje' => 'Quisiera conocer los lotes disponibles.',
        ])->assertRedirect()->assertSessionHas('public_status');

        $this->assertDatabaseHas('public_leads', ['nombre' => 'Persona interesada', 'origen' => 'web', 'estado' => 'nuevo']);
        $this->assertSame($clientsBefore, Cliente::count());
        $this->assertSame(1, PublicLead::count());
    }

    public function test_public_contact_rejects_honeypot_submission(): void
    {
        $this->seed();

        $this->post(route('public.contacto.store'), [
            'website' => 'https://spam.example',
            'nombre' => 'Bot',
            'celular' => '70000000',
            'mensaje' => 'Spam',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, PublicLead::count());
    }

    public function test_public_availability_does_not_expose_private_buyer_data(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cliente = Cliente::where('urbanizacion_id', $urbanizacion->id)->firstOrFail();

        $this->get(route('disponibilidad.publica', ['urbanizacion_id' => $urbanizacion->id]))
            ->assertOk()
            ->assertDontSee($cliente->documento)
            ->assertDontSee($cliente->telefono)
            ->assertDontSee('saldo_pendiente');
    }

    public function test_public_project_card_uses_shared_component_and_anonymous_whatsapp(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::query()->where('estado', 'activa')->firstOrFail();
        SystemSetting::updateOrCreate(['key' => 'whatsapp'], ['value' => '70000000']);
        Cache::flush();

        $this->get('/')
            ->assertOk()
            ->assertSee('urbanization-card', false)
            ->assertSee($urbanizacion->nombre)
            ->assertSee((string) $urbanizacion->lotes()->count())
            ->assertSee(route('disponibilidad.urbanizacion', $urbanizacion->slug), false)
            ->assertSee('https://wa.me/59170000000', false)
            ->assertSee(rawurlencode("Hola, quisiera reservar una visita para conocer la urbanización {$urbanizacion->nombre}."), false)
            ->assertDontSee('Hola, soy ');
    }
}
