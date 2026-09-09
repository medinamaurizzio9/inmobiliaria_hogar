@extends('layouts.app')
@section('title', 'Noticias y novedades')
@section('content')
<x-crm.page-header title="Noticias y novedades" subtitle="Publicaciones visibles en el portal público."><a class="btn" href="{{ route('admin.noticias.create') }}">+ Nueva noticia</a></x-crm.page-header>
@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
<div class="table-scroll"><table class="table"><thead><tr><th>Imagen</th><th>Título</th><th>Fecha</th><th>Estado</th><th>Destacada</th><th>Orden</th><th>Acciones</th></tr></thead><tbody>
@forelse($noticias as $noticia)@php($thumbnailUrl = $noticia->thumbnailUrl())<tr>
    <td>@if($thumbnailUrl)<img class="news-admin-thumb" src="{{ $thumbnailUrl }}" alt="Miniatura de {{ $noticia->titulo }}" loading="lazy" decoding="async">@else<span class="news-admin-placeholder"><i class="fa-regular fa-image"></i></span>@endif</td>
    <td><strong>{{ $noticia->titulo }}</strong><small>{{ $noticia->resumen }}</small></td>
    <td>{{ $noticia->fecha_publicacion?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
    <td><span class="badge {{ $noticia->publicada ? 'activa' : 'pendiente' }}">{{ $noticia->publicada ? 'Publicada' : 'Borrador' }}</span></td>
    <td>{{ $noticia->destacada ? 'Sí' : 'No' }}</td><td>{{ $noticia->orden ?? '—' }}</td>
    <td class="actions"><a class="btn secondary" href="{{ route('admin.noticias.edit', $noticia) }}">Editar</a><form method="POST" action="{{ route('admin.noticias.toggle', $noticia) }}">@csrf<button class="btn secondary" type="submit">{{ $noticia->publicada ? 'Ocultar' : 'Publicar' }}</button></form><form method="POST" action="{{ route('admin.noticias.destroy', $noticia) }}" onsubmit="return confirm('¿Eliminar esta noticia?')">@csrf @method('DELETE')<button class="btn danger">Eliminar</button></form></td>
</tr>@empty<tr><td colspan="7"><x-crm.empty-state title="Aún no hay noticias" message="Crea la primera publicación para el portal." /></td></tr>@endforelse
</tbody></table></div>{{ $noticias->links() }}
@endsection
