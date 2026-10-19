@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">{{ $urbanizacion->exists ? 'Editar urbanizacion' : 'Nueva urbanizacion' }}</h1><div class="actions">@if($urbanizacion->exists)<a class="btn" href="{{ route('urbanizaciones.edit', $urbanizacion) }}">Datos generales</a><a class="btn secondary" href="{{ route('urbanizaciones.public-page.edit', $urbanizacion) }}">Página pública</a>@endif<a class="btn secondary" href="{{ route('urbanizaciones.index') }}">Volver</a></div></div>
@if ($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif
<form class="form card" method="POST" enctype="multipart/form-data" action="{{ $urbanizacion->exists ? route('urbanizaciones.update', $urbanizacion) : route('urbanizaciones.store') }}">
    @csrf @if($urbanizacion->exists) @method('PUT') @endif
    <div class="field"><label>Nombre</label><input name="nombre" value="{{ old('nombre', $urbanizacion->nombre) }}" required></div>
    <div class="field"><label>Propietario</label><input name="propietario" value="{{ old('propietario', $urbanizacion->propietario) }}"></div>
    <div class="field"><label>Ubicacion</label><input name="ubicacion" value="{{ old('ubicacion', $urbanizacion->ubicacion) }}"></div>
    <div class="field"><label>Superficie total</label><input name="superficie_total" type="number" step="0.01" value="{{ old('superficie_total', $urbanizacion->superficie_total ?? 0) }}"></div>
    <div class="field"><label>Estado</label><select name="estado">@foreach(['activa','pausada','cerrada'] as $estado)<option @selected(old('estado', $urbanizacion->estado ?? 'activa') === $estado)>{{ $estado }}</option>@endforeach</select></div>
    <label class="check-row"><input type="checkbox" name="mostrar_precio_publico" value="1" @checked(old('mostrar_precio_publico', $urbanizacion->mostrar_precio_publico ?? true))> Mostrar precio en disponibilidad publica</label>
    <div class="field full">
        <h2>Imagen comercial</h2>
        <p class="muted">Portada fotográfica de esta urbanización para las tarjetas de «Nuestros proyectos» y su página pública. JPG, PNG o WebP de hasta 8 MB.</p>
        <label>Fotografía o portada</label>
        <input type="file" name="commercial_image" accept="image/jpeg,image/png,image/webp">
    </div>
    @if($urbanizacion->publicSetting?->heroUrl())
        <div class="field full">
            <img class="public-hero-preview" src="{{ $urbanizacion->publicSetting->heroUrl() }}" alt="Portada comercial de {{ $urbanizacion->nombre }}">
            <label class="check-row"><input type="checkbox" name="remove_commercial_image" value="1"> Quitar portada comercial actual</label>
        </div>
    @endif
    <div class="field full">
        <h2>Plano técnico</h2>
        <label>Plano de la urbanizacion</label>
        <input type="file" name="plano_imagen" accept="application/pdf,image/jpeg,image/png,image/webp">
        <p class="muted">Se utiliza únicamente en el visor de lotes, manzanos y disponibilidad. Nunca se mostrará como portada comercial. Los raster mayores de 2 MB se optimizan sin cambiar sus dimensiones.</p>
    </div>
    @if($urbanizacion->imageUrl())
        <div class="field full">
            <label>Preview del plano cargado</label>
            <img class="plan-preview" src="{{ $urbanizacion->imageUrl() }}" alt="Plano de {{ $urbanizacion->nombre }}" decoding="async">
            @if($urbanizacion->plano_archivo_original)
                <p class="muted">PDF original guardado. El mapa usa la imagen convertida de alta resolución.</p>
            @endif
        </div>
    @endif
    <div class="field full"><label>Descripcion</label><textarea name="descripcion">{{ old('descripcion', $urbanizacion->descripcion) }}</textarea></div>
    <div class="field full"><button class="btn">Guardar</button></div>
</form>
@endsection
