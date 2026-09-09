<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PlanImageService
{
    public const TARGET_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly ManagedImageService $images) {}

    public function store(UploadedFile $file): array
    {
        if ($this->isPdf($file)) {
            return $this->storePdf($file);
        }

        $isLarge = (int) $file->getSize() > self::TARGET_BYTES;
        $result = $this->images->storeOptimized($file, 'planos', [
            'max_width' => 1600,
            'max_height' => 1600,
            'preserve_dimensions' => $isLarge,
            'quality' => $isLarge ? 88 : 84,
            'quality_candidates' => $isLarge ? [88, 85, 82, 80, 78] : [84],
            'target_bytes' => $isLarge ? self::TARGET_BYTES : null,
            'generate_thumbnail' => true,
            'thumbnail_width' => 600,
            'thumbnail_height' => 600,
        ]);

        if (! Storage::disk('public')->exists($result['path'])) {
            throw new RuntimeException('No se pudo verificar el plano procesado.');
        }

        return [
            'plano_imagen' => $result['path'],
            'plano_archivo_original' => null,
            'optimization' => $result,
        ];
    }

    public function delete(?string $imagePath, ?string $originalPath = null): void
    {
        $this->images->delete($imagePath, 'planos');

        if ($originalPath) {
            Storage::disk('public')->delete($originalPath);
        }
    }

    private function storePdf(UploadedFile $file): array
    {
        $originalPath = $file->store('planos/originales', 'public');
        $imagePath = 'planos/'.Str::uuid().'.png';

        try {
            $this->convertPdfFirstPageToPng(
                Storage::disk('public')->path($originalPath),
                Storage::disk('public')->path($imagePath)
            );
        } catch (RuntimeException $exception) {
            Storage::disk('public')->delete($originalPath);
            throw $exception;
        }

        return [
            'plano_imagen' => $imagePath,
            'plano_archivo_original' => $originalPath,
        ];
    }

    private function convertPdfFirstPageToPng(string $pdfPath, string $outputPath): void
    {
        if (! class_exists(\Imagick::class)) {
            throw new RuntimeException('No se pudo convertir el PDF. Suba una imagen JPG o PNG en alta resolución.');
        }

        try {
            $image = new \Imagick;
            $image->setResolution(300, 300);
            $image->readImage($pdfPath.'[0]');
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $image->setImageFormat('png');
            $image->setImageCompressionQuality(100);
            $image->stripImage();

            if (! $image->writeImage($outputPath)) {
                throw new RuntimeException('No se pudo convertir el PDF. Suba una imagen JPG o PNG en alta resolución.');
            }

            $image->clear();
            $image->destroy();
        } catch (\Throwable $exception) {
            throw new RuntimeException('No se pudo convertir el PDF. Suba una imagen JPG o PNG en alta resolución.', 0, $exception);
        }
    }

    private function isPdf(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'pdf'
            || $file->getMimeType() === 'application/pdf';
    }
}
