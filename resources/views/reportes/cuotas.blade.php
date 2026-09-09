@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Reporte de cuotas pendientes/vencidas</h1>@can('exportar reportes')<a class="btn secondary" href="{{ route('reportes.csv', ['reporte' => 'cuotas'] + request()->query()) }}">Exportar CSV</a>@endcan</div>

<form class="card form" method="GET">
    <div class="field"><label>Desde</label><input type="date" name="desde" value="{{ request('desde') }}"></div>
    <div class="field"><label>Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}"></div>
    <div class="field"><label>Estado</label><select name="estado"><option value="">Todos</option>@foreach(['pendiente','parcial','pagada','vencida'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst($estado) }}</option>@endforeach</select></div>
    <div class="field"><label>Cliente</label><select name="cliente_id"><option value="">Todos</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" @selected((int) request('cliente_id') === $cliente->id)>{{ $cliente->nombre }}</option>@endforeach</select></div>
    <div class="field"><label>&nbsp;</label><button class="btn">Filtrar</button></div>
</form>

<div class="grid stats" style="margin-top:18px;">
    <div class="card"><div class="muted">Pendientes</div><div class="stat-value">{{ $pendientes }}</div></div>
    <div class="card"><div class="muted">Vencidas</div><div class="stat-value">{{ $vencidas }}</div></div>
    <div class="card"><div class="muted">Parciales</div><div class="stat-value">{{ $parciales }}</div></div>
    <div class="card"><div class="muted">Pagadas</div><div class="stat-value">{{ $pagadas }}</div></div>
    <div class="card"><div class="muted">Saldo pendiente total</div><div class="stat-value">{{ number_format($saldoPendiente, 2) }}</div></div>
</div>

<div class="card" style="margin-top:18px;" data-report="cuotas">
    <h2>Tabla de cuotas</h2>
    <table class="table"><thead><tr><th>Cliente</th><th>Venta</th><th>Lote</th><th>Numero cuota</th><th>Fecha vencimiento</th><th>Monto</th><th>Monto pagado</th><th>Saldo</th><th>Estado</th></tr></thead><tbody>
        @forelse($cuotas as $cuota)
            <tr><td>{{ $cuota->venta->cliente->nombre }}</td><td>#{{ $cuota->venta_id }}</td><td>{{ $cuota->venta->lote->manzano->codigo }}-{{ $cuota->venta->lote->codigo }}</td><td>{{ $cuota->numero }}</td><td>{{ $cuota->fecha_vencimiento?->format('d/m/Y') }}</td><td>{{ number_format($cuota->monto, 2) }}</td><td>{{ number_format($cuota->monto_pagado, 2) }}</td><td>{{ number_format($cuota->saldo_pendiente, 2) }}</td><td><span class="badge {{ $cuota->estado }}">{{ $cuota->estado }}</span></td></tr>
        @empty
            <tr><td colspan="9">No se encontraron cuotas con los filtros aplicados.</td></tr>
        @endforelse
    </tbody></table>
</div>
{{ $cuotas->links() }}
@endsection
