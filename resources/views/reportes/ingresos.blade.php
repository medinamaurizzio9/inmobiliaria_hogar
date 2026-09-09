@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Reporte de ingresos</h1>@can('exportar reportes')<a class="btn secondary" href="{{ route('reportes.csv', ['reporte' => 'ingresos'] + request()->query()) }}">Exportar CSV</a>@endcan</div>

<form class="card form" method="GET">
    <div class="field"><label>Desde</label><input type="date" name="desde" value="{{ request('desde') }}"></div>
    <div class="field"><label>Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}"></div>
    <div class="field"><label>Metodo de pago</label><select name="metodo_pago"><option value="">Todos</option>@foreach($metodos as $metodo)<option value="{{ $metodo }}" @selected(request('metodo_pago') === $metodo)>{{ ucfirst($metodo) }}</option>@endforeach</select></div>
    <div class="field"><label>Concepto</label><select name="concepto"><option value="">Todos</option>@foreach($conceptos as $concepto)<option value="{{ $concepto }}" @selected(request('concepto') === $concepto)>{{ ucfirst($concepto) }}</option>@endforeach</select></div>
    <div class="field"><label>&nbsp;</label><button class="btn">Filtrar</button></div>
</form>

<div class="grid stats" style="margin-top:18px;">
    <div class="card"><div class="muted">Ingresos del dia</div><div class="stat-value">{{ number_format($ingresosDia, 2) }}</div></div>
    <div class="card"><div class="muted">Ingresos del rango</div><div class="stat-value">{{ number_format($ingresosRango, 2) }}</div></div>
    <div class="card"><div class="muted">Ingresos anulados</div><div class="stat-value">{{ number_format($ingresosAnulados, 2) }}</div></div>
    <div class="card"><div class="muted">Total neto</div><div class="stat-value">{{ number_format($totalNeto, 2) }}</div></div>
</div>

<div class="card" style="margin-top:18px;" data-report="ingresos">
    <h2>Tabla de ingresos</h2>
    <table class="table"><thead><tr><th>Fecha</th><th>Cliente</th><th>Concepto</th><th>Metodo de pago</th><th>Monto</th><th>Usuario/cajero</th><th>Estado</th></tr></thead><tbody>
        @forelse($movimientos as $movimiento)
            <tr><td>{{ $movimiento->fecha?->format('d/m/Y') }}</td><td>{{ $movimiento->cliente?->nombre ?? 'Sin cliente' }}</td><td>{{ $movimiento->concepto }}</td><td>{{ $movimiento->metodo_pago }}</td><td>{{ number_format($movimiento->monto, 2) }}</td><td>{{ $movimiento->user?->name ?? 'Sin usuario' }}</td><td><span class="badge {{ $movimiento->estado }}">{{ $movimiento->estado }}</span></td></tr>
        @empty
            <tr><td colspan="7">No se encontraron ingresos con los filtros aplicados.</td></tr>
        @endforelse
    </tbody></table>
</div>
{{ $movimientos->links() }}
@endsection
