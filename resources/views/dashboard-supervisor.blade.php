@extends('layouts.app')
@section('title', 'Dashboard comercial')
@section('content')
<x-crm.page-header title="Dashboard comercial" subtitle="Resumen de tu equipo y operaciones comerciales.">
    <a class="btn secondary" href="{{ route('urbanizaciones.select') }}"><i class="fa-solid fa-building"></i> Seleccionar urbanización</a>
</x-crm.page-header>

@if($reservasVencidas > 0 || $reservasPorVencer->isNotEmpty())
<section class="operations-alert supervisor-alert" role="alert">
    <span class="operations-alert-icon"><i class="fa-solid fa-calendar-exclamation"></i></span>
    <div><strong>Seguimiento de reservas</strong><p>{{ $reservasVencidas }} vencidas y {{ $reservasPorVencer->count() }} próximas a vencer en los siguientes 10 días.</p></div>
    @can('ver reservas')<a class="btn" href="{{ route('reservas.index') }}">Revisar reservas</a>@endcan
</section>
@endif

<section class="crm-kpi-grid">
    <x-crm.kpi-card label="Reservas activas" :value="$reservasActivas" icon="fa-calendar-check" hint="Del equipo" tone="warning" />
    <x-crm.kpi-card label="Asesores activos" :value="$asesoresActivos" icon="fa-user-group" hint="Equipo asignado" />
    <x-crm.kpi-card label="Ventas del mes" :value="$ventasMes" icon="fa-handshake" hint="Operaciones activas o completadas" />
    <x-crm.kpi-card label="Monto vendido este mes" :value="'Bs '.number_format($montoVendidoMes, 2)" icon="fa-chart-line" hint="{{ $clientesAtendidos }} clientes atendidos" />
</section>

<section class="operations-section">
    <div class="card-heading"><div><span class="eyebrow">Herramientas</span><h2>Accesos rápidos</h2></div></div>
    <div class="operations-quick-grid">
        @can('editar asesores')<a class="operations-quick" href="{{ route('asesores.index') }}"><i class="fa-solid fa-user-group"></i><span>Ver asesores</span></a>@endcan
        @can('editar asesores')<a class="operations-quick" href="{{ route('grupos-comerciales.index') }}"><i class="fa-solid fa-people-group"></i><span>Grupos comerciales</span></a>@endcan
        <a class="operations-quick" href="{{ route('urbanizaciones.select') }}"><i class="fa-solid fa-building"></i><span>Urbanizaciones</span></a>
        @can('ver lotes')<a class="operations-quick" href="{{ route('mapa') }}"><i class="fa-solid fa-map-location-dot"></i><span>Disponibilidad</span></a>@endcan
        @can('ver reservas')<a class="operations-quick" href="{{ route('reservas.index') }}"><i class="fa-solid fa-calendar-check"></i><span>Reservas</span></a>@endcan
        @can('ver ventas')<a class="operations-quick" href="{{ route('ventas.index') }}"><i class="fa-solid fa-file-contract"></i><span>Ventas</span></a>@endcan
        @can('ver clientes')<a class="operations-quick" href="{{ route('clientes.index') }}"><i class="fa-solid fa-users"></i><span>Clientes</span></a>@endcan
        @can('ver reportes')<a class="operations-quick" href="{{ route('reportes.index') }}"><i class="fa-solid fa-chart-column"></i><span>Reportes comerciales</span></a>@endcan
    </div>
</section>

<section class="card supervisor-team">
    <div class="card-heading"><div><span class="eyebrow">Mi equipo</span><h2>Actividad comercial del mes</h2></div><span class="card-total">{{ $equipo->count() }} asesores</span></div>
    <div class="table-scroll"><table class="table responsive-table"><thead><tr><th>Asesor</th><th>Grupo</th><th>Urbanizaciones</th><th>Ventas del mes</th><th>Reservas activas</th><th>Estado</th></tr></thead><tbody>
        @forelse($equipo as $asesor)<tr><td data-label="Asesor"><strong>{{ $asesor['nombre'] }}</strong></td><td data-label="Grupo">{{ $asesor['grupo'] }}</td><td data-label="Urbanizaciones">{{ $asesor['urbanizaciones'] ?: 'Sin asignación' }}</td><td data-label="Ventas">{{ $asesor['ventas'] }}<small>Bs {{ number_format($asesor['monto'], 2) }}</small></td><td data-label="Reservas">{{ $asesor['reservas'] }}</td><td data-label="Estado"><span class="badge {{ $asesor['activo'] ? 'activa' : 'cancelada' }}">{{ $asesor['activo'] ? 'Activo' : 'Inactivo' }}</span></td></tr>
        @empty<tr class="responsive-empty"><td colspan="6"><x-crm.empty-state title="No hay asesores asignados" icon="fa-user-group" /></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="operations-section supervisor-projects">
    <div class="card-heading"><div><span class="eyebrow">Cobertura comercial</span><h2>Urbanizaciones de mi equipo</h2></div></div>
    <div class="supervisor-project-grid">
        @forelse($urbanizacionesEquipo as $urbanizacion)
            <article class="card"><h3>{{ $urbanizacion->nombre }}</h3><p>{{ $urbanizacion->zona }}</p><div class="project-counts"><span><strong>{{ $urbanizacion->disponibles_count }}</strong> disponibles</span><span><strong>{{ $urbanizacion->reservados_count }}</strong> reservados</span><span><strong>{{ $urbanizacion->vendidos_count }}</strong> vendidos</span></div><form method="POST" action="{{ route('urbanizaciones.select.store') }}">@csrf<input type="hidden" name="urbanizacion_id" value="{{ $urbanizacion->id }}"><button class="btn" type="submit">Ingresar</button></form></article>
        @empty<x-crm.empty-state title="No tienes urbanizaciones asignadas" icon="fa-building" />@endforelse
    </div>
</section>
@endsection
