@extends('layouts.public')
@section('title', $noticia->titulo.' — '.$systemSettings['system_name'])
@section('description', $noticia->resumen)
@if($noticia->imageUrl())@section('og_image', $noticia->imageUrl())@endif
@section('content')
<article class="public-article site-article">
    <x-public.breadcrumb current="Noticias" />
    <time datetime="{{ $noticia->fecha_publicacion->toDateString() }}">{{ $noticia->fecha_publicacion->format('d/m/Y') }}</time>
    <h1>{{ $noticia->titulo }}</h1>
    <p class="lead">{{ $noticia->resumen }}</p>
    @if($noticia->imageUrl())<img src="{{ $noticia->imageUrl() }}" alt="{{ $noticia->titulo }}" fetchpriority="high">@else<div class="public-article-placeholder"><i class="fa-regular fa-newspaper" aria-hidden="true"></i></div>@endif
    <div class="article-content">{!! nl2br(e($noticia->contenido)) !!}</div>
    @if($recientes->isNotEmpty())<aside class="recent-news"><h2>Noticias recientes</h2>@foreach($recientes as $item)<a href="{{ route('public.noticias.show', $item->slug) }}">{{ $item->titulo }} <i class="fa-solid fa-arrow-right"></i></a>@endforeach</aside>@endif
</article>
@endsection
