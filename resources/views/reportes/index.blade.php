@extends('layouts.app')
@section('content')
<div class="topbar"><h1 class="title">Reportes</h1></div>

<div class="grid stats">
    <div class="card"><div class="muted">Lotes de la urbanizacion</div><div class="stat-value">{{ $lotesTotal }}</div></div>
    <div class="card"><div class="muted">Reservas activas</div><div class="stat-value">{{ $reservasActivas }}</div></div>
    <div class="card"><div class="muted">Cuotas pendientes</div><div class="stat-value">{{ $cuotasPendientes }}</div></div>
    <div class="card"><div class="muted">Ingresos del mes</div><div class="stat-value">{{ number_format($ingresosMes, 2) }}</div></div>
    <div class="card"><div class="muted">Monto devuelto</div><div class="stat-value">{{ number_format($montoDevuelto, 2) }}</div></div>
    <div class="card"><div class="muted">Monto retenido</div><div class="stat-value">{{ number_format($montoRetenido, 2) }}</div></div>
</div>

<section class="grid report-card-grid" style="margin-top:18px;">
    <a class="card report-link-card" href="{{ route('reportes.lotes-estado') }}"><strong>Lotes por estado</strong><span>Disponibles, vendidos, reservados y bloqueados.</span></a>
    <a class="card report-link-card" href="{{ route('reportes.reservas') }}"><strong>Reservas</strong><span>Reservas activas, vencidas, canceladas y convertidas.</span></a>
    @can('ver reporte mejor vendedor')<a class="card report-link-card" href="{{ route('reportes.mejor-vendedor') }}"><strong>Mejor vendedor</strong><span>Ranking mensual de asesores y conversion.</span></a>@endcan
    <a class="card report-link-card" href="{{ route('reportes.cuotas') }}"><strong>Cuotas pendientes/vencidas</strong><span>Plan de pagos, saldos y vencimientos.</span></a>
    <a class="card report-link-card" href="{{ route('reportes.ingresos') }}"><strong>Ingresos</strong><span>Movimientos de caja de tipo ingreso.</span></a>
    <a class="card report-link-card" href="{{ route('reportes.estado-cuenta') }}"><strong>Estado de cuenta</strong><span>Consulta individual por cliente.</span></a>
    @can('exportar reportes')<a class="card report-link-card" href="{{ route('reportes.exportaciones') }}"><strong>Exportaciones</strong><span>Descargas CSV por modulo.</span></a>@endcan
</section>
@endsection
