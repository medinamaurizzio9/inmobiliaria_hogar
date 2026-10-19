@extends('layouts.public')
@section('title', 'Urbanizaciones — '.($systemSettings['system_name'] ?: $systemSettings['company_name']))
@section('description', 'Consulta las urbanizaciones activas y la disponibilidad actual de lotes.')
@section('content')
<section class="site-page-hero compact-hero" @if($heroImage) style="--hero-image:url('{{ $heroImage }}')" @endif><div><x-public.breadcrumb current="Urbanizaciones"/><h1>Encuentra una urbanización para tu <em>próximo proyecto</em></h1><p>La disponibilidad que ves se obtiene de los datos actuales del sistema.</p></div></section>
<section class="site-section"><x-public.section-heading eyebrow="Proyectos activos" title="Urbanizaciones disponibles" description="Entra a cada proyecto para revisar su información pública, mapa y lotes." /><div class="site-project-grid">@forelse($urbanizaciones as $urbanizacion)<x-public.urbanization-card :urbanizacion="$urbanizacion" :whatsapp-phone="$whatsappPhone" />@empty<div class="site-empty"><i class="fa-regular fa-map"></i><h3>No hay proyectos publicados</h3><p>Vuelve pronto o contáctanos para recibir información.</p></div>@endforelse</div></section>
<div class="site-section compact"><x-public.whatsapp-cta :url="$whatsappUrl" :phone="$whatsappPhone" /></div>
@endsection
