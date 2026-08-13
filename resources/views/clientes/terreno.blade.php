@extends('layouts.app')
@section('title', 'Detalle de mi terreno')
@section('content')
@php
    $activas = $venta->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida']);
    $saldo = $activas->sum('saldo_pendiente');
    $pagos = $venta->cashMovements->where('tipo', 'ingreso');
    $pagado = $pagos->where('estado', 'confirmado')->sum('monto');
@endphp
<div class="client-portal client-property-detail">
    <header class="client-section-header"><div><span class="client-eyebrow">Detalle del contrato</span><h1>Detalle de mi terreno</h1><p>{{ $venta->lote->manzano->urbanizacion->nombre }} · Mz {{ $venta->lote->manzano->codigo }} · Lote {{ $venta->lote->codigo }}</p></div><a class="btn secondary" href="{{ route('clientes.mi-cuenta') }}#mis-terrenos"><i class="fa-solid fa-arrow-left"></i> Volver a mi cuenta</a></header>
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="client-detail-summary">
        <div><span class="client-modality">{{ strtoupper($venta->tipo_operacion) }}</span><h2>{{ $venta->lote->manzano->urbanizacion->nombre }}</h2><p>Manzano {{ $venta->lote->manzano->codigo }} · Lote {{ $venta->lote->codigo }}</p><dl><div><dt>Precio pactado</dt><dd>$us {{ number_format((float) $venta->precio_final, 2) }}</dd></div><div><dt>Inicial</dt><dd>$us {{ number_format((float) $venta->cuota_inicial, 2) }}</dd></div><div><dt>Pagado confirmado</dt><dd>$us {{ number_format((float) $pagado, 2) }}</dd></div></dl></div>
        <div class="client-detail-balance"><span>Saldo actual</span><strong>{{ $saldo > 0 ? '$us '.number_format((float) $saldo, 2) : 'Pagado' }}</strong><div class="actions">@if($saldo > 0)<a class="btn" href="{{ route('portal.pagar', $venta) }}">Pagar</a>@endif<a class="btn secondary" href="{{ route('portal.documentos', $venta) }}">Documentos</a><a class="btn secondary" href="{{ route('portal.estado-cuenta.pdf', $venta) }}">Estado de cuenta</a></div></div>
    </section>
    <section class="client-detail-panel"><h2>Plan de cuotas</h2><div class="table-scroll"><table class="table"><thead><tr><th>N.º</th><th>Vencimiento</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Estado</th></tr></thead><tbody>@foreach($venta->cuotas as $cuota)<tr><td>{{ $cuota->numero }}</td><td>{{ $cuota->fecha_vencimiento?->format('d/m/Y') }}</td><td>{{ number_format((float) $cuota->monto, 2) }}</td><td>{{ number_format((float) $cuota->monto_pagado, 2) }}</td><td>{{ number_format((float) $cuota->saldo_pendiente, 2) }}</td><td><span class="badge {{ $cuota->estado }}">{{ strtoupper($cuota->estado) }}</span></td></tr>@endforeach</tbody></table></div></section>
    <section class="client-detail-panel"><h2>Historial de pagos</h2><div class="table-scroll"><table class="table"><thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th>Monto</th><th>Estado</th><th>Recibo</th></tr></thead><tbody>@forelse($pagos as $pago)<tr><td>{{ $pago->fecha?->format('d/m/Y') }}</td><td>{{ $pago->metodo_pago }}</td><td>{{ $pago->referencia }}</td><td>{{ number_format((float) $pago->monto, 2) }}</td><td><span class="badge {{ $pago->estado }}">{{ strtoupper(\App\Models\CashMovement::ESTADO_LABELS[$pago->estado] ?? $pago->estado) }}</span>@if($pago->estado === 'rechazado')<br>{{ $pago->motivo_rechazo }}@endif</td><td>@if($pago->estado === 'confirmado')<a href="{{ route('portal.recibo', $pago) }}">Recibo</a>@else—@endif</td></tr>@empty<tr><td colspan="6">Todavía no existen pagos registrados.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
