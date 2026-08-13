@php
    $currentUser = auth()->user();
    $roleLabel = $currentUser?->roles->pluck('name')->map(fn ($role) => ucfirst($role))->join(', ');
    $initials = collect(explode(' ', trim($currentUser?->name ?? 'U')))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
@endphp
<header class="crm-topbar">
    <div class="topbar-left">
        <button class="icon-button sidebar-trigger" type="button" data-sidebar-toggle aria-label="Contraer o expandir navegación" aria-expanded="true"><i class="fa-solid fa-bars"></i></button>
        <form class="crm-search" action="{{ auth()->user()?->can('ver clientes') && $urbanizacionActual ? route('clientes.index') : '#' }}" method="GET" role="search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input name="q" type="search" placeholder="Buscar en el CRM..." aria-label="Buscar en el CRM">
            <kbd>Ctrl K</kbd>
        </form>
    </div>
    <div class="topbar-actions">
        @can('crear clientes')<a class="icon-button topbar-create" href="{{ route('clientes.create') }}" title="Nuevo cliente" aria-label="Nuevo cliente"><i class="fa-solid fa-plus"></i></a>@endcan
        <button class="icon-button" type="button" title="Notificaciones" aria-label="Notificaciones"><i class="fa-regular fa-bell"></i></button>
        <button class="icon-button" type="button" title="Ayuda" aria-label="Ayuda"><i class="fa-regular fa-circle-question"></i></button>
        <div class="user-menu">
            <button class="user-menu-trigger" type="button" data-user-menu aria-expanded="false">
                <span class="user-avatar">{{ $initials ?: 'U' }}</span>
                <span class="user-copy"><strong>{{ $currentUser?->name }}</strong><small>{{ $roleLabel }}</small></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>
            <div class="user-dropdown" data-user-dropdown hidden>
                @if(auth()->user()?->hasRole('cliente'))<a href="{{ route('clientes.mi-cuenta') }}"><i class="fa-regular fa-user"></i> Mi perfil</a>@endif
                @if(auth()->user()?->hasAnyRole(['super administrador','administrador']))<a href="{{ route('admin.configuracion-general') }}"><i class="fa-solid fa-gear"></i> Configuración</a>@endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión</button></form>
            </div>
        </div>
    </div>
</header>
