<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Devolucion;
use App\Models\Reestructuracion;
use App\Models\Venta;
use App\Support\UrbanizacionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ManagementReportService
{
    public function data(array $filters): array
    {
        $ventas = $this->salesQuery($filters)->with('cliente', 'user', 'lote.manzano.urbanizacion', 'cuotas', 'cashMovements', 'devoluciones', 'reestructuraciones.administrador')->get();
        $saleIds = $ventas->pluck('id');
        $payments = CashMovement::with('user')->whereIn('sale_id', $saleIds)->where('tipo', 'ingreso')->where('estado', 'confirmado')->when($filters['metodo_pago'] ?? null, fn ($q, $v) => $q->where('metodo_pago', $v))->when($filters['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))->when($filters['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))->get();
        $refunds = Devolucion::with('venta.cliente', 'venta.lote.manzano.urbanizacion', 'responsable')->whereIn('venta_id', $saleIds)->when($filters['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))->when($filters['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))->get();
        $restructures = Reestructuracion::with('venta.cliente', 'venta.lote.manzano.urbanizacion', 'administrador')->whereIn('venta_id', $saleIds)->get();
        $days = app(FinancialSettingsService::class)->diasAvisoVencimiento();
        $rows = $ventas->map(fn (Venta $sale) => $this->portfolioRow($sale, $days));
        if ($state = $filters['estado_financiero'] ?? null) {
            $rows = $rows->where('estado', $state)->values();
        }
        $activeRows = $rows->whereNotIn('estado', ['RESCINDIDA']);
        $gross = (float) $payments->sum('monto');
        $returned = (float) $refunds->sum('monto_devuelto');

        return compact('ventas', 'payments', 'refunds', 'restructures', 'rows') + [
            'kpi' => ['vendido' => (float) $ventas->where('estado', '!=', 'anulada')->sum('precio_final'), 'por_modalidad' => collect(['contado', 'semicontado', 'credito'])->mapWithKeys(fn ($m) => [$m => (float) $ventas->where('estado', '!=', 'anulada')->where('tipo_operacion', $m)->sum('precio_final')]), 'cobrado_bruto' => $gross, 'devuelto' => $returned, 'cobrado_neto' => $gross - $returned, 'retenido' => (float) $refunds->sum('monto_retenido_empresa'), 'saldo' => (float) $activeRows->sum('saldo'), 'al_dia' => (float) $activeRows->whereIn('estado', ['AL DÍA', 'PRÓXIMA'])->sum('saldo'), 'vencida' => (float) $activeRows->sum('monto_vencido'), 'proxima' => (float) $activeRows->where('estado', 'PRÓXIMA')->sum('proxima_monto'), 'cobrado_hoy' => (float) $payments->where('fecha', today())->sum('monto'), 'cobrado_mes' => (float) $payments->filter(fn ($p) => $p->fecha?->isSameMonth(today()))->sum('monto'), 'clientes_saldo' => $activeRows->where('saldo', '>', 0)->pluck('cliente_id')->unique()->count(), 'activas' => $ventas->where('estado', 'activa')->count(), 'completadas' => $ventas->where('estado', 'completada')->count(), 'anuladas' => $ventas->where('estado', 'anulada')->count()],
            'installments' => ['pagadas' => $ventas->flatMap->cuotas->where('estado', 'pagada')->count(), 'parciales' => $ventas->flatMap->cuotas->where('estado', 'parcial')->count(), 'pendientes' => $ventas->flatMap->cuotas->where('estado', 'pendiente')->count(), 'vencidas' => $ventas->flatMap->cuotas->where('estado', 'vencida')->count()],
            'byUrbanization' => $this->groupRows($rows, 'urbanizacion'), 'bySeller' => $this->groupRows($rows, 'vendedor'), 'byModality' => $this->groupRows($rows, 'modalidad'),
            'byMethod' => $payments->groupBy('metodo_pago')->map(fn ($g, $key) => ['nombre' => $key, 'operaciones' => $g->count(), 'monto' => (float) $g->sum('monto'), 'porcentaje' => $gross > 0 ? round($g->sum('monto') / $gross * 100, 2) : 0])->values(),
            'byCashier' => $payments->groupBy(fn ($p) => $p->user?->name ?? 'Sistema')->map(fn ($g, $key) => ['nombre' => $key, 'operaciones' => $g->count(), 'efectivo' => (float) $g->where('metodo_pago', 'efectivo')->sum('monto'), 'qr' => (float) $g->where('metodo_pago', 'QR')->sum('monto'), 'transferencia' => (float) $g->where('metodo_pago', 'transferencia')->sum('monto')])->values(),
            'upcoming' => $ventas->where('estado', '!=', 'anulada')->flatMap(fn (Venta $sale) => $sale->cuotas->whereIn('estado', ['pendiente', 'parcial'])->where('saldo_pendiente', '>', 0)->map(fn ($c) => ['fecha' => $c->fecha_vencimiento, 'cliente' => $sale->cliente->nombre, 'terreno' => $sale->lote->manzano->codigo.'-'.$sale->lote->codigo, 'cuota' => $c->numero, 'monto' => (float) $c->saldo_pendiente, 'modalidad' => $sale->tipo_operacion]))->filter(fn ($r) => $r['fecha']?->between(today(), today()->addDays((int) ($filters['horizonte'] ?? 30))))->sortBy('fecha')->values(),
        ];
    }

    private function salesQuery(array $f): Builder
    {
        $urbanization = isset($f['urbanizacion_id']) && $f['urbanizacion_id'] !== '' ? (int) $f['urbanizacion_id'] : UrbanizacionContext::currentId();

        return UrbanizacionContext::ventas(Venta::query(), $urbanization)->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_venta', '>=', $v))->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_venta', '<=', $v))->when($f['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v))->when($f['vendedor_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))->when($f['modalidad'] ?? null, fn ($q, $v) => $q->where('tipo_operacion', $v))->when($f['estado_venta'] ?? null, fn ($q, $v) => $q->where('estado', $v));
    }

    private function portfolioRow(Venta $sale, int $notice): array
    {
        $active = $sale->estado === 'anulada' ? collect() : $sale->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('saldo_pendiente', '>', 0);
        $overdue = $active->filter(fn ($c) => $c->estado === 'vencida' || $c->fecha_vencimiento?->lt(today()));
        $next = $active->sortBy('fecha_vencimiento')->first();
        $balance = (float) $active->sum('saldo_pendiente');
        $state = $sale->estado === 'anulada' ? 'RESCINDIDA' : ($balance <= 0 ? 'PAGADA' : ($overdue->isNotEmpty() ? 'VENCIDA' : ($next?->fecha_vencimiento?->lte(today()->addDays($notice)) ? 'PRÓXIMA' : 'AL DÍA')));

        return ['venta_id' => $sale->id, 'cliente_id' => $sale->cliente_id, 'cliente' => $sale->cliente?->nombre, 'telefono' => $sale->cliente?->telefono, 'urbanizacion' => $sale->lote->manzano->urbanizacion->nombre, 'terreno' => $sale->lote->manzano->codigo.'-'.$sale->lote->codigo, 'modalidad' => $sale->tipo_operacion, 'vendedor' => $sale->user?->name ?? 'Sin asignar', 'precio' => (float) $sale->precio_final, 'inicial' => (float) $sale->cuota_inicial, 'cobrado' => (float) $sale->cashMovements->where('tipo', 'ingreso')->where('estado', 'confirmado')->sum('monto'), 'saldo' => $balance, 'proxima' => $next?->fecha_vencimiento?->toDateString(), 'proxima_monto' => (float) ($next?->saldo_pendiente ?? 0), 'cuotas_vencidas' => $overdue->count(), 'monto_vencido' => (float) $overdue->sum('saldo_pendiente'), 'dias_atraso' => $overdue->min('fecha_vencimiento') ? (int) Carbon::parse($overdue->min('fecha_vencimiento'))->diffInDays(today()) : 0, 'estado' => $state];
    }

    private function groupRows(Collection $rows, string $key): Collection
    {
        return $rows->groupBy($key)->map(fn ($g, $name) => ['nombre' => $name, 'operaciones' => $g->count(), 'vendido' => (float) $g->sum('precio'), 'cobrado' => (float) $g->sum('cobrado'), 'saldo' => (float) $g->sum('saldo'), 'vencido' => (float) $g->sum('monto_vencido'), 'ticket' => $g->count() ? round($g->sum('precio') / $g->count(), 2) : 0])->values();
    }
}
