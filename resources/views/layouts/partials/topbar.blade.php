@php
    $currentUser = auth()->user();
    $roleLabel = $currentUser?->roles->pluck('name')->map(fn ($role) => ucfirst($role))->join(', ');
    $initials = collect(explode(' ', trim($currentUser?->name ?? 'U')))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
    $pendingNotificationCount = $pendingPayments ?? (($currentUser?->can('cobrar cuotas') && $urbanizacionActual)
        ? \App\Support\UrbanizacionContext::cashMovements(\App\Models\CashMovement::query(), $urbanizacionActual->id)->where('estado', 'pendiente_verificacion')->count()
        : 0);
@endphp
<header class="crm-topbar">
    <div class="topbar-left">
        <button class="icon-button sidebar-trigger" type="button" data-sidebar-toggle aria-controls="crm-sidebar" aria-label="Abrir navegación" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <a class="mobile-topbar-brand" href="{{ $currentUser?->hasRole('cliente') ? route('clientes.mi-cuenta') : ($urbanizacionActual ? route('dashboard') : route('urbanizaciones.select')) }}" aria-label="Ir al inicio">
            <x-brand-logo variant="topbar" alt="" />
        </a>
        <form class="crm-search" action="{{ auth()->user()?->can('ver clientes') && $urbanizacionActual ? route('clientes.index') : '#' }}" method="GET" role="search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input name="q" type="search" placeholder="Buscar en el CRM..." aria-label="Buscar en el CRM">
            <kbd>Ctrl K</kbd>
        </form>
    </div>
    <div class="topbar-actions">
        @can('crear clientes')<a class="icon-button topbar-create" href="{{ route('clientes.create') }}" title="Nuevo cliente" aria-label="Nuevo cliente"><i class="fa-solid fa-plus"></i></a>@endcan
        @if($currentUser?->can('cobrar cuotas'))<a class="icon-button notification-button" href="{{ $urbanizacionActual ? route('cobranza.index').'#pendientes' : route('urbanizaciones.select') }}" title="Pagos pendientes de verificación" aria-label="Notificaciones: {{ $pendingNotificationCount }} pendientes"><i class="fa-regular fa-bell"></i>@if($pendingNotificationCount > 0)<span>{{ $pendingNotificationCount }}</span>@endif</a>@endif
        <button class="icon-button" type="button" title="Ayuda" aria-label="Ayuda"><i class="fa-regular fa-circle-question"></i></button>
        <div class="user-menu">
            <button class="user-menu-trigger" type="button" data-user-menu aria-expanded="false">
                <x-crm.avatar :name="$currentUser?->name ?? 'Usuario'" :path="$currentUser?->profilePhotoPath()" size="sm" class="user-avatar" />
                <span class="user-copy"><strong>{{ $currentUser?->name }}</strong><small>{{ $roleLabel }}</small></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="user-dropdown" data-user-dropdown hidden>
                @if(auth()->user()?->hasRole('cliente'))<a href="{{ route('clientes.mi-cuenta') }}"><i class="fa-solid fa-table-columns"></i> Mi cuenta</a><a href="{{ route('portal.perfil') }}"><i class="fa-regular fa-user"></i> Mi perfil</a>@endif
                @unless(auth()->user()?->hasRole('cliente'))<a href="{{ route('password.change') }}"><i class="fa-regular fa-user"></i> Mi perfil y seguridad</a>@endunless
                @if(auth()->user()?->hasAnyRole(['super administrador','administrador']))<a href="{{ route('admin.configuracion-general') }}"><i class="fa-solid fa-gear"></i> Configuración</a>@endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión</button></form>
            </div>
        </div>
    </div>
</header>
