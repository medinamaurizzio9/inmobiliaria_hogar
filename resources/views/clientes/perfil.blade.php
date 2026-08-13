@extends('layouts.app')
@section('title', 'Mi perfil')
@section('content')
<div class="client-portal">
    <header class="client-section-header"><div><span class="client-eyebrow">Información personal</span><h1>Mi perfil</h1><p>Consulta los datos asociados a tu cuenta.</p></div><a class="btn secondary" href="{{ route('clientes.mi-cuenta') }}"><i class="fa-solid fa-arrow-left"></i> Volver a mi cuenta</a></header>
    <section class="client-profile-card">
        <div class="client-profile-identity"><x-crm.avatar :name="$cliente->nombre" :path="$cliente->foto" size="lg" /><div><h2>{{ $cliente->nombre }}</h2><span>Comprador</span></div></div>
        <dl class="client-profile-details"><div><dt>CI</dt><dd>{{ $cliente->documento ?: 'No registrado' }}</dd></div><div><dt>Celular</dt><dd>{{ $cliente->telefono ?: 'No registrado' }}</dd></div><div><dt>Correo</dt><dd>{{ $cliente->email ?: 'No registrado' }}</dd></div><div><dt>Dirección</dt><dd>{{ $cliente->direccion ?: 'No registrada' }}</dd></div></dl>
        <p class="client-profile-note"><i class="fa-solid fa-shield-halved"></i> Para actualizar información sensible o solicitar un cambio de acceso, contacta al equipo administrativo.</p>
    </section>
</div>
@endsection
