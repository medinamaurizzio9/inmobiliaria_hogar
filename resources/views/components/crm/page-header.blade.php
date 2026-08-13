@props(['title', 'subtitle' => null])
<header {{ $attributes->class(['crm-page-header']) }}>
    <div><span class="eyebrow">Hogar Inmobiliaria CRM</span><h1>{{ $title }}</h1>@if($subtitle)<p>{{ $subtitle }}</p>@endif</div>
    @if(trim($slot))<div class="page-header-actions">{{ $slot }}</div>@endif
</header>
