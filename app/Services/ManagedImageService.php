<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ManagedImageService
{
    public function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        $stored = $this->storeOptimized($file, $directory);
        $this->delete($oldPath, $directory);

        return $stored['path'];
    }

    /** @return array{path: string, thumbnail_path: ?string, width: int, height: int, bytes: int, format: string, quality: int, source_bytes: int, target_bytes_reached: ?bool} */
    public function storeOptimized(UploadedFile $file, string $directory, array $options = []): array
    {
        $directory = trim($directory, '/');
        $this->assertSafeDirectory($directory);
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('El archivo no es una imagen JPG, PNG o WebP válida.');
        }
        if ($info[0] > 12000 || $info[1] > 12000 || ($info[0] * $info[1]) > 40_000_000) {
            throw new RuntimeException('La imagen tiene dimensiones excesivas para procesarse de forma segura.');
        }

        $source = $this->orient($this->createImage($file->getRealPath(), $info[2]), $file->getRealPath(), $info[2]);
        $width = imagesx($source);
        $height = imagesy($source);
        $sourceBytes = (int) (filesize($file->getRealPath()) ?: 0);
        $format = $this->outputFormat((string) ($options['format'] ?? 'webp'), $info[2]);
        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $basename = (string) Str::uuid();
        $path = $directory.'/'.$basename.'.'.$extension;
        $thumbnailPath = ! empty($options['generate_thumbnail']) ? $directory.'/thumbs/'.$basename.'.'.$extension : null;
        $written = [];

        $stream = null;
        try {
            $preserveDimensions = (bool) ($options['preserve_dimensions'] ?? false);
            [$main, $mainWidth, $mainHeight] = $preserveDimensions
                ? [$source, $width, $height]
                : $this->resize($source, $width, $height, (int) ($options['max_width'] ?? 1600), (int) ($options['max_height'] ?? 1600));
            $targetBytes = isset($options['target_bytes']) ? max(1, (int) $options['target_bytes']) : null;
            $qualities = $this->qualityCandidates($options);
            $usedQuality = $qualities[0];
            $written[] = $path;

            foreach ($qualities as $quality) {
                $this->persist($main, $path, $format, $quality);
                $usedQuality = $quality;
                if ($targetBytes === null || Storage::disk('public')->size($path) <= $targetBytes || ! in_array($format, ['webp', 'jpeg'], true)) {
                    break;
                }
            }
            if ($main !== $source) {
                imagedestroy($main);
            }

            if ($thumbnailPath !== null) {
                [$thumb] = $this->resize($source, $width, $height, (int) ($options['thumbnail_width'] ?? 600), (int) ($options['thumbnail_height'] ?? 600));
                $this->persist($thumb, $thumbnailPath, $format, (int) ($options['thumbnail_quality'] ?? $options['quality'] ?? 82));
                $written[] = $thumbnailPath;
                if ($thumb !== $source) {
                    imagedestroy($thumb);
                }
            }
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($written);
            imagedestroy($source);
            throw new RuntimeException('No se pudo procesar y guardar la imagen.', 0, $exception);
        }

        imagedestroy($source);

        $bytes = Storage::disk('public')->size($path);

        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'width' => $mainWidth,
            'height' => $mainHeight,
            'bytes' => $bytes,
            'format' => $format,
            'quality' => $usedQuality,
            'source_bytes' => $sourceBytes,
            'target_bytes_reached' => $targetBytes === null ? null : $bytes <= $targetBytes,
        ];
    }

    public function replaceOptimized(?string $oldPath, UploadedFile $file, string $directory, array $options = []): array
    {
        $stored = $this->storeOptimized($file, $directory, $options);
        $this->delete($oldPath, $directory);

        return $stored;
    }

    public function thumbnailPath(?string $path): ?string
    {
        $path = ltrim((string) $path, '/');
        if ($path === '' || str_contains($path, '://') || str_contains($path, '..')) {
            return null;
        }

        return dirname($path).'/thumbs/'.basename($path);
    }

    public function delete(?string $path, string $directory): void
    {
        $path = ltrim((string) $path, '/');
        $directory = trim($directory, '/');
        if ($path === '' || str_contains($path, '://') || ! str_starts_with($path, $directory.'/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('public')->delete(array_filter([$path, $this->thumbnailPath($path)]));
    }

    private function assertSafeDirectory(string $directory): void
    {
        if ($directory === '' || str_contains($directory, '..') || str_contains($directory, '://') || str_starts_with($directory, '/')) {
            throw new RuntimeException('El directorio de imagen no es válido.');
        }
    }

    private function createImage(string $path, int $type): \GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $image instanceof \GdImage) {
            throw new RuntimeException('La imagen está dañada o su formato no está soportado.');
        }

        return $image;
    }

    private function orient(\GdImage $image, string $path, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        $angle = match ($orientation) {
            3 => 180, 6 => -90, 8 => 90, default => 0
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /** @return array{0: \GdImage, 1: int, 2: int} */
    private function resize(\GdImage $source, int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        if ($targetWidth === $width && $targetHeight === $height) {
            return [$source, $width, $height];
        }
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return [$target, $targetWidth, $targetHeight];
    }

    private function outputFormat(string $requested, int $sourceType): string
    {
        if ($requested === 'webp' && function_exists('imagewebp')) {
            return 'webp';
        }

        return $sourceType === IMAGETYPE_PNG ? 'png' : 'jpeg';
    }

    private function qualityCandidates(array $options): array
    {
        $fallback = (int) ($options['quality'] ?? 84);
        $candidates = $options['quality_candidates'] ?? [$fallback];
        $candidates = array_values(array_unique(array_map(
            fn (mixed $quality): int => max(0, min(100, (int) $quality)),
            is_array($candidates) && $candidates !== [] ? $candidates : [$fallback]
        )));

        return $candidates === [] ? [$fallback] : $candidates;
    }

    private function persist(\GdImage $image, string $path, string $format, int $quality): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'hogar-image-');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }
        try {
            $saved = match ($format) {
                'webp' => imagewebp($image, $temporary, max(0, min(100, $quality))),
                'png' => imagepng($image, $temporary, 6),
                default => imagejpeg($image, $temporary, max(0, min(100, $quality))),
            };
            $stream = fopen($temporary, 'rb');
            if (! $saved || $stream === false || ! Storage::disk('public')->put($path, $stream)) {
                throw new RuntimeException('No se pudo persistir la imagen.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($temporary);
        }
    }
}
