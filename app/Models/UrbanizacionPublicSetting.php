<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UrbanizacionPublicSetting extends Model
{
    protected $fillable = [
        'urbanizacion_id',
        'hero_image',
        'youtube_url',
        'titulo_descripcion',
        'descripcion_principal',
        'info_title',
    ];

    public function urbanizacion(): BelongsTo
    {
        return $this->belongsTo(Urbanizacion::class);
    }

    public function heroUrl(): ?string
    {
        $path = ltrim((string) $this->hero_image, '/');

        return $path !== '' && ! str_contains($path, '..') && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }
}
