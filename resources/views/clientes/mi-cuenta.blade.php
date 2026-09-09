@extends('layouts.app')
@section('title', 'Mi cuenta')
@section('content')
@php
    $ventas = $cliente->ventas;
    $saldoTotal = $ventas->sum(fn ($venta) => $venta->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('saldo_pendiente', '>', 0)->sum('saldo_pendiente'));
    $vencidas = $ventas->flatMap->cuotas->where('estado', 'vencida')->where('saldo_pendiente', '>', 0)->count();
@endphp
<div class="client-portal">
    <header class="client-welcome">
        <x-crm.avatar :name="$cliente->nombre" :path="$cliente->foto" size="lg" />
        <div><span class="client-eyebrow">Mi cuenta</span><h1>Hola, {{ $cliente->nombre }}</h1><p>Bienvenida a tu panel personal.</p></div>
    </header>

    <section class="client-financial-stats" aria-label="Resumen financiero">
        <article class="client-financial-stat balance"><i class="fa-solid fa-wallet"></i><div><span>Saldo total informativo</span><strong>$us {{ number_format((float) $saldoTotal, 2) }}</strong><small>Suma de saldos pendientes de tus terrenos</small></div></article>
        <article class="client-financial-stat pending"><i class="fa-regular fa-clock"></i><div><span>Pagos por verificar</span><strong>{{ $pendientes }}</strong><small>Comprobantes pendientes de revisión</small></div></article>
        <article class="client-financial-stat overdue"><i class="fa-solid fa-triangle-exclamation"></i><div><span>Cuotas vencidas</span><strong>{{ $vencidas }}</strong><small>{{ $vencidas ? 'Requieren tu atención' : 'Sin cuotas vencidas' }}</small></div></article>
    </section>

    <section id="mis-terrenos" class="client-section">
        <div class="client-section-heading"><div><span class="client-eyebrow">Patrimonio</span><h2>Mis terrenos</h2><p>Cada contrato conserva su saldo y calendario independientes.</p></div><span class="client-count">{{ $ventas->count() }} {{ $ventas->count() === 1 ? 'terreno' : 'terrenos' }}</span></div>
        <div class="client-properties">
            @forelse($ventas as $venta)
                @php
                    $activas = $venta->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('saldo_pendiente', '>', 0);
                    $saldo = $activas->sum('saldo_pendiente');
                    $pagado = $venta->cashMovements->where('tipo', 'ingreso')->where('estado', 'confirmado')->sum('monto');
                    $proxima = $activas->sortBy(fn ($cuota) => $cuota->fecha_vencimiento?->timestamp ?? PHP_INT_MAX)->first();
                    $urbanizacion = $venta->lote->manzano->urbanizacion;
                @endphp
                <article class="client-property-card">
                    <div class="client-property-image">@if($urbanizacion->imageUrl(true))<img src="{{ $urbanizacion->imageUrl(true) }}" alt="{{ $urbanizacion->nombre }}" loading="lazy" decoding="async">@else<span><i class="fa-regular fa-map"></i></span>@endif</div>
                    <div class="client-property-copy"><span class="client-modality">{{ strtoupper($venta->tipo_operacion) }}</span><h3>{{ $urbanizacion->nombre }}</h3><p class="client-lot-label">Mz {{ $venta->lote->manzano->codigo }} · Lote {{ $venta->lote->codigo }}</p><dl><div><dt>Precio pactado</dt><dd>$us {{ number_format((float) $venta->precio_final, 2) }}</dd></div><div><dt>Cuota inicial</dt><dd>$us {{ number_format((float) $venta->cuota_inicial, 2) }}</dd></div><div><dt>Pagado confirmado</dt><dd>$us {{ number_format((float) $pagado, 2) }}</dd></div></dl></div>
                    <div class="client-property-balance"><span>Saldo actual</span><strong>{{ $saldo > 0 ? '$us '.number_format((float) $saldo, 2) : 'Pagado' }}</strong>@if($proxima)<small>Próxima cuota {{ $proxima->fecha_vencimiento?->format('d/m/Y') ?: 'sin fecha' }} · $us {{ number_format((float) $proxima->saldo_pendiente, 2) }}</small>@else<small>Sin cuotas pendientes</small>@endif</div>
                    <nav class="client-property-actions" aria-label="Acciones de {{ $urbanizacion->nombre }} lote {{ $venta->lote->codigo }}"><a href="{{ route('portal.terrenos.show', $venta) }}"><i class="fa-regular fa-eye"></i><span>Ver detalle</span></a><a href="{{ route('portal.estado-cuenta.pdf', $venta) }}"><i class="fa-regular fa-file-lines"></i><span>Estado de cuenta</span></a>@if($saldo > 0)<a class="primary" href="{{ route('portal.pagar', $venta) }}"><i class="fa-regular fa-credit-card"></i><span>Pagar</span></a>@endif<a href="{{ route('portal.documentos', $venta) }}"><i class="fa-regular fa-folder-open"></i><span>Documentos</span></a></nav>
                </article>
            @empty
                <x-crm.empty-state title="No tienes terrenos asociados" />
            @endforelse
        </div>
    </section>

    @if($alertas->isNotEmpty())<section class="client-alerts" aria-label="Avisos de pago">@foreach($alertas as $alerta)<article class="{{ $alerta['indicador'] === 'vencida' ? 'overdue' : 'upcoming' }}"><i class="fa-solid {{ $alerta['indicador'] === 'vencida' ? 'fa-circle-exclamation' : 'fa-calendar-day' }}"></i><div><strong>{{ $alerta['indicador'] === 'vencida' ? 'TIENES CUOTAS VENCIDAS' : 'Próximo pago' }}</strong><p>$us {{ number_format($alerta['saldo'], 2) }} · {{ $alerta['cuota']->venta->lote->manzano->urbanizacion->nombre }} · vence {{ \Carbon\Carbon::parse($alerta['fecha'])->format('d/m/Y') }}</p></div><a class="btn" href="{{ route('portal.pagar', $alerta['cuota']->venta) }}">Pagar</a></article>@endforeach</section>@endif

    <section class="client-section">
        <div class="client-section-heading"><div><span class="client-eyebrow">Descubre</span><h2>Urbanizaciones disponibles</h2><p>Explora nuestros proyectos y reserva una visita.</p></div><a href="{{ route('portal.urbanizaciones') }}">Ver todas <i class="fa-solid fa-arrow-right"></i></a></div>
        <div class="client-project-grid">@foreach($urbanizaciones->take(3) as $urbanizacion)<x-client.urbanization-card :urbanizacion="$urbanizacion" :cliente="$cliente" :whatsapp-phone="$whatsappPhone" />@endforeach</div>
    </section>

    <section class="client-help"><div><i class="fa-regular fa-comments"></i><div><h2>¿Tienes dudas o quieres más información?</h2><p>Nuestro equipo está listo para ayudarte.</p></div></div>@if($helpWhatsappUrl)<a class="btn whatsapp" href="{{ $helpWhatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> Contactar por WhatsApp</a>@else<span>WhatsApp comercial no configurado</span>@endif</section>
</div>
@endsection
