@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Reestructurar deuda</h1><a class="btn secondary" href="{{ route('ventas.show', $venta) }}">Volver</a></div>
@php
    $activas = $venta->cuotas->whereIn('estado', ['pendiente','parcial','vencida']);
    $saldo = $activas->sum('saldo_pendiente');
    $pagado = $venta->cashMovements->where('tipo','ingreso')->where('estado','confirmado')->sum('monto');
@endphp
<div class="card"><h2>ANTES</h2><p><strong>Cliente:</strong> {{ $venta->cliente->nombre }}</p><p><strong>Terreno:</strong> {{ $venta->lote->manzano->codigo }}-{{ $venta->lote->codigo }}</p><p><strong>Modalidad:</strong> {{ $venta->tipo_operacion }}</p><p><strong>Precio pactado:</strong> $us {{ number_format((float)$venta->precio_final,2) }}</p><p><strong>Total pagado:</strong> $us {{ number_format((float)$pagado,2) }}</p><p><strong>Saldo actual:</strong> $us {{ number_format((float)$saldo,2) }}</p><p><strong>Cuotas pendientes:</strong> {{ $activas->count() }} (vencidas: {{ $activas->where('estado','vencida')->count() }})</p></div>
<form method="POST" action="{{ route('ventas.reestructurar.store', $venta) }}" class="card" style="margin-top:18px;">@csrf
<h2>DESPUES</h2>
<label>Nuevo plazo<input id="nuevo_plazo" name="nuevo_plazo" type="number" min="1" required value="{{ old('nuevo_plazo') }}"></label>
<label>Nueva primera fecha<input name="fecha_primer_vencimiento" type="date" required value="{{ old('fecha_primer_vencimiento') }}"></label>
<p>Mensualidad estimada: <strong id="estimada">$us 0.00</strong></p>
<label>Motivo<textarea name="motivo" required>{{ old('motivo') }}</textarea></label><label>Observaciones<textarea name="observaciones">{{ old('observaciones') }}</textarea></label>
<button class="btn" type="submit">Confirmar reestructuracion</button>
</form>
<script>document.getElementById('nuevo_plazo').addEventListener('input',e=>document.getElementById('estimada').textContent='$us '+(e.target.value>0?({{ (float)$saldo }}/e.target.value).toFixed(2):'0.00'));</script>
@endsection
