<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use RuntimeException;

class ImpactoBackup extends Command
{
    protected $signature = 'impacto:backup';

    protected $description = 'Genera un respaldo seguro de la base de datos.';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $target = $backups->create();
            $this->info("Backup generado: {$target}");

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
