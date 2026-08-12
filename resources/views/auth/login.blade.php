@extends('layouts.app')

@section('content')
<div
    @class(['login-page', 'has-background' => !empty($systemSettings['login_background_url'])])
    @if(!empty($systemSettings['login_background_url']))
        style="background-image: url('{{ $systemSettings['login_background_url'] }}');"
    @endif
>
    <form method="POST" action="{{ route('login.store') }}" class="login-card">
        @csrf
        <header class="login-heading">
            @if($logoUrl = ($systemSettings['logo_login_url'] ?: $systemSettings['logo_main_url']))
                <img class="login-logo" src="{{ $logoUrl }}" alt="Logo">
            @endif
            <h1>{{ $systemSettings['system_name'] ?? 'IMPACTO URBANIZACIONES' }}</h1>
            <p>{{ $systemSettings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</p>
            <span>Ingreso administrativo</span>
        </header>
        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif
        <div class="field login-field">
            <label>Email</label>
            <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>
        <div class="field login-field">
            <label>Contraseña</label>
            <input name="password" type="password" required autocomplete="current-password">
        </div>
        <label class="login-remember">
            <input type="checkbox" name="remember" value="1"> Recordarme
        </label>
        <button class="btn login-submit" type="submit">Ingresar</button>
    </form>
</div>
@endsection
