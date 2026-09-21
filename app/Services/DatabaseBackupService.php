<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function create(): string
    {
        $directory = (string) config('backup.directory');
        File::ensureDirectoryExists($directory);
        $connection = (string) config('database.default');
        $timestamp = now()->format('Ymd_His');

        if ($connection === 'sqlite') {
            return $this->backupSqlite($directory, $timestamp);
        }

        if ($connection === 'mysql') {
            return $this->backupMysql($directory, $timestamp);
        }

        throw new RuntimeException("La conexión {$connection} no está soportada para respaldos.");
    }

    private function backupSqlite(string $directory, string $timestamp): string
    {
        $database = (string) config('database.connections.sqlite.database');
        if ($database === ':memory:') {
            $target = "{$directory}/database_{$timestamp}_memory.txt";
            File::put($target, 'Base SQLite en memoria: no existe un archivo físico para copiar.');

            return $target;
        }

        if ($database === '' || ! File::exists($database)) {
            throw new RuntimeException('No se encontró el archivo SQLite para respaldar.');
        }

        $target = "{$directory}/database_{$timestamp}.sqlite";
        if (! File::copy($database, $target)) {
            throw new RuntimeException('No se pudo copiar la base SQLite.');
        }

        return $target;
    }

    private function backupMysql(string $directory, string $timestamp): string
    {
        $cfg = config('database.connections.mysql');
        $binary = trim((string) config('backup.mysqldump_path', 'mysqldump'));
        if ($binary === '') {
            throw new RuntimeException('Configure MYSQLDUMP_PATH para generar respaldos MySQL.');
        }

        $target = "{$directory}/database_{$timestamp}.sql";
        $arguments = [
            $binary,
            '--host='.$cfg['host'],
            '--port='.(string) $cfg['port'],
            '--user='.$cfg['username'],
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            (string) $cfg['database'],
        ];
        $environment = is_array(getenv()) ? getenv() : [];
        if ($cfg['password'] !== '') {
            $environment['MYSQL_PWD'] = (string) $cfg['password'];
        }
        $stream = null;

        try {
            $stream = fopen($target, 'wb');
            if ($stream === false) {
                throw new RuntimeException('No se pudo crear el archivo de respaldo.');
            }

            $process = new Process($arguments, base_path(), $environment, null, 300);
            $process->run(function (string $type, string $buffer) use ($stream): void {
                if ($type === Process::OUT) {
                    fwrite($stream, $buffer);
                }
            });

            if (! $process->isSuccessful()) {
                $detail = trim($process->getErrorOutput());
                throw new RuntimeException('mysqldump no pudo completar el respaldo (código '.$process->getExitCode().').'.($detail !== '' ? ' '.$detail : '').' Revise MYSQLDUMP_PATH y el acceso a MySQL.');
            }
        } catch (Throwable $exception) {
            if (is_resource($stream)) {
                fclose($stream);
                $stream = null;
            }
            File::delete($target);

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException('No se pudo ejecutar mysqldump. Revise MYSQLDUMP_PATH y la instalación de MySQL.', previous: $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! File::exists($target) || File::size($target) === 0) {
            File::delete($target);
            throw new RuntimeException('mysqldump terminó sin generar un respaldo válido.');
        }

        return $target;
    }
}
