@extends('layouts.app')

@section('content')
@if(session('client_temporary_credential'))
<div class="card" style="border:2px solid #d97706;margin-bottom:18px;">
    <h2>Acceso temporal del cliente — visible una sola vez</h2>
    <p>Correo: <strong>{{ session('client_temporary_credential.email') }}</strong></p>
    <p>Contraseña temporal: <strong>{{ session('client_temporary_credential.password') }}</strong></p>
    <p class="muted">Comunícala de forma segura. No quedará almacenada ni volverá a mostrarse.</p>
</div>
@endif
@if(session('warning'))<div class="status" style="background:#fff7ed;color:#9a3412;">{{ session('warning') }}</div>@endif
<div class="topbar">
    <h1 class="title">Ventas</h1>
    <div class="actions">
        @can('exportar reportes')
            <a class="btn secondary" href="{{ route('export.csv', ['tipo' => 'ventas'] + request()->query()) }}">Exportar CSV</a>
        @endcan
        @can('crear ventas')
            <a class="btn" href="{{ route('ventas.create') }}">Nueva</a>
        @endcan
    </div>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<form method="GET" action="{{ route('ventas.index') }}" class="card filter-form list-filters sales-filters">
    <div class="field">
        <label for="q">Buscar venta</label>
        <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cliente, documento, telefono, lote o manzano">
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            @foreach(['activa', 'completada', 'anulada'] as $estado)
                <option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ ucfirst($estado) }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="fecha_desde">Desde</label>
        <input id="fecha_desde" type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}">
    </div>

    <div class="field">
        <label for="fecha_hasta">Hasta</label>
        <input id="fecha_hasta" type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}">
    </div>

    <div class="field">
        <label for="per_page">Mostrar</label>
        <select id="per_page" name="per_page">
            @foreach([15, 30, 50, 100] as $size)
                <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 50) === $size)>{{ $size }} por pagina</option>
            @endforeach
        </select>
    </div>

    <div class="filter-actions">
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn secondary" href="{{ route('ventas.index') }}">Limpiar</a>
    </div>
</form>

<div class="list-summary">
    Mostrando {{ $ventas->firstItem() ?? 0 }}-{{ $ventas->lastItem() ?? 0 }} de {{ $ventas->total() }} ventas
</div>

<div class="table-scroll">
    <table class="table responsive-table commercial-list">
        <thead>
            <tr>
                <th><x-sort-link field="fecha">Fecha</x-sort-link></th>
                <th><x-sort-link field="cliente">Cliente</x-sort-link></th>
                <th><x-sort-link field="lote">Lote</x-sort-link></th>
                <th><x-sort-link field="tipo">Tipo operacion</x-sort-link></th>
                <th><x-sort-link field="monto">Precio</x-sort-link></th>
                <th>Cuota inicial</th>
                <th>Saldo</th>
                <th>Cuotas</th>
                <th><x-sort-link field="estado">Estado</x-sort-link></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $venta)
                <tr>
                    <td data-label="Fecha">{{ $venta->fecha_venta->format('d/m/Y') }}</td>
                    <td data-label="Cliente"><strong>{{ $venta->cliente->nombre }}</strong></td>
                    <td data-label="Lote">{{ $venta->lote->manzano->codigo }}-{{ $venta->lote->codigo }}</td>
                    <td data-label="Tipo">{{ $venta->tipo_operacion ?: 'Sin registrar' }}</td>
                    <td data-label="Precio">{{ number_format((float) ($venta->precio_final_usd ?? $venta->precio_final), 2) }}<br><span class="muted">Bs {{ $venta->precio_final_bs ? number_format((float) $venta->precio_final_bs, 2) : 'Sin registrar' }}</span></td>
                    <td data-label="Cuota inicial">{{ number_format($venta->cuota_inicial, 2) }}</td>
                    <td data-label="Saldo">{{ number_format($venta->saldo_financiar, 2) }}</td>
                    <td data-label="Cuotas">{{ $venta->cuotas->count() }}</td>
                    <td data-label="Estado"><span class="badge {{ $venta->estado }}">{{ $venta->estado }}</span></td>
                    <td class="actions" data-label="Acciones">
                        <a class="btn secondary" href="{{ route('ventas.show', $venta) }}">Ver</a>
                        @if(auth()->user()->hasRole('administrador') && auth()->user()->can('editar ventas') && ($venta->estado !== 'anulada' || auth()->user()->can('editar ventas anuladas')))
                            <a class="btn secondary" href="{{ route('ventas.edit', $venta) }}">Editar</a>
                        @endif
                        <a class="btn secondary" href="{{ route('pdf.plan', $venta) }}">Imprimir plan de pagos</a>
                        <a class="btn secondary" href="{{ route('pdf.contrato', $venta) }}">Generar contrato</a>
                        @can('anular ventas')
                            <form method="POST" action="{{ route('ventas.destroy', $venta) }}" onsubmit="return confirm('Confirma anular esta venta?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="motivo" value="Anulacion confirmada desde listado">
                                <button class="btn danger">Anular</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr class="responsive-empty">
                    <td colspan="10" class="empty-table">No se encontraron ventas con los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($ventas->hasPages())
    <div class="pagination-wrapper">
        {{ $ventas->appends(request()->query())->links() }}
    </div>
@endif
@endsection
