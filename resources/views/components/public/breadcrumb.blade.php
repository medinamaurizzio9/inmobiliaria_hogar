@props(['current'])
<nav class="site-breadcrumb" aria-label="Migas de pan"><a href="{{ route('public.home') }}">Inicio</a><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span aria-current="page">{{ $current }}</span></nav>
