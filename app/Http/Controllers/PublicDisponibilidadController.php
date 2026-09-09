<?php

namespace App\Http\Controllers;

use App\Models\Urbanizacion;
use App\Services\PublicUrlService;
use App\Services\SystemSettingsService;
use App\Support\WhatsAppLink;
use App\Support\YouTubeEmbed;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDisponibilidadController extends Controller
{
    public function __invoke(Request $request, PublicUrlService $publicUrl, SystemSettingsService $settings): View
    {
        $urbanizacion = $this->publicUrbanizacionQuery()
            ->where('estado', 'activa')
            ->when($request->integer('urbanizacion_id'), fn ($query, int $id) => $query->whereKey($id))
            ->orderBy('nombre')
            ->first();

        return $this->view($urbanizacion, $publicUrl, $settings);
    }

    public function showBySlug(string $slug, PublicUrlService $publicUrl, SystemSettingsService $settings): View
    {
        $urbanizacion = $this->publicUrbanizacionQuery()
            ->where('estado', 'activa')
            ->where('slug', $slug)
            ->first();

        return $this->view($urbanizacion, $publicUrl, $settings);
    }

    private function publicUrbanizacionQuery(): Builder
    {
        return Urbanizacion::query()
            ->select(['id', 'nombre', 'slug', 'ubicacion', 'plano_imagen', 'estado', 'mostrar_precio_publico'])
            ->with([
                'manzanos' => fn ($query) => $query->select(['id', 'urbanizacion_id', 'codigo']),
                'manzanos.lotes' => fn ($query) => $query->select(['id', 'manzano_id', 'codigo', 'superficie', 'precio', 'cuota_inicial_tipo', 'cuota_inicial_valor', 'estado', 'coord_x', 'coord_y']),
                'publicSetting:id,urbanizacion_id,hero_image,youtube_url,titulo_descripcion,descripcion_principal,info_title',
                'publicFeatures' => fn ($query) => $query->select(['id', 'urbanizacion_id', 'titulo', 'descripcion', 'orden', 'activo'])->where('activo', true),
            ]);
    }

    private function view(?Urbanizacion $urbanizacion, PublicUrlService $publicUrl, SystemSettingsService $settings): View
    {
        $publicLink = $urbanizacion?->slug
            ? $publicUrl->route('disponibilidad.urbanizacion', ['slug' => $urbanizacion->slug])
            : null;
        $publicQrDataUri = $publicLink
            ? (new PngWriter)->write(new QrCode(
                data: $publicLink,
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 180,
                margin: 8,
            ))->getDataUri()
            : null;

        $whatsappPhone = $settings->get('whatsapp') ?: $settings->get('celular');
        $whatsappUrl = WhatsAppLink::urlWithMessage($whatsappPhone, $urbanizacion
            ? "Hola, estoy interesado en un terreno de {$urbanizacion->nombre}. Quisiera recibir más información."
            : 'Hola, estoy interesado en un terreno. Quisiera recibir más información.');
        $youtubeEmbedUrl = YouTubeEmbed::url($urbanizacion?->publicSetting?->youtube_url);

        return view('disponibilidad.index', compact('urbanizacion', 'publicLink', 'publicQrDataUri', 'whatsappPhone', 'whatsappUrl', 'youtubeEmbedUrl'));
    }
}
