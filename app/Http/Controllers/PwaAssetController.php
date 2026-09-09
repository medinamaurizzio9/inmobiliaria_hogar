<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PwaAssetController extends Controller
{
    public function manifest(): BinaryFileResponse
    {
        return response()->file(public_path('manifest.webmanifest'), [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function serviceWorker(): BinaryFileResponse
    {
        return response()->file(public_path('service-worker.js'), [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): Response
    {
        return response()->view('offline', headers: [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
