@extends('layouts.app')

@section('title', 'Iniciar sesión')
@section('content')
<main @class(['crm-login', 'login-page', 'has-background' => !empty($systemSettings['login_background_url'])])>
    <section
        @class(['login-visual', 'has-background' => !empty($systemSettings['login_background_url'])])
        @if(!empty($systemSettings['login_background_url'])) style="background-image:url('{{ $systemSettings['login_background_url'] }}')" @endif
    >
        <div class="login-visual-overlay"></div>
        <div class="login-visual-content">
            <x-brand-logo variant="login-hero" :prefer-login="true" />
            <span class="eyebrow">CRM inmobiliario</span>
            <h1>{{ $systemSettings['system_name'] ?? 'HOGAR INMOBILIARIA' }}</h1>
            <p>Gestiona urbanizaciones, clientes y oportunidades comerciales desde un solo lugar.</p>
        </div>
    </section>
    <section class="login-panel">
        <form method="POST" action="{{ route('login.store') }}" class="login-card">
            @csrf
            <header class="login-heading">
                <x-brand-logo variant="login" :prefer-login="true" />
                <span class="eyebrow">Acceso seguro</span>
                <h2>Iniciar sesión</h2>
                <p>{{ $systemSettings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</p>
            </header>
            @if ($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
            <div class="field login-field"><label for="email">Correo electrónico</label><div class="input-icon"><i class="fa-regular fa-envelope"></i><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nombre@empresa.com"></div></div>
            <div class="field login-field"><label for="password">Contraseña</label><div class="input-icon"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña"></div></div>
            <label class="login-remember"><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>
            <button class="btn login-submit" type="submit">Ingresar al CRM <i class="fa-solid fa-arrow-right"></i></button>
        </form>
    </section>
</main>
@endsection
