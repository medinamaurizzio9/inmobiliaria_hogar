<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\Venta;
use App\Services\LotPricingService;
use App\Services\ReservationVisibilityService;
use App\Support\UrbanizacionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function __invoke(string $tipo, ReservationVisibilityService $visibility, LotPricingService $pricingService): Response
    {
        $canSeePrecioOportunidad = request()->user()?->hasAnyRole(['administrador', 'super administrador']) ?? false;
        $loteHeaders = $canSeePrecioOportunidad
            ? ['urbanizacion', 'manzano', 'lote', 'estado', 'superficie', 'precio_oportunidad', 'precio_real', 'cuota_inicial_tipo', 'cuota_inicial_valor', 'cuota_inicial']
            : ['urbanizacion', 'manzano', 'lote', 'estado', 'superficie', 'precio_real', 'cuota_inicial_tipo', 'cuota_inicial_valor', 'cuota_inicial'];

        $map = [
            'lotes' => [UrbanizacionContext::lotes(Lote::with('manzano.urbanizacion'))->get(), $loteHeaders],
            'clientes' => [UrbanizacionContext::clientes(Cliente::query())->get(), ['nombre', 'documento', 'telefono', 'email']],
            'ventas' => [$this->filteredVentas(Venta::with('cliente', 'lote.manzano'))->get(), ['fecha', 'cliente', 'lote', 'tipo_operacion', 'precio_base_usd', 'precio_final_usd', 'precio_final_bs', 'tipo_cambio_usd_bs', 'precio_final', 'estado']],
            'cuotas' => [UrbanizacionContext::cuotas(Cuota::with('venta.cliente'))->get(), ['cliente', 'numero', 'monto', 'pagado', 'saldo', 'estado']],
            'reservas' => [$visibility->apply(UrbanizacionContext::reservas(Reserva::with('cliente', 'lote'), UrbanizacionContext::currentId()), request()->user())->get(), ['cliente', 'lote', 'tipo_operacion', 'vence', 'monto', 'estado']],
            'caja' => [$this->filteredCaja(CashMovement::with('cliente'))->get(), ['fecha', 'cliente', 'tipo', 'concepto', 'metodo', 'referencia', 'monto', 'estado']],
        ];

        abort_unless(isset($map[$tipo]), 404);
        [$rows, $headers] = $map[$tipo];
        $csv = implode(',', $headers)."\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $this->values($tipo, $row, $pricingService, $canSeePrecioOportunidad)))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$tipo.'-impacto.csv"',
        ]);
    }

    private function values(string $tipo, mixed $row, LotPricingService $pricingService, bool $canSeePrecioOportunidad): array
    {
        return match ($tipo) {
            'lotes' => $this->loteValues($row, $pricingService, $canSeePrecioOportunidad),
            'clientes' => [$row->nombre, $row->documento, $row->telefono, $row->email],
            'ventas' => [$row->fecha_venta?->format('Y-m-d'), $row->cliente->nombre, $row->lote->manzano->codigo.'-'.$row->lote->codigo, $row->tipo_operacion, $row->precio_base_usd, $row->precio_final_usd, $row->precio_final_bs, $row->tipo_cambio_usd_bs, $row->precio_final, $row->estado],
            'cuotas' => [$row->venta->cliente->nombre, $row->numero, $row->monto, $row->monto_pagado, $row->saldo_pendiente, $row->estado],
            'reservas' => [$row->cliente->nombre, $row->lote->codigo, $row->tipo_operacion, $row->fecha_vencimiento?->format('Y-m-d'), $row->monto_reserva, $row->estado],
            'caja' => [$row->fecha?->format('Y-m-d'), $row->cliente?->nombre, $row->tipo, $row->concepto, $row->metodo_pago, $row->referencia, $row->monto, $row->estado],
        };
    }

    private function loteValues(Lote $row, LotPricingService $pricingService, bool $canSeePrecioOportunidad): array
    {
        $payload = $pricingService->payload($row);
        $values = [$row->manzano->urbanizacion->nombre, $row->manzano->codigo, $row->codigo, $row->estado, $row->superficie];

        if ($canSeePrecioOportunidad) {
            $values[] = $row->precio;
        }

        return [
            ...$values,
            $payload['credit_usd'],
            $row->cuota_inicial_tipo,
            $row->cuota_inicial_valor,
            $row->cuotaInicialTexto(),
        ];
    }

    private function filteredVentas(Builder $query): Builder
    {
        $search = trim((string) request('q', ''));

        return UrbanizacionContext::ventas($query)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested->whereHas('cliente', function (Builder $clienteQuery) use ($search): void {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('documento', 'like', "%{$search}%")
                            ->orWhere('telefono', 'like', "%{$search}%");
                    })->orWhereHas('lote', function (Builder $loteQuery) use ($search): void {
                        $loteQuery->where('codigo', 'like', "%{$search}%")
                            ->orWhereHas('manzano', fn (Builder $manzanoQuery) => $manzanoQuery->where('codigo', 'like', "%{$search}%"));
                    });
                });
            })
            ->when(request()->filled('estado'), fn (Builder $builder) => $builder->where('estado', request('estado')))
            ->when(request()->filled('fecha_desde'), fn (Builder $builder) => $builder->whereDate('fecha_venta', '>=', request('fecha_desde')))
            ->when(request()->filled('fecha_hasta'), fn (Builder $builder) => $builder->whereDate('fecha_venta', '<=', request('fecha_hasta')))
            ->latest();
    }

    private const FILTROS_CAJA = [
        'q', 'cliente', 'documento', 'referencia', 'lote', 'modalidad',
        'tipo', 'concepto', 'metodo_pago', 'estado',
        'fecha_desde', 'fecha_hasta', 'monto_min', 'monto_max', 'usuario_id',
    ];

    private function filteredCaja(Builder $query): Builder
    {
        $urbanizacionId = UrbanizacionContext::filtroUrbanizacion(request()->user(), request('urbanizacion_id', ''));

        return UrbanizacionContext::cashMovements($query, $urbanizacionId)
            ->filtered(request()->only(self::FILTROS_CAJA))
            ->latest();
    }
}
