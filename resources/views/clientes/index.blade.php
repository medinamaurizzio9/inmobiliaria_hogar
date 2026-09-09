@extends('layouts.app')

@section('content')
<div class="topbar">
    <h1 class="title">Clientes</h1>
    <div class="actions">
        @can('exportar reportes')
            <a class="btn secondary" href="{{ route('export.csv', 'clientes') }}">Exportar CSV</a>
        @endcan
        @can('crear clientes')
            <a class="btn" href="{{ route('clientes.create') }}">Nuevo</a>
        @endcan
    </div>
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="errors">
        @foreach($errors->all() as $error)
            @foreach(explode("\n", $error) as $line)
                <div>{{ $line }}</div>
            @endforeach
        @endforeach
    </div>
@endif

<form method="GET" action="{{ route('clientes.index') }}" class="card filter-form list-filters">
    <div class="field">
        <label for="q">Buscar cliente</label>
        <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre, documento, telefono o email">
    </div>

    <div class="field">
        <label for="ventas">Ventas</label>
        <select id="ventas" name="ventas">
            <option value="">Todos</option>
            <option value="con_ventas" @selected(($filters['ventas'] ?? '') === 'con_ventas')>Con ventas</option>
            <option value="sin_ventas" @selected(($filters['ventas'] ?? '') === 'sin_ventas')>Sin ventas</option>
        </select>
    </div>

    @if($canFilterAsesor)
        <div class="field">
            <label for="usuario_id">Asesor</label>
            <select id="usuario_id" name="usuario_id">
                <option value="">Todos</option>
                @foreach($asesores as $asesor)
                    <option value="{{ $asesor->id }}" @selected((int) ($filters['usuario_id'] ?? 0) === $asesor->id)>{{ $asesor->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="field">
        <label for="per_page">Mostrar</label>
        <select id="per_page" name="per_page">
            @foreach([10, 15, 25, 50, 100] as $size)
                <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 50) === $size)>{{ $size }} por pagina</option>
            @endforeach
        </select>
    </div>

    <div class="filter-actions">
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn secondary" href="{{ route('clientes.index') }}">Limpiar</a>
    </div>
</form>

<div class="list-summary">
    Mostrando {{ $clientes->firstItem() ?? 0 }}-{{ $clientes->lastItem() ?? 0 }} de {{ $clientes->total() }} clientes
</div>

<div class="table-scroll">
    <table class="table responsive-table commercial-list">
        <thead>
            <tr>
                <th><x-sort-link field="nombre">Nombre</x-sort-link></th>
                <th><x-sort-link field="documento">Documento</x-sort-link></th>
                <th><x-sort-link field="telefono">Telefono</x-sort-link></th>
                <th><x-sort-link field="email">Email</x-sort-link></th>
                <th>Asesor</th>
                <th><x-sort-link field="ventas">Ventas</x-sort-link></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $cliente)
                <tr>
                    <td data-label="Nombre"><strong>{{ $cliente->nombre }}</strong></td>
                    <td data-label="CI">{{ $cliente->documento }}</td>
                    <td data-label="Teléfono">@include('clientes.partials.whatsapp-link', ['cliente' => $cliente])</td>
                    <td data-label="Email">{{ $cliente->email }}</td>
                    <td data-label="Asesor">{{ $cliente->createdBy?->name ?? 'Sin asesor' }}</td>
                    <td data-label="Ventas">{{ $cliente->ventas_count }}</td>
                    <td class="actions" data-label="Acciones">
                        <a class="btn secondary" href="{{ route('clientes.show', $cliente) }}">Ver</a>
                        @can('editar clientes')
                            <a class="btn secondary" href="{{ route('clientes.edit', $cliente) }}">Editar</a>
                        @endcan
                        @can('eliminar clientes')
                            <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" onsubmit="return confirm('Confirma eliminar este cliente?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr class="responsive-empty">
                    <td colspan="7" class="empty-table">No se encontraron clientes con los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($clientes->hasPages())
    <div class="pagination-wrapper">
        {{ $clientes->appends(request()->query())->links() }}
    </div>
@endif
@endsection
