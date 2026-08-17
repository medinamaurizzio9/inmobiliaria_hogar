@extends('layouts.app')
@section('content')
<div class="topbar">
    <h1 class="title">Reporte mejor vendedor por mes</h1>
    <div class="actions">
        @can('exportar reporte mejor vendedor')<a class="btn secondary" href="{{ route('reportes.mejor-vendedor.excel', request()->query()) }}">Exportar Excel</a>@endcan
        @can('exportar reporte mejor vendedor')<a class="btn secondary" href="{{ route('reportes.mejor-vendedor.pdf', request()->query()) }}">Exportar PDF</a>@endcan
    </div>
</div>

<form class="card form" method="GET">
    <div class="field"><label>Ambito</label><select name="ambito"><option value="urbanizacion" @selected($ambito === 'urbanizacion')>Urbanizacion actual</option><option value="global" @selected($ambito === 'global')>Global</option></select></div>
    <div class="field"><label>Mes</label><input type="number" name="mes" min="1" max="12" value="{{ $mes }}"></div>
    <div class="field"><label>Anio</label><input type="number" name="anio" min="2020" max="2100" value="{{ $anio }}"></div>
    @if(auth()->user()->hasAnyRole(['administrador', 'gerente']))
        <div class="field"><label>Supervisor</label><select name="supervisor_id"><option value="">Todos</option>@foreach($supervisores as $supervisor)<option value="{{ $supervisor->id }}" @selected((int) request('supervisor_id') === $supervisor->id)>{{ $supervisor->name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label>Grupo comercial</label><select name="grupo_comercial_id"><option value="">Todos</option>@foreach($grupos as $grupo)<option value="{{ $grupo->id }}" @selected((int) request('grupo_comercial_id') === $grupo->id)>{{ $grupo->nombre }}</option>@endforeach</select></div>
    <div class="field"><label>Asesor</label><select name="usuario_id"><option value="">Todos</option>@foreach($vendedores as $vendedor)<option value="{{ $vendedor->id }}" @selected((int) request('usuario_id') === $vendedor->id)>{{ $vendedor->name }}</option>@endforeach</select></div>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Filtrar</button></div>
</form>

<div class="card" style="margin-top:18px;" data-report="mejor-vendedor">
    <h2>Ranking comercial</h2>
    <p class="muted">Ambito: <strong>{{ $ambitoLabel }}</strong></p>
    <table class="table">
        <thead><tr><th>Ranking</th><th>Asesor</th><th>Supervisor</th><th>Reservas</th><th>Activas</th><th>Canceladas</th><th>Vencidas</th><th>Convertidas</th><th>Ventas cerradas</th><th>Monto vendido</th><th>Conversion %</th></tr></thead>
        <tbody>
        @forelse($ranking as $row)
            <tr>
                <td>{{ $row['ranking'] }}</td>
                <td>{{ $row['asesor'] }}</td>
                <td>{{ $row['supervisor'] }}</td>
                <td>{{ $row['reservas'] }}</td>
                <td>{{ $row['activas'] }}</td>
                <td>{{ $row['canceladas'] }}</td>
                <td>{{ $row['vencidas'] }}</td>
                <td>{{ $row['convertidas'] }}</td>
                <td>{{ $row['ventas_cerradas'] }}</td>
                <td>{{ number_format($row['monto_vendido'], 2) }}</td>
                <td>{{ number_format($row['conversion'], 2) }}%</td>
            </tr>
        @empty
            <tr><td colspan="11">No hay actividad comercial para el periodo seleccionado.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
