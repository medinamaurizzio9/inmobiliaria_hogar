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

<aside class="sidebar">
    <div class="brand">
        @if(!empty($systemSettings['logo_main']))
            <img src="{{ asset('storage/'.$systemSettings['logo_main']) }}" alt="Logo" style="max-width:72px;max-height:72px;display:block;margin-bottom:8px;">
        @endif
        {{ $systemSettings['system_name'] ?? 'IMPACTO URBANIZACIONES' }}<span>{{ $systemSettings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</span>
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
        @if($isCliente || $user?->can('ver dashboard'))
            @php($isOpen = $active(['dashboard', 'urbanizaciones.select', 'clientes.mi-cuenta']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="inicio">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>+</span> Inicio</button>
                <div class="sidebar-submenu">
                    @if($isCliente)
                        <a @class(['sidebar-link', 'active' => $active('clientes.mi-cuenta')]) href="{{ route('clientes.mi-cuenta') }}">Inicio</a>
                        <a class="sidebar-link" href="{{ route('clientes.mi-cuenta') }}#mis-terrenos">Mis terrenos</a>
                        <a class="sidebar-link" href="{{ route('clientes.mi-cuenta') }}#pagos">Pagos</a>
                        <a class="sidebar-link" href="{{ route('clientes.mi-cuenta') }}#mis-terrenos">Documentos</a>
                        <a class="sidebar-link" href="{{ route('clientes.mi-cuenta') }}#perfil">Mi perfil</a>
                    @else
                        @can('ver dashboard')<a @class(['sidebar-link', 'active' => $active('dashboard')]) href="{{ route('dashboard') }}">Dashboard</a>@endcan
                        <a @class(['sidebar-link', 'active' => $active('urbanizaciones.select')]) href="{{ route('urbanizaciones.select') }}">Seleccionar urbanizacion</a>
                    @endif
                </div>
            </div>
        @endif

        @if($hasProject && ($isAdmin || $isGerente || $isSupervisor || $isVendedor))
            @php($isOpen = $active(['urbanizaciones.*', 'manzanos.*', 'lotes.*', 'mapa', 'lotes.import.*']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="terrenos">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>#</span> Terrenos</button>
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
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>*</span> Comercial</button>
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
            @php($isOpen = $active(['cuotas.*', 'caja.*', 'cobranza.*']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="finanzas">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>$</span> Finanzas</button>
                <div class="sidebar-submenu">
                    <a @class(['sidebar-link', 'active' => $active('cobranza.*')]) href="{{ route('cobranza.index') }}">Cobranza</a>
                    <a @class(['sidebar-link', 'active' => $active('cuotas.*')]) href="{{ route('cuotas.index') }}">Cuotas</a>
                    <a @class(['sidebar-link', 'active' => $active('caja.*')]) href="{{ route('caja.index') }}">Caja</a>
                </div>
            </div>
        @endif

        @if($hasProject && $user?->can('ver reportes'))
            @php($isOpen = $active(['reportes.*', 'export.csv']))
            <div @class(['sidebar-group', 'open' => $isOpen, 'active' => $isOpen]) data-menu-key="reportes">
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>%</span> Reportes</button>
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
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>@</span> Equipo comercial</button>
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
                <button class="sidebar-group-toggle" type="button" data-menu-toggle aria-expanded="{{ $isOpen ? 'true' : 'false' }}"><span>=</span> Administracion</button>
                <div class="sidebar-submenu">
                    @if($isSuperAdmin || $isAdmin)
                        <a @class(['sidebar-link', 'active' => $active(['admin.usuarios', 'admin.usuarios.*'])]) href="{{ route('admin.usuarios') }}">Usuarios</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.roles')]) href="{{ route('admin.roles') }}">Roles y permisos</a>
                        <a @class(['sidebar-link', 'active' => $active('admin.configuracion-general')]) href="{{ route('admin.configuracion-general') }}">Configuracion general</a>
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

        <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout">Salir</button></form>
    </nav>
</aside>
