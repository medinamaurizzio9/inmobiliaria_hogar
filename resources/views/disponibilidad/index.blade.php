<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $urbanizacion?->nombre ?? 'Disponibilidad' }} - {{ $systemSettings['system_name'] }}</title>
    @include('partials.pwa-head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="project-public-body">
<main class="project-public-page">
    <header class="public-header crm-public-header project-public-header">
        <a class="public-brand" href="{{ url('/') }}">
            @if($systemSettings['logo_main_url'])<img src="{{ $systemSettings['logo_main_url'] }}" alt="{{ $systemSettings['system_name'] }}">@endif
            <span><strong>{{ $systemSettings['system_name'] }}</strong><small>{{ $systemSettings['system_subtitle'] }}</small></span>
        </a>
        <nav><a href="#informacion">Información</a><a href="#disponibilidad">Disponibilidad</a><a href="#consulta">Contacto</a></nav>
        <a class="btn secondary" href="{{ route('login') }}">Iniciar sesión</a>
    </header>

    @if($urbanizacion)
        @php
            $lotes = $urbanizacion->manzanos->flatMap->lotes;
            $locatedLotes = $lotes->filter(fn ($lote) => ! is_null($lote->coord_x) && ! is_null($lote->coord_y));
            $counts = $lotes->countBy('estado');
            $heroUrl = $urbanizacion->publicSetting?->heroUrl();
            $publicSetting = $urbanizacion->publicSetting;
        @endphp
        @inject('pricingService', 'App\Services\LotPricingService')

        <section class="project-hero {{ $heroUrl ? 'has-image' : 'fallback' }}" @if($heroUrl) style="background-image:linear-gradient(90deg,rgba(14,17,23,.9),rgba(14,17,23,.42)),url('{{ $heroUrl }}')" @endif>
            <div class="project-hero-copy">
                <span class="eyebrow">Proyecto inmobiliario</span>
                <h1>{{ $urbanizacion->nombre }}</h1>
                <p>{{ $urbanizacion->ubicacion ?: 'Conoce este proyecto y consulta su disponibilidad actual.' }}</p>
                <div class="actions">
                    <a class="btn" href="#disponibilidad">Ver disponibilidad</a>
                    @if($whatsappUrl)<a class="btn project-whatsapp-outline" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> Consultar por WhatsApp</a>@endif
                </div>
            </div>
        </section>

        @if($youtubeEmbedUrl || $publicSetting?->titulo_descripcion || $publicSetting?->descripcion_principal)
            <section id="informacion" class="project-description-section {{ $youtubeEmbedUrl ? '' : 'without-video' }}">
                @if($youtubeEmbedUrl)
                    <div class="project-video"><iframe src="{{ $youtubeEmbedUrl }}" title="Video de {{ $urbanizacion->nombre }}" loading="lazy" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
                @endif
                <div class="project-description-copy">
                    <span class="eyebrow">Conoce el proyecto</span>
                    <h2>{{ $publicSetting?->titulo_descripcion ?: $urbanizacion->nombre }}</h2>
                    @if($publicSetting?->descripcion_principal)<p>{{ $publicSetting->descripcion_principal }}</p>@endif
                </div>
            </section>
        @endif

        @if($urbanizacion->publicFeatures->isNotEmpty())
            <section class="project-features-section" data-public-features>
                <div class="project-section-title"><span class="eyebrow">Beneficios del proyecto</span><h2>{{ $publicSetting?->info_title ?: 'Características de la urbanización' }}</h2></div>
                <div class="project-feature-cards">
                    @foreach($urbanizacion->publicFeatures as $feature)
                        <article><i class="fa-solid fa-check"></i><h3>{{ $feature->titulo }}</h3><p>{{ $feature->descripcion }}</p></article>
                    @endforeach
                </div>
            </section>
        @endif

        <section id="disponibilidad" class="project-map-section">
            <div class="project-section-heading">
                <div><span class="eyebrow">Disponibilidad actual</span><h2>Elige tu terreno</h2><p>Selecciona un punto del plano para consultar su estado e información pública.</p></div>
                <div class="legend">@foreach(['disponible'=>'Disponible','vendido'=>'Vendido','reservado'=>'Reservado','bloqueado'=>'Bloqueado'] as $key=>$label)<span><i class="legend-dot {{ $key }}"></i>{{ $label }}: {{ $counts[$key] ?? 0 }}</span>@endforeach</div>
            </div>

            @if($urbanizacion->imageUrl())
                <div class="map-shell project-map-shell">
                    <div class="map-zoom-controls"><button class="btn secondary" type="button" data-zoom-in aria-label="Acercar">Zoom +</button><button class="btn secondary" type="button" data-zoom-out aria-label="Alejar">Zoom -</button><button class="btn secondary" type="button" data-zoom-reset>Restablecer</button><button class="btn secondary" type="button" data-zoom-fullscreen>Pantalla completa</button><span class="zoom-value" data-zoom-value>100%</span></div>
                    <div class="plan-map-viewport" id="public-plan-map"><div class="plan-map-layer" id="public-plan-map-layer">
                        <img class="plan-map-image" src="{{ $urbanizacion->imageUrl() }}" alt="Plano {{ $urbanizacion->nombre }}" loading="lazy" decoding="async">
                        @foreach($locatedLotes as $lote)
                            @php
                                $lotMessage = "Hola, quisiera recibir información sobre este terreno.\n\nUrbanización: {$urbanizacion->nombre}\nManzano: {$lote->manzano->codigo}\nLote: {$lote->codigo}\nSuperficie: ".number_format((float) $lote->superficie, 2, '.', '')." m²\n\nGracias.";
                                $lotWhatsappUrl = $lote->estado === 'disponible' ? \App\Support\WhatsAppLink::urlWithMessage($whatsappPhone, $lotMessage) : null;
                                $pricePayload = $urbanizacion->mostrar_precio_publico ? $pricingService->payload($lote) : null;
                            @endphp
                            <button type="button" class="map-point lot-point public {{ $lote->estado }}" style="left: {{ max(0,min(100,(float)$lote->coord_x)) }}%; top: {{ max(0,min(100,(float)$lote->coord_y)) }}%;" data-public-lot data-state="{{ $lote->estado }}" data-block="{{ $lote->manzano->codigo }}" data-lot="{{ $lote->codigo }}" data-area="{{ number_format((float)$lote->superficie,2,'.','') }}" @if($lotWhatsappUrl)data-whatsapp="{{ $lotWhatsappUrl }}"@endif @if($pricePayload)data-price="{{ $pricingService->formatUsd($pricePayload['credit_usd']) }}"@endif aria-label="Lote {{ $lote->codigo }} {{ $lote->estado }}"><span>{{ $lote->codigo }}</span></button>
                        @endforeach
                    </div></div>
                </div>
            @else
                <div class="empty-plan">El plano de esta urbanización estará disponible próximamente.</div>
            @endif

            @if($publicLink && $publicQrDataUri)<details class="project-share"><summary>Compartir disponibilidad</summary><div><a href="{{ $publicLink }}">{{ $publicLink }}</a><button class="btn secondary" type="button" data-copy-link="{{ $publicLink }}">Copiar enlace</button><img class="public-link-qr" src="{{ $publicQrDataUri }}" alt="QR de disponibilidad publica" loading="lazy" decoding="async"></div></details>@endif
        </section>

        <section id="consulta" class="project-final-cta"><div><span class="eyebrow">Consultar con asesor</span><h2>¿Quieres conocer este proyecto?</h2><p>Un asesor puede ayudarte a confirmar disponibilidad y resolver tus consultas.</p></div>@if($whatsappUrl)<a class="btn whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> Consultar por WhatsApp</a>@endif</section>
    @else
        <section class="project-empty"><h1>No hay urbanizaciones disponibles.</h1></section>
    @endif

    <footer class="project-footer"><strong>{{ $systemSettings['system_name'] }}</strong><span>{{ $systemSettings['footer_text'] }}</span></footer>
</main>

<dialog class="public-lot-dialog" data-lot-dialog>
    <button type="button" class="dialog-close" data-close-dialog aria-label="Cerrar">×</button>
    <span class="eyebrow" data-dialog-heading>Lote disponible</span>
    <h2 data-dialog-block></h2>
    <dl><div><dt>Lote</dt><dd data-dialog-lot></dd></div><div><dt>Superficie</dt><dd data-dialog-area></dd></div><div><dt>Estado</dt><dd><span class="badge" data-dialog-state></span></dd></div><div data-dialog-price-row hidden><dt>Precio</dt><dd data-dialog-price></dd></div></dl>
    <p class="muted" data-dialog-notice hidden></p>
    <a class="btn whatsapp" data-dialog-whatsapp target="_blank" rel="noopener noreferrer" hidden><i class="fa-brands fa-whatsapp"></i> Consultar por WhatsApp</a>
</dialog>
<script src="{{ asset('js/map-zoom.js') }}"></script>
<script src="{{ asset('js/pwa.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const map = document.getElementById('public-plan-map'), layer = document.getElementById('public-plan-map-layer'), shell = map?.closest('.map-shell');
    if (map && layer && typeof window.createImpactoMapZoom === 'function') window.createImpactoMapZoom({map, layer, zoomIn:shell.querySelector('[data-zoom-in]'), zoomOut:shell.querySelector('[data-zoom-out]'), reset:shell.querySelector('[data-zoom-reset]'), fullscreen:shell.querySelector('[data-zoom-fullscreen]'), zoomLabel:shell.querySelector('[data-zoom-value]'), shouldIgnorePanTarget: target => Boolean(target.closest('[data-public-lot]'))});
    const dialog = document.querySelector('[data-lot-dialog]');
    const labels = {disponible:'Disponible', reservado:'Actualmente reservado', vendido:'Vendido', bloqueado:'No disponible'};
    document.addEventListener('click', async event => {
        const lot = event.target.closest('[data-public-lot]');
        if (lot) {
            document.querySelectorAll('[data-public-lot].is-selected').forEach(marker => marker.classList.remove('is-selected'));
            lot.classList.add('is-selected');
            const available = lot.dataset.state === 'disponible', whatsapp = dialog.querySelector('[data-dialog-whatsapp]'), priceRow = dialog.querySelector('[data-dialog-price-row]');
            dialog.querySelector('[data-dialog-heading]').textContent = available ? 'Lote disponible' : labels[lot.dataset.state];
            dialog.querySelector('[data-dialog-block]').textContent = `Manzano ${lot.dataset.block}`;
            dialog.querySelector('[data-dialog-lot]').textContent = lot.dataset.lot;
            dialog.querySelector('[data-dialog-area]').textContent = `${lot.dataset.area} m²`;
            dialog.querySelector('[data-dialog-state]').textContent = labels[lot.dataset.state];
            dialog.querySelector('[data-dialog-state]').className = `badge ${lot.dataset.state}`;
            dialog.querySelector('[data-dialog-notice]').hidden = available;
            dialog.querySelector('[data-dialog-notice]').textContent = available ? '' : 'Este terreno no está disponible para consulta comercial.';
            priceRow.hidden = !lot.dataset.price; dialog.querySelector('[data-dialog-price]').textContent = lot.dataset.price || '';
            whatsapp.hidden = !available || !lot.dataset.whatsapp; if (lot.dataset.whatsapp) whatsapp.href = lot.dataset.whatsapp;
            dialog.showModal(); return;
        }
        if (event.target.closest('[data-close-dialog]')) { dialog.close(); document.querySelectorAll('[data-public-lot].is-selected').forEach(marker => marker.classList.remove('is-selected')); }
        const copy = event.target.closest('[data-copy-link]'); if (copy) { await navigator.clipboard.writeText(copy.dataset.copyLink); copy.textContent = 'Copiado'; }
    });
    dialog?.addEventListener('click', event => { if (event.target === dialog) { dialog.close(); document.querySelectorAll('[data-public-lot].is-selected').forEach(marker => marker.classList.remove('is-selected')); } });
});
</script>
</body>
</html>
