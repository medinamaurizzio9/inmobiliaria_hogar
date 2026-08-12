<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Cuota;
use Illuminate\Support\Collection;

class PaymentAlertService
{
    public function __construct(private FinancialSettingsService $settings) {}

    /** @return Collection<int, array{cuota: Cuota, indicador: string, saldo: float, fecha: string, dias: int}> */
    public function forCliente(Cliente $cliente): Collection
    {
        $diasAviso = $this->settings->diasAvisoVencimiento();
        $hoy = now()->startOfDay();

        return Cuota::query()
            ->with('venta.lote.manzano.urbanizacion')
            ->whereHas('venta', fn ($query) => $query->where('cliente_id', $cliente->id))
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->where('saldo_pendiente', '>', 0)
            ->get()
            ->map(function (Cuota $cuota) use ($diasAviso, $hoy): ?array {
                $fecha = ($cuota->fecha_vencimiento ?? $cuota->fecha_programada)?->startOfDay();
                if (! $fecha) {
                    return null;
                }

                $dias = $hoy->diffInDays($fecha, false);
                if ($dias < 0) {
                    $indicador = 'vencida';
                } elseif ($dias <= $diasAviso) {
                    $indicador = 'proxima_vencer';
                } else {
                    return null;
                }

                return [
                    'cuota' => $cuota,
                    'indicador' => $indicador,
                    'saldo' => (float) $cuota->saldo_pendiente,
                    'fecha' => $fecha->toDateString(),
                    'dias' => (int) $dias,
                ];
            })
            ->filter()
            ->sortBy(fn (array $alerta) => [$alerta['indicador'] === 'vencida' ? 0 : 1, $alerta['fecha']])
            ->values();
    }
}
