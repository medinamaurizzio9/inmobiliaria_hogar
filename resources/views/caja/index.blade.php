@extends('layouts.app')

@section('content')
@php
    $estadoLabels = \App\Models\CashMovement::ESTADO_LABELS;
    $metodoLabels = \App\Models\CashMovement::METODO_LABELS;
@endphp
<div class="topbar">
    <h1 class="title">Caja</h1>
    @can('exportar reportes')
        <a class="btn secondary" href="{{ route('export.csv', ['tipo' => 'caja'] + request()->query()) }}">Exportar CSV</a>
    @endcan
</div>

@if (session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<div class="crm-kpi-grid compact">
    <x-crm.kpi-card label="Ingresos" :value="'Bs '.number_format($summary['ingresos'],2,',','.')" icon="fa-arrow-trend-up" />
    <x-crm.kpi-card label="Egresos" :value="'Bs '.number_format($summary['egresos'],2,',','.')" icon="fa-arrow-trend-down" tone="warning" />
    <x-crm.kpi-card label="Saldo neto" :value="'Bs '.number_format($summary['ingresos']-$summary['egresos'],2,',','.')" icon="fa-scale-balanced" />
    <x-crm.kpi-card label="Operaciones" :value="$summary['operaciones']" icon="fa-receipt" />
</div>

<form method="GET" action="{{ route('caja.index') }}" class="card filter-form cash-filters">
    <div class="field">
        <label for="q">Buscar movimiento</label>
        <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cliente, documento, concepto o referencia">
    </div>

    <div class="field">
        <label for="cliente">Cliente</label>
        <input id="cliente" name="cliente" value="{{ $filters['cliente'] ?? '' }}" placeholder="Nombre del cliente">
    </div>

    <div class="field">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            @foreach(\App\Models\CashMovement::ESTADOS_PAGO as $estado)
                <option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ $estadoLabels[$estado] ?? ucfirst($estado) }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="metodo_pago">Metodo</label>
        <select id="metodo_pago" name="metodo_pago">
            <option value="">Todos</option>
            @foreach(\App\Models\CashMovement::METODOS as $metodo)
                <option value="{{ $metodo }}" @selected(($filters['metodo_pago'] ?? '') === $metodo)>{{ $metodoLabels[$metodo] ?? ucfirst($metodo) }}</option>
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
                <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 15) === $size)>{{ $size }} por pagina</option>
            @endforeach
        </select>
    </div>

    <details class="more-filters">
        <summary>Mas filtros</summary>
        <div class="more-filters-grid">
            <div class="field">
                <label for="documento">Documento</label>
                <input id="documento" name="documento" value="{{ $filters['documento'] ?? '' }}" placeholder="CI o documento">
            </div>

            <div class="field">
                <label for="referencia">Referencia</label>
                <input id="referencia" name="referencia" value="{{ $filters['referencia'] ?? '' }}" placeholder="Numero de transaccion">
            </div>

            <div class="field">
                <label for="urbanizacion_id">Urbanizacion</label>
                <select id="urbanizacion_id" name="urbanizacion_id">
                    <option value="">Todas</option>
                    @foreach($urbanizaciones as $urbanizacion)
                        <option value="{{ $urbanizacion->id }}" @selected((string) ($filters['urbanizacion_id'] ?? '') === (string) $urbanizacion->id)>{{ $urbanizacion->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="lote">Manzano / Lote</label>
                <input id="lote" name="lote" value="{{ $filters['lote'] ?? '' }}" placeholder="Codigo de manzano o lote">
            </div>

            <div class="field">
                <label for="modalidad">Modalidad</label>
                <select id="modalidad" name="modalidad">
                    <option value="">Todas</option>
                    @foreach(\App\Models\CashMovement::MODALIDAD_LABELS as $modalidad => $label)
                        <option value="{{ $modalidad }}" @selected(($filters['modalidad'] ?? '') === $modalidad)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Todos</option>
                    @foreach(\App\Models\CashMovement::TIPOS as $tipo)
                        <option value="{{ $tipo }}" @selected(($filters['tipo'] ?? '') === $tipo)>{{ ucfirst($tipo) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="concepto">Concepto</label>
                <select id="concepto" name="concepto">
                    <option value="">Todos</option>
                    @foreach(\App\Models\CashMovement::CONCEPTOS as $concepto)
                        <option value="{{ $concepto }}" @selected(($filters['concepto'] ?? '') === $concepto)>{{ ucfirst($concepto) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="monto_min">Monto minimo</label>
                <input id="monto_min" type="number" step="0.01" min="0" name="monto_min" value="{{ $filters['monto_min'] ?? '' }}">
            </div>

            <div class="field">
                <label for="monto_max">Monto maximo</label>
                <input id="monto_max" type="number" step="0.01" min="0" name="monto_max" value="{{ $filters['monto_max'] ?? '' }}">
            </div>

            <div class="field">
                <label for="usuario_id">Usuario / Cajero</label>
                <select id="usuario_id" name="usuario_id">
                    <option value="">Todos</option>
                    @foreach($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected((string) ($filters['usuario_id'] ?? '') === (string) $usuario->id)>{{ $usuario->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </details>

    <div class="filter-actions">
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn secondary" href="{{ route('caja.index') }}">Limpiar</a>
    </div>
</form>

<div class="list-summary">
    Mostrando {{ $movimientos->firstItem() ?? 0 }}-{{ $movimientos->lastItem() ?? 0 }} de {{ $movimientos->total() }} movimientos
</div>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Metodo</th>
                <th>Usuario</th>
                <th>Referencia</th>
                <th>Monto</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientos as $movimiento)
                <tr>
                    <td>{{ $movimiento->fecha->format('d/m/Y') }}</td>
                    <td>{{ $movimiento->cliente?->nombre }}</td>
                    <td>{{ $movimiento->tipo }}</td>
                    <td>{{ $movimiento->concepto }}</td>
                    <td>{{ $metodoLabels[$movimiento->metodo_pago] ?? $movimiento->metodo_pago }}</td>
                    <td>{{ $movimiento->user?->name }}</td>
                    <td>{{ $movimiento->referencia ?: '-' }}</td>
                    <td>{{ number_format($movimiento->monto, 2) }}</td>
                    <td><span class="badge {{ $movimiento->estado }}">{{ $estadoLabels[$movimiento->estado] ?? $movimiento->estado }}</span></td>
                    <td class="actions">
                        <a class="btn secondary" href="{{ route('caja.show', $movimiento) }}">Ver</a>
                        @if($movimiento->estado === 'confirmado')
                            <a class="btn secondary" href="{{ route('pdf.recibo', $movimiento) }}" target="_blank" rel="noopener">Imprimir recibo</a>
                        @endif
                        @if($movimiento->estado === 'pendiente_verificacion')
                            @if(auth()->user()?->hasAnyRole(['administrador', 'gerente']))
                                <form method="POST" action="{{ route('caja.confirm', $movimiento) }}" onsubmit="return confirm('Confirma que deseas verificar y confirmar este pago?')">
                                    @csrf
                                    <button class="btn success">Confirmar</button>
                                </form>
                                <form method="POST" action="{{ route('caja.reject', $movimiento) }}" onsubmit="const m = prompt('Motivo obligatorio del rechazo'); if(!m) return false; this.motivo.value=m; return confirm('Confirma que deseas rechazar este pago?');">
                                    @csrf
                                    <input type="hidden" name="motivo">
                                    <button class="btn danger">Rechazar</button>
                                </form>
                            @endif
                        @endif
                        @can('anular caja')
                            @if($movimiento->estado === 'confirmado')
                                <form method="POST" action="{{ route('caja.annul', $movimiento) }}" onsubmit="const m = prompt('Motivo obligatorio de anulacion'); if(!m) return false; this.motivo.value=m; return confirm('Confirma que deseas anular este movimiento de caja?');">
                                    @csrf
                                    <input type="hidden" name="motivo">
                                    <button class="btn danger">Anular</button>
                                </form>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty-table">No se encontraron movimientos con los filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($movimientos->hasPages())
    <div class="pagination-wrapper">
        {{ $movimientos->appends(request()->query())->links() }}
    </div>
@endif
@endsection
