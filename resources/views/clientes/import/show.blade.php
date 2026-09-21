@extends('layouts.app')

@section('content')
@php
    $rows = $batch->rows;
    $ready = $rows->where('status', 'ready')->count();
    $existing = $rows->where('status', 'existing')->count();
    $matches = $rows->where('status', 'match')->count();
    $errorsCount = $rows->where('status', 'error')->count();
    $completed = $batch->status === 'completed';
    $statusLabels = ['ready'=>'LISTO','existing'=>'YA EXISTE','match'=>'COINCIDENCIA','error'=>'ERROR','created'=>'CREADO'];
@endphp
<div class="topbar"><div><h1 class="title">{{ $completed ? 'Importación completada' : 'Vista previa de importación' }}</h1><p>{{ $batch->original_name }}</p></div><a class="btn secondary" href="{{ route('clientes.index') }}">Ver clientes</a></div>
@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<div class="import-steps"><span class="active">1. Plantilla</span><span class="active">2. Archivo</span><span class="active">3. Validación</span><span @class(['active'=>$completed])>4. Confirmación</span></div>
<div class="import-summary">
    <div><strong>{{ $batch->total_rows }}</strong><span>Total filas</span></div>
    <div class="ready"><strong>{{ $completed ? $batch->created_rows : $ready }}</strong><span>{{ $completed ? 'Creadas' : 'Listas' }}</span></div>
    <div><strong>{{ $batch->existing_rows ?: $existing }}</strong><span>Ya existentes</span></div>
    <div class="match"><strong>{{ $batch->match_rows ?: $matches }}</strong><span>Coincidencias</span></div>
    <div class="error"><strong>{{ $batch->error_rows ?: $errorsCount }}</strong><span>Errores</span></div>
</div>

<form method="POST" action="{{ route('clientes.import.confirm', $batch) }}" data-import-confirm-form>
@csrf
<div class="table-scroll"><table class="table import-table"><thead><tr><th>Fila</th><th>Nombre</th><th>CI</th><th>Teléfono</th><th>Email</th><th>Asesor</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
@foreach($rows as $row)
<tr class="import-row-{{ $row->status }}"><td>{{ $row->row_number }}</td><td>{{ $row->payload['nombre'] }}</td><td>{{ $row->payload['documento'] }}</td><td>{{ $row->payload['telefono'] ?: '—' }}</td><td>{{ $row->payload['email'] ?: '—' }}</td><td>{{ $row->payload['asesor_email'] ?: 'Importador' }}</td><td><span class="import-status">{{ $statusLabels[$row->status] ?? strtoupper($row->status) }}</span>@if(!$completed && $row->status==='match')<label class="import-accept"><input type="checkbox" name="accepted_matches[]" value="{{ $row->id }}"> Confirmar como persona diferente</label>@endif</td><td>{{ $row->detail }}</td></tr>
@endforeach
</tbody></table></div>
@unless($completed)
<div class="actions import-actions"><button class="btn" type="submit" onclick="this.disabled=true;this.form.submit()">Confirmar importación</button></div>
@endunless
</form>
<div class="actions">
    @if($completed)<a class="btn secondary" href="{{ route('clientes.import.report', $batch) }}">Descargar reporte CSV</a>@else<form method="POST" action="{{ route('clientes.import.cancel', $batch) }}">@csrf @method('DELETE')<button class="btn secondary" type="submit">Cancelar importación</button></form>@endif
</div>
@endsection
