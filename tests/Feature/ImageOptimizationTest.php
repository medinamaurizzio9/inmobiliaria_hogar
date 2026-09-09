<?php

namespace Tests\Feature;

use App\Models\Noticia;
use App\Models\Urbanizacion;
use App\Services\ManagedImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    private ManagedImageService $images;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->images = app(ManagedImageService::class);
    }

    public function test_jpg_grande_se_redimensiona_y_convierte_a_webp_cuando_esta_disponible(): void
    {
        $result = $this->images->storeOptimized(UploadedFile::fake()->image('grande.jpg', 2400, 1800), 'noticias', ['max_width' => 1600, 'max_height' => 1600]);

        [$width, $height] = getimagesize(Storage::disk('public')->path($result['path']));
        $this->assertSame([1600, 1200], [$width, $height]);
        $this->assertSame(function_exists('imagewebp') ? 'webp' : 'jpeg', $result['format']);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_png_y_webp_de_entrada_se_procesan_y_una_imagen_pequena_no_se_amplia(): void
    {
        foreach (['entrada.png', 'entrada.webp'] as $name) {
            $result = $this->images->storeOptimized(UploadedFile::fake()->image($name, 320, 180), 'pruebas', ['max_width' => 1600, 'max_height' => 1600]);
            $this->assertSame([320, 180], array_slice(getimagesize(Storage::disk('public')->path($result['path'])), 0, 2));
        }
    }

    public function test_thumbnail_se_genera_una_vez_en_ruta_derivada(): void
    {
        $result = $this->images->storeOptimized(UploadedFile::fake()->image('foto.jpg', 1200, 900), 'clientes', ['max_width' => 600, 'max_height' => 600, 'generate_thumbnail' => true, 'thumbnail_width' => 240, 'thumbnail_height' => 240]);

        $this->assertSame($this->images->thumbnailPath($result['path']), $result['thumbnail_path']);
        $this->assertSame([240, 180], array_slice(getimagesize(Storage::disk('public')->path($result['thumbnail_path'])), 0, 2));
    }

    public function test_reemplazo_elimina_principal_y_thumbnail_anteriores_solo_despues_del_nuevo_guardado(): void
    {
        $old = $this->images->storeOptimized(UploadedFile::fake()->image('old.jpg', 900, 600), 'noticias', ['generate_thumbnail' => true]);
        $new = $this->images->replaceOptimized($old['path'], UploadedFile::fake()->image('new.png', 800, 500), 'noticias', ['generate_thumbnail' => true]);

        Storage::disk('public')->assertMissing($old['path']);
        Storage::disk('public')->assertMissing($old['thumbnail_path']);
        Storage::disk('public')->assertExists($new['path']);
        Storage::disk('public')->assertExists($new['thumbnail_path']);
    }

    public function test_si_la_imagen_nueva_es_invalida_la_anterior_no_se_elimina(): void
    {
        Storage::disk('public')->put('noticias/anterior.webp', 'anterior');
        $invalid = UploadedFile::fake()->createWithContent('ataque.jpg', '<?php echo "no";');

        try {
            $this->images->replaceOptimized('noticias/anterior.webp', $invalid, 'noticias');
            $this->fail('Se esperaba rechazo de la imagen inválida.');
        } catch (RuntimeException) {
            Storage::disk('public')->assertExists('noticias/anterior.webp');
        }
    }

    public function test_archivo_php_renombrado_es_rechazado(): void
    {
        $this->expectException(RuntimeException::class);
        $this->images->storeOptimized(UploadedFile::fake()->createWithContent('foto.png', '<?php phpinfo();'), 'noticias');
    }

    public function test_path_traversal_en_directorio_es_imposible(): void
    {
        $this->expectException(RuntimeException::class);
        $this->images->storeOptimized(UploadedFile::fake()->image('foto.png'), '../public');
    }

    public function test_qr_png_conserva_png_y_dimensiones_si_ya_es_pequeno(): void
    {
        $result = $this->images->storeOptimized(UploadedFile::fake()->image('qr.png', 500, 500), 'configuracion-financiera/qr', ['max_width' => 2000, 'max_height' => 2000, 'format' => 'original', 'quality' => 95]);

        $this->assertSame('png', $result['format']);
        $this->assertSame([500, 500], array_slice(getimagesize(Storage::disk('public')->path($result['path'])), 0, 2));
    }

    public function test_noticia_usa_thumbnail_en_listado_principal_en_detalle_y_soporta_ruta_heredada(): void
    {
        $stored = $this->images->storeOptimized(UploadedFile::fake()->image('noticia.jpg', 1200, 800), 'noticias', ['generate_thumbnail' => true]);
        $noticia = new Noticia(['imagen' => $stored['path']]);
        $this->assertStringContainsString('/thumbs/', $noticia->thumbnailUrl());
        $this->assertStringNotContainsString('/thumbs/', $noticia->imageUrl());

        Storage::disk('public')->put('noticias/heredada.jpg', 'legacy');
        $legacy = new Noticia(['imagen' => 'noticias/heredada.jpg']);
        $this->assertSame($legacy->imageUrl(), $legacy->thumbnailUrl());
    }

    public function test_fallback_no_entrega_url_cuando_archivo_no_existe(): void
    {
        $this->assertNull((new Noticia(['imagen' => 'noticias/falta.webp']))->thumbnailUrl());
        $this->assertNull((new Urbanizacion(['plano_imagen' => 'planos/falta.webp']))->imageUrl(true));
    }
}
