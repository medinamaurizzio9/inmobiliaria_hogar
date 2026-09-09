@props([
    'variant' => 'public-header',
    'src' => null,
    'name' => null,
    'alt' => null,
    'showName' => false,
    'preferLogin' => false,
])

@php
    $allowedVariants = ['sidebar', 'topbar', 'login', 'login-hero', 'public-header', 'footer', 'pwa'];
    $variant = in_array($variant, $allowedVariants, true) ? $variant : 'public-header';
    $settings = app(\App\Services\SystemSettingsService::class)->all();
    $brandName = trim((string) ($name ?: ($settings['system_name'] ?: $settings['company_name'])));
    $brandName = $brandName !== '' ? $brandName : 'Sistema inmobiliario';
    $logoUrl = $src ?: ($preferLogin
        ? ($settings['logo_login_url'] ?: $settings['logo_main_url'])
        : $settings['logo_main_url']);
    $initials = collect(preg_split('/\s+/u', $brandName))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : 'SI';
    $imageAlt = $alt ?? ($showName ? '' : $brandName);
@endphp

<span {{ $attributes->class(['brand-logo-frame', "brand-logo-frame--{$variant}"]) }} data-brand-logo="{{ $variant }}">
    @if($logoUrl)
        <img class="brand-logo brand-logo--{{ $variant }}" src="{{ $logoUrl }}" alt="{{ $imageAlt }}">
    @else
        <span class="brand-logo-fallback brand-logo-fallback--{{ $variant }}" role="img" aria-label="{{ $brandName }}">{{ $initials }}</span>
    @endif
    @if($showName)
        <span class="brand-logo-name">{{ $brandName }}</span>
    @endif
</span>
