<?php

namespace App\Http\Controllers;

use App\Models\Noticia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NoticiaController extends Controller
{
    public function index(): View
    {
        return view('administracion.noticias.index', ['noticias' => Noticia::latest()->paginate(15)]);
    }

    public function create(): View
    {
        return view('administracion.noticias.form', ['noticia' => new Noticia]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['autor_id'] = $request->user()->id;
        $data['publicada'] = $request->boolean('publicada');
        $data['fecha_publicacion'] = $data['publicada'] ? ($data['fecha_publicacion'] ?? now()) : null;
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('noticias', 'public');
        }
        Noticia::create($data);

        return redirect()->route('admin.noticias.index')->with('status', 'Noticia creada.');
    }

    public function edit(Noticia $noticia): View
    {
        return view('administracion.noticias.form', compact('noticia'));
    }

    public function update(Request $request, Noticia $noticia): RedirectResponse
    {
        $data = $this->validated($request);
        $data['publicada'] = $request->boolean('publicada');
        $data['fecha_publicacion'] = $data['publicada'] ? ($data['fecha_publicacion'] ?? $noticia->fecha_publicacion ?? now()) : null;
        if ($request->hasFile('imagen')) {
            if ($noticia->imagen) {
                Storage::disk('public')->delete($noticia->imagen);
            }
            $data['imagen'] = $request->file('imagen')->store('noticias', 'public');
        }
        $noticia->update($data);

        return redirect()->route('admin.noticias.index')->with('status', 'Noticia actualizada.');
    }

    public function destroy(Noticia $noticia): RedirectResponse
    {
        if ($noticia->imagen) {
            Storage::disk('public')->delete($noticia->imagen);
        }
        $noticia->delete();

        return redirect()->route('admin.noticias.index')->with('status', 'Noticia eliminada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'resumen' => ['required', 'string', 'max:500'],
            'contenido' => ['required', 'string', 'max:20000'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'publicada' => ['nullable', 'boolean'],
            'fecha_publicacion' => ['nullable', 'date'],
        ]);
    }
}
