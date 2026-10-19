<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', ($systemSettings['system_name'] ?: $systemSettings['company_name']).' — Terrenos y urbanizaciones')</title>
    <meta name="description" content="@yield('description', 'Conoce nuestros proyectos inmobiliarios y consulta la disponibilidad actual de terrenos.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', $systemSettings['system_name'] ?: $systemSettings['company_name'])">
    <meta property="og:description" content="@yield('description', 'Proyectos inmobiliarios y disponibilidad actualizada.')">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@elseif($systemSettings['landing_hero_image_url'])<meta property="og:image" content="{{ $systemSettings['landing_hero_image_url'] }}">@endif
    @include('partials.pwa-head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-site.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/public-site-compat.css') }}?v=5">
</head>
<body class="hogar-public">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <x-public.header />
    <main id="contenido">@yield('content')</main>
    <x-public.footer />
    <script src="{{ asset('js/password-toggle.js') }}"></script>
    <script src="{{ asset('js/public-portal.js') }}"></script>
    <script src="{{ asset('js/public-site.js') }}?v=2"></script>
    <script src="{{ asset('js/pwa.js') }}"></script>
    @stack('scripts')
</body>
</html>
