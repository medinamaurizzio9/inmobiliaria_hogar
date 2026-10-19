@props(['quote', 'name', 'context' => null])
<article class="testimonial-card"><i class="fa-solid fa-quote-left" aria-hidden="true"></i><blockquote>{{ $quote }}</blockquote><footer><strong>{{ $name }}</strong>@if($context)<span>{{ $context }}</span>@endif</footer></article>
