@extends('layouts.app')
@section('title', 'Control comercial de clientes')
@section('content')
<x-crm.page-header title="Control comercial de clientes" subtitle="Coincidencias, protección y nuevas operaciones sin exponer información financiera">
    <a class="btn secondary" href="{{ route('reportes.index') }}">Volver a reportes</a>
</x-crm.page-header>

@if($pendingReview > 0)<div class="operations-alert" role="status"><span class="operations-alert-icon"><i class="fa-solid fa-user-shield"></i></span><div><strong>{{ $pendingReview }} {{ $pendingReview === 1 ? 'coincidencia comercial pendiente' : 'coincidencias comerciales pendientes' }} de revisión</strong><p>Revise los intentos de duplicado y coincidencias secundarias.</p></div></div>@endif

<section class="crm-kpi-grid commercial-control-kpis">
    <x-crm.kpi-card label="Clientes protegidos" :value="$protectedClients" icon="fa-shield-heart" />
    <x-crm.kpi-card label="Protecciones vencidas" :value="$expiredClients" icon="fa-shield" />
    <x-crm.kpi-card label="Coincidencias" :value="$matchesCount" icon="fa-code-compare" tone="warning" />
    <x-crm.kpi-card label="Intentos CI duplicado" :value="$duplicateAttempts" icon="fa-id-card" tone="warning" />
    <x-crm.kpi-card label="Operaciones protegidas" :value="$protectedOperations" icon="fa-handshake" />
</section>

<form class="card filter-form commercial-control-filters" method="GET">
    <label>Desde<input type="date" name="desde" value="{{ request('desde') }}"></label>
    <label>Hasta<input type="date" name="hasta" value="{{ request('hasta') }}"></label>
    <label>Cliente o CI<input name="q" value="{{ request('q') }}" placeholder="Nombre o documento"></label>
    <label>Asesor<select name="advisor_id"><option value="">Todos</option>@foreach($advisors as $advisor)<option value="{{ $advisor->id }}" @selected((int) request('advisor_id') === $advisor->id)>{{ $advisor->name }}</option>@endforeach</select></label>
    @if($supervisors->isNotEmpty())<label>Supervisor / equipo<select name="supervisor_id"><option value="">Todos</option>@foreach($supervisors as $supervisor)<option value="{{ $supervisor->id }}" @selected((int) request('supervisor_id') === $supervisor->id)>{{ $supervisor->name }}</option>@endforeach</select></label>@endif
    <label>Tipo de evento<select name="event_type"><option value="">Todos</option>@foreach($eventTypes as $value => $label)<option value="{{ $value }}" @selected(request('event_type') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label>Protección<select name="protection"><option value="">Todos</option><option value="protected" @selected(request('protection') === 'protected')>Vigente</option><option value="expired" @selected(request('protection') === 'expired')>Vencida</option></select></label>
    <div class="filter-actions"><button class="btn">Filtrar</button><a class="btn secondary" href="{{ route('reportes.control-comercial-clientes') }}">Limpiar</a></div>
</form>

<div class="table-scroll"><table class="table responsive-table commercial-control-table"><thead><tr><th>Fecha</th><th>Cliente</th><th>CI</th><th>Evento</th><th>Asesor original</th><th>Asesor involucrado</th><th>Tiempo</th><th>Protección</th><th></th></tr></thead><tbody>
@forelse($events as $event)
    @php($protected = $event->cliente?->protection_expires_at?->isFuture())
    <tr>
        <td data-label="Fecha">{{ $event->created_at->format('d/m/Y H:i') }}</td>
        <td data-label="Cliente"><strong>{{ $event->cliente?->nombre }}</strong></td>
        <td data-label="CI">{{ $event->cliente?->documento }}</td>
        <td data-label="Evento"><span class="commercial-event-badge {{ $event->match_type ? 'warning' : 'valid' }}">{{ $eventTypes[$event->event_type] ?? $event->event_type }}</span></td>
        <td data-label="Asesor original">{{ $event->originalAdvisor?->name ?? 'Sin registro' }}</td>
        <td data-label="Asesor involucrado">{{ $event->involvedAdvisor?->name ?? 'Sin registro' }}</td>
        <td data-label="Tiempo">{{ $event->created_at->diffForHumans() }}</td>
        <td data-label="Protección"><span class="commercial-protection-status {{ $protected ? 'protected' : 'expired' }}">{{ $protected ? 'Vigente' : 'Vencida' }}</span></td>
        <td class="actions" data-label="Acción"><a class="btn secondary" href="{{ route('clientes.show', $event->cliente_id) }}">Ver</a></td>
    </tr>
@empty<tr class="responsive-empty"><td colspan="9"><x-crm.empty-state title="Sin eventos comerciales para los filtros aplicados" icon="fa-user-shield" /></td></tr>@endforelse
</tbody></table></div>
{{ $events->links() }}
@endsection
