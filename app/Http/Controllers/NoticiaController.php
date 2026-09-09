<?php

namespace App\Http\Controllers;

use App\Models\Noticia;
use App\Services\AuditService;
use App\Services\ManagedImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticiaController extends Controller
{
    public function __construct(private ManagedImageService $images, private AuditService $audit) {}

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
        $data['publicada'] = $data['estado'] === 'publicada';
        $data['destacada'] = $request->boolean('destacada');
        $data['fecha_publicacion'] = $data['publicada'] ? ($data['fecha_publicacion'] ?? now()) : null;
        unset($data['estado']);
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $this->images->replaceOptimized(null, $request->file('imagen'), 'noticias', $this->imageOptions())['path'];
        }
        $noticia = Noticia::create($data);
        $this->audit->log($noticia, 'noticia_creada', 'Noticia creada.', null, $noticia->toArray(), $request);

        return redirect()->route('admin.noticias.index')->with('status', 'Noticia creada.');
    }

    public function edit(Noticia $noticia): View
    {
        return view('administracion.noticias.form', compact('noticia'));
    }

    public function update(Request $request, Noticia $noticia): RedirectResponse
    {
        $data = $this->validated($request);
        $before = $noticia->toArray();
        $data['publicada'] = $data['estado'] === 'publicada';
        $data['destacada'] = $request->boolean('destacada');
        $data['fecha_publicacion'] = $data['publicada'] ? ($data['fecha_publicacion'] ?? $noticia->fecha_publicacion ?? now()) : null;
        unset($data['estado']);
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $this->images->replaceOptimized($noticia->imagen, $request->file('imagen'), 'noticias', $this->imageOptions())['path'];
        }
        $noticia->update($data);
        $this->audit->log($noticia, 'noticia_actualizada', 'Noticia actualizada.', $before, $noticia->fresh()->toArray(), $request);

        if ((bool) $before['publicada'] !== $noticia->publicada) {
            $this->audit->log($noticia, $noticia->publicada ? 'noticia_publicada' : 'noticia_ocultada', $noticia->publicada ? 'Noticia publicada.' : 'Noticia ocultada.', ['publicada' => $before['publicada']], ['publicada' => $noticia->publicada], $request);
        }

        return redirect()->route('admin.noticias.index')->with('status', 'Noticia actualizada.');
    }

    public function toggle(Request $request, Noticia $noticia): RedirectResponse
    {
        $before = $noticia->toArray();
        $noticia->update([
            'publicada' => ! $noticia->publicada,
            'fecha_publicacion' => $noticia->publicada ? null : ($noticia->fecha_publicacion ?? now()),
        ]);
        $this->audit->log($noticia, $noticia->publicada ? 'noticia_publicada' : 'noticia_ocultada', $noticia->publicada ? 'Noticia publicada.' : 'Noticia ocultada.', $before, $noticia->fresh()->toArray(), $request);

        return redirect()->route('admin.noticias.index')->with('status', $noticia->publicada ? 'Noticia publicada.' : 'Noticia ocultada.');
    }

    public function destroy(Request $request, Noticia $noticia): RedirectResponse
    {
        $before = $noticia->toArray();
        $this->images->delete($noticia->imagen, 'noticias');
        $noticia->delete();
        $this->audit->log($noticia, 'noticia_eliminada', 'Noticia eliminada.', $before, null, $request);

        return redirect()->route('admin.noticias.index')->with('status', 'Noticia eliminada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'resumen' => ['required', 'string', 'max:500'],
            'contenido' => ['required', 'string', 'max:20000'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'estado' => ['required', 'in:borrador,publicada'],
            'destacada' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fecha_publicacion' => ['nullable', 'date'],
        ]);
    }

    private function imageOptions(): array
    {
        return ['max_width' => 1600, 'max_height' => 1600, 'quality' => 84, 'generate_thumbnail' => true, 'thumbnail_width' => 600, 'thumbnail_height' => 600];
    }
}
