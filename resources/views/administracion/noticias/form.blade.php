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
<label class="check-row"><input type="checkbox" name="publicada" value="1" @checked(old('publicada', $noticia->publicada))> Publicada</label>
<div class="field full"><button class="btn" type="submit">Guardar noticia</button></div></form>
@endsection
