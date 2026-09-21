@extends('layouts.app')

@section('content')
<h1>Backups</h1>

<div class="card">
    <div class="card-body">
        <p>Genera un respaldo de la base de datos en el almacenamiento privado del sistema.</p>
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @error('backup')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route('admin.backups.store') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Generar backup</button>
        </form>
    </div>
</div>
@endsection
