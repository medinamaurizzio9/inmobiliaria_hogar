@extends('layouts.app')
@section('content')
<div class="topbar">
    <h1 class="title">{{ $referencia->exists ? 'Editar punto GPS' : 'Nuevo punto GPS' }}</h1>
    <a class="btn secondary" href="{{ route('admin.urbanizacion-gps.index', ['urbanizacion_id' => old('urbanizacion_id', $referencia->urbanizacion_id)]) }}">Volver</a>
</div>

@if ($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif

@php
    $selectedUrbanizacionId = (int) old('urbanizacion_id', $referencia->urbanizacion_id);
    $oldPlanoX = old('plano_x', $referencia->plano_x);
    $oldPlanoY = old('plano_y', $referencia->plano_y);
@endphp

<form class="form card" method="POST" action="{{ $referencia->exists ? route('admin.urbanizacion-gps.update', $referencia) : route('admin.urbanizacion-gps.store') }}">
    @csrf
    @if($referencia->exists) @method('PUT') @endif

    <div class="field">
        <label>Urbanizacion</label>
        <select name="urbanizacion_id" id="gps-urbanizacion-select" required>
            <option value="">Seleccionar urbanizacion</option>
            @foreach($urbanizaciones as $urbanizacion)
                <option value="{{ $urbanizacion->id }}" data-plano="{{ $urbanizacion->imageUrl() ?? '' }}" @selected($selectedUrbanizacionId === $urbanizacion->id)>{{ $urbanizacion->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="field"><label>Nombre</label><input name="nombre" value="{{ old('nombre', $referencia->nombre) }}" placeholder="Ej. Rotonda de Ingreso" required></div>
    <div class="field">
        <label>Tipo de referencia</label>
        <select name="tipo_referencia" required>
            @foreach($tiposReferencia as $tipo)
                <option value="{{ $tipo }}" @selected(old('tipo_referencia', $referencia->tipo_referencia ?? 'otro') === $tipo)>{{ ucfirst($tipo) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Latitud</label><input name="latitud" type="number" step="0.00000001" min="-90" max="90" value="{{ old('latitud', $referencia->latitud) }}" required></div>
    <div class="field"><label>Longitud</label><input name="longitud" type="number" step="0.00000001" min="-180" max="180" value="{{ old('longitud', $referencia->longitud) }}" required></div>
    <div class="field"><label>Plano X (%)</label><input name="plano_x" id="plano_x" type="number" step="0.001" min="0" max="100" value="{{ $oldPlanoX }}"></div>
    <div class="field"><label>Plano Y (%)</label><input name="plano_y" id="plano_y" type="number" step="0.001" min="0" max="100" value="{{ $oldPlanoY }}"></div>
    <label class="check-row"><input type="checkbox" name="activo" value="1" @checked(old('activo', $referencia->activo ?? true))> Activo</label>
    <div class="field full"><label>Descripcion</label><textarea name="descripcion" placeholder="Detalle de referencia o indicaciones internas">{{ old('descripcion', $referencia->descripcion) }}</textarea></div>

    <div class="field full">
        <div class="topbar compact">
            <div>
                <label>Ubicacion visual en plano</label>
                <p class="muted">Use este selector solo como referencia visual. La georreferenciacion real se calibrara en una fase posterior.</p>
            </div>
            <button class="btn secondary" id="select-plan-position" type="button">Seleccionar posicion en plano</button>
        </div>
        <div class="gps-plan-picker" id="gps-plan-picker" data-initial-x="{{ $oldPlanoX }}" data-initial-y="{{ $oldPlanoY }}">
            <p class="empty-plan" id="gps-plan-empty">Seleccione una urbanizacion con plano cargado.</p>
            <div class="gps-plan-frame hidden" id="gps-plan-frame">
                <img id="gps-plan-image" src="" alt="Plano de urbanizacion">
                <button class="gps-temp-marker hidden" id="gps-temp-marker" type="button" aria-label="Posicion seleccionada"></button>
            </div>
        </div>
        <p class="muted" id="gps-plan-message">Presione "Seleccionar posicion en plano" y haga clic sobre la imagen.</p>
    </div>

    <div class="field full"><button class="btn" type="submit">Guardar</button></div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const urbanizacionSelect = document.getElementById('gps-urbanizacion-select');
    const picker = document.getElementById('gps-plan-picker');
    const frame = document.getElementById('gps-plan-frame');
    const empty = document.getElementById('gps-plan-empty');
    const image = document.getElementById('gps-plan-image');
    const marker = document.getElementById('gps-temp-marker');
    const xInput = document.getElementById('plano_x');
    const yInput = document.getElementById('plano_y');
    const selectButton = document.getElementById('select-plan-position');
    const message = document.getElementById('gps-plan-message');
    let selecting = false;

    function renderMarker() {
        const x = parseFloat(xInput.value);
        const y = parseFloat(yInput.value);
        if (Number.isNaN(x) || Number.isNaN(y)) {
            marker.classList.add('hidden');
            return;
        }

        marker.style.left = `${Math.max(0, Math.min(100, x))}%`;
        marker.style.top = `${Math.max(0, Math.min(100, y))}%`;
        marker.classList.remove('hidden');
    }

    function loadPlan() {
        const option = urbanizacionSelect.selectedOptions[0];
        const plano = option?.dataset.plano || '';

        if (!plano) {
            image.removeAttribute('src');
            frame.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }

        image.src = plano;
        empty.classList.add('hidden');
        frame.classList.remove('hidden');
        renderMarker();
    }

    selectButton.addEventListener('click', () => {
        selecting = !selecting;
        selectButton.classList.toggle('active', selecting);
        message.textContent = selecting
            ? 'Haga clic sobre la imagen para capturar X/Y del plano.'
            : 'Seleccion de posicion desactivada.';
    });

    frame.addEventListener('click', (event) => {
        if (!selecting || event.target === marker) return;

        const rect = image.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
            return;
        }

        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;

        xInput.value = x.toFixed(3);
        yInput.value = y.toFixed(3);
        renderMarker();
        message.textContent = `Posicion capturada: X ${xInput.value}%, Y ${yInput.value}%.`;
    });

    urbanizacionSelect.addEventListener('change', () => {
        xInput.value = '';
        yInput.value = '';
        loadPlan();
    });
    xInput.addEventListener('input', renderMarker);
    yInput.addEventListener('input', renderMarker);
    image.addEventListener('load', renderMarker);

    loadPlan();
});
</script>
@endsection
