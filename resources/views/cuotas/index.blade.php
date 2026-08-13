@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Cuotas</h1><div class="actions">@can('exportar reportes')<a class="btn secondary" href="{{ route('export.csv', 'cuotas') }}">Exportar CSV</a>@endcan<a class="btn secondary" href="{{ route('cuotas.index', ['estado' => 'vencidas']) }}">Ver vencidas</a></div></div>
@if (session('status')) <div class="status">{{ session('status') }}</div> @endif
<div class="crm-kpi-grid compact"><x-crm.kpi-card label="Pendientes" :value="$summary['pendientes']" icon="fa-clock"/><x-crm.kpi-card label="Vencidas" :value="$summary['vencidas']" icon="fa-triangle-exclamation" tone="warning"/><x-crm.kpi-card label="Pagadas este mes" :value="$summary['pagadas_mes']" icon="fa-circle-check"/><x-crm.kpi-card label="Monto pendiente" :value="'Bs '.number_format($summary['saldo'],2,',','.')" icon="fa-wallet"/></div>
<form class="card filter-form crm-filter-bar" method="GET"><div class="field wide"><label>Buscar</label><input name="q" value="{{ $filters['q']??'' }}" placeholder="Cliente, CI, manzano o lote"></div><div class="field"><label>Estado</label><select name="estado"><option value="">Todas</option>@foreach(['pendiente','parcial','vencida','pagada'] as $estado)<option value="{{ $estado }}" @selected(($filters['estado']??'')===$estado)>{{ ucfirst($estado) }}</option>@endforeach</select></div><div class="field"><label>Desde</label><input type="date" name="fecha_desde" value="{{ $filters['fecha_desde']??'' }}"></div><div class="field"><label>Hasta</label><input type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta']??'' }}"></div><div class="filter-actions"><button class="btn">Buscar</button><a class="btn secondary" href="{{ route('cuotas.index') }}">Limpiar</a></div></form>
<table class="table"><thead><tr><th>Cliente</th><th>Lote</th><th>#</th><th>Fecha programada</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse ($cuotas as $cuota)
<tr>
<form method="POST" action="{{ route('cuotas.update', $cuota) }}">@csrf @method('PUT')
<td>{{ $cuota->venta->cliente->nombre }}</td><td>{{ $cuota->venta->lote->manzano->codigo }}-{{ $cuota->venta->lote->codigo }}</td><td>{{ $cuota->numero }}</td><td>{{ optional($cuota->fecha_programada ?? $cuota->fecha_vencimiento)->format('d/m/Y') }}</td><td>{{ number_format($cuota->monto, 2) }}</td>
<td>{{ number_format($cuota->monto_pagado, 2) }}</td><td>{{ number_format($cuota->saldo_pendiente, 2) }}</td><td><span class="badge {{ $cuota->estado }}">{{ $cuota->estado }}</span></td>
<td class="actions"><input name="monto_pagado" type="number" step="0.01" min="0.01" max="{{ $cuota->venta->cuotas->sum('saldo_pendiente') }}" placeholder="Monto"><select name="metodo_pago">@foreach(['efectivo','transferencia','QR','banco','otro'] as $metodo)<option>{{ $metodo }}</option>@endforeach</select><input name="referencia" placeholder="Referencia"><button class="btn secondary" name="tipo_aplicacion" value="cuotas">Cobrar próximas</button><button class="btn secondary" name="tipo_aplicacion" value="amortizacion">Amortizar plazo</button></td>
</form>
</tr>
@empty<tr><td colspan="9"><x-crm.empty-state title="No encontramos cuotas con estos filtros" icon="fa-magnifying-glass" /></td></tr>@endforelse
</tbody></table>
@if ($cuotas->hasPages())
<div class="pagination-wrapper">
    {{ $cuotas->appends(request()->query())->links() }}
</div>
@endif
@endsection
