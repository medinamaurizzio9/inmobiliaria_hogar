<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\PlanImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PlanImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/large-plan-test.png'));

        parent::tearDown();
    }

    public function test_small_plan_keeps_current_processing(): void
    {
        $file = UploadedFile::fake()->image('small-plan.jpg', 1200, 800);
        $result = app(PlanImageService::class)->store($file);
        $optimization = $result['optimization'];

        $this->assertLessThanOrEqual(PlanImageService::TARGET_BYTES, $optimization['source_bytes']);
        $this->assertSame([1200, 800], array_slice(getimagesize(Storage::disk('public')->path($result['plano_imagen'])), 0, 2));
        $this->assertNull($optimization['target_bytes_reached']);
    }

    public function test_large_plan_is_optimized_without_changing_dimensions_or_aspect_ratio(): void
    {
        $file = $this->largePng();
        $beforeBytes = $file->getSize();
        $beforeDimensions = array_slice(getimagesize($file->getRealPath()), 0, 2);
        $result = app(PlanImageService::class)->store($file);
        $path = Storage::disk('public')->path($result['plano_imagen']);
        $afterDimensions = array_slice(getimagesize($path), 0, 2);

        $this->assertGreaterThan(PlanImageService::TARGET_BYTES, $beforeBytes);
        $this->assertSame($beforeDimensions, $afterDimensions);
        $this->assertSame($beforeDimensions[0] / $beforeDimensions[1], $afterDimensions[0] / $afterDimensions[1]);
        $this->assertLessThan($beforeBytes, filesize($path));
        $this->assertSame(function_exists('imagewebp') ? 'webp' : 'png', $result['optimization']['format']);
        $this->assertContains($result['optimization']['quality'], [88, 85, 82, 80, 78]);
    }

    public function test_replacing_plan_preserves_coordinates_and_deletes_old_files_after_success(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->firstOrFail();
        $lote->update(['coord_x' => 42.5, 'coord_y' => 61.25]);
        Storage::disk('public')->put('planos/old.webp', 'old');
        Storage::disk('public')->put('planos/thumbs/old.webp', 'old thumb');
        $urbanizacion->update(['plano_imagen' => 'planos/old.webp']);

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->put(route('urbanizaciones.update', $urbanizacion), $this->urbanizacionPayload($urbanizacion, $this->largePng()))
            ->assertRedirect(route('urbanizaciones.index'));

        $urbanizacion->refresh();
        Storage::disk('public')->assertExists($urbanizacion->plano_imagen);
        Storage::disk('public')->assertMissing('planos/old.webp');
        Storage::disk('public')->assertMissing('planos/thumbs/old.webp');
        $this->assertSame(42.5, (float) $lote->fresh()->coord_x);
        $this->assertSame(61.25, (float) $lote->fresh()->coord_y);
    }

    public function test_invalid_mime_is_rejected_and_previous_plan_remains(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        Storage::disk('public')->put('planos/previous.webp', 'previous');
        $urbanizacion->update(['plano_imagen' => 'planos/previous.webp']);
        $invalid = UploadedFile::fake()->createWithContent('attack.jpg', '<?php echo "invalid";');

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->from(route('urbanizaciones.edit', $urbanizacion))
            ->put(route('urbanizaciones.update', $urbanizacion), $this->urbanizacionPayload($urbanizacion, $invalid))
            ->assertRedirect(route('urbanizaciones.edit', $urbanizacion))->assertSessionHasErrors('plano_imagen');

        $this->assertSame('planos/previous.webp', $urbanizacion->fresh()->plano_imagen);
        Storage::disk('public')->assertExists('planos/previous.webp');
    }

    public function test_service_rejects_file_with_fake_image_extension(): void
    {
        $this->expectException(RuntimeException::class);

        app(PlanImageService::class)->store(UploadedFile::fake()->createWithContent('attack.png', 'not an image'));
    }

    public function test_public_page_uses_processed_plan_and_renders_marker(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::firstOrFail();
        $lote = Lote::whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->firstOrFail();
        $lote->update(['coord_x' => 20, 'coord_y' => 30]);
        $stored = app(PlanImageService::class)->store($this->largePng());
        $urbanizacion->update(['plano_imagen' => $stored['plano_imagen'], 'plano_archivo_original' => null]);

        $this->get(route('disponibilidad.urbanizacion', $urbanizacion->slug))->assertOk()
            ->assertSee(Storage::disk('public')->url($stored['plano_imagen']), false)
            ->assertSee('left: 20%; top: 30%;', false)
            ->assertSee('data-public-lot', false);
    }

    private function largePng(): UploadedFile
    {
        $path = storage_path('framework/large-plan-test.png');
        $image = imagecreatetruecolor(2200, 1400);
        imagefill($image, 0, 0, imagecolorallocate($image, 34, 82, 126));
        imagepng($image, $path, 0);
        imagedestroy($image);

        return new UploadedFile($path, 'large-plan.png', 'image/png', null, true);
    }

    private function urbanizacionPayload(Urbanizacion $urbanizacion, UploadedFile $file): array
    {
        return [
            'nombre' => $urbanizacion->nombre,
            'ubicacion' => $urbanizacion->ubicacion,
            'descripcion' => $urbanizacion->descripcion,
            'superficie_total' => $urbanizacion->superficie_total,
            'estado' => $urbanizacion->estado,
            'mostrar_precio_publico' => $urbanizacion->mostrar_precio_publico,
            'plano_imagen' => $file,
        ];
    }
}
