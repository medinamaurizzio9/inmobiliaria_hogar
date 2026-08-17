<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Noticia extends Model
{
    protected $fillable = ['titulo', 'slug', 'resumen', 'contenido', 'imagen', 'publicada', 'destacada', 'orden', 'fecha_publicacion', 'autor_id'];

    protected function casts(): array
    {
        return ['publicada' => 'boolean', 'destacada' => 'boolean', 'orden' => 'integer', 'fecha_publicacion' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Noticia $noticia): void {
            if (! $noticia->slug || $noticia->isDirty('titulo')) {
                $base = Str::slug($noticia->titulo) ?: 'noticia';
                $slug = $base;
                $suffix = 2;
                while (static::where('slug', $slug)->when($noticia->exists, fn (Builder $query) => $query->whereKeyNot($noticia->id))->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $noticia->slug = $slug;
            }
        });
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('publicada', true)->whereNotNull('fecha_publicacion')->where('fecha_publicacion', '<=', now());
    }

    public function scopeOrdenPublico(Builder $query): Builder
    {
        return $query->orderByDesc('destacada')->orderByDesc('fecha_publicacion')->orderByDesc('id');
    }

    public function imageUrl(): ?string
    {
        return $this->imagen && Storage::disk('public')->exists($this->imagen)
            ? Storage::disk('public')->url($this->imagen)
            : null;
    }
}
