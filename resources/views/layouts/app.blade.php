<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>try{if(localStorage.getItem('crm_sidebar_collapsed')==='true')document.documentElement.classList.add('crm-sidebar-collapsed')}catch(e){}</script>
    <title>@yield('title', $systemSettings['system_name'] ?? 'IMPACTO URBANIZACIONES')</title>
    @include('partials.pwa-head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root{--primary:{{ $systemSettings['primary_color'] ?: '#879A32' }};--secondary:{{ $systemSettings['secondary_color'] ?: '#151815' }};}</style>
</head>
<body>
@auth
    <div class="shell">
        @include('layouts.partials.sidebar')
        <div class="crm-workspace">
            @include('layouts.partials.topbar')
            <main class="main main-content">
                @yield('content')
                <footer class="footer">{{ $systemSettings['footer_text'] ?? 'Version piloto - MVP funcional.' }}</footer>
            </main>
        </div>
        <button class="sidebar-overlay" type="button" data-sidebar-close aria-label="Cerrar navegación"></button>
    </div>
@else
    @yield('content')
@endauth
<script src="{{ asset('js/sidebar-menu.js') }}"></script>
<script src="{{ asset('js/password-toggle.js') }}"></script>
<script src="{{ asset('js/pwa.js') }}"></script>
</body>
</html>
