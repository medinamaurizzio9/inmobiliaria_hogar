@extends('layouts.app')

@section('title', 'Resumen ejecutivo')

@section('content')
<div class="hero-panel">
    <div>
        @if(!empty($systemSettings['logo_main_url']))
            <img src="{{ $systemSettings['logo_main_url'] }}" alt="Logo" style="max-height:80px;margin-bottom:10px;">
        @endif
        <h1 class="title">{{ $systemSettings['system_name'] ?? 'IMPACTO URBANIZACIONES' }}</h1>
        <div class="subtitle">{{ $systemSettings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</div>
        <p class="muted">Resumen ejecutivo para seguimiento comercial, disponibilidad, cobranza y reservas.</p>
    </div>
    @can('crear ventas')
        <a class="btn" href="{{ route('ventas.create') }}">Registrar venta</a>
    @endcan
</div>

@if($supervisorDashboard)
<div class="grid stats" style="margin-top:18px;">
    <div class="card metric"><div class="muted">Reservas activas equipo</div><div class="stat-value">{{ $reservasActivasEquipo }}</div></div>
    <div class="card metric"><div class="muted">Reservas canceladas equipo</div><div class="stat-value">{{ $reservasCanceladasEquipo }}</div></div>
    <div class="card metric"><div class="muted">Reservas convertidas equipo</div><div class="stat-value">{{ $reservasConvertidasEquipo }}</div></div>
    <div class="card metric"><div class="muted">Ventas cerradas equipo</div><div class="stat-value">{{ $ventasCerradasEquipo }}</div></div>
    <div class="card metric"><div class="muted">Monto vendido equipo</div><div class="stat-value">{{ number_format($montoVendidoEquipo, 2) }}</div></div>
</div>
<div class="card" style="margin-top:18px;">
    <h2>Ranking asesores de mi equipo</h2>
    <table class="table"><thead><tr><th>Asesor</th><th>Reservas</th><th>Ventas</th><th>Monto vendido</th></tr></thead><tbody>
        @forelse($rankingAsesoresEquipo as $row)
            <tr><td>{{ $row['asesor'] }}</td><td>{{ $row['reservas'] }}</td><td>{{ $row['ventas'] }}</td><td>{{ number_format($row['monto'], 2) }}</td></tr>
        @empty
            <tr><td colspan="4">Aun no hay actividad del equipo.</td></tr>
        @endforelse
    </tbody></table>
</div>
@endif

<div class="grid stats">
    <div class="card metric"><div class="muted">Lotes totales</div><div class="stat-value">{{ $totalLotes }}</div></div>
    <div class="card metric disponible"><div class="muted">Disponibles</div><div class="stat-value">{{ $lotesDisponibles }}</div></div>
    <div class="card metric vendido"><div class="muted">Vendidos</div><div class="stat-value">{{ $lotesVendidos }}</div></div>
    <div class="card metric reservado"><div class="muted">Reservados</div><div class="stat-value">{{ $lotesReservados }}</div></div>
    <div class="card metric"><div class="muted">Ingresos del dia</div><div class="stat-value">{{ number_format($ingresosDia, 2) }}</div></div>
    <div class="card metric"><div class="muted">Ingresos del mes</div><div class="stat-value">{{ number_format($ingresosMes, 2) }}</div></div>
    <div class="card metric"><div class="muted">Monto vendido</div><div class="stat-value">{{ number_format($montoVendido, 2) }}</div></div>
</div>

<div class="grid dashboard-grid">
    <div class="card">
        <h2>Lotes por estado</h2>
        <div class="bar-list">
            @foreach(['disponible','vendido','reservado','bloqueado'] as $estado)
                @php($total = (int) ($lotesPorEstado[$estado] ?? 0))
                @php($width = $totalLotes > 0 ? max(4, round(($total / $totalLotes) * 100)) : 0)
                <div class="bar-row">
                    <span>{{ ucfirst($estado) }}</span>
                    <div class="bar-track"><div class="bar-fill {{ $estado }}" style="width: {{ $width }}%"></div></div>
                    <strong>{{ $total }}</strong>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <h2>Ingresos por mes</h2>
        <div class="bar-list">
            @php($maxIngreso = max(1, (float) $ingresosPorMes->max()))
            @forelse($ingresosPorMes as $mes => $monto)
                <div class="bar-row">
                    <span>{{ $mes }}</span>
                    <div class="bar-track"><div class="bar-fill income" style="width: {{ max(5, round(($monto / $maxIngreso) * 100)) }}%"></div></div>
                    <strong>{{ number_format($monto, 0) }}</strong>
                </div>
            @empty
                <p class="muted">Aun no hay ingresos registrados.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="grid dashboard-grid">
    <div class="card">
        <h2>Cuotas vencidas</h2>
        <table class="table">
            <thead><tr><th>Cliente</th><th>Lote</th><th>Vence</th><th>Saldo</th></tr></thead>
            <tbody>
            @forelse($cuotasVencidasLista as $cuota)
                <tr><td>{{ $cuota->venta->cliente->nombre }}</td><td>{{ $cuota->venta->lote->manzano->codigo }}-{{ $cuota->venta->lote->codigo }}</td><td>{{ $cuota->fecha_programada->format('d/m/Y') }}</td><td>{{ number_format($cuota->saldo_pendiente, 2) }}</td></tr>
            @empty
                <tr><td colspan="4">No hay cuotas vencidas pendientes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Reservas proximas a vencer</h2>
        <table class="table">
            <thead><tr><th>Cliente</th><th>Lote</th><th>Vence</th><th>Monto</th></tr></thead>
            <tbody>
            @forelse($reservasPorVencer as $reserva)
                <tr><td>{{ $reserva->cliente->nombre }}</td><td>{{ $reserva->lote->manzano->codigo }}-{{ $reserva->lote->codigo }}</td><td>{{ $reserva->fecha_vencimiento->format('d/m/Y') }}</td><td>{{ number_format($reserva->monto_reserva, 2) }}</td></tr>
            @empty
                <tr><td colspan="4">No hay reservas por vencer en los proximos dias.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
