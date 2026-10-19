@props(['eyebrow' => null, 'title', 'description' => null, 'centered' => false])
<div {{ $attributes->class(['site-section-heading', 'centered' => $centered]) }}>@if($eyebrow)<span class="site-eyebrow"><i class="fa-regular fa-leaf" aria-hidden="true"></i>{{ $eyebrow }}</span>@endif<h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>
