<?php

namespace App\Http\Controllers;

use App\Models\Urbanizacion;
use App\Services\PublicUrlService;
use App\Services\SystemSettingsService;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDisponibilidadController extends Controller
{
    public function __invoke(Request $request, PublicUrlService $publicUrl, SystemSettingsService $settings): View
    {
        $urbanizaciones = Urbanizacion::where('estado', 'activa')->orderBy('nombre')->get();
        $urbanizacion = Urbanizacion::with('manzanos.lotes')
            ->where('estado', 'activa')
            ->find($request->integer('urbanizacion_id') ?: $urbanizaciones->first()?->id);

        return $this->view($urbanizaciones, $urbanizacion, $publicUrl, $settings);
    }

    public function showBySlug(string $slug, PublicUrlService $publicUrl, SystemSettingsService $settings): View
    {
        $urbanizaciones = Urbanizacion::where('estado', 'activa')->orderBy('nombre')->get();
        $urbanizacion = Urbanizacion::with('manzanos.lotes')
            ->where('estado', 'activa')
            ->where('slug', $slug)
            ->first();

        return $this->view($urbanizaciones, $urbanizacion, $publicUrl, $settings);
    }

    private function view($urbanizaciones, ?Urbanizacion $urbanizacion, PublicUrlService $publicUrl, SystemSettingsService $settings): View
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

        return view('disponibilidad.index', compact('urbanizaciones', 'urbanizacion', 'publicLink', 'publicQrDataUri', 'whatsappPhone'));
    }
}
