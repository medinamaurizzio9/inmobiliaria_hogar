<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManagedImageService
{
    public function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');
        $this->delete($oldPath, $directory);

        return $path;
    }

    public function delete(?string $path, string $directory): void
    {
        $path = ltrim((string) $path, '/');
        if ($path === '' || str_contains($path, '://') || ! str_starts_with($path, trim($directory, '/').'/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
