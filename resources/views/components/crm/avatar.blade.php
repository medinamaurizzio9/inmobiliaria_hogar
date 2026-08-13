@props(['name', 'path' => null, 'size' => 'md'])
@php
    $url = $path && ! str_contains($path, '://') && Storage::disk('public')->exists(ltrim(preg_replace('#^storage/#', '', $path), '/'))
        ? Storage::disk('public')->url(ltrim(preg_replace('#^storage/#', '', $path), '/'))
        : null;
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
@endphp
<span {{ $attributes->class(['crm-avatar', 'crm-avatar-'.$size]) }}>
    @if($url)<img src="{{ $url }}" alt="Foto de {{ $name }}" loading="lazy">@else<span aria-hidden="true">{{ $initials ?: 'U' }}</span>@endif
</span>
