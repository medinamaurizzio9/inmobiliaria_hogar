@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Cuotas</h1><div class="actions">@can('exportar reportes')<a class="btn secondary" href="{{ route('export.csv', 'cuotas') }}">Exportar CSV</a>@endcan<a class="btn secondary" href="{{ route('cuotas.index', ['estado' => 'vencidas']) }}">Ver vencidas</a></div></div>
@if (session('status')) <div class="status">{{ session('status') }}</div> @endif
<table class="table"><thead><tr><th>Cliente</th><th>Lote</th><th>#</th><th>Fecha programada</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Estado</th><th></th></tr></thead><tbody>
@foreach ($cuotas as $cuota)
<tr>
<form method="POST" action="{{ route('cuotas.update', $cuota) }}">@csrf @method('PUT')
<td>{{ $cuota->venta->cliente->nombre }}</td><td>{{ $cuota->venta->lote->manzano->codigo }}-{{ $cuota->venta->lote->codigo }}</td><td>{{ $cuota->numero }}</td><td>{{ optional($cuota->fecha_programada ?? $cuota->fecha_vencimiento)->format('d/m/Y') }}</td><td>{{ number_format($cuota->monto, 2) }}</td>
<td>{{ number_format($cuota->monto_pagado, 2) }}</td><td>{{ number_format($cuota->saldo_pendiente, 2) }}</td><td><span class="badge {{ $cuota->estado }}">{{ $cuota->estado }}</span></td>
<td class="actions"><input name="monto_pagado" type="number" step="0.01" min="0.01" max="{{ $cuota->venta->cuotas->sum('saldo_pendiente') }}" placeholder="Monto"><select name="metodo_pago">@foreach(['efectivo','transferencia','QR','banco','otro'] as $metodo)<option>{{ $metodo }}</option>@endforeach</select><input name="referencia" placeholder="Referencia"><button class="btn secondary" name="tipo_aplicacion" value="cuotas">Cobrar próximas</button><button class="btn secondary" name="tipo_aplicacion" value="amortizacion">Amortizar plazo</button></td>
</form>
</tr>
@endforeach
</tbody></table>
@if ($cuotas->hasPages())
<div class="pagination-wrapper">
    {{ $cuotas->appends(request()->query())->links() }}
</div>
@endif
@endsection
