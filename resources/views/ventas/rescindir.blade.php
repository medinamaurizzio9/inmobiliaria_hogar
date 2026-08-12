@extends('layouts.app')
@section('content')
@php $pagado=$venta->cashMovements->where('tipo','ingreso')->where('estado','confirmado')->sum('monto'); $saldo=$venta->cuotas->whereIn('estado',['pendiente','parcial','vencida'])->sum('saldo_pendiente'); @endphp
<div class="topbar"><h1 class="title">Rescindir venta</h1><a class="btn secondary" href="{{ route('ventas.show', $venta) }}">Volver</a></div>
<div class="card"><p><strong>Cliente:</strong> {{ $venta->cliente->nombre }}</p><p><strong>Terreno:</strong> {{ $venta->lote->manzano->codigo }}-{{ $venta->lote->codigo }}</p><p><strong>Total pagado:</strong> $us {{ number_format((float)$pagado,2) }}</p><p><strong>Saldo pendiente:</strong> $us {{ number_format((float)$saldo,2) }}</p><p class="muted">Devuelto + retenido no puede superar $us {{ number_format((float)$pagado,2) }}. El monto retenido no genera salida de caja.</p></div>
<form method="POST" action="{{ route('ventas.rescindir.store', $venta) }}" class="card" style="margin-top:18px;">@csrf
<label>Monto a devolver<input name="monto_devuelto" type="number" min="0" step="0.01" required value="{{ old('monto_devuelto',0) }}"></label><label>Monto retenido empresa<input name="monto_retenido_empresa" type="number" min="0" step="0.01" required value="{{ old('monto_retenido_empresa',0) }}"></label>
<label>Medio de devolucion<select name="metodo_pago" required>@foreach(['efectivo','transferencia','QR','banco','otro'] as $metodo)<option value="{{ $metodo }}">{{ $metodo }}</option>@endforeach</select></label><label>Referencia<input name="referencia" value="{{ old('referencia') }}"></label>
<label>Motivo<textarea name="motivo" required>{{ old('motivo') }}</textarea></label><label>Observaciones<textarea name="observaciones">{{ old('observaciones') }}</textarea></label>
<label><input type="checkbox" name="confirmacion" value="1" required> Confirmo que la venta sera anulada, las cuotas activas dejaran de ser deuda y el lote se sincronizara.</label><button class="btn danger" type="submit">Rescindir venta</button>
</form>@endsection
