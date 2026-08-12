@extends('layouts.app')
@section('content')
<div class="topbar">
    <h1 class="title">Detalle de venta</h1>
    <div class="actions">
        @if(auth()->user()->hasRole('administrador') && in_array($venta->tipo_operacion, ['semicontado','credito']) && $venta->estado !== 'anulada' && $venta->cuotas->whereIn('estado',['pendiente','parcial','vencida'])->sum('saldo_pendiente') > 0)
            <a class="btn" href="{{ route('ventas.reestructurar', $venta) }}">Reestructurar deuda</a>
        @endif
        @if(auth()->user()->hasRole('administrador') && $venta->estado !== 'anulada')
            <a class="btn danger" href="{{ route('ventas.rescindir', $venta) }}">Rescindir venta</a>
        @endif
        <a class="btn secondary" href="{{ route('pdf.plan', $venta) }}">Imprimir plan de pagos</a>
        <a class="btn secondary" href="{{ route('pdf.contrato', $venta) }}">Generar contrato</a>
        @if(auth()->user()->hasRole('administrador') && auth()->user()->can('editar ventas') && ($venta->estado !== 'anulada' || auth()->user()->can('editar ventas anuladas')))
            <a class="btn" href="{{ route('ventas.edit', $venta) }}">Editar</a>
        @endif
        <a class="btn secondary" href="{{ route('ventas.index') }}">Volver</a>
    </div>
</div>

<div class="card" style="margin-top:18px;"><h2>Historial de reestructuraciones</h2>
@forelse($venta->reestructuraciones as $item)<p><strong>{{ $item->fecha?->format('d/m/Y') }}</strong> — saldo anterior $us {{ number_format((float)$item->saldo_antes,2) }}, plazo anterior {{ $item->plazo_anterior }}, nuevo plazo {{ $item->nuevo_plazo }}. Administrador: {{ $item->administrador?->name }}. Motivo: {{ $item->motivo }}</p>@empty<p>Sin reestructuraciones.</p>@endforelse</div>
<div class="card" style="margin-top:18px;"><h2>Rescision / devolucion</h2>
@forelse($venta->devoluciones as $item)<p><strong>{{ $item->fecha?->format('d/m/Y') }}</strong> — pagado $us {{ number_format((float)$item->monto_pagado,2) }}, devuelto $us {{ number_format((float)$item->monto_devuelto,2) }}, retenido $us {{ number_format((float)$item->monto_retenido_empresa,2) }}. Responsable: {{ $item->responsable?->name }}. Motivo: {{ $item->motivo }}</p>@empty<p>Sin rescisiones.</p>@endforelse</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<div class="card" style="margin-top:18px;">
    <h2>{{ $venta->cliente?->nombre }}</h2>
    <p><strong>Lote:</strong> {{ $venta->lote->manzano->urbanizacion->nombre }} / {{ $venta->lote->manzano->codigo }}-{{ $venta->lote->codigo }}</p>
    <p><strong>Fecha de venta:</strong> {{ $venta->fecha_venta?->format('d/m/Y') }}</p>
    <p><strong>Modalidad:</strong> {{ $venta->tipo_operacion ?: 'Sin registrar' }}</p>
    <p><strong>Precio de lista:</strong> $us {{ number_format((float) ($venta->precio_base_usd ?? $venta->precio_final), 2) }}</p>
    <p><strong>Descuento:</strong> $us {{ number_format((float) $venta->descuento, 2) }}</p>
    <p><strong>Precio final pactado:</strong> $us {{ number_format((float) ($venta->precio_final_usd ?? $venta->precio_final), 2) }} <span class="muted">(Bs {{ $venta->precio_final_bs ? number_format((float) $venta->precio_final_bs, 2) : 'Sin registrar' }})</span></p>
    <p><strong>Cuota inicial:</strong> $us {{ number_format((float) $venta->cuota_inicial, 2) }}</p>
    <p><strong>Saldo financiado:</strong> $us {{ number_format((float) $venta->saldo_financiar, 2) }}</p>
    <p><strong>Numero de cuotas:</strong> {{ (int) $venta->numero_cuotas }}</p>
    <p><strong>Cuota mensual:</strong> $us {{ number_format((float) ($venta->cuotas->sortBy('numero')->first()?->monto ?? 0), 2) }}</p>
    <p><strong>Primer vencimiento:</strong> {{ $venta->fecha_primer_vencimiento?->format('d/m/Y') ?: 'Sin definir' }}</p>
    @if ((float) $venta->descuento > 0)
        <p><strong>Descuento autorizado por:</strong> {{ $venta->descuentoAutorizador?->name ?? 'Usuario no disponible' }}</p>
        <p><strong>Fecha de autorizacion:</strong> {{ $venta->descuento_autorizado_en?->format('d/m/Y H:i') }}</p>
    @endif
    <p><strong>Estado:</strong> <span class="badge {{ $venta->estado }}">{{ $venta->estado }}</span></p>
    @if($venta->observaciones)
        <p><strong>Observaciones:</strong> {{ $venta->observaciones }}</p>
    @endif
</div>

<div class="card" style="margin-top:18px;">
    <h2>Plan de pagos</h2>
    <table class="table">
        <thead><tr><th>Cuota</th><th>Fecha programada</th><th>Monto</th><th>Pagado</th><th>Saldo</th><th>Estado</th></tr></thead>
        <tbody>
        @forelse($venta->cuotas as $cuota)
            <tr>
                <td>{{ $cuota->numero }}</td>
                <td>{{ $cuota->fecha_programada?->format('d/m/Y') }}</td>
                <td>$us {{ number_format((float) $cuota->monto, 2) }}</td>
                <td>$us {{ number_format((float) $cuota->monto_pagado, 2) }}</td>
                <td>$us {{ number_format((float) $cuota->saldo_pendiente, 2) }}</td>
                <td><span class="badge {{ $cuota->estado }}">{{ $cuota->estado }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6">Sin cuotas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
