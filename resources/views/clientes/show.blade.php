@extends('layouts.app')
@section('content')
<div class="topbar">
    <h1 class="title">Cliente</h1>
    <div class="actions no-print">
        <button class="btn secondary" type="button" onclick="window.print()">Imprimir</button>
        @if($canSeeFinancial)<a class="btn secondary" href="{{ route('clientes.pdf', $cliente) }}">PDF ficha cliente</a>
        <a class="btn secondary" href="{{ route('clientes.estado-cuenta.pdf', $cliente) }}">PDF estado de cuenta</a>@endif
        <a class="btn secondary" href="{{ route('clientes.reservas.pdf', $cliente) }}">PDF reservas</a>
        <a class="btn secondary" href="{{ route('clientes.index') }}">Volver</a>
        @can('editar clientes')<a class="btn" href="{{ route('clientes.edit', $cliente) }}">Editar</a>@endcan
    </div>
</div>

@if($canSeeFinancial)<div class="grid stats" style="margin-top:18px;">
    <div class="card metric"><strong>Total vendido</strong><div class="stat-value">{{ number_format($cliente->ventas->where('estado', '!=', 'anulada')->sum('precio_final'), 2) }}</div></div>
    <div class="card metric"><strong>Total pagado en cuotas</strong><div class="stat-value">{{ number_format($cliente->ventas->flatMap->cuotas->sum('monto_pagado'), 2) }}</div></div>
    <div class="card metric"><strong>Saldo pendiente</strong><div class="stat-value">{{ number_format($cliente->ventas->flatMap->cuotas->sum('saldo_pendiente'), 2) }}</div></div>
</div>@endif

<div class="card">
    <h2>{{ $cliente->nombre }}</h2>
    <p><strong>Documento:</strong> {{ $cliente->documento }}</p>
    <p><strong>Telefono:</strong> @include('clientes.partials.whatsapp-link', ['cliente' => $cliente])</p>
    <p><strong>Email:</strong> {{ $cliente->email }}</p>
    <p><strong>Direccion:</strong> {{ $cliente->direccion }}</p>
    <p><strong>Urbanización de registro:</strong> {{ $cliente->urbanizacion?->nombre }}</p>
    <p><strong>Registrado por:</strong> {{ $cliente->createdBy?->name ?? 'Usuario no registrado' }}</p>
    <p><strong>Fecha de registro:</strong> {{ $cliente->created_at?->format('d/m/Y H:i') }}</p>
</div>

<section class="card commercial-control-card">
    <div class="card-heading"><div><span class="eyebrow">Control comercial</span><h2>Protección y responsabilidad</h2></div><span class="commercial-protection-status {{ $commercialProtection['active'] ? 'protected' : 'expired' }}">{{ $commercialProtection['label'] }}</span></div>
    <dl class="commercial-control-details">
        <div><dt>Registrado originalmente por</dt><dd>{{ $cliente->createdBy?->name ?? 'Usuario no registrado' }}</dd></div>
        <div><dt>Fecha de registro</dt><dd>{{ $cliente->created_at?->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Asesor responsable</dt><dd>{{ $cliente->responsibleAdvisor?->name ?? 'Sin asesor responsable' }}</dd></div>
        <div><dt>Inicio de protección</dt><dd>{{ $commercialProtection['protected_at']?->format('d/m/Y H:i') ?? 'Sin registro' }}</dd></div>
        <div><dt>Vencimiento</dt><dd>{{ $commercialProtection['expires_at']?->format('d/m/Y H:i') ?? 'Sin registro' }}</dd></div>
        <div><dt>Días restantes</dt><dd>{{ $commercialProtection['days_remaining'] }}</dd></div>
        <div><dt>Coincidencias/eventos detectados</dt><dd>{{ $cliente->commercial_events_count }}</dd></div>
        <div><dt>Operaciones/propiedades</dt><dd>{{ $commercialSummary['operations_count'] }}</dd></div>
    </dl>
</section>

<section class="card commercial-properties-card">
    <div class="card-heading"><div><span class="eyebrow">Patrimonio</span><h2>Propiedades / compras</h2></div></div>
    <div class="commercial-property-grid">
        @forelse($commercialSummary['properties'] as $property)
            <article><strong>{{ $property['urbanizacion'] }}</strong><span>Mz {{ $property['manzano'] }} · Lote {{ $property['lote'] }}</span><small>{{ ucfirst($property['modalidad'] ?? 'Sin modalidad') }} · {{ $property['tipo'] }} {{ $property['estado'] }}</small></article>
        @empty<p class="muted">Sin operaciones registradas.</p>@endforelse
    </div>
</section>

<div class="card" style="margin-top:18px;">
    <h2>Reservas</h2>
    <table class="table">
        <thead><tr><th>Lote</th><th>Estado</th><th>Fecha reserva</th></tr></thead>
        <tbody>
        @forelse($cliente->reservas as $reserva)
            <tr><td>{{ $reserva->lote->manzano->codigo }}-{{ $reserva->lote->codigo }}</td><td><span class="badge {{ $reserva->estado }}">{{ $reserva->estado }}</span></td><td>{{ $reserva->fecha_reserva?->format('d/m/Y') }}</td></tr>
        @empty
            <tr><td colspan="3">Sin reservas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:18px;">
    <h2>Ventas</h2>
    <table class="table">
        <thead><tr><th>Lote</th><th>Urbanización</th><th>Modalidad</th>@if($canSeeFinancial)<th>Precio</th>@endif<th>Estado</th></tr></thead>
        <tbody>
        @forelse($cliente->ventas as $venta)
            <tr><td>{{ $venta->lote->manzano->codigo }}-{{ $venta->lote->codigo }}</td><td>{{ $venta->lote->manzano->urbanizacion?->nombre }}</td><td>{{ ucfirst($venta->tipo_operacion) }}</td>@if($canSeeFinancial)<td>{{ number_format($venta->precio_final, 2) }}</td>@endif<td><span class="badge {{ $venta->estado }}">{{ $venta->estado }}</span></td></tr>
        @empty
            <tr><td colspan="{{ $canSeeFinancial ? 5 : 4 }}">Sin ventas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
