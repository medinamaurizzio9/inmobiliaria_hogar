@extends('layouts.app')
@section('content')
@php
    $referenceIcons = [
        'ingreso' => '🚪',
        'construccion' => '🏠',
        'servicio' => '💧',
        'mirador' => '👁',
        'parque' => '🌳',
        'transporte' => '🚌',
        'sector' => '📍',
        'otro' => '⭐',
    ];
@endphp
<div class="topbar">
    <h1 class="title">Mapa de disponibilidad</h1>
    <div class="actions">
        @if($urbanizacion?->imageUrl())
            @role('administrador')
                <button class="btn" id="toggle-edit" type="button">Activar ubicacion manual</button>
            @endrole
        @endif
    </div>
</div>

<form method="GET" action="{{ route('mapa') }}" class="card form" style="margin-bottom:18px;">
    <div class="field">
        <label>Urbanizacion</label>
        <select name="urbanizacion_id" onchange="this.form.submit()">
            @foreach($urbanizaciones as $item)
                <option value="{{ $item->id }}" @selected($urbanizacion?->id === $item->id)>{{ $item->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Manzano</label>
        <select name="manzano_id">
            <option value="">Todos</option>
            @foreach($urbanizacion?->manzanos ?? [] as $manzano)
                <option value="{{ $manzano->id }}" @selected($manzanoId === $manzano->id)>{{ $manzano->codigo }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Estado</label>
        <select name="estado">
            <option value="">Todos</option>
            @foreach(['disponible','reservado','vendido','bloqueado'] as $item)
                <option value="{{ $item }}" @selected($estado === $item)>{{ $item }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Buscar lote</label><input name="lote" value="{{ $busqueda }}" placeholder="Ej. 01"></div>
    <label class="check-row"><input type="checkbox" name="sin_ubicacion" value="1" @checked($sinUbicacion)> Mostrar solo lotes sin ubicacion</label>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Filtrar</button></div>
</form>

@if($urbanizacion)
    @php
        $allLotes = $urbanizacion->manzanos->flatMap->lotes;
        $lotes = $urbanizacion->manzanos
            ->when($manzanoId, fn ($items) => $items->where('id', $manzanoId))
            ->flatMap->lotes
            ->filter(fn ($lote) => ! $estado || $lote->estado === $estado)
            ->filter(fn ($lote) => ! $busqueda || str_contains(strtolower($lote->codigo), strtolower($busqueda)))
            ->filter(fn ($lote) => ! $sinUbicacion || is_null($lote->coord_x) || is_null($lote->coord_y));
        $locatedLotes = $lotes->filter(fn ($lote) => ! is_null($lote->coord_x) && ! is_null($lote->coord_y));
        $unlocatedLotes = $allLotes->filter(fn ($lote) => is_null($lote->coord_x) || is_null($lote->coord_y))->values();
        $counts = $allLotes->countBy('estado');
    @endphp

    <div class="card">
        <div class="map-toolbar">
            <h2>{{ $urbanizacion->nombre }}</h2>
            <div class="legend">
                @foreach(['disponible' => 'Disponible', 'vendido' => 'Vendido', 'reservado' => 'Reservado', 'bloqueado' => 'Bloqueado'] as $key => $label)
                    <span><i class="legend-dot {{ $key }}"></i>{{ $label }}: {{ $counts[$key] ?? 0 }}</span>
                @endforeach
                <span>Sin ubicacion: {{ $unlocatedLotes->count() }}</span>
            </div>
            @if(($urbanizacion->referencias ?? collect())->isNotEmpty())
                <div class="gps-reference-legend">
                    @foreach($urbanizacion->referencias->pluck('tipo_referencia')->filter()->unique()->values() as $tipoReferencia)
                        <span>{{ $referenceIcons[$tipoReferencia] ?? $referenceIcons['otro'] }} {{ ucfirst($tipoReferencia) }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        @if(! $urbanizacion->imageUrl())
            <div class="empty-plan">La imagen del plano no está disponible. Los datos de lotes se conservan.</div>
        @endif
            @role('administrador')
                <div class="edit-panel" id="edit-panel" hidden>
                    <div class="field">
                        <label>Urbanizacion</label>
                        <select onchange="window.location='{{ route('mapa') }}?urbanizacion_id='+this.value">
                            @foreach($urbanizaciones as $item)
                                <option value="{{ $item->id }}" @selected($urbanizacion->id === $item->id)>{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Manzano</label>
                        <select id="edit-manzano">
                            <option value="">Todos</option>
                            @foreach($urbanizacion->manzanos->sortBy('orden') as $manzano)
                                <option value="{{ $manzano->id }}">{{ $manzano->codigo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Lote</label>
                        <select id="selected-lote">
                            <option value="">Selecciona un lote</option>
                            @foreach($urbanizacion->manzanos->sortBy('orden') as $manzano)
                                @foreach($manzano->lotes->sortBy('codigo') as $lote)
                                    <option value="{{ $lote->id }}" data-manzano="{{ $manzano->id }}" data-label="{{ $manzano->codigo }}-{{ $lote->codigo }}" data-estado="{{ $lote->estado }}" data-has-position="{{ ! is_null($lote->coord_x) && ! is_null($lote->coord_y) ? '1' : '0' }}">{{ $manzano->codigo }}-{{ $lote->codigo }}{{ is_null($lote->coord_x) || is_null($lote->coord_y) ? ' (sin ubicacion)' : '' }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <button class="btn secondary" id="next-unlocated" type="button">Siguiente lote sin ubicacion</button>
                    <button class="btn secondary" id="toggle-edit-lock" type="button">Bloquear edicion</button>
                    <button class="btn danger" id="clear-position" type="button">Quitar ubicacion del lote seleccionado</button>
                    <div id="mouse-coordinates" class="muted">X: --%, Y: --%</div>
                    <div id="position-message" class="muted">Modo ubicacion activo: selecciona un lote y haz clic sobre su posicion exacta en el plano</div>
                </div>
            @endrole

            <p class="muted map-help">Las posiciones se guardan proporcionalmente, por eso se mantienen en celular y escritorio.</p>

            <div class="map-shell">
                <button class="btn secondary map-tools-toggle" type="button" id="map-tools-toggle" aria-expanded="false" aria-controls="map-tools-panel">Mapa</button>
                <button class="btn map-location-quick" type="button" id="quick-my-location" aria-label="Activar mi ubicacion">GPS</button>
                <div class="map-zoom-controls" id="map-tools-panel" aria-hidden="true">
                    <button class="btn secondary" type="button" data-zoom-in title="Acercar" aria-label="Acercar">Zoom +</button>
                    <button class="btn secondary" type="button" data-zoom-out title="Alejar" aria-label="Alejar">Zoom -</button>
                    <button class="btn secondary" type="button" data-zoom-reset title="Restablecer vista" aria-label="Restablecer vista">Restablecer</button>
                    <button class="btn secondary" type="button" data-zoom-fullscreen title="Pantalla completa" aria-label="Pantalla completa">Pantalla completa</button>
                    <button class="btn secondary" type="button" id="toggle-gps-points">Puntos GPS</button>
                    <button class="btn secondary" type="button" id="toggle-my-location">Mi ubicacion</button>
                    <span class="zoom-value" data-zoom-value>100%</span>
                    <span class="gps-location-status" id="gps-location-status" hidden></span>
                </div>

                <div class="plan-map-viewport" id="plan-map">
                    <div class="plan-map-layer" id="plan-map-layer">
                        @if($urbanizacion->imageUrl())<img class="plan-map-image" id="plan-image" src="{{ $urbanizacion->imageUrl() }}" alt="Plano {{ $urbanizacion->nombre }}" decoding="async">@endif
                        @foreach($locatedLotes as $lote)
                            @php
                                $manzano = $lote->manzano;
                            @endphp
                            <button type="button" class="map-point lot-point {{ $lote->estado }}" data-lote-id="{{ $lote->id }}" data-label="{{ $manzano->codigo }}-{{ $lote->codigo }}" data-estado="{{ $lote->estado }}" title="{{ $manzano->codigo }}-{{ $lote->codigo }}" style="left: {{ max(0, min(100, (float) $lote->coord_x)) }}%; top: {{ max(0, min(100, (float) $lote->coord_y)) }}%;"><span>{{ $lote->codigo }}</span></button>
                        @endforeach
                        <div class="gps-reference-layer hidden" id="gps-reference-layer" aria-label="Puntos GPS de referencia">
                            @forelse($urbanizacion->referencias as $referencia)
                                @php($hasPlanPosition = ! is_null($referencia->plano_x) && ! is_null($referencia->plano_y))
                                <div @class(['gps-reference-marker', 'on-plan' => $hasPlanPosition]) @if($hasPlanPosition) style="left: {{ max(0, min(100, (float) $referencia->plano_x)) }}%; top: {{ max(0, min(100, (float) $referencia->plano_y)) }}%;" @endif>
                                    <span class="gps-reference-pin">{{ $referenceIcons[$referencia->tipo_referencia] ?? $referenceIcons['otro'] }}</span>
                                    <div>
                                        <strong>{{ $referencia->nombre }}</strong>
                                        <small>{{ ucfirst($referencia->tipo_referencia ?? 'otro') }}</small>
                                        <small>{{ $referencia->latitud }}, {{ $referencia->longitud }}</small>
                                        @if($referencia->descripcion)
                                            <small>{{ $referencia->descripcion }}</small>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="gps-reference-marker">
                                    <span class="gps-reference-pin">{{ $referenceIcons['otro'] }}</span>
                                    <div>
                                        <strong>Sin puntos GPS configurados</strong>
                                        <small>Registre referencias en Administracion.</small>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                        <div class="current-location-marker hidden" id="current-location-marker" aria-live="polite">
                            <span></span>
                            <strong>Usted esta aqui</strong>
                        </div>
                    </div>
                </div>

                <div id="lotModalOverlay" class="modal-overlay hidden"></div>
                <div id="lote-map-modal" class="lot-modal hidden" role="dialog" aria-modal="true" aria-labelledby="lotModalTitle">
                    <div class="map-dialog">
                        <h2 id="lotModalTitle" data-modal-title>Detalle de lote</h2>
                        <p><strong>Urbanizacion:</strong> <span data-modal-urbanizacion></span></p>
                        <p><strong>Manzano:</strong> <span data-modal-manzano></span></p>
                        <p><strong>Lote:</strong> <span data-modal-lote></span></p>
                        <p><strong>Superficie:</strong> <span data-modal-superficie></span></p>
                        <p><strong>Precio lote:</strong> <span data-modal-precio></span></p>
                        <p><strong>Precio lote en Bs:</strong> <span data-modal-precio-bs></span></p>
                        <p><strong>Cuota inicial:</strong> <span data-modal-cuota-inicial></span></p>
                        <p><strong>Cuota inicial en Bs:</strong> <span data-modal-cuota-inicial-bs></span></p>
                        <p><strong>Estado:</strong> <span class="badge" data-modal-estado></span></p>
                        <p class="muted" data-modal-message hidden></p>
                        <div class="actions lot-modal-actions">
                            <a class="btn secondary" href="" data-modal-link="detalle">Ver detalle</a>
                            <a class="btn secondary" href="" data-modal-link="reservar" hidden>Reservar lote</a>
                            <a class="btn secondary" href="" data-modal-link="vender" hidden>Vender lote</a>
                            <a class="btn secondary" href="" data-modal-link="editar" hidden>Editar lote</a>
                            <button type="button" class="btn calculator-btn" data-modal-calculator hidden>Calculadora</button>
                            <button type="button" id="lotModalClose" class="btn">Cerrar</button>
                        </div>
                    </div>
                </div>

                <div id="commercialCalculatorOverlay" class="modal-overlay hidden"></div>
                <div id="commercialCalculatorModal" class="commercial-calculator-modal hidden" role="dialog" aria-modal="true" aria-labelledby="commercialCalculatorTitle" aria-hidden="true">
                    <div class="commercial-calculator-dialog">
                        <div class="calculator-header">
                            <div>
                                <h2 id="commercialCalculatorTitle">Calculadora comercial</h2>
                                <p class="muted"><span data-calc-lote></span></p>
                            </div>
                            <button type="button" class="btn secondary" id="commercialCalculatorClose">Cerrar</button>
                        </div>

                        <div class="calculator-tabs" role="tablist" aria-label="Tipo de calculo">
                            <button type="button" class="btn calculator-tab active" data-calculator-tab="credito">Credito</button>
                            <button type="button" class="btn calculator-tab semi" data-calculator-tab="semi">Semi contado</button>
                        </div>

                        <div class="calculator-panel" data-calculator-panel="credito">
                            <div class="calculator-fields">
                                <div class="field">
                                    <label>Precio real USD</label>
                                    <input data-calc-credit-price-usd readonly>
                                </div>
                                <div class="field">
                                    <label>Precio real Bs</label>
                                    <input data-calc-credit-price-bs readonly>
                                </div>
                                <div class="field">
                                    <label>Inicial USD</label>
                                    <input type="number" min="0" step="0.01" data-calc-initial-usd>
                                </div>
                                <div class="field">
                                    <label>Inicial Bs</label>
                                    <input data-calc-initial-bs readonly>
                                </div>
                                <div class="field">
                                    <label>Plazo</label>
                                    <select data-calc-plazo required></select>
                                </div>
                            </div>
                            <p class="calculator-alert" data-calc-minimum></p>
                            <p class="calculator-error" data-calc-credit-error hidden></p>
                            <div class="calculator-results teal" data-calc-credit-results></div>
                        </div>

                        <div class="calculator-panel hidden" data-calculator-panel="semi">
                            <div class="calculator-fields">
                                <div class="field">
                                    <label>Precio real USD</label>
                                    <input data-calc-semi-price-usd readonly>
                                </div>
                                <div class="field">
                                    <label>Precio real Bs</label>
                                    <input data-calc-semi-price-bs readonly>
                                </div>
                            </div>
                            <p class="muted" data-calc-promo-status></p>
                            <p class="calculator-error" data-calc-semi-error hidden></p>
                            <div class="calculator-results gold" data-calc-semi-results></div>
                        </div>

                        <p class="calculator-note">Simulacion referencial. Los valores finales deben ser confirmados por administracion.</p>
                    </div>
                </div>
            </div>
    </div>
@else
    <div class="card">Crea una urbanizacion para ver el mapa.</div>
@endif

<script src="{{ asset('js/map-zoom.js') }}"></script>
<script>
window.commercialConfig = @json($commercialConfig ?? []);

document.addEventListener('DOMContentLoaded', () => {
    const map = document.getElementById('plan-map');
    const layer = document.getElementById('plan-map-layer');
    const image = document.getElementById('plan-image');
    if (!map || !layer || !image || typeof window.createImpactoMapZoom !== 'function') return;

    let editMode = false;
    let editLocked = false;
    let dragging = null;
    const panel = document.getElementById('edit-panel');
    const selected = document.getElementById('selected-lote');
    const editManzano = document.getElementById('edit-manzano');
    const message = document.getElementById('position-message');
    const mouseCoordinates = document.getElementById('mouse-coordinates');
    const toggleEdit = document.getElementById('toggle-edit');
    const toggleLock = document.getElementById('toggle-edit-lock');
    const toggleGpsPoints = document.getElementById('toggle-gps-points');
    const gpsReferenceLayer = document.getElementById('gps-reference-layer');
    const toggleMyLocation = document.getElementById('toggle-my-location');
    const quickMyLocation = document.getElementById('quick-my-location');
    const mapToolsToggle = document.getElementById('map-tools-toggle');
    const mapToolsPanel = document.getElementById('map-tools-panel');
    const calculatorModal = document.getElementById('commercialCalculatorModal');
    const calculatorOverlay = document.getElementById('commercialCalculatorOverlay');
    const calculatorClose = document.getElementById('commercialCalculatorClose');
    let calculatorLote = null;
    const gpsLocationStatus = document.getElementById('gps-location-status');
    const currentLocationMarker = document.getElementById('current-location-marker');
    const csrf = '{{ csrf_token() }}';
    let locationWatcher = null;
    let lastLocationSentAt = 0;
    const zoomController = window.createImpactoMapZoom({
        map,
        layer,
        zoomIn: map.closest('.map-shell')?.querySelector('[data-zoom-in]'),
        zoomOut: map.closest('.map-shell')?.querySelector('[data-zoom-out]'),
        reset: map.closest('.map-shell')?.querySelector('[data-zoom-reset]'),
        fullscreen: map.closest('.map-shell')?.querySelector('[data-zoom-fullscreen]'),
        zoomLabel: map.closest('.map-shell')?.querySelector('[data-zoom-value]'),
        canPan: () => !editMode,
        shouldIgnorePanTarget: (target) => Boolean(target.closest('.map-point')),
    });
    const lotModal = window.createImpactoLotModal?.({
        modal: document.getElementById('lote-map-modal'),
        overlay: document.getElementById('lotModalOverlay'),
        closeButton: document.getElementById('lotModalClose'),
        endpointBase: '{{ url('/mapa/lote') }}',
        onCalculator: openCalculator,
    });

    toggleEdit?.addEventListener('click', () => {
        editMode = !editMode;
        panel.hidden = !editMode;
        map.classList.toggle('editing', editMode && !editLocked);
        toggleEdit.textContent = editMode ? 'Desactivar ubicacion manual' : 'Activar ubicacion manual';
        message.textContent = editMode
            ? 'Modo ubicacion activo: selecciona un lote y haz clic sobre su posicion exacta en el plano'
            : 'Modo ubicacion desactivado.';
        if (!editMode && mouseCoordinates) mouseCoordinates.textContent = 'X: --%, Y: --%';
    });

    toggleLock?.addEventListener('click', () => {
        editLocked = !editLocked;
        toggleLock.textContent = editLocked ? 'Desbloquear edicion' : 'Bloquear edicion';
        map.classList.toggle('editing', editMode && !editLocked);
        message.textContent = editLocked ? 'Edicion bloqueada para evitar movimientos accidentales.' : 'Edicion desbloqueada.';
    });

    toggleGpsPoints?.addEventListener('click', () => {
        gpsReferenceLayer?.classList.toggle('hidden');
        const visible = gpsReferenceLayer && !gpsReferenceLayer.classList.contains('hidden');
        toggleGpsPoints.classList.toggle('active', visible);
        toggleGpsPoints.textContent = visible ? 'Ocultar GPS' : 'Puntos GPS';
    });

    function setMapToolsOpen(open) {
        if (!mapToolsPanel || !mapToolsToggle) return;
        mapToolsPanel.classList.toggle('open', open);
        mapToolsToggle.classList.toggle('active', open);
        mapToolsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        mapToolsPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    mapToolsToggle?.addEventListener('click', () => {
        setMapToolsOpen(!mapToolsPanel?.classList.contains('open'));
    });

    document.addEventListener('click', (event) => {
        if (!mapToolsPanel?.classList.contains('open')) return;
        if (event.target.closest('#map-tools-panel, #map-tools-toggle')) return;
        setMapToolsOpen(false);
    });

    function moneyUsd(value) {
        return `$us ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function moneyBs(value) {
        return `Bs ${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function calcConfig() {
        return window.commercialConfig || {};
    }

    function writeCalc(selector, value) {
        const element = calculatorModal?.querySelector(selector);
        if (element) element.value = value;
    }

    function resultRow(label, usd, bs) {
        return `<div><span>${label}</span><strong>${moneyUsd(usd)} / ${moneyBs(bs)}</strong></div>`;
    }

    function setCalculatorTab(tab) {
        calculatorModal?.querySelectorAll('[data-calculator-tab]').forEach((button) => {
            const active = button.dataset.calculatorTab === tab;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        calculatorModal?.querySelectorAll('[data-calculator-panel]').forEach((panelElement) => {
            panelElement.classList.toggle('hidden', panelElement.dataset.calculatorPanel !== tab);
        });
    }

    function renderCreditCalculator() {
        if (!calculatorLote || !window.ImpactoCommercialCalculator) return;

        const config = calcConfig();
        const priceUsd = Number(calculatorLote.precio_real_usd || 0);
        const tipoCambio = Number(config.tipoCambio || calculatorLote.tipo_cambio_usd_bs || 0);
        const initialInput = calculatorModal?.querySelector('[data-calc-initial-usd]');
        const plazoSelect = calculatorModal?.querySelector('[data-calc-plazo]');
        const plazos = Array.isArray(config.plazos) ? config.plazos : [];

        if (plazoSelect && plazoSelect.options.length === 0) {
            plazoSelect.innerHTML = plazos.map((plazo) => `<option value="${plazo}">${plazo} meses</option>`).join('');
        }

        if (initialInput && initialInput.value === '') {
            initialInput.value = Number(config.inicialMinimaUsd || 0).toFixed(2);
        }

        const result = window.ImpactoCommercialCalculator.calculateCredit({
            priceUsd,
            initialUsd: Number(initialInput?.value || 0),
            plazo: Number(plazoSelect?.value || plazos[0] || 0),
            tipoCambio,
            inicialMinimaUsd: Number(config.inicialMinimaUsd || 0),
            plazos,
        });

        writeCalc('[data-calc-credit-price-usd]', moneyUsd(result.precioUsd));
        writeCalc('[data-calc-credit-price-bs]', moneyBs(result.precioBs));
        writeCalc('[data-calc-initial-bs]', moneyBs(result.inicialBs));

        const minimum = calculatorModal?.querySelector('[data-calc-minimum]');
        if (minimum) {
            minimum.textContent = `La inicial minima para esta urbanizacion es ${moneyUsd(result.inicialMinimaUsd)} / ${moneyBs(result.inicialMinimaBs)}.`;
        }

        const error = calculatorModal?.querySelector('[data-calc-credit-error]');
        if (error) {
            error.hidden = result.errors.length === 0;
            error.textContent = result.errors.join(' ');
        }

        const results = calculatorModal?.querySelector('[data-calc-credit-results]');
        if (results) {
            results.innerHTML = [
                resultRow('Precio real', result.precioUsd, result.precioBs),
                resultRow('Inicial', result.inicialUsd, result.inicialBs),
                resultRow('Saldo a financiar', result.saldoUsd, result.saldoBs),
                `<div><span>Plazo</span><strong>${result.plazo || '--'} meses</strong></div>`,
                resultRow('Cuota mensual', result.cuotaUsd, result.cuotaBs),
            ].join('');
        }
    }

    function renderSemiCalculator() {
        if (!calculatorLote || !window.ImpactoCommercialCalculator) return;

        const config = calcConfig();
        const priceUsd = Number(calculatorLote.precio_real_usd || 0);
        const tipoCambio = Number(config.tipoCambio || calculatorLote.tipo_cambio_usd_bs || 0);
        const promo = config.descuentoPromo || {};
        const result = window.ImpactoCommercialCalculator.calculateSemiContado({
            priceUsd,
            tipoCambio,
            descuentoContado: config.descuentoContado || {},
            descuentoPromo: promo,
        });

        writeCalc('[data-calc-semi-price-usd]', moneyUsd(result.precioUsd));
        writeCalc('[data-calc-semi-price-bs]', moneyBs(result.precioBs));

        const promoStatus = calculatorModal?.querySelector('[data-calc-promo-status]');
        if (promoStatus) {
            promoStatus.textContent = promo.activo
                ? (promo.nombre || 'Promocion activa')
                : 'Sin promocion activa';
        }

        const error = calculatorModal?.querySelector('[data-calc-semi-error]');
        if (error) {
            error.hidden = result.errors.length === 0;
            error.textContent = result.errors.join(' ');
        }

        const results = calculatorModal?.querySelector('[data-calc-semi-results]');
        if (results) {
            results.innerHTML = [
                resultRow('Precio real', result.precioUsd, result.precioBs),
                resultRow('Descuento contado', result.descuentoContadoUsd, result.descuentoContadoBs),
                resultRow('Descuento promo', result.descuentoPromoUsd, result.descuentoPromoBs),
                resultRow('Total descuentos', result.totalDescuentoUsd, result.totalDescuentoBs),
                resultRow('Precio final semi contado', result.precioFinalUsd, result.precioFinalBs),
            ].join('');
        }
    }

    function renderCalculator() {
        renderCreditCalculator();
        renderSemiCalculator();
    }

    function openCalculator(loteData) {
        calculatorLote = loteData;
        const label = calculatorModal?.querySelector('[data-calc-lote]');
        if (label) {
            label.textContent = `${loteData.urbanizacion || ''} / ${loteData.manzano || ''}-${loteData.lote || ''}`;
        }

        const plazoSelect = calculatorModal?.querySelector('[data-calc-plazo]');
        if (plazoSelect) plazoSelect.innerHTML = '';
        const initialInput = calculatorModal?.querySelector('[data-calc-initial-usd]');
        if (initialInput) initialInput.value = '';

        setCalculatorTab('credito');
        calculatorOverlay?.classList.remove('hidden');
        calculatorModal?.classList.remove('hidden');
        calculatorModal?.setAttribute('aria-hidden', 'false');
        calculatorOverlay?.setAttribute('aria-hidden', 'false');
        renderCalculator();
    }

    function closeCalculator() {
        calculatorModal?.classList.add('hidden');
        calculatorOverlay?.classList.add('hidden');
        calculatorModal?.setAttribute('aria-hidden', 'true');
        calculatorOverlay?.setAttribute('aria-hidden', 'true');
        calculatorLote = null;
    }

    calculatorModal?.querySelectorAll('[data-calculator-tab]').forEach((button) => {
        button.addEventListener('click', () => setCalculatorTab(button.dataset.calculatorTab));
    });
    calculatorModal?.querySelector('[data-calc-initial-usd]')?.addEventListener('input', renderCreditCalculator);
    calculatorModal?.querySelector('[data-calc-plazo]')?.addEventListener('change', renderCreditCalculator);
    calculatorClose?.addEventListener('click', closeCalculator);
    calculatorOverlay?.addEventListener('click', closeCalculator);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && calculatorModal && !calculatorModal.classList.contains('hidden')) {
            closeCalculator();
        }
    });

    function setGpsStatus(text, level = '') {
        if (!gpsLocationStatus) return;
        gpsLocationStatus.hidden = false;
        gpsLocationStatus.textContent = text;
        gpsLocationStatus.className = `gps-location-status ${level}`;
    }

    function accuracyLevel(accuracy) {
        if (accuracy <= 10) return 'good';
        if (accuracy <= 20) return 'medium';
        return 'bad';
    }

    function moveCurrentLocation(data) {
        if (!currentLocationMarker) return;
        currentLocationMarker.style.left = `${data.x}%`;
        currentLocationMarker.style.top = `${data.y}%`;
        currentLocationMarker.classList.remove('hidden');
        currentLocationMarker.title = `Precision GPS: ${data.accuracy ?? '--'} metros`;
    }

    async function sendCurrentLocation(position) {
        const coords = position.coords;
        setGpsStatus('Calculando ubicacion en plano...');

        const response = await fetch('{{ route('mapa.mi-ubicacion') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                latitud: coords.latitude,
                longitud: coords.longitude,
                accuracy: coords.accuracy,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            setGpsStatus(data.message || 'No se pudo calcular la ubicacion en el plano.', 'bad');
            return;
        }

        moveCurrentLocation(data);
        setGpsStatus(`Precision GPS: ${data.accuracy ?? '--'} metros`, accuracyLevel(Number(data.accuracy || 999)));
    }

    toggleMyLocation?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setGpsStatus('Este dispositivo no permite geolocalizacion.', 'bad');
            return;
        }

        if (locationWatcher) {
            navigator.geolocation.clearWatch(locationWatcher);
            locationWatcher = null;
            toggleMyLocation.classList.remove('active');
            quickMyLocation?.classList.remove('active');
            toggleMyLocation.textContent = 'Mi ubicacion';
            setGpsStatus('Ubicacion GPS desactivada.');
            return;
        }

        setGpsStatus('Buscando ubicacion...');
        toggleMyLocation.classList.add('active');
        quickMyLocation?.classList.add('active');
        toggleMyLocation.textContent = 'Detener ubicacion';

        locationWatcher = navigator.geolocation.watchPosition(
            (position) => {
                const now = Date.now();
                if (now - lastLocationSentAt < 3000) return;
                lastLocationSentAt = now;
                sendCurrentLocation(position);
            },
            () => {
                setGpsStatus('No se pudo obtener la ubicacion GPS.', 'bad');
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 5000,
            }
        );
    });

    quickMyLocation?.addEventListener('click', () => {
        toggleMyLocation?.click();
        quickMyLocation.classList.toggle('active', Boolean(locationWatcher));
    });

    editManzano?.addEventListener('change', () => {
        const manzanoId = editManzano.value;
        [...selected.options].forEach((option) => {
            option.hidden = option.value && manzanoId && option.dataset.manzano !== manzanoId;
        });
        selected.value = '';
    });

    document.getElementById('next-unlocated')?.addEventListener('click', () => {
        const next = [...selected.options].find((option) => option.value && option.dataset.hasPosition === '0' && !option.hidden);
        if (next) {
            selected.value = next.value;
            message.textContent = `Lote seleccionado: ${next.dataset.label}`;
        } else {
            message.textContent = 'No hay lotes sin ubicacion en este filtro.';
        }
    });

    function coordinates(event) {
        const rect = image.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
            return null;
        }

        return {
            coord_x: Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100)),
            coord_y: Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100)),
        };
    }

    function createPoint(loteId, coords) {
        const option = selected.querySelector(`option[value="${loteId}"]`);
        const point = document.createElement('button');
        point.type = 'button';
        point.className = `map-point lot-point ${option?.dataset.estado || 'disponible'}`;
        point.dataset.loteId = loteId;
        point.dataset.label = option?.dataset.label || '';
        point.dataset.estado = option?.dataset.estado || 'disponible';
        point.title = option?.dataset.label || '';
        point.style.left = `${coords.coord_x}%`;
        point.style.top = `${coords.coord_y}%`;
        point.innerHTML = `<span>${(option?.dataset.label || '').split('-').pop()}</span>`;
        attachDrag(point);
        layer.appendChild(point);
        return point;
    }

    function selectedOption(loteId) {
        return selected?.querySelector(`option[value="${loteId}"]`);
    }

    async function savePosition(loteId, point, coords) {
        if (!loteId) {
            message.textContent = 'Selecciona primero un lote';
            return;
        }
        if (editLocked) {
            message.textContent = 'Edicion bloqueada. Desbloquea para mover puntos.';
            return;
        }
        if (!coords) {
            message.textContent = 'Haz clic dentro de la imagen del plano.';
            return;
        }

        const response = await fetch(`{{ url('/mapa/lotes') }}/${loteId}/posicion`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify(coords),
        });

        if (!response.ok) {
            message.textContent = 'No se pudo guardar la posicion.';
            return;
        }

        const currentPoint = point || map.querySelector(`[data-lote-id="${loteId}"]`) || createPoint(loteId, coords);
        currentPoint.style.left = `${coords.coord_x}%`;
        currentPoint.style.top = `${coords.coord_y}%`;

        const option = selectedOption(loteId);
        if (option) {
            option.dataset.hasPosition = '1';
            option.textContent = option.textContent.replace(' (sin ubicacion)', '');
        }

        message.textContent = 'Lote ubicado correctamente';
    }

    async function clearPosition() {
        const loteId = selected?.value;
        if (!loteId) {
            message.textContent = 'Selecciona primero un lote';
            return;
        }

        const response = await fetch(`{{ url('/mapa/lotes') }}/${loteId}/posicion`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            message.textContent = 'No se pudo quitar la ubicacion.';
            return;
        }

        map.querySelector(`[data-lote-id="${loteId}"]`)?.remove();

        const option = selectedOption(loteId);
        if (option) {
            option.dataset.hasPosition = '0';
            if (!option.textContent.includes('(sin ubicacion)')) {
                option.textContent = `${option.textContent} (sin ubicacion)`;
            }
        }

        message.textContent = 'Ubicacion quitada correctamente';
    }

    document.getElementById('clear-position')?.addEventListener('click', clearPosition);

    map.addEventListener('mousemove', (event) => {
        if (!editMode || !mouseCoordinates) return;

        const coords = coordinates(event);
        mouseCoordinates.textContent = coords
            ? `X: ${coords.coord_x.toFixed(2)}%, Y: ${coords.coord_y.toFixed(2)}%`
            : 'X: --%, Y: --%';
    });

    map.addEventListener('click', (event) => {
        if (event.target.closest('.map-zoom-controls, .lot-modal, .modal-overlay, .edit-panel')) return;

        const point = event.target.closest('.map-point');

        if (!editMode) {
            if (!zoomController.hasPanMoved() && point) {
                lotModal?.open(point);
            }
            return;
        }

        if (!point) {
            savePosition(selected?.value, null, coordinates(event));
        }
    });

    function attachDrag(point) {
        point.addEventListener('pointerdown', (event) => {
            if (!editMode || editLocked) return;
            event.preventDefault();
            dragging = point;
            point.setPointerCapture(event.pointerId);
        });

        point.addEventListener('pointermove', (event) => {
            if (!editMode || editLocked || dragging !== point) return;
            const coords = coordinates(event);
            if (!coords) return;
            point.style.left = `${coords.coord_x}%`;
            point.style.top = `${coords.coord_y}%`;
        });

        point.addEventListener('pointerup', (event) => {
            if (!editMode || editLocked || dragging !== point) return;
            savePosition(point.dataset.loteId, point, coordinates(event));
            dragging = null;
        });
    }

    map.querySelectorAll('.map-point').forEach(attachDrag);
});
</script>
@endsection
