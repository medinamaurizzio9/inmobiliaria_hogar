@props(['name', 'path' => null, 'size' => 'md'])
@php
    $normalizedPath = ltrim(preg_replace('#^storage/#', '', (string) $path), '/');
    $thumbnailPath = app(\App\Services\ManagedImageService::class)->thumbnailPath($normalizedPath);
    $resolvedPath = $thumbnailPath && Storage::disk('public')->exists($thumbnailPath) ? $thumbnailPath : $normalizedPath;
    $url = $path && ! str_contains($path, '://') && Storage::disk('public')->exists($resolvedPath) ? Storage::disk('public')->url($resolvedPath) : null;
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
@endphp
<span {{ $attributes->class(['crm-avatar', 'crm-avatar-'.$size]) }}>
    @if($url)<img src="{{ $url }}" alt="Foto de {{ $name }}" loading="lazy" decoding="async">@else<span aria-hidden="true">{{ $initials ?: 'U' }}</span>@endif
</span>
