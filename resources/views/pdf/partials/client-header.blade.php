@php($logo = ($settings['logo_pdf_path'] ?? '') ?: ($settings['logo_main_path'] ?? null))
<div class="header">
    <div class="logo">@if($logo)<img src="{{ $logo }}" alt="Logo">@endif</div>
    <div class="company">
        <div class="system-name">{{ $settings['system_name'] ?? 'IMPACTO URBANIZACIONES' }}</div>
        <div>{{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</div>
        <div class="muted">{{ $settings['company_name'] ?? '' }} {{ $settings['nit'] ? '| NIT: '.$settings['nit'] : '' }}</div>
        <div class="muted">{{ $settings['direccion'] ?? '' }} {{ $settings['ciudad'] ? '- '.$settings['ciudad'] : '' }}</div>
        <div class="muted">{{ $settings['telefono'] ?? '' }} {{ $settings['email'] ? '| '.$settings['email'] : '' }}</div>
    </div>
</div>
