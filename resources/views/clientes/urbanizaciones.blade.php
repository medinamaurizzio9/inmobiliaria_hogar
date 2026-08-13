@extends('layouts.app')
@section('title', $visitas ? 'Reserva de visitas' : 'Urbanizaciones')
@section('content')
<div class="client-portal">
    <header class="client-section-header">
        <div><span class="client-eyebrow">{{ $visitas ? 'Atención personalizada' : 'Proyectos inmobiliarios' }}</span><h1>{{ $visitas ? 'Reserva de visitas' : 'Ver urbanizaciones' }}</h1><p>{{ $visitas ? 'Elige un proyecto y solicita por WhatsApp un horario para conocerlo.' : 'Explora proyectos, disponibilidad y planos públicos sin exponer información privada.' }}</p></div>
        <a class="btn secondary" href="{{ route('clientes.mi-cuenta') }}"><i class="fa-solid fa-arrow-left"></i> Volver a Mi cuenta</a>
    </header>
    <div class="client-project-grid">
        @forelse($urbanizaciones as $urbanizacion)
            <x-client.urbanization-card :urbanizacion="$urbanizacion" :cliente="$cliente" :whatsapp-phone="$whatsappPhone" :visit-only="$visitas" />
        @empty
            <x-crm.empty-state title="No hay urbanizaciones activas" />
        @endforelse
    </div>
</div>
@endsection
