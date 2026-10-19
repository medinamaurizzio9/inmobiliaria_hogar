@extends('layouts.public')
@section('title', 'Preguntas frecuentes — '.($systemSettings['system_name'] ?: $systemSettings['company_name']))
@section('description', 'Respuestas sobre disponibilidad, visitas y contacto comercial.')
@php($faqs = [
 ['question'=>'¿Cómo consulto los lotes disponibles?', 'answer'=>'Ingresa a Urbanizaciones, elige un proyecto activo y revisa su mapa público. Los estados visibles se obtienen del sistema.'],
 ['question'=>'¿Puedo solicitar información sobre un lote específico?', 'answer'=>'Sí. Desde la página de cada urbanización puedes seleccionar un lote disponible y enviar una consulta al equipo comercial.'],
 ['question'=>'¿Cómo coordino una visita?', 'answer'=>'Utiliza el botón de WhatsApp o el formulario de contacto e indica la urbanización que te interesa. El equipo confirmará los detalles disponibles.'],
 ['question'=>'¿La consulta crea una compra o reserva?', 'answer'=>'No. Enviar el formulario público solamente registra una solicitud de contacto. Cualquier operación posterior requiere el proceso correspondiente.'],
 ['question'=>'¿Los precios aparecen en el sitio?', 'answer'=>'Solo se muestran cuando la configuración de la urbanización permite publicar esa información. Puedes solicitar detalles por los canales de contacto.'],
])
@section('content')
<section class="site-page-hero compact-hero" @if($heroImage) style="--hero-image:url('{{ $heroImage }}')" @endif><div><x-public.breadcrumb current="Preguntas frecuentes"/><h1>Respuestas para explorar con <em>más claridad</em></h1><p>Información general sobre el uso del sitio y nuestros canales de atención.</p></div></section>
<section class="site-section narrow"><x-public.section-heading eyebrow="Todo lo que necesitas saber" title="Dudas comunes" centered /><x-public.faq-accordion :items="$faqs" /></section>
<div class="site-section compact"><x-public.whatsapp-cta :url="$whatsappUrl" :phone="$whatsappPhone" title="¿Tienes otra pregunta?" /></div>
@endsection
