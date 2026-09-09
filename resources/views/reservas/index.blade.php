@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Reservas</h1><div class="actions">@can('exportar reportes')<a class="btn secondary" href="{{ route('export.csv', 'reservas') }}">Exportar CSV</a>@endcan @can('crear reservas')<a class="btn" href="{{ route('reservas.create') }}">Nueva</a>@endcan</div></div>
@if (session('status')) <div class="status">{{ session('status') }}</div> @endif
<form class="card form" method="GET" style="margin-bottom:18px;">
<div class="field"><label>Urbanizacion</label><select name="urbanizacion_id"><option value="">Actual / todas permitidas</option>@foreach($urbanizaciones as $urbanizacion)<option value="{{ $urbanizacion->id }}" @selected((int) request('urbanizacion_id') === $urbanizacion->id)>{{ $urbanizacion->nombre }}</option>@endforeach</select></div>
<div class="field"><label>Cliente</label><input name="cliente" value="{{ request('cliente') }}" placeholder="Nombre/interesado"></div>
<div class="field"><label>Lote</label><input name="lote" value="{{ request('lote') }}"></div>
<div class="field"><label>Manzano</label><input name="manzano" value="{{ request('manzano') }}"></div>
<div class="field"><label>Estado</label><select name="estado"><option value="">Todos</option>@foreach(['activa','vencida','cancelada','convertida'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst($estado) }}</option>@endforeach</select></div>
<div class="field"><label>Tipo operacion</label><select name="tipo_operacion"><option value="">Todos</option>@foreach($tiposOperacion as $tipo)<option value="{{ $tipo }}" @selected(request('tipo_operacion') === $tipo)>{{ ucfirst($tipo) }}</option>@endforeach</select></div>
@if($canFilterAsesor)
<div class="field"><label>Asesor</label><select name="usuario_id"><option value="">Todos</option>@foreach($vendedores as $vendedor)<option value="{{ $vendedor->id }}" @selected((int) request('usuario_id') === $vendedor->id)>{{ $vendedor->name }}</option>@endforeach</select></div>
@endif
<div class="field"><label>Desde</label><input type="date" name="desde" value="{{ request('desde') }}"></div>
<div class="field"><label>Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}"></div>
<div class="field"><label>&nbsp;</label><button type="submit" class="btn">Filtrar</button></div>
<div class="field"><label>&nbsp;</label><a class="btn secondary" href="{{ route('reservas.index') }}">Limpiar</a></div>
</form>
<div class="table-scroll"><table class="table responsive-table commercial-list"><thead><tr><th><x-sort-link field="cliente">Cliente</x-sort-link></th><th>Documento</th><th><x-sort-link field="lote">Lote</x-sort-link></th><th>Tipo</th><th><x-sort-link field="fecha">Reserva</x-sort-link></th><th>Vence</th><th>Monto</th><th><x-sort-link field="estado">Estado</x-sort-link></th><th>Asesor</th><th></th></tr></thead><tbody>
@foreach($reservas as $reserva)
<tr>
<td data-label="Cliente"><strong>{{ $reserva->cliente->nombre }}</strong></td>
<td data-label="Documento">{{ $reserva->cliente->documento }}</td>
<td data-label="Lote">{{ $reserva->lote->manzano->codigo }}-{{ $reserva->lote->codigo }}</td>
<td data-label="Tipo">{{ $reserva->tipo_operacion }}</td>
<td data-label="Reserva">{{ $reserva->fecha_reserva->format('d/m/Y') }}</td>
<td data-label="Vence">{{ $reserva->fecha_vencimiento->format('d/m/Y') }}</td>
<td data-label="Monto">{{ number_format($reserva->monto_reserva, 2) }}</td>
<td data-label="Estado"><span class="badge {{ $reserva->estado }}">{{ $reserva->estado }}</span></td>
<td data-label="Asesor">{{ $reserva->usuario?->name ?? 'Sin asesor' }}</td>
<td class="actions" data-label="Acciones"><a class="btn secondary" href="{{ route('clientes.show', $reserva->cliente) }}">Ver detalle</a>@can('ver recibo reserva')@if($reserva->cashMovements->contains(fn($movimiento) => $movimiento->concepto === 'reserva' && $movimiento->estado !== 'anulado'))<a class="btn secondary" href="{{ route('reservas.recibo', $reserva) }}" target="_blank" rel="noopener">Recibo PDF</a>@endif @endcan @can('editar reservas')<a class="btn secondary" href="{{ route('reservas.edit', $reserva) }}">Editar</a>@endcan @if($canDeleteReserva && auth()->user()->can('cancelar reservas'))<form method="POST" action="{{ route('reservas.expire', $reserva) }}" onsubmit="return confirm('Confirma marcar esta reserva como vencida?');">@csrf<button type="submit" class="btn secondary">Vencer</button></form><form method="POST" action="{{ route('reservas.destroy', $reserva) }}" onsubmit="const m = prompt('Motivo obligatorio de cancelacion'); if(!m) return false; this.motivo.value=m; return confirm('Confirma cancelar esta reserva y liberar el lote?');">@csrf @method('DELETE')<input type="hidden" name="motivo"><button type="submit" class="btn danger">Cancelar</button></form>@endif</td>
</tr>
@endforeach
</tbody></table></div>
@if ($reservas->hasPages())
<div class="pagination-wrapper">
    {{ $reservas->appends(request()->query())->links() }}
</div>
@endif
@endsection
