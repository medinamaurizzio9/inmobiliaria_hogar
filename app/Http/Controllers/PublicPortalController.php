<?php

namespace App\Http\Controllers;

use App\Models\Noticia;
use App\Models\Urbanizacion;
use App\Services\SystemSettingsService;
use App\Support\WhatsAppLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PublicPortalController extends Controller
{
    public function __invoke(Request $request, SystemSettingsService $settings): View
    {
        $data = $this->siteData($settings);
        $data['noticias'] = Schema::hasTable('noticias')
            ? Noticia::publicadas()->ordenPublico()->limit(3)->get()
            : collect();

        return view('public.portal', $data);
    }

    public function about(SystemSettingsService $settings): View
    {
        return view('public.about', $this->siteData($settings));
    }

    public function urbanizations(SystemSettingsService $settings): View
    {
        return view('public.urbanizations', $this->siteData($settings));
    }

    public function testimonials(SystemSettingsService $settings): View
    {
        return view('public.testimonials', $this->siteData($settings));
    }

    public function faq(SystemSettingsService $settings): View
    {
        return view('public.faq', $this->siteData($settings));
    }

    public function contact(SystemSettingsService $settings): View
    {
        return view('public.contact', $this->siteData($settings));
    }

    public function noticia(string $slug): View
    {
        abort_unless(Schema::hasTable('noticias'), 404);

        $noticia = Noticia::publicadas()->where('slug', $slug)->firstOrFail();
        $recientes = Noticia::publicadas()->whereKeyNot($noticia->id)->ordenPublico()->limit(3)->get();

        return view('public.noticia', compact('noticia', 'recientes'));
    }

    private function siteData(SystemSettingsService $settings): array
    {
        $urbanizaciones = Schema::hasTable('urbanizaciones')
            ? Urbanizacion::query()->where('estado', 'activa')->with('publicSetting')->withLotStats()->orderBy('nombre')->get()
            : collect();
        $whatsappPhone = $settings->get('whatsapp') ?: $settings->get('celular');
        $configuredHero = $settings->get('landing_hero_image_url');
        $referenceAbout = $this->publicAsset('branding/landing/hogar-nosotros.webp');

        return [
            'urbanizaciones' => $urbanizaciones,
            'totals' => [
                'proyectos' => $urbanizaciones->count(),
                'disponibles' => $urbanizaciones->sum('disponibles_count'),
                'vendidos' => $urbanizaciones->sum('vendidos_count'),
                'reservados' => $urbanizaciones->sum('reservados_count'),
            ],
            'whatsappPhone' => $whatsappPhone,
            'whatsappUrl' => WhatsAppLink::urlWithMessage($whatsappPhone, 'Hola, quisiera información sobre sus proyectos inmobiliarios.'),
            'heroImage' => $configuredHero,
            'aboutImage' => $configuredHero ?: $referenceAbout,
            'projectComplementImage' => $configuredHero ?: $this->publicAsset('branding/landing/hogar-proyectos.webp'),
        ];
    }

    private function publicAsset(string $path): ?string
    {
        return is_file(public_path($path)) ? asset($path) : null;
    }
}
