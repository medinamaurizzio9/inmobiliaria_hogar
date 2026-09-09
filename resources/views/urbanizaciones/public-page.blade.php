@extends('layouts.app')
@section('content')
<div class="topbar">
    <div><h1 class="title">Página pública</h1><p class="muted">{{ $urbanizacion->nombre }} · el nombre y la ubicación se toman siempre de la urbanización.</p></div>
    <div class="actions"><a class="btn secondary" href="{{ route('urbanizaciones.edit', $urbanizacion) }}">Datos generales</a><a class="btn">Página pública</a><a class="btn secondary" href="{{ route('disponibilidad.urbanizacion', $urbanizacion->slug) }}" target="_blank" rel="noopener">Ver página</a></div>
</div>
@if ($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif
@if (session('status')) <div class="alert success">{{ session('status') }}</div> @endif
<form class="form card public-page-admin" method="POST" enctype="multipart/form-data" action="{{ route('urbanizaciones.public-page.update', $urbanizacion) }}">
    @csrf @method('PUT')
    <div class="field full"><h2>Hero</h2><p class="muted">Imagen JPG, PNG o WebP de hasta 8 MB. Se optimiza a un máximo de 1920 × 1080.</p></div>
    <div class="field full"><label>Imagen de fondo</label><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"></div>
    @if($urbanizacion->publicSetting?->heroUrl())
        <div class="field full"><img class="public-hero-preview" src="{{ $urbanizacion->publicSetting->heroUrl() }}" alt="Hero actual de {{ $urbanizacion->nombre }}"><label class="check-row"><input type="checkbox" name="remove_hero" value="1"> Eliminar imagen actual</label></div>
    @endif
    <div class="field"><label>Video de YouTube</label><input type="url" name="youtube_url" value="{{ old('youtube_url', $urbanizacion->publicSetting?->youtube_url) }}" placeholder="https://www.youtube.com/watch?v=..."><small class="muted">Solo enlaces youtube.com o youtu.be.</small></div>
    <div class="field"><label>Título de descripción</label><input name="titulo_descripcion" maxlength="255" value="{{ old('titulo_descripcion', $urbanizacion->publicSetting?->titulo_descripcion) }}" placeholder="Tu próximo terreno puede estar aquí"></div>
    <div class="field full"><label>Descripción principal</label><textarea name="descripcion_principal" maxlength="5000" rows="6" placeholder="Describe la ubicación, propuesta y ventajas principales del proyecto.">{{ old('descripcion_principal', $urbanizacion->publicSetting?->descripcion_principal) }}</textarea><small class="muted">Texto plano; no se admite HTML.</small></div>
    @php($youtubePreview = \App\Support\YouTubeEmbed::url(old('youtube_url', $urbanizacion->publicSetting?->youtube_url)))
    @if($youtubePreview)<div class="field full"><label>Preview del video</label><div class="project-video admin-video-preview"><iframe src="{{ $youtubePreview }}" title="Preview de video" loading="lazy" allowfullscreen></iframe></div></div>@endif
    <div class="field"><label>Título de características</label><input name="info_title" maxlength="255" value="{{ old('info_title', $urbanizacion->publicSetting?->info_title) }}" placeholder="Características de la urbanización"></div>
    <div class="field full"><div class="section-heading-inline"><div><h2>Bloques informativos</h2><p class="muted">Agregue entre 1 y 10; puede ordenar y desactivar cada bloque.</p></div><button class="btn secondary" type="button" data-add-feature>Agregar bloque</button></div></div>
    <div class="field full public-feature-editor" data-feature-editor>
        @php($featureRows = old('features', $urbanizacion->publicFeatures->map(fn($feature) => ['titulo' => $feature->titulo, 'descripcion' => $feature->descripcion, 'orden' => $feature->orden, 'activo' => $feature->activo ? '1' : null])->all()))
        @foreach($featureRows as $index => $feature)
            <article class="public-feature-row" data-feature-row>
                <div class="field"><label>Título</label><input name="features[{{ $index }}][titulo]" maxlength="255" value="{{ $feature['titulo'] ?? '' }}"></div>
                <div class="field"><label>Orden</label><input type="number" min="0" max="255" name="features[{{ $index }}][orden]" value="{{ $feature['orden'] ?? $index }}"></div>
                <div class="field full"><label>Descripción</label><textarea maxlength="2000" name="features[{{ $index }}][descripcion]">{{ $feature['descripcion'] ?? '' }}</textarea></div>
                <label class="check-row"><input type="checkbox" name="features[{{ $index }}][activo]" value="1" @checked(!empty($feature['activo']))> Activo</label>
                <button class="btn danger" type="button" data-remove-feature>Eliminar</button>
            </article>
        @endforeach
    </div>
    <div class="field full"><button class="btn" type="submit">Guardar página pública</button></div>
</form>
<template id="public-feature-template"><article class="public-feature-row" data-feature-row><div class="field"><label>Título</label><input data-name="titulo" maxlength="255"></div><div class="field"><label>Orden</label><input data-name="orden" type="number" min="0" max="255"></div><div class="field full"><label>Descripción</label><textarea data-name="descripcion" maxlength="2000"></textarea></div><label class="check-row"><input data-name="activo" type="checkbox" value="1" checked> Activo</label><button class="btn danger" type="button" data-remove-feature>Eliminar</button></article></template>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-feature-editor]');
    const template = document.getElementById('public-feature-template');
    const reindex = () => editor.querySelectorAll('[data-feature-row]').forEach((row, index) => row.querySelectorAll('[data-name]').forEach(field => { field.name = `features[${index}][${field.dataset.name}]`; if (field.dataset.name === 'orden' && field.value === '') field.value = index; }));
    document.querySelector('[data-add-feature]')?.addEventListener('click', () => { if (editor.children.length >= 10) return; editor.append(template.content.cloneNode(true)); reindex(); });
    editor.addEventListener('click', event => { const button = event.target.closest('[data-remove-feature]'); if (!button) return; button.closest('[data-feature-row]').remove(); reindex(); });
});
</script>
@endsection
