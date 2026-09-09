@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Reporte de lotes por estado</h1>@can('exportar reportes')<a class="btn secondary" href="{{ route('reportes.csv', ['reporte' => 'lotes-estado'] + request()->query()) }}">Exportar CSV</a>@endcan</div>

<form class="card form" method="GET">
    <div class="field"><label>Manzano</label><select name="manzano_id"><option value="">Todos</option>@foreach($manzanos as $manzano)<option value="{{ $manzano->id }}" @selected((int) request('manzano_id') === $manzano->id)>{{ $manzano->codigo }}</option>@endforeach</select></div>
    <div class="field"><label>Estado</label><select name="estado"><option value="">Todos</option>@foreach($estados as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst($estado) }}</option>@endforeach</select></div>
    <div class="field"><label>&nbsp;</label><button class="btn">Filtrar</button></div>
</form>

<div class="grid stats" style="margin-top:18px;">
    @foreach(['disponible', 'vendido', 'reservado', 'bloqueado'] as $estado)
        <div class="card"><div class="muted">{{ ucfirst($estado) }}</div><div class="stat-value">{{ $conteos[$estado] ?? 0 }}</div></div>
    @endforeach
</div>

<div class="card" style="margin-top:18px;" data-report="lotes-estado">
    <h2>Tabla de lotes</h2>
    <table class="table"><thead><tr><th>Manzano</th><th>Lote</th><th>Superficie</th><th>Precio</th><th>Cuota inicial</th><th>Estado</th></tr></thead><tbody>
        @forelse($lotes as $lote)
            <tr><td>{{ $lote->manzano->codigo }}</td><td>{{ $lote->codigo }}</td><td>{{ number_format($lote->superficie, 2) }}</td><td>{{ number_format($lote->precio, 2) }}</td><td>{{ $lote->cuotaInicialTexto() }}</td><td><span class="badge {{ $lote->estado }}">{{ $lote->estado }}</span></td></tr>
        @empty
            <tr><td colspan="6">No se encontraron lotes con los filtros aplicados.</td></tr>
        @endforelse
    </tbody></table>
</div>
{{ $lotes->links() }}
@endsection
