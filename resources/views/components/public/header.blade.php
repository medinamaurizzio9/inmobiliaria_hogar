@php
    $nav = [
        ['route' => 'public.home', 'label' => 'Inicio'],
        ['route' => 'public.about', 'label' => 'Nosotros'],
        ['route' => 'public.urbanizations', 'label' => 'Urbanizaciones'],
        ['route' => 'public.testimonials', 'label' => 'Testimonios'],
        ['route' => 'public.faq', 'label' => 'Preguntas frecuentes'],
        ['route' => 'public.contact', 'label' => 'Contacto'],
    ];
    $phone = $systemSettings['whatsapp'] ?: $systemSettings['celular'];
    $wa = \App\Support\WhatsAppLink::urlWithMessage($phone, 'Hola, quisiera información sobre sus proyectos inmobiliarios.');
@endphp
<header class="site-header" data-portal-header>
    <a class="site-logo" href="{{ route('public.home') }}" aria-label="Ir al inicio"><x-brand-logo variant="public-header" :show-name="empty($systemSettings['logo_main_url'])" /></a>
    <nav class="site-nav" id="portal-nav" aria-label="Navegación principal">
        @foreach($nav as $item)<a @class(['active' => request()->routeIs($item['route'])]) href="{{ route($item['route']) }}">{{ $item['label'] }}</a>@endforeach
    </nav>
    <div class="site-header-actions">
        <button class="site-login" type="button" data-login-open>Ingresar</button>
        @if($wa)<a class="site-whatsapp" href="{{ $wa }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>WhatsApp<small>{{ $phone }}</small></span></a>@endif
        <button class="portal-menu-toggle" type="button" aria-controls="portal-nav" aria-expanded="false" aria-label="Abrir menú" data-portal-menu><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    </div>
</header>
@if($errors->has('email') || $errors->has('password'))<div class="portal-login-error" role="alert">{{ $errors->first() }}</div>@endif
<dialog class="mobile-login" data-login-dialog @if($errors->has('email') || $errors->has('password')) open @endif>
    <form method="POST" action="{{ route('login.store') }}">@csrf
        <button class="dialog-close" type="button" data-login-close aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        <x-brand-logo variant="login" :prefer-login="true" />
        <span class="site-eyebrow">Acceso al CRM</span><h2>Iniciar sesión</h2>
        <label>Correo electrónico<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
        <label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
        <label class="login-remember"><input type="checkbox" name="remember" value="1"><span>Mantener sesión iniciada</span></label>
        @if($errors->has('email') || $errors->has('password'))<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
        <button class="site-button" type="submit">Ingresar al CRM</button>
    </form>
</dialog>
