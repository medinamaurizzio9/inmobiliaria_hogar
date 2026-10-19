@props(['icon', 'title', 'value', 'detail' => null, 'href' => null])
<article class="contact-info-card"><i class="{{ $icon }}" aria-hidden="true"></i><div><span>{{ $title }}</span>@if($href)<a href="{{ $href }}">{{ $value }}</a>@else<strong>{{ $value }}</strong>@endif @if($detail)<small>{{ $detail }}</small>@endif</div></article>
