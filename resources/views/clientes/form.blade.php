@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">{{ $cliente->exists ? 'Editar cliente' : 'Nuevo cliente' }}</h1><a class="btn secondary" href="{{ route('clientes.index') }}">Volver</a></div>
@if ($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif
@if (session('duplicate_cliente_data'))
    @php
        $duplicate = session('duplicate_cliente_data');
        $duplicateLoteId = session('duplicate_lote_id') ?: old('lote_id', $loteId ?? null);
        $useUrl = $duplicateLoteId
            ? route('reservas.create', ['cliente_id' => $duplicate['id'], 'lote_id' => $duplicateLoteId])
            : route('clientes.show', $duplicate['id']);
    @endphp
    <div class="commercial-match-card exact" role="alert">
        <span class="eyebrow">Coincidencia exacta de CI</span>
        <h2>Cliente ya registrado.</h2>
        <p>{{ session('duplicate_cliente_message') }}</p>
        <p><strong>Nombre:</strong> {{ $duplicate['nombre'] }}</p>
        <p><strong>CI:</strong> {{ $duplicate['documento'] }}</p>
        <p><strong>Registrado originalmente por:</strong> {{ $duplicate['registered_by'] }}</p>
        <p><strong>Asesor responsable:</strong> {{ $duplicate['responsible_advisor'] }}</p>
        <p><strong>Fecha:</strong> {{ $duplicate['registered_at'] }} (hace {{ $duplicate['registered_days_ago'] }} días)</p>
        <p><strong>Protección comercial:</strong> {{ $duplicate['protection']['label'] }}@if($duplicate['protection']['active']) · {{ $duplicate['protection']['days_remaining'] }} días restantes @endif</p>
        @if(count($duplicate['properties']))
            <strong>Propiedades / operaciones existentes:</strong>
            <ul>@foreach($duplicate['properties'] as $property)<li>{{ $property['urbanizacion'] }} / Mz {{ $property['manzano'] }} / Lote {{ $property['lote'] }} · {{ $property['tipo'] }} {{ $property['estado'] }}</li>@endforeach</ul>
        @endif
        <div class="actions">
            <a class="btn" href="{{ $useUrl }}">Usar cliente existente</a>
            <a class="btn secondary" href="{{ route('clientes.show', $duplicate['id']) }}">Ver cliente</a>
        </div>
    </div>
@endif
@if(session('potential_client_matches'))
    <div class="commercial-match-card potential" role="alert">
        <span class="eyebrow">Posible coincidencia</span>
        <h2>Verifique antes de continuar</h2>
        <p>El teléfono, WhatsApp o correo coincide con otra persona. Esto no confirma que sea el mismo cliente y no se realizará ninguna fusión automática.</p>
        @foreach(session('potential_client_matches') as $match)
            <article><strong>{{ $match['nombre'] }}</strong><span>{{ implode(' / ', array_map(fn ($type) => $type === 'phone' ? 'Teléfono coincidente' : 'Correo coincidente', $match['match_types'])) }}</span><small>Asesor: {{ $match['responsible_advisor'] }}</small></article>
        @endforeach
        <label class="check-row"><input type="checkbox" name="confirm_potential_match" value="1" form="client-form" required> Verifiqué la coincidencia y confirmo que se trata de una persona diferente.</label>
    </div>
@endif
<form id="client-form" class="form card" method="POST" action="{{ $cliente->exists ? route('clientes.update', $cliente) : route('clientes.store') }}" @if(! $cliente->exists) data-client-match-form data-match-url="{{ route('clientes.matches') }}" @endif>
@csrf @if($cliente->exists) @method('PUT') @endif
@if(! $cliente->exists && old('lote_id', $loteId ?? null))
    <input type="hidden" name="lote_id" value="{{ old('lote_id', $loteId ?? null) }}">
@endif
<div class="field"><label>Nombre</label><input name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required></div>
<div class="field"><label for="documento">CI / Documento</label><input id="documento" name="documento" value="{{ old('documento', $cliente->documento) }}" data-match-field></div>
<div class="field"><label for="telefono">Teléfono / WhatsApp</label><input id="telefono" name="telefono" value="{{ old('telefono', $cliente->telefono) }}" data-match-field></div>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $cliente->email) }}" data-match-field></div>
@if(old('confirm_potential_match'))<input type="hidden" name="confirm_potential_match" value="1">@endif
<div class="field full client-match-preview" data-match-preview hidden aria-live="polite"></div>
<div class="field full"><label>Direccion</label><textarea name="direccion">{{ old('direccion', $cliente->direccion) }}</textarea></div>
<div class="field full"><button class="btn">Guardar</button></div>
</form>
@if(! $cliente->exists)<script src="{{ asset('js/client-commercial-matching.js') }}" defer></script>@endif
@endsection
