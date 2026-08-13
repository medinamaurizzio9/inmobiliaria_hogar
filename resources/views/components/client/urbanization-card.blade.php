@props(['urbanizacion', 'cliente' => null, 'whatsappPhone', 'visitOnly' => false, 'context' => 'client'])
@php
    $message = $cliente
        ? "Hola, soy {$cliente->nombre}. Quisiera reservar una visita para conocer la urbanización {$urbanizacion->nombre}. ¿Podrían brindarme información sobre horarios disponibles? Gracias."
        : "Hola, quisiera reservar una visita para conocer la urbanización {$urbanizacion->nombre}. ¿Podrían brindarme información sobre horarios disponibles? Gracias.";
    $whatsappUrl = \App\Support\WhatsAppLink::urlWithMessage($whatsappPhone, $message);
@endphp
<article {{ $attributes->class(['client-project-card', 'urbanization-card', 'is-public' => $context === 'public']) }}>
    <div class="client-project-image">
        @if($urbanizacion->plano_imagen)
            <img class="technical-plan" src="{{ Storage::disk('public')->url(ltrim(preg_replace('#^storage/#', '', $urbanizacion->plano_imagen), '/')) }}" alt="Plano de {{ $urbanizacion->nombre }}" loading="lazy">
        @else
            <span><i class="fa-regular fa-map"></i></span>
        @endif
    </div>
    <div class="client-project-body">
        <div><span class="client-eyebrow">{{ $visitOnly ? 'Reserva una visita' : 'Proyecto disponible' }}</span><h3>{{ $urbanizacion->nombre }}</h3><p><i class="fa-solid fa-location-dot"></i> {{ $urbanizacion->ubicacion ?: 'Ubicación por confirmar' }}</p></div>
        <div class="client-project-counts"><span class="total"><strong>{{ $urbanizacion->total_lotes }}</strong><small>Lotes</small></span><span class="available"><strong>{{ $urbanizacion->disponibles_count }}</strong><small>Disponibles</small></span><span class="sold"><strong>{{ $urbanizacion->vendidos_count }}</strong><small>Vendidos</small></span><span class="reserved"><strong>{{ $urbanizacion->reservados_count }}</strong><small>Reservados</small></span></div>
        <div class="client-project-actions">
            @unless($visitOnly)<a class="btn secondary" href="{{ route('disponibilidad.urbanizacion', $urbanizacion->slug) }}"><i class="fa-regular fa-map"></i> Ver disponibilidad</a>@endunless
            @if($whatsappUrl)<a class="btn whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> Reservar visita</a>@else<span class="muted">WhatsApp comercial no configurado</span>@endif
        </div>
    </div>
</article>
