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
        $urbanizaciones = Schema::hasTable('urbanizaciones')
            ? Urbanizacion::query()->where('estado', 'activa')->withLotStats()->orderBy('nombre')->get()
            : collect();
        $noticias = Schema::hasTable('noticias')
                ? Noticia::publicadas()->ordenPublico()->limit(3)->get()
            : collect();
        $totals = [
            'proyectos' => $urbanizaciones->count(),
            'disponibles' => $urbanizaciones->sum('disponibles_count'),
            'vendidos' => $urbanizaciones->sum('vendidos_count'),
            'reservados' => $urbanizaciones->sum('reservados_count'),
        ];
        $whatsappPhone = $settings->get('whatsapp') ?: $settings->get('celular');
        $whatsappUrl = WhatsAppLink::urlWithMessage($whatsappPhone, 'Hola, quisiera información sobre sus proyectos inmobiliarios.');

        return view('public.portal', compact('urbanizaciones', 'noticias', 'totals', 'whatsappPhone', 'whatsappUrl'));
    }

    public function noticia(string $slug): View
    {
        abort_unless(Schema::hasTable('noticias'), 404);

        $noticia = Noticia::publicadas()->where('slug', $slug)->firstOrFail();
        $recientes = Noticia::publicadas()->whereKeyNot($noticia->id)->ordenPublico()->limit(3)->get();

        return view('public.noticia', compact('noticia', 'recientes'));
    }
}
