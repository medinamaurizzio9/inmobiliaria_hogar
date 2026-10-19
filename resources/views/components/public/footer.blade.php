@php
    $subtitle = trim((string) $systemSettings['system_subtitle']);
    $publicSubtitle = strcasecmp($subtitle, 'Sistema Integral de Terrenos') === 0 ? '' : $subtitle;
    $footerText = trim((string) $systemSettings['footer_text']);
    $publicFooterText = str_contains(mb_strtolower($footerText), 'versión piloto') || str_contains(mb_strtolower($footerText), 'version piloto') ? '' : $footerText;
@endphp
<footer class="site-footer">
    <div class="site-footer-main"><div><x-brand-logo variant="footer" :show-name="true" />@if($publicSubtitle)<p>{{ $publicSubtitle }}</p>@elseif($systemSettings['ciudad'] || $systemSettings['departamento'])<p>{{ collect([$systemSettings['ciudad'], $systemSettings['departamento']])->filter()->unique()->implode(', ') }}</p>@endif</div><nav aria-label="Navegación secundaria"><a href="{{ route('public.about') }}">Nosotros</a><a href="{{ route('public.urbanizations') }}">Urbanizaciones</a><a href="{{ route('public.faq') }}">Preguntas frecuentes</a><a href="{{ route('public.contact') }}">Contacto</a></nav></div>
    <div class="site-footer-bottom"><span>© {{ now()->year }} {{ $systemSettings['system_name'] ?: $systemSettings['company_name'] }}. Todos los derechos reservados.</span>@if($publicFooterText)<span>{{ $publicFooterText }}</span>@else<a href="{{ route('public.contact') }}">Información y contacto</a>@endif</div>
</footer>
