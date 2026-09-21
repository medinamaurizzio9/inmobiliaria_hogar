@extends('layouts.app')

@section('content')
<div class="topbar"><h1 class="title">Importar clientes</h1><a class="btn secondary" href="{{ route('clientes.index') }}">Volver</a></div>

@if($errors->any())<div class="errors">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<div class="import-steps"><span class="active">1. Descargar plantilla</span><span class="active">2. Subir archivo</span><span>3. Validar</span><span>4. Confirmar</span></div>

<div class="card"><div class="card-body">
    <h2>Plantilla de clientes</h2>
    <p>Nombre y CI son obligatorios. Teléfono, email, dirección y email del asesor son opcionales. Esta importación no crea ventas, créditos, cuotas ni pagos.</p>
    <a class="btn secondary" href="{{ route('clientes.import.template') }}">Descargar plantilla XLSX</a>
</div></div>

<form class="card" method="POST" action="{{ route('clientes.import.preview') }}" enctype="multipart/form-data">
    @csrf
    <div class="card-body">
        <div class="field"><label for="archivo">Archivo CSV o XLSX</label><input id="archivo" type="file" name="archivo" accept=".csv,.xlsx" required></div>
        <p class="muted">Máximo 5 MB y 5.000 filas. El archivo se analiza antes de crear clientes.</p>
        <button class="btn" type="submit">Validar y mostrar vista previa</button>
    </div>
</form>
@endsection
