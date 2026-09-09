@extends('layouts.app')
@section('content')
<div class="topbar">
    <h1 class="title">Reporte de reservas</h1>
    <div class="actions">
        @can('exportar reportes')<a class="btn secondary" href="{{ route('reportes.csv', ['reporte' => 'reservas'] + request()->query()) }}">Exportar CSV</a>@endcan
        @can('exportar reporte reservas')<a class="btn secondary" href="{{ route('reportes.reservas.excel', request()->query()) }}">Exportar Excel</a>@endcan
        @can('exportar reporte reservas')<a class="btn secondary" href="{{ route('reportes.reservas.pdf', request()->query()) }}">Exportar PDF</a>@endcan
    </div>
</div>

<form class="card form" method="GET">
    <div class="field"><label>Cliente/interesado</label><input name="cliente" value="{{ request('cliente') }}" placeholder="Nombre del cliente"></div>
    <div class="field"><label>Carnet/documento</label><input name="documento" value="{{ request('documento') }}" placeholder="Documento"></div>
    <div class="field"><label>Lote</label><input name="lote" value="{{ request('lote') }}" placeholder="Lote"></div>
    <div class="field"><label>Manzano</label><input name="manzano" value="{{ request('manzano') }}" placeholder="Manzano"></div>
    <div class="field"><label>Estado</label><select name="estado"><option value="">Todos</option>@foreach(['activa','vencida','cancelada','convertida'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst($estado) }}</option>@endforeach</select></div>
    <div class="field"><label>Tipo operacion</label><select name="tipo_operacion"><option value="">Todos</option>@foreach($tiposOperacion as $tipo)<option value="{{ $tipo }}" @selected(request('tipo_operacion') === $tipo)>{{ ucfirst($tipo) }}</option>@endforeach</select></div>
    @if(auth()->user()->hasAnyRole(['administrador', 'gerente']))
        <div class="field"><label>Supervisor</label><select name="supervisor_id"><option value="">Todos</option>@foreach($supervisores as $supervisor)<option value="{{ $supervisor->id }}" @selected((int) request('supervisor_id') === $supervisor->id)>{{ $supervisor->name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label>Grupo comercial</label><select name="grupo_comercial_id"><option value="">Todos</option>@foreach($grupos as $grupo)<option value="{{ $grupo->id }}" @selected((int) request('grupo_comercial_id') === $grupo->id)>{{ $grupo->nombre }}</option>@endforeach</select></div>
    @if(auth()->user()->hasAnyRole(['administrador', 'gerente', 'supervisor']))
        <div class="field"><label>Asesor/vendedor</label><select name="usuario_id"><option value="">Todos</option>@foreach($vendedores as $vendedor)<option value="{{ $vendedor->id }}" @selected((int) request('usuario_id') === $vendedor->id)>{{ $vendedor->name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label>Desde</label><input type="date" name="desde" value="{{ request('desde') }}"></div>
    <div class="field"><label>Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}"></div>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Filtrar</button></div>
</form>

<div class="grid stats" style="margin-top:18px;">
    <div class="card"><div class="muted">Total reservas</div><div class="stat-value">{{ $metricas['total'] }}</div></div>
    <div class="card"><div class="muted">Activas</div><div class="stat-value">{{ $metricas['activas'] }}</div></div>
    <div class="card"><div class="muted">Vencidas</div><div class="stat-value">{{ $metricas['vencidas'] }}</div></div>
    <div class="card"><div class="muted">Canceladas</div><div class="stat-value">{{ $metricas['canceladas'] }}</div></div>
    <div class="card"><div class="muted">Convertidas</div><div class="stat-value">{{ $metricas['convertidas'] }}</div></div>
    @foreach($metricas['porTipo'] as $tipo => $total)
        <div class="card"><div class="muted">{{ ucfirst($tipo) }}</div><div class="stat-value">{{ $total }}</div></div>
    @endforeach
</div>

<div class="card" style="margin-top:18px;" data-report="reservas">
    <h2>Tabla de reservas</h2>
    <table class="table">
        <thead><tr><th>Fecha</th><th>Cliente</th><th>Documento</th><th>Manzano</th><th>Lote</th><th>Tipo operacion</th><th>Estado</th><th>Asesor</th><th>Fecha vencimiento</th></tr></thead>
        <tbody>
        @forelse($reservas as $reserva)
            <tr>
                <td>{{ $reserva->fecha_reserva?->format('d/m/Y') }}</td>
                <td>{{ $reserva->cliente->nombre }}</td>
                <td>{{ $reserva->cliente->documento }}</td>
                <td>{{ $reserva->lote->manzano->codigo }}</td>
                <td>{{ $reserva->lote->codigo }}</td>
                <td>{{ ucfirst($reserva->tipo_operacion) }}</td>
                <td><span class="badge {{ $reserva->estado }}">{{ $reserva->estado }}</span></td>
                <td>{{ $reserva->usuario?->name ?? 'Sin asignar' }}</td>
                <td>{{ $reserva->fecha_vencimiento?->format('d/m/Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No se encontraron reservas con los filtros aplicados.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $reservas->links() }}
@endsection
