@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<x-crm.page-header title="Dashboard" subtitle="Resumen general de tu inmobiliaria">
    <span class="period-chip"><i class="fa-regular fa-calendar"></i> Este mes</span>
    <a class="btn secondary" href="{{ route('dashboard') }}"><i class="fa-solid fa-rotate"></i> Actualizar</a>
    @can('crear ventas')<a class="btn" href="{{ route('ventas.create') }}"><i class="fa-solid fa-plus"></i> Registrar venta</a>@endcan
</x-crm.page-header>

<section class="crm-kpi-grid">
    <x-crm.kpi-card label="Clientes totales" :value="number_format($clientes)" icon="fa-users" hint="Base comercial activa" />
    <x-crm.kpi-card label="Lotes disponibles" :value="number_format($lotesDisponibles)" icon="fa-map" hint="de {{ number_format($totalLotes) }} lotes" />
    <x-crm.kpi-card label="Reservas activas" :value="number_format($reservasActivasEquipo)" icon="fa-calendar-check" hint="Seguimiento comercial" tone="warning" />
    <x-crm.kpi-card :label="auth()->user()->hasRole('vendedor') ? 'Monto comercial' : 'Ventas acumuladas'" :value="'Bs '.number_format($montoVendido, 2)" icon="fa-chart-line" hint="Operaciones activas" />
</section>

@if($supervisorDashboard)
<section class="crm-kpi-grid compact">
    <x-crm.kpi-card label="Reservas del equipo" :value="$reservasActivasEquipo" icon="fa-users-viewfinder" />
    <x-crm.kpi-card label="Convertidas" :value="$reservasConvertidasEquipo" icon="fa-circle-check" />
    <x-crm.kpi-card label="Ventas cerradas" :value="$ventasCerradasEquipo" icon="fa-handshake" />
    <x-crm.kpi-card label="Monto del equipo" :value="'Bs '.number_format($montoVendidoEquipo, 2)" icon="fa-coins" />
</section>
@endif

<section class="grid dashboard-grid crm-dashboard-panels">
    <article class="card crm-chart-card"><div class="card-heading"><div><span class="eyebrow">Inventario</span><h2>Propiedades por estado</h2></div><span class="card-total">{{ $totalLotes }} lotes</span></div><div class="bar-list">@foreach(['disponible','vendido','reservado','bloqueado'] as $estado) @php($total=(int)($lotesPorEstado[$estado]??0)) @php($width=$totalLotes>0?max(4,round(($total/$totalLotes)*100)):0)<div class="bar-row"><span>{{ ucfirst($estado) }}</span><div class="bar-track"><div class="bar-fill {{ $estado }}" style="width:{{ $width }}%"></div></div><strong>{{ $total }}</strong></div>@endforeach</div></article>
    <article class="card crm-chart-card"><div class="card-heading"><div><span class="eyebrow">Rendimiento</span><h2>Ingresos mensuales</h2></div><span class="card-total">Bs {{ number_format($ingresosMes, 0) }}</span></div><div class="bar-list">@php($maxIngreso=max(1,(float)$ingresosPorMes->max())) @forelse($ingresosPorMes as $mes=>$monto)<div class="bar-row"><span>{{ $mes }}</span><div class="bar-track"><div class="bar-fill income" style="width:{{ max(5,round(($monto/$maxIngreso)*100)) }}%"></div></div><strong>{{ number_format($monto,0) }}</strong></div>@empty<x-crm.empty-state title="Sin ingresos registrados" icon="fa-chart-simple" />@endforelse</div></article>
</section>

<section class="grid dashboard-grid crm-dashboard-panels">
    <article class="card"><div class="card-heading"><div><span class="eyebrow">Cobranza</span><h2>Cuotas vencidas</h2></div><span class="status-count danger">{{ $cuotasVencidas }}</span></div><div class="table-scroll"><table class="table"><thead><tr><th>Cliente</th><th>Lote</th><th>Vence</th><th>Saldo</th></tr></thead><tbody>@forelse($cuotasVencidasLista as $cuota)<tr><td><strong>{{ $cuota->venta->cliente->nombre }}</strong></td><td>{{ $cuota->venta->lote->manzano->codigo }}-{{ $cuota->venta->lote->codigo }}</td><td>{{ $cuota->fecha_programada->format('d/m/Y') }}</td><td>Bs {{ number_format($cuota->saldo_pendiente,2) }}</td></tr>@empty<tr><td colspan="4"><x-crm.empty-state title="No existen cuotas vencidas" icon="fa-circle-check" /></td></tr>@endforelse</tbody></table></div></article>
    <article class="card"><div class="card-heading"><div><span class="eyebrow">Actividad próxima</span><h2>Reservas por vencer</h2></div><span class="status-count">{{ $reservasVencidas }}</span></div><div class="table-scroll"><table class="table"><thead><tr><th>Cliente</th><th>Lote</th><th>Vence</th><th>Monto</th></tr></thead><tbody>@forelse($reservasPorVencer as $reserva)<tr><td><strong>{{ $reserva->cliente->nombre }}</strong></td><td>{{ $reserva->lote->manzano->codigo }}-{{ $reserva->lote->codigo }}</td><td>{{ $reserva->fecha_vencimiento->format('d/m/Y') }}</td><td>Bs {{ number_format($reserva->monto_reserva,2) }}</td></tr>@empty<tr><td colspan="4"><x-crm.empty-state title="Sin reservas próximas a vencer" icon="fa-calendar-check" /></td></tr>@endforelse</tbody></table></div></article>
</section>

@if($supervisorDashboard)<article class="card"><div class="card-heading"><div><span class="eyebrow">Equipo comercial</span><h2>Ranking de asesores</h2></div></div><div class="table-scroll"><table class="table"><thead><tr><th>Asesor</th><th>Reservas</th><th>Ventas</th><th>Monto vendido</th></tr></thead><tbody>@forelse($rankingAsesoresEquipo as $row)<tr><td>{{ $row['asesor'] }}</td><td>{{ $row['reservas'] }}</td><td>{{ $row['ventas'] }}</td><td>Bs {{ number_format($row['monto'],2) }}</td></tr>@empty<tr><td colspan="4">Aún no hay actividad del equipo.</td></tr>@endforelse</tbody></table></div></article>@endif
@endsection
