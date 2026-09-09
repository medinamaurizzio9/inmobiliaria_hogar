@extends('layouts.app')
@section('content')
<div class="topbar">
    <div>
        <h1 class="title">Seleccionar urbanizacion</h1>
        <p class="muted">Elige el proyecto sobre el que vas a trabajar.</p>
    </div>
</div>
@if (session('status')) <div class="status">{{ session('status') }}</div> @endif
@if ($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif

<div class="project-grid grid-urbanizaciones">
    @forelse($urbanizaciones as $urbanizacion)
        <article class="project-card">
            @if($urbanizacion->imageUrl(true))
                <img src="{{ $urbanizacion->imageUrl(true) }}" alt="Plano {{ $urbanizacion->nombre }}" loading="lazy" decoding="async">
            @else
                <div class="project-placeholder">Sin plano cargado</div>
            @endif
            <div class="project-body">
                <h2>{{ $urbanizacion->nombre }}</h2>
                <p class="muted">{{ $urbanizacion->ubicacion ?? 'Ubicacion pendiente' }}</p>
                @if($urbanizacion->propietario)
                    <p class="project-owner">Propietario: <strong>{{ $urbanizacion->propietario }}</strong></p>
                @endif
                <div class="project-stats">
                    <span>Total terrenos: <strong>{{ $urbanizacion->total_lotes }}</strong></span>
                    <span>Disponibles: <strong>{{ $urbanizacion->disponibles_count }}</strong></span>
                    <span>Vendidos: <strong>{{ $urbanizacion->vendidos_count }}</strong></span>
                    <span>Reservados: <strong>{{ $urbanizacion->reservados_count }}</strong></span>
                    <span>Bloqueados: <strong>{{ $urbanizacion->bloqueados_count }}</strong></span>
                </div>
                <form method="POST" action="{{ route('urbanizaciones.select.store') }}">
                    @csrf
                    <input type="hidden" name="urbanizacion_id" value="{{ $urbanizacion->id }}">
                    <button class="btn">Ingresar a urbanizacion</button>
                </form>
            </div>
        </article>
    @empty
        <div class="card">No hay urbanizaciones activas disponibles para tu usuario.</div>
    @endforelse
</div>
@endsection
