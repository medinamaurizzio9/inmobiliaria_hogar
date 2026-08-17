<?php

namespace Tests\Feature;

use App\Models\Noticia;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $urbanizationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizationId = Urbanizacion::firstOrFail()->id;
    }

    public function test_admin_lists_creates_slug_image_and_audit(): void
    {
        Storage::fake('public');
        $this->adminGet(route('admin.noticias.index'))->assertOk()->assertSee('Noticias y novedades')->assertSee('+ Nueva noticia');

        $this->adminPost(route('admin.noticias.store'), $this->payload([
            'titulo' => 'Nueva inversión inmobiliaria',
            'imagen' => UploadedFile::fake()->image('portada.png', 900, 600),
            'destacada' => '1',
            'orden' => '4',
        ]))->assertRedirect(route('admin.noticias.index'));

        $noticia = Noticia::where('titulo', 'Nueva inversión inmobiliaria')->firstOrFail();
        $this->assertSame('nueva-inversion-inmobiliaria', $noticia->slug);
        $this->assertTrue($noticia->destacada);
        $this->assertSame(4, $noticia->orden);
        Storage::disk('public')->assertExists($noticia->imagen);
        $this->assertDatabaseHas('audit_logs', ['modelo' => 'Noticia', 'modelo_id' => $noticia->id, 'accion' => 'noticia_creada', 'user_id' => $this->admin->id]);
    }

    public function test_publication_date_drafts_and_featured_order_are_respected(): void
    {
        $normal = $this->news('Noticia normal', true, now()->subDay());
        $featured = $this->news('Noticia destacada', true, now()->subDays(3), true);
        $draft = $this->news('Borrador privado', false, null);
        $future = $this->news('Publicación futura', true, now()->addDay());

        $response = $this->get('/')->assertOk()->assertSee($normal->titulo)->assertSee($featured->titulo)->assertDontSee($draft->titulo)->assertDontSee($future->titulo);
        $this->assertLessThan(strpos($response->getContent(), $normal->titulo), strpos($response->getContent(), $featured->titulo));
        $this->get(route('public.noticias.show', $featured->slug))->assertOk()->assertSee($featured->contenido);
        $this->get(route('public.noticias.show', $draft->slug))->assertNotFound();
        $this->get(route('public.noticias.show', $future->slug))->assertNotFound();
    }

    public function test_admin_updates_publishes_hides_and_audits_each_change(): void
    {
        $noticia = $this->news('Título inicial', false, null);

        $this->adminPut(route('admin.noticias.update', $noticia), $this->payload(['titulo' => 'Título actualizado', 'estado' => 'publicada']))->assertRedirect();
        $noticia->refresh();
        $this->assertTrue($noticia->publicada);
        $this->assertDatabaseHas('audit_logs', ['modelo_id' => $noticia->id, 'accion' => 'noticia_actualizada']);
        $this->assertDatabaseHas('audit_logs', ['modelo_id' => $noticia->id, 'accion' => 'noticia_publicada']);

        $this->adminPost(route('admin.noticias.toggle', $noticia))->assertRedirect();
        $this->assertFalse($noticia->fresh()->publicada);
        $this->assertDatabaseHas('audit_logs', ['modelo_id' => $noticia->id, 'accion' => 'noticia_ocultada']);
    }

    public function test_delete_removes_exclusive_image_and_registers_audit(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('noticias/exclusiva.webp', 'imagen');
        $noticia = $this->news('Noticia eliminable', false, null, false, 'noticias/exclusiva.webp');

        $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationId])
            ->delete(route('admin.noticias.destroy', $noticia))->assertRedirect();

        Storage::disk('public')->assertMissing('noticias/exclusiva.webp');
        $this->assertDatabaseMissing('noticias', ['id' => $noticia->id]);
        $this->assertDatabaseHas('audit_logs', ['modelo_id' => $noticia->id, 'accion' => 'noticia_eliminada']);
    }

    public function test_non_admin_roles_cannot_manage_news(): void
    {
        foreach (['vendedor@impacto.test', 'supervisor@impacto.test', 'cliente@impacto.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $user->update(['must_change_password' => false]);
            $this->actingAs($user)->withSession(['urbanizacion_id' => $this->urbanizationId])
                ->get(route('admin.noticias.index'))->assertForbidden();
        }
    }

    public function test_missing_image_uses_placeholder_without_broken_img(): void
    {
        Storage::fake('public');
        $noticia = $this->news('Imagen inexistente', true, now()->subMinute(), false, 'noticias/no-existe.webp');

        $this->get('/')->assertOk()->assertSee($noticia->titulo)->assertSee('portal-news-placeholder')->assertDontSee('no-existe.webp');
        $this->get(route('public.noticias.show', $noticia->slug))->assertOk()->assertSee('public-article-placeholder')->assertDontSee('no-existe.webp');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['titulo' => 'Noticia de prueba', 'resumen' => 'Resumen de prueba', 'contenido' => 'Contenido seguro de prueba', 'estado' => 'borrador', 'fecha_publicacion' => ''], $overrides);
    }

    private function news(string $title, bool $published, $date, bool $featured = false, ?string $image = null): Noticia
    {
        return Noticia::create(['titulo' => $title, 'resumen' => 'Resumen '.$title, 'contenido' => 'Contenido '.$title, 'publicada' => $published, 'destacada' => $featured, 'fecha_publicacion' => $date, 'imagen' => $image, 'autor_id' => $this->admin->id]);
    }

    private function adminGet(string $url)
    {
        return $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationId])->get($url);
    }

    private function adminPost(string $url, array $data = [])
    {
        return $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationId])->post($url, $data);
    }

    private function adminPut(string $url, array $data)
    {
        return $this->actingAs($this->admin)->withSession(['urbanizacion_id' => $this->urbanizationId])->put($url, $data);
    }
}
