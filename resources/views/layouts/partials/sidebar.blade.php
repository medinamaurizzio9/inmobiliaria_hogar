@php
    $user = auth()->user();
    $isSuperAdmin = $user?->hasRole('super administrador');
    $isAdmin = $user?->hasRole('administrador');
    $isGerente = $user?->hasRole('gerente');
    $isSupervisor = $user?->hasRole('supervisor');
    $isVendedor = $user?->hasRole('vendedor');
    $isCajero = $user?->hasRole('cajero');
    $isCliente = $user?->hasRole('cliente');
    $hasProject = (bool) $urbanizacionActual || $isCliente;
    $active = fn (array|string $patterns) => request()->routeIs(...(array) $patterns);
@endphp

<aside class="sidebar" id="crm-sidebar">
    <div class="sidebar-brand-row">
    <a class="brand" href="{{ $isCliente ? route('clientes.mi-cuenta') : '#' }}">
        @if(!empty($systemSettings['logo_main_url']))
            <img src="{{ $systemSettings['logo_main_url'] }}" alt="Logo" style="max-width:72px;max-height:72px;display:block;margin-bottom:8px;">
        @endif
        {{ $systemSettings['system_name'] ?? 'IMPACTO URBANIZACIONES' }}<span>{{ $systemSettings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</span>
    </a>
    <button class="sidebar-collapse" type="button" data-sidebar-toggle aria-label="Contraer navegación"><i class="fa-solid fa-angles-left"></i></button>
    </div>

    @unless($isCliente)
        <div class="current-project">
            <span>Urbanizacion actual</span>
            <strong>{{ $urbanizacionActual?->nombre ?? 'Sin seleccionar' }}</strong>
            <a href="{{ route('urbanizaciones.select') }}">Cambiar urbanizacion</a>
        </div>
        @if(($urbanizacionesDisponibles ?? collect())->isNotEmpty())
            <form method="POST" action="{{ route('urbanizaciones.select.store') }}" class="sidebar-selector">
                @csrf
                <select name="urbanizacion_id" onchange="this.form.submit()">
                    @foreach($urbanizacionesDisponibles as $item)
                        <option value="{{ $item->id }}" @selected($urbanizacionActual?->id === $item->id)>{{ $item->nombre }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    @endunless

    <nav class="nav accordion-nav" data-sidebar-accordion>
        @if($isCliente)
            <div class="client-sidebar-nav">
                <span class="client-sidebar-title">Mi cuenta</span>
                <a @class(['active' => $active('portal.perfil')]) href="{{ route('portal.perfil') }}"><i class="fa-regular fa-user"></i><span>Mi perfil</span></a>
                <a href="{{ route('clientes.mi-cuenta') }}#mis-terrenos"><i class="fa-regular fa-map"></i><span>Mis terrenos</span></a>
                <a @class(['active' => $active('portal.visitas')]) href="{{ route('portal.visitas') }}"><i class="fa-regular fa-calendar-check"></i><span>Reserva de visitas</span></a>
                <a @class(['active' => $active('portal.urbanizaciones')]) href="{{ route('portal.urbanizaciones') }}"><i class="fa-regular fa-building"></i><span>Ver urbanizaciones</span></a>
            </div>
        @elseif($user?->can('ver dashboard'))
            @php($isOpen = $active(['dashboard', 'urbanizaciones.select', 'clientes.mi-cuenta']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="inicio">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Principal"><span class="menu-icon"><i class="fa-solid fa-house"></i></span><span class="menu-label">Principal</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    @can('ver dashboard')<a @class(['sidebar-link', 'active' => $active('dashboard')]) href="{{ route('dashboard') }}">Dashboard</a>@endcan
                    <a @class(['sidebar-link', 'active' => $active('urbanizaciones.select')]) href="{{ route('urbanizaciones.select') }}">Seleccionar urbanizacion</a>
                </div>
            </div>
        @endif

        @if($hasProject && ($isAdmin || $isGerente || $isSupervisor || $isVendedor))
            @php($isOpen = $active(['urbanizaciones.*', 'manzanos.*', 'lotes.*', 'mapa', 'lotes.import.*']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="terrenos">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Gestión inmobiliaria"><span class="menu-icon"><i class="fa-solid fa-map-location-dot"></i></span><span class="menu-label">Gestión inmobiliaria</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    @if($isAdmin || $isGerente)
                        @can('ver lotes')<a @class(['sidebar-link', 'active' => $active('urbanizaciones.*')]) href="{{ route('urbanizaciones.index') }}">Urbanizaciones</a>@endcan
                        @can('ver lotes')<a @class(['sidebar-link', 'active' => $active('manzanos.*')]) href="{{ route('manzanos.index') }}">Manzanos</a>@endcan
                        @can('ver lotes')<a @class(['sidebar-link', 'active' => $active('lotes.index')]) href="{{ route('lotes.index') }}">Lotes</a>@endcan
                        @can('ver lotes')<a @class(['sidebar-link', 'active' => $active('mapa')]) href="{{ route('mapa') }}">Mapa de disponibilidad</a>@endcan
                        @can('crear lotes')<a @class(['sidebar-link', 'active' => $active('lotes.import.*')]) href="{{ route('lotes.import.create') }}">Importar lotes</a>@endcan
                    @else
                        @can('ver lotes')<a @class(['sidebar-link', 'active' => $active('mapa')]) href="{{ route('mapa') }}">Mapa de disponibilidad</a>@endcan
                        @can('ver lotes')<a @class(['sidebar-link', 'active' => $active('lotes.index')]) href="{{ route('lotes.index') }}">Lotes disponibles</a>@endcan
                    @endif
                </div>
            </div>
        @endif

        @if($hasProject && ($isAdmin || $isGerente || $isSupervisor || $isVendedor))
            @php($isOpen = $active(['reservas.*', 'clientes.*', 'ventas.*']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="comercial">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Comercial"><span class="menu-icon"><i class="fa-solid fa-handshake"></i></span><span class="menu-label">Comercial</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    @if($isAdmin || $isGerente)
                        @can('ver reservas')<a @class(['sidebar-link', 'active' => $active('reservas.*')]) href="{{ route('reservas.index') }}">Reservas</a>@endcan
                        @can('ver clientes')<a @class(['sidebar-link', 'active' => $active('clientes.index')]) href="{{ route('clientes.index') }}">Clientes / Interesados</a>@endcan
                        @can('ver ventas')<a @class(['sidebar-link', 'active' => $active('ventas.*')]) href="{{ route('ventas.index') }}">Ventas</a>@endcan
                    @elseif($isSupervisor)
                        @can('ver reservas')<a @class(['sidebar-link', 'active' => $active('reservas.*')]) href="{{ route('reservas.index') }}">Reservas del equipo</a>@endcan
                        @can('ver clientes')<a @class(['sidebar-link', 'active' => $active('clientes.index')]) href="{{ route('clientes.index') }}">Clientes / Interesados</a>@endcan
                    @else
                        @can('ver reservas')<a @class(['sidebar-link', 'active' => $active('reservas.*')]) href="{{ route('reservas.index') }}">Mis reservas</a>@endcan
                        @can('ver clientes')<a @class(['sidebar-link', 'active' => $active('clientes.index')]) href="{{ route('clientes.index') }}">Clientes / Interesados</a>@endcan
                    @endif
                </div>
            </div>
        @endif

        @if($hasProject && ($isAdmin || $isGerente || $isCajero) && $user?->can('cobrar cuotas'))
            @php($isOpen = $active(['caja.*', 'cobranza.*']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="finanzas">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Finanzas"><span class="menu-icon"><i class="fa-solid fa-wallet"></i></span><span class="menu-label">Finanzas</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    <a @class(['sidebar-link', 'active' => $active('cobranza.*')]) href="{{ route('cobranza.index') }}">Cobranza</a>
                    <a @class(['sidebar-link', 'active' => $active('caja.*')]) href="{{ route('caja.index') }}">Caja</a>
                </div>
            </div>
        @endif

        @if($hasProject && $user?->can('ver reportes'))
            @php($isOpen = $active(['reportes.*', 'export.csv']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="reportes">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Reportes"><span class="menu-icon"><i class="fa-solid fa-chart-column"></i></span><span class="menu-label">Reportes</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    <a @class(['sidebar-link', 'active' => $active('reportes.index')]) href="{{ route('reportes.index') }}">Resumen</a>
                    @if($isAdmin || $isGerente)<a @class(['sidebar-link', 'active' => $active('reportes.gerencia')]) href="{{ route('reportes.gerencia') }}">Reportes gerenciales</a>@endif
                    <a @class(['sidebar-link', 'active' => $active('reportes.lotes-estado')]) href="{{ route('reportes.lotes-estado') }}">Lotes por estado</a>
                    <a @class(['sidebar-link', 'active' => $active('reportes.reservas')]) href="{{ route('reportes.reservas') }}">Reservas</a>
                    @can('ver reporte mejor vendedor')<a @class(['sidebar-link', 'active' => $active('reportes.mejor-vendedor')]) href="{{ route('reportes.mejor-vendedor') }}">Mejor vendedor</a>@endcan
                    <a @class(['sidebar-link', 'active' => $active('reportes.cuotas')]) href="{{ route('reportes.cuotas') }}">Cuotas pendientes/vencidas</a>
                    <a @class(['sidebar-link', 'active' => $active('reportes.ingresos')]) href="{{ route('reportes.ingresos') }}">Ingresos</a>
                    <a @class(['sidebar-link', 'active' => $active('reportes.estado-cuenta')]) href="{{ route('reportes.estado-cuenta') }}">Estado de cuenta</a>
                    @can('exportar reportes')<a @class(['sidebar-link', 'active' => $active('reportes.exportaciones')]) href="{{ route('reportes.exportaciones') }}">Exportaciones</a>@endcan
                </div>
            </div>
        @endif

        @if($user?->can('editar asesores') || $user?->can('asignar urbanizaciones a asesores'))
            @php($isOpen = $active(['asesores.*', 'supervisores.*', 'grupos-comerciales.*', 'urbanizaciones.asignaciones']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="equipo-comercial">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Equipo comercial"><span class="menu-icon"><i class="fa-solid fa-users"></i></span><span class="menu-label">Equipo comercial</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    @can('editar asesores')<a @class(['sidebar-link', 'active' => $active('asesores.*')]) href="{{ route('asesores.index') }}">{{ $isSupervisor ? 'Asesores de mi equipo' : 'Asesores' }}</a>@endcan
                    @if($isAdmin)
                        <a @class(['sidebar-link', 'active' => $active('supervisores.*')]) href="{{ route('supervisores.index') }}">Supervisores</a>
                        <a @class(['sidebar-link', 'active' => $active('grupos-comerciales.*')]) href="{{ route('grupos-comerciales.index') }}">Grupos comerciales</a>
                    @elseif($isSupervisor)
                        <a @class(['sidebar-link', 'active' => $active('grupos-comerciales.*')]) href="{{ route('grupos-comerciales.index') }}">Mis grupos</a>
                    @endif
                    @can('asignar urbanizaciones a asesores')<a @class(['sidebar-link', 'active' => $active('urbanizaciones.asignaciones')]) href="{{ route('urbanizaciones.asignaciones') }}">Asignar urbanizaciones</a>@endcan
                </div>
            </div>
        @endif

        @if($isSuperAdmin || $isAdmin || $isGerente)
            @php($isOpen = $active('admin.*'))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="administracion">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}" title="Administración"><span class="menu-icon"><i class="fa-solid fa-sliders"></i></span><span class="menu-label">Administración</span><i class="fa-solid fa-chevron-down menu-chevron"></i></button>
                <div class="sidebar-submenu">
                    @if($isSuperAdmin || $isAdmin)
                        <a @class(['sidebar-link', 'active' => $active(['admin.usuarios', 'admin.usuarios.*'])]) href="{{ route('admin.usuarios') }}">Usuarios del sistema</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.compradores*')]) href="{{ route('admin.compradores') }}">Usuarios compradores</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.roles')]) href="{{ route('admin.roles') }}">Roles y permisos</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.configuracion-general')]) href="{{ route('admin.configuracion-general') }}">Configuracion general</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.noticias.*')]) href="{{ route('admin.noticias.index') }}">Noticias y novedades</a>
                    @endif
                    <a @class(['sidebar-link', 'active' => $active('admin.configuracion')]) href="{{ route('admin.configuracion') }}">Configuracion comercial</a>
                    <a @class(['sidebar-link', 'active' => $active('admin.configuracion-financiera')]) href="{{ route('admin.configuracion-financiera') }}">Configuracion financiera</a>
                    <a @class(['sidebar-link', 'active' => $active('admin.urbanizacion-gps.*')]) href="{{ route('admin.urbanizacion-gps.index') }}">Configuracion Urbanizacion GPS</a>
                    @if($isSuperAdmin || $isAdmin)
                        <a @class(['sidebar-link', 'active' => $active('admin.auditoria')]) href="{{ route('admin.auditoria') }}">Auditoria</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.backups')]) href="{{ route('admin.backups') }}">Backups</a>
                    @endif
                </div>
            </div>
        @endif

        <form class="sidebar-logout" method="POST" action="{{ route('logout') }}">@csrf<button class="logout" title="Cerrar sesión"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Cerrar sesión</span></button></form>
    </nav>
</aside>
