@extends('layouts.app')
@section('title', $noticia->exists ? 'Editar noticia' : 'Nueva noticia')
@section('content')
<x-crm.page-header :title="$noticia->exists ? 'Editar noticia' : 'Nueva noticia'"><a class="btn secondary" href="{{ route('admin.noticias.index') }}">Volver</a></x-crm.page-header>
<form class="card form" method="POST" enctype="multipart/form-data" action="{{ $noticia->exists ? route('admin.noticias.update', $noticia) : route('admin.noticias.store') }}">@csrf @if($noticia->exists) @method('PUT') @endif
<div class="field full"><label>Título</label><input name="titulo" value="{{ old('titulo', $noticia->titulo) }}" required></div>
<div class="field full"><label>Resumen</label><textarea name="resumen" required>{{ old('resumen', $noticia->resumen) }}</textarea></div>
<div class="field full"><label>Contenido</label><textarea name="contenido" rows="12" required>{{ old('contenido', $noticia->contenido) }}</textarea></div>
<div class="field"><label>Imagen</label><input type="file" name="imagen" accept="image/jpeg,image/png,image/webp"></div>
<div class="field"><label>Fecha de publicación</label><input type="datetime-local" name="fecha_publicacion" value="{{ old('fecha_publicacion', $noticia->fecha_publicacion?->format('Y-m-d\TH:i')) }}"></div>
<div class="field"><label>Estado</label><select name="estado" required><option value="borrador" @selected(old('estado', $noticia->publicada ? 'publicada' : 'borrador') === 'borrador')>Borrador</option><option value="publicada" @selected(old('estado', $noticia->publicada ? 'publicada' : 'borrador') === 'publicada')>Publicada</option></select></div>
<div class="field"><label>Orden opcional</label><input type="number" name="orden" min="0" max="9999" value="{{ old('orden', $noticia->orden) }}"></div>
<label class="check-row"><input type="checkbox" name="destacada" value="1" @checked(old('destacada', $noticia->destacada))> Destacada</label>
@if($noticia->imageUrl())<div class="field full"><img class="news-form-preview" src="{{ $noticia->imageUrl() }}" alt="Imagen actual de {{ $noticia->titulo }}"><small>La imagen actual se conservará si no cargas otra.</small></div>@endif
<div class="field full"><button class="btn" type="submit">Guardar noticia</button></div></form>
@endsection
