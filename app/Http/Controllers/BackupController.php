<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('administracion.backups.index');
    }

    public function store(DatabaseBackupService $backups): RedirectResponse
    {
        try {
            $path = $backups->create();

            return back()->with('status', 'Backup generado correctamente: '.basename($path));
        } catch (RuntimeException $exception) {
            Log::warning('No se pudo generar el backup de base de datos.', ['exception' => $exception]);

            return back()->withErrors(['backup' => $exception->getMessage()]);
        }
    }
}
