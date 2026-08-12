@extends('layouts.app')

@section('content')
@php
    $estadoLabels = \App\Models\CashMovement::ESTADO_LABELS;
    $metodoLabels = \App\Models\CashMovement::METODO_LABELS;
    $lote = $movimiento->venta?->lote
        ?? $movimiento->reserva?->lote
        ?? $movimiento->cuota?->venta?->lote;
    $numero = str_pad((string) $movimiento->id, 8, '0', STR_PAD_LEFT);
@endphp
<div class="topbar">
    <h1 class="title">Movimiento de Caja #{{ $numero }}</h1>
    <a class="btn secondary" href="{{ route('caja.index') }}">Volver a Caja</a>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<div class="card">
    <div class="field">
        <label>Estado</label>
        <span class="badge {{ $movimiento->estado }}">{{ $estadoLabels[$movimiento->estado] ?? $movimiento->estado }}</span>
    </div>

    <div class="field">
        <label>Cliente</label>
        <p>{{ $movimiento->cliente?->nombre ?? 'No registrado' }}</p>
    </div>

    @if ($lote)
        <div class="field">
            <label>Lote</label>
            <p>{{ $lote->manzano?->urbanizacion?->nombre ?? '-' }} / Manzano {{ $lote->manzano?->codigo ?? '-' }} / Lote {{ $lote->codigo }}</p>
        </div>
    @endif

    <div class="field">
        <label>Cuota</label>
        <p>{{ $movimiento->cuota ? 'Cuota Nro '.$movimiento->cuota->numero : 'No aplica' }}</p>
    </div>

    <div class="field">
        <label>Concepto</label>
        <p>{{ ucfirst($movimiento->concepto) }}</p>
    </div>

    <div class="field">
        <label>Metodo de pago</label>
        <p>{{ $metodoLabels[$movimiento->metodo_pago] ?? $movimiento->metodo_pago }}</p>
    </div>

    <div class="field">
        <label>Banco</label>
        <p>{{ $movimiento->banco ?: '-' }}</p>
    </div>

    <div class="field">
        <label>Referencia</label>
        <p>{{ $movimiento->referencia ?: '-' }}</p>
    </div>

    <div class="field">
        <label>Monto</label>
        <p>Bs {{ number_format((float) $movimiento->monto, 2) }}</p>
    </div>

    <div class="field">
        <label>Fecha del pago</label>
        <p>{{ $movimiento->fecha?->format('d/m/Y') }}</p>
    </div>

    <div class="field">
        <label>Registrado por</label>
        <p>{{ $movimiento->user?->name ?? 'Sistema' }}</p>
    </div>

    @if ($movimiento->estado === 'confirmado')
        <div class="field">
            <label>Confirmado por</label>
            <p>{{ $movimiento->confirmador?->name ?? 'Sistema' }}</p>
        </div>

        <div class="field">
            <label>Fecha de confirmacion</label>
            <p>{{ $movimiento->confirmado_en?->format('d/m/Y H:i') ?? '-' }}</p>
        </div>
    @endif

    @if ($movimiento->motivo_rechazo)
        <div class="field">
            <label>Motivo de rechazo</label>
            <p>{{ $movimiento->motivo_rechazo }}</p>
        </div>
    @endif
</div>

@if ($movimiento->pagoAplicaciones->isNotEmpty())
    <div class="card">
        <h2 class="title">Aplicacion del pago</h2>
        <p>Este pago se distribuyo sobre las siguientes cuotas.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>Cuota</th>
                    <th>Vencimiento</th>
                    <th>Monto aplicado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movimiento->pagoAplicaciones as $aplicacion)
                    <tr>
                        <td>Cuota Nro {{ $aplicacion->cuota?->numero ?? '-' }}</td>
                        <td>{{ $aplicacion->cuota?->fecha_vencimiento?->format('d/m/Y') ?? '-' }}</td>
                        <td>Bs {{ number_format((float) $aplicacion->monto_aplicado, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($movimiento->estado === 'confirmado')
    <div class="filter-actions">
        <a class="btn secondary" href="{{ route('pdf.recibo', $movimiento) }}" target="_blank" rel="noopener">Imprimir recibo</a>
    </div>
@endif

@if ($movimiento->estado === 'pendiente_verificacion' && auth()->user()?->hasAnyRole(['administrador', 'gerente', 'cajero']))
    <div class="filter-actions">
        <form method="POST" action="{{ route('caja.confirm', $movimiento) }}" onsubmit="return confirm('Confirma que deseas verificar y confirmar este pago?')">
            @csrf
            <button class="btn success">Confirmar pago</button>
        </form>
        <form method="POST" action="{{ route('caja.reject', $movimiento) }}" onsubmit="const m = prompt('Motivo obligatorio del rechazo'); if(!m) return false; this.motivo.value=m; return confirm('Confirma que deseas rechazar este pago?');">
            @csrf
            <input type="hidden" name="motivo">
            <button class="btn danger">Rechazar pago</button>
        </form>
    </div>
@endif
@endsection
