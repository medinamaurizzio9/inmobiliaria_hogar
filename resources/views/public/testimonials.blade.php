@extends('layouts.public')
@section('title', 'Testimonios — '.($systemSettings['system_name'] ?: $systemSettings['company_name']))
@section('description', 'Experiencias de clientes publicadas por nuestro equipo.')
@section('content')
<section class="site-page-hero compact-hero" @if($heroImage) style="--hero-image:url('{{ $heroImage }}')" @endif><div><x-public.breadcrumb current="Testimonios"/><h1>Experiencias que podremos <em>compartir contigo</em></h1><p>Este espacio mostrará únicamente testimonios reales y autorizados.</p></div></section>
<section class="site-section"><x-public.section-heading eyebrow="Historias reales" title="Testimonios de nuestros clientes" centered /><div class="site-empty bordered"><i class="fa-regular fa-comments"></i><h3>Aún no hay testimonios publicados</h3><p>No usamos nombres, fotografías ni historias ficticias. Cuando existan testimonios autorizados, aparecerán aquí.</p></div></section>
<div class="site-section compact"><x-public.whatsapp-cta :url="$whatsappUrl" :phone="$whatsappPhone" title="Conoce nuestros proyectos directamente" /></div>
@endsection
