<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Disponibilidad - {{ $systemSettings['system_name'] }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="public-page">
    @inject('pricingService', 'App\Services\LotPricingService')
    <header class="public-header crm-public-header"><a class="public-brand" href="{{ route('disponibilidad.publica') }}">@if($systemSettings['logo_main_url'])<img src="{{ $systemSettings['logo_main_url'] }}" alt="{{ $systemSettings['system_name'] }}">@endif<span><strong>{{ $systemSettings['system_name'] }}</strong><small>{{ $systemSettings['system_subtitle'] }}</small></span></a><nav><a href="#urbanizaciones">Urbanizaciones</a><a href="#disponibles">Lotes disponibles</a><a href="#consulta">Contacto</a></nav><a class="btn secondary" href="{{ route('login') }}">Iniciar sesión</a></header>

    <section class="public-hero"><div><span class="eyebrow">Inversión inmobiliaria</span><h1>Encuentra el terreno para construir tu futuro</h1><p>Explora urbanizaciones, ubicaciones y lotes disponibles con información actualizada.</p><div class="actions"><a class="btn" href="#disponibles">Ver disponibilidad</a><a class="btn secondary" href="#consulta">Contactarnos</a></div></div><div class="public-hero-mark"><i class="fa-solid fa-building-circle-check"></i><strong>Disponibilidad real</strong><span>Consulta directa por urbanización</span></div></section>

    <form id="urbanizaciones" method="GET" action="{{ route('disponibilidad.publica') }}" class="card form public-filter">
        <div class="field">
            <label>Urbanizacion</label>
            <select name="urbanizacion_id" onchange="this.form.submit()">
                @foreach($urbanizaciones as $item)
                    <option value="{{ $item->id }}" @selected($urbanizacion?->id === $item->id)>{{ $item->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Ver disponibilidad</button></div>
    </form>

    @if($urbanizacion)
        @php
            $lotes = $urbanizacion->manzanos->flatMap->lotes;
            $locatedLotes = $lotes->filter(fn ($lote) => ! is_null($lote->coord_x) && ! is_null($lote->coord_y));
            $counts = $lotes->countBy('estado');
        @endphp

        <section class="card">
            <div class="map-toolbar">
                <div>
                    <h2>{{ $urbanizacion->nombre }}</h2>
                    <p class="muted">{{ $urbanizacion->ubicacion }}</p>
                    @if(!empty($publicLink))
                        <div class="public-link-box">
                            <span>Link publico:</span>
                            <a href="{{ $publicLink }}" target="_blank" rel="noopener">{{ $publicLink }}</a>
                            <button class="btn secondary" type="button" data-copy-link="{{ $publicLink }}">Copiar link publico</button>
                            @if(!empty($publicQrDataUri))
                                <img class="public-link-qr" src="{{ $publicQrDataUri }}" alt="QR de disponibilidad publica">
                            @endif
                        </div>
                    @endif
                </div>
                <div class="legend">
                    @foreach(['disponible' => 'Disponible', 'vendido' => 'Vendido', 'reservado' => 'Reservado', 'bloqueado' => 'Bloqueado'] as $key => $label)
                        <span><i class="legend-dot {{ $key }}"></i>{{ $label }}: {{ $counts[$key] ?? 0 }}</span>
                    @endforeach
                </div>
            </div>

            @if($urbanizacion->plano_imagen)
                <p class="muted map-help">Las posiciones se guardan proporcionalmente, por eso se mantienen en celular y escritorio.</p>
                <div class="map-shell">
                    <div class="map-zoom-controls">
                        <button class="btn secondary" type="button" data-zoom-in title="Acercar" aria-label="Acercar">Zoom +</button>
                        <button class="btn secondary" type="button" data-zoom-out title="Alejar" aria-label="Alejar">Zoom -</button>
                        <button class="btn secondary" type="button" data-zoom-reset title="Restablecer vista" aria-label="Restablecer vista">Restablecer</button>
                        <button class="btn secondary" type="button" data-zoom-fullscreen title="Pantalla completa" aria-label="Pantalla completa">Pantalla completa</button>
                        <span class="zoom-value" data-zoom-value>100%</span>
                    </div>
                    <div class="plan-map-viewport" id="public-plan-map">
                        <div class="plan-map-layer" id="public-plan-map-layer">
                            <img class="plan-map-image" src="{{ asset('storage/'.$urbanizacion->plano_imagen) }}" alt="Plano {{ $urbanizacion->nombre }}">
                            @foreach($locatedLotes as $lote)
                                <span class="map-point lot-point public {{ $lote->estado }}" style="left: {{ max(0, min(100, (float) $lote->coord_x)) }}%; top: {{ max(0, min(100, (float) $lote->coord_y)) }}%;"><span>{{ $lote->codigo }}</span></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="empty-plan">Esta urbanizacion aun no tiene plano cargado</div>
            @endif
        </section>

        <section id="disponibles" class="card" style="margin-top:18px;">
            <h2>Lotes disponibles</h2>
            <table class="table">
                <thead><tr><th>Manzano</th><th>Lote</th><th>Superficie</th>@if($urbanizacion->mostrar_precio_publico)<th>Precio</th>@endif<th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($lotes->where('estado', 'disponible')->sortBy([['manzano.codigo', 'asc'], ['codigo', 'asc']]) as $lote)
                    <tr>
                        <td>{{ $lote->manzano->codigo }}</td>
                        <td>{{ $lote->codigo }}</td>
                        <td>{{ number_format($lote->superficie, 2) }} m2</td>
                        @if($urbanizacion->mostrar_precio_publico)
                            @php($pricePayload = $pricingService->payload($lote))
                            <td>{{ $pricingService->formatUsd($pricePayload['credit_usd']) }}<br><span class="muted">{{ $pricingService->formatBs($pricePayload['credit_bs']) }}</span></td>
                        @endif
                        <td><span class="badge {{ $lote->estado }}">{{ $lote->estado }}</span></td>
                        <td>@if($lote->estado === 'disponible')<a class="btn secondary" href="#consulta">Consultar con asesor</a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section id="consulta" class="card public-cta">
            <h2>Consultar con asesor</h2>
            <p class="muted">Escribenos indicando urbanizacion, manzano y lote de interes para confirmar disponibilidad actual.</p>
            <a class="btn" href="mailto:ventas@impacto.test?subject=Consulta%20de%20lote%20{{ urlencode($urbanizacion->nombre) }}">Consultar con asesor</a>
        </section>
    @else
        <div class="card">No hay urbanizaciones disponibles.</div>
    @endif
</main>
<script src="{{ asset('js/map-zoom.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const map = document.getElementById('public-plan-map');
    const layer = document.getElementById('public-plan-map-layer');
    if (!map || !layer || typeof window.createImpactoMapZoom !== 'function') return;

    const shell = map.closest('.map-shell');
    window.createImpactoMapZoom({
        map,
        layer,
        zoomIn: shell?.querySelector('[data-zoom-in]'),
        zoomOut: shell?.querySelector('[data-zoom-out]'),
        reset: shell?.querySelector('[data-zoom-reset]'),
        fullscreen: shell?.querySelector('[data-zoom-fullscreen]'),
        zoomLabel: shell?.querySelector('[data-zoom-value]'),
    });
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-link]');
    if (!button) return;

    await navigator.clipboard.writeText(button.dataset.copyLink);
    button.textContent = 'Copiado';
    setTimeout(() => button.textContent = 'Copiar link publico', 1600);
});
</script>
</body>
</html>
