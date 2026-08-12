<?php

namespace App\Http\Controllers;

use App\Models\Asesor;
use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Devolucion;
use App\Models\GrupoComercial;
use App\Models\Lote;
use App\Models\Manzano;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Services\AuditService;
use App\Services\ManagementReportService;
use App\Services\ReservationVisibilityService;
use App\Services\SystemSettingsService;
use App\Support\UrbanizacionContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const MANAGEMENT_FILTERS = ['desde', 'hasta', 'urbanizacion_id', 'cliente_id', 'vendedor_id', 'modalidad', 'estado_financiero', 'metodo_pago', 'estado_venta', 'horizonte'];

    public function management(Request $request, ManagementReportService $service): View
    {
        $this->authorizeManagement($request);
        $filters = $request->only(self::MANAGEMENT_FILTERS);

        return view('reportes.gerencia', $service->data($filters) + ['filters' => $filters, 'urbanizaciones' => UrbanizacionContext::accessibleUrbanizaciones($request->user()), 'clientes' => Cliente::orderBy('nombre')->get(), 'vendedores' => User::role('vendedor')->orderBy('name')->get()]);
    }

    public function managementCsv(Request $request, ManagementReportService $service): Response
    {
        $this->authorizeManagement($request);
        $data = $service->data($request->only(self::MANAGEMENT_FILTERS));
        $csv = $this->csvFromRows(['venta', 'cliente', 'telefono', 'urbanizacion', 'terreno', 'modalidad', 'vendedor', 'precio', 'inicial', 'cobrado', 'saldo', 'proxima', 'cuotas_vencidas', 'monto_vencido', 'dias_atraso', 'estado'], $data['rows'], fn ($r) => array_values($r));

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="cartera-gerencial.csv"']);
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['administrador', 'gerente']), 403, 'No tienes acceso a reportes gerenciales.');
    }

    public function index(): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();

        return view('reportes.index', [
            'lotesTotal' => UrbanizacionContext::lotes(Lote::query(), $urbanizacionId)->count(),
            'reservasActivas' => UrbanizacionContext::reservas(Reserva::query(), $urbanizacionId)->where('estado', 'activa')->count(),
            'cuotasPendientes' => UrbanizacionContext::cuotas(Cuota::query(), $urbanizacionId)->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->count(),
            'ingresosMes' => UrbanizacionContext::cashMovements(CashMovement::query(), $urbanizacionId)
                ->where('tipo', 'ingreso')
                ->where('estado', 'confirmado')
                ->whereBetween('fecha', [now()->startOfMonth(), now()])
                ->sum('monto'),
            'montoDevuelto' => Devolucion::whereHas('venta.lote.manzano', fn (Builder $query) => $query->where('urbanizacion_id', $urbanizacionId))->sum('monto_devuelto'),
            'montoRetenido' => Devolucion::whereHas('venta.lote.manzano', fn (Builder $query) => $query->where('urbanizacion_id', $urbanizacionId))->sum('monto_retenido_empresa'),
        ]);
    }

    public function lotesEstado(Request $request): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();
        $query = UrbanizacionContext::lotes(Lote::with('manzano'), $urbanizacionId);

        if ($request->filled('manzano_id')) {
            $query->where('manzano_id', $request->integer('manzano_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        $lotes = $query->orderBy('manzano_id')->orderBy('codigo')->get();
        $conteos = UrbanizacionContext::lotes(Lote::query(), $urbanizacionId)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('reportes.lotes-estado', [
            'lotes' => $lotes,
            'conteos' => $conteos,
            'manzanos' => Manzano::where('urbanizacion_id', $urbanizacionId)->orderBy('codigo')->get(),
            'estados' => Lote::ESTADOS,
        ]);
    }

    public function reservas(Request $request, ReservationVisibilityService $visibility): View
    {
        $reservas = $this->reservasReportQuery($request, $visibility)
            ->orderByDesc('fecha_reserva')
            ->get();

        return view('reportes.reservas', [
            'reservas' => $reservas,
            'vendedores' => $visibility->vendedores($request->user()),
            'grupos' => $this->gruposDisponibles($request),
            'supervisores' => User::role('supervisor')->orderBy('name')->get(),
            'tiposOperacion' => Reserva::TIPOS_OPERACION,
            'metricas' => $this->reservasMetricas($reservas),
        ]);
    }

    public function reservasExcel(Request $request, ReservationVisibilityService $visibility, AuditService $auditService): Response
    {
        $reservas = $this->reservasReportQuery($request, $visibility)->orderByDesc('fecha_reserva')->get();
        $auditService->log(null, 'exportar_reporte_reservas', 'Exportacion Excel del reporte de reservas.', null, $request->query(), $request);

        return response()
            ->view('reportes.exports.reservas-excel', ['reservas' => $reservas, 'metricas' => $this->reservasMetricas($reservas)])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="reporte-reservas-impacto.xls"');
    }

    public function reservasPdf(Request $request, ReservationVisibilityService $visibility, AuditService $auditService, SystemSettingsService $settings): Response
    {
        $reservas = $this->reservasReportQuery($request, $visibility)->orderByDesc('fecha_reserva')->get();
        $auditService->log(null, 'exportar_reporte_reservas', 'Exportacion PDF del reporte de reservas.', null, $request->query(), $request);

        return Pdf::loadView('pdf.reporte-reservas', [
            'reservas' => $reservas,
            'metricas' => $this->reservasMetricas($reservas),
            'urbanizacion' => UrbanizacionContext::current(),
            'settings' => $settings->all(),
        ])->download('reporte-reservas-impacto.pdf');
    }

    public function cuotas(Request $request): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();
        $query = UrbanizacionContext::cuotas(Cuota::with('venta.cliente', 'venta.lote.manzano'), $urbanizacionId);

        $this->applyDateRange($query, $request, 'fecha_vencimiento');

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->filled('cliente_id')) {
            $query->whereHas('venta', fn (Builder $builder) => $builder->where('cliente_id', $request->integer('cliente_id')));
        }

        $cuotas = $query->orderBy('fecha_vencimiento')->get();
        $base = UrbanizacionContext::cuotas(Cuota::query(), $urbanizacionId);

        return view('reportes.cuotas', [
            'cuotas' => $cuotas,
            'clientes' => $this->clientesDeUrbanizacion($urbanizacionId),
            'pendientes' => (clone $base)->where('estado', 'pendiente')->count(),
            'vencidas' => (clone $base)->where('estado', 'vencida')->count(),
            'parciales' => (clone $base)->where('estado', 'parcial')->count(),
            'pagadas' => (clone $base)->where('estado', 'pagada')->count(),
            'saldoPendiente' => (clone $base)->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->sum('saldo_pendiente'),
        ]);
    }

    public function ingresos(Request $request): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();
        $query = UrbanizacionContext::cashMovements(CashMovement::with('cliente', 'user'), $urbanizacionId)->where('tipo', 'ingreso');

        $this->applyDateRange($query, $request, 'fecha');

        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->query('metodo_pago'));
        }

        if ($request->filled('concepto')) {
            $query->where('concepto', $request->query('concepto'));
        }

        $movimientos = $query->orderByDesc('fecha')->get();
        $desde = $request->filled('desde') ? Carbon::parse($request->query('desde')) : now()->startOfMonth();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->query('hasta')) : now();
        $base = UrbanizacionContext::cashMovements(CashMovement::query(), $urbanizacionId)->where('tipo', 'ingreso');

        return view('reportes.ingresos', [
            'movimientos' => $movimientos,
            'metodos' => CashMovement::METODOS,
            'conceptos' => CashMovement::CONCEPTOS,
            'ingresosDia' => (clone $base)->where('estado', 'confirmado')->whereDate('fecha', now()->toDateString())->sum('monto'),
            'ingresosRango' => (clone $base)->where('estado', 'confirmado')->whereBetween('fecha', [$desde, $hasta])->sum('monto'),
            'ingresosAnulados' => (clone $base)->where('estado', 'anulado')->whereBetween('fecha', [$desde, $hasta])->sum('monto'),
            'totalNeto' => (clone $base)->where('estado', 'confirmado')->whereBetween('fecha', [$desde, $hasta])->sum('monto'),
        ]);
    }

    public function estadoCuenta(Request $request): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();
        $clientes = $this->clientesDeUrbanizacion($urbanizacionId);
        $cliente = null;

        if ($request->filled('cliente_id')) {
            $cliente = Cliente::whereKey($request->integer('cliente_id'))
                ->whereIn('id', $clientes->pluck('id'))
                ->with(['ventas' => fn ($query) => UrbanizacionContext::ventas($query, $urbanizacionId)->with('lote.manzano', 'cuotas')])
                ->first();
        }

        return view('reportes.estado-cuenta', [
            'clientes' => $clientes,
            'cliente' => $cliente,
        ]);
    }

    public function mejorVendedor(Request $request, ReservationVisibilityService $visibility): View
    {
        return view('reportes.mejor-vendedor', [
            'ranking' => $this->mejorVendedorRanking($request, $visibility),
            'vendedores' => $visibility->vendedores($request->user()),
            'supervisores' => User::role('supervisor')->orderBy('name')->get(),
            'grupos' => $this->gruposDisponibles($request),
            'mes' => $request->integer('mes') ?: now()->month,
            'anio' => $request->integer('anio') ?: now()->year,
        ]);
    }

    public function mejorVendedorExcel(Request $request, ReservationVisibilityService $visibility, AuditService $auditService): Response
    {
        $ranking = $this->mejorVendedorRanking($request, $visibility);
        $auditService->log(null, 'exportar_reporte_mejor_vendedor', 'Exportacion Excel del reporte mejor vendedor.', null, $request->query(), $request);

        return response()
            ->view('reportes.exports.mejor-vendedor-excel', ['ranking' => $ranking])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="reporte-mejor-vendedor-impacto.xls"');
    }

    public function mejorVendedorPdf(Request $request, ReservationVisibilityService $visibility, AuditService $auditService, SystemSettingsService $settings): Response
    {
        $ranking = $this->mejorVendedorRanking($request, $visibility);
        $auditService->log(null, 'exportar_reporte_mejor_vendedor', 'Exportacion PDF del reporte mejor vendedor.', null, $request->query(), $request);

        return Pdf::loadView('pdf.reporte-mejor-vendedor', [
            'ranking' => $ranking,
            'urbanizacion' => UrbanizacionContext::current(),
            'mes' => $request->integer('mes') ?: now()->month,
            'anio' => $request->integer('anio') ?: now()->year,
            'settings' => $settings->all(),
        ])->download('reporte-mejor-vendedor-impacto.pdf');
    }

    public function exportaciones(): View
    {
        return view('reportes.exportaciones');
    }

    public function csv(Request $request, string $reporte, ReservationVisibilityService $visibility, AuditService $auditService): Response
    {
        $csv = match ($reporte) {
            'lotes-estado' => $this->lotesCsv($request),
            'reservas' => $this->reservasCsv($request, $visibility),
            'cuotas' => $this->cuotasCsv($request),
            'ingresos' => $this->ingresosCsv($request),
            default => abort(404),
        };

        if ($reporte === 'reservas') {
            $auditService->log(null, 'exportar_reporte_reservas', 'Exportacion CSV del reporte de reservas.', null, $request->query(), $request);
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$reporte.'-impacto.csv"',
        ]);
    }

    private function reservasReportQuery(Request $request, ReservationVisibilityService $visibility): Builder
    {
        $query = UrbanizacionContext::reservas(Reserva::with('cliente', 'lote.manzano', 'usuario'), UrbanizacionContext::currentId());
        $visibility->apply($query, $request->user());
        $this->applyReservaFilters($query, $request, $visibility);

        return $query;
    }

    private function applyReservaFilters(Builder $query, Request $request, ReservationVisibilityService $visibility): void
    {
        $this->applyDateRange($query, $request, 'fecha_reserva');

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->filled('tipo_operacion')) {
            $query->where('tipo_operacion', $request->query('tipo_operacion'));
        }

        if ($request->filled('usuario_id') && $visibility->canFilterUser($request->user(), $request->integer('usuario_id'))) {
            $query->where('usuario_id', $request->integer('usuario_id'));
        }

        if ($request->filled('supervisor_id')) {
            $query->where(function (Builder $builder) use ($request): void {
                $builder->where('usuario_id', $request->integer('supervisor_id'))
                    ->orWhereHas('usuario.asesor', fn (Builder $nested) => $nested->where('supervisor_id', $request->integer('supervisor_id')));
            });
        }

        if ($request->filled('grupo_comercial_id')) {
            $query->whereHas('usuario.asesor', fn (Builder $builder) => $builder->where('grupo_comercial_id', $request->integer('grupo_comercial_id')));
        }

        if ($request->filled('cliente')) {
            $query->whereHas('cliente', fn (Builder $builder) => $builder->where('nombre', 'like', '%'.$request->query('cliente').'%'));
        }

        if ($request->filled('documento')) {
            $query->whereHas('cliente', fn (Builder $builder) => $builder->where('documento', 'like', '%'.$request->query('documento').'%'));
        }

        if ($request->filled('lote')) {
            $query->whereHas('lote', fn (Builder $builder) => $builder->where('codigo', 'like', '%'.$request->query('lote').'%'));
        }

        if ($request->filled('manzano')) {
            $query->whereHas('lote.manzano', fn (Builder $builder) => $builder->where('codigo', 'like', '%'.$request->query('manzano').'%'));
        }
    }

    private function reservasMetricas(Collection $reservas): array
    {
        return [
            'total' => $reservas->count(),
            'activas' => $reservas->where('estado', 'activa')->count(),
            'vencidas' => $reservas->where('estado', 'vencida')->count(),
            'canceladas' => $reservas->where('estado', 'cancelada')->count(),
            'convertidas' => $reservas->where('estado', 'convertida')->count(),
            'porTipo' => collect(Reserva::TIPOS_OPERACION)->mapWithKeys(fn (string $tipo) => [$tipo => $reservas->where('tipo_operacion', $tipo)->count()]),
        ];
    }

    private function mejorVendedorRanking(Request $request, ReservationVisibilityService $visibility): Collection
    {
        $mes = $request->integer('mes') ?: now()->month;
        $anio = $request->integer('anio') ?: now()->year;
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth()->endOfDay();

        $vendedores = $visibility->vendedores($request->user());

        if ($request->filled('usuario_id') && $visibility->canFilterUser($request->user(), $request->integer('usuario_id'))) {
            $vendedores = $vendedores->where('id', $request->integer('usuario_id'))->values();
        }

        if ($request->filled('supervisor_id')) {
            $asesorUserIds = Asesor::where('supervisor_id', $request->integer('supervisor_id'))->pluck('user_id');
            $vendedores = $vendedores->whereIn('id', $asesorUserIds)->values();
        }

        if ($request->filled('grupo_comercial_id')) {
            $asesorUserIds = Asesor::where('grupo_comercial_id', $request->integer('grupo_comercial_id'))->pluck('user_id');
            $vendedores = $vendedores->whereIn('id', $asesorUserIds)->values();
        }

        $ranking = $vendedores->map(function (User $vendedor) use ($desde, $hasta) {
            $reservas = UrbanizacionContext::reservas(Reserva::query(), UrbanizacionContext::currentId())
                ->where('usuario_id', $vendedor->id)
                ->whereBetween('fecha_reserva', [$desde, $hasta])
                ->get();

            $ventas = UrbanizacionContext::ventas(Venta::query(), UrbanizacionContext::currentId())
                ->where('user_id', $vendedor->id)
                ->whereBetween('fecha_venta', [$desde, $hasta])
                ->whereIn('estado', ['activa', 'completada'])
                ->get();

            $asesor = Asesor::with('supervisor')->where('user_id', $vendedor->id)->first();
            $totalReservas = $reservas->count();
            $ventasCerradas = $ventas->count();

            return [
                'asesor' => $vendedor->name,
                'supervisor' => $asesor?->supervisor?->name ?? '-',
                'reservas' => $totalReservas,
                'activas' => $reservas->where('estado', 'activa')->count(),
                'canceladas' => $reservas->where('estado', 'cancelada')->count(),
                'vencidas' => $reservas->where('estado', 'vencida')->count(),
                'convertidas' => $reservas->where('estado', 'convertida')->count(),
                'ventas_cerradas' => $ventasCerradas,
                'monto_vendido' => (float) $ventas->sum('precio_final'),
                'conversion' => $totalReservas > 0 ? round(($ventasCerradas / $totalReservas) * 100, 2) : 0,
            ];
        })->sort(function (array $a, array $b) {
            return [$b['monto_vendido'], $b['ventas_cerradas'], $b['reservas']]
                <=> [$a['monto_vendido'], $a['ventas_cerradas'], $a['reservas']];
        })->values();

        return $ranking->map(function (array $row, int $index) {
            return ['ranking' => $index + 1, ...$row];
        });
    }

    private function applyDateRange(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('desde')) {
            $query->whereDate($column, '>=', Carbon::parse($request->query('desde'))->toDateString());
        }

        if ($request->filled('hasta')) {
            $query->whereDate($column, '<=', Carbon::parse($request->query('hasta'))->toDateString());
        }
    }

    private function gruposDisponibles(Request $request): Collection
    {
        $query = GrupoComercial::where('activo', true)->orderBy('nombre');

        if ($request->user()->hasRole('supervisor')) {
            $query->where('supervisor_id', $request->user()->id);
        }

        return $query->get();
    }

    private function clientesDeUrbanizacion(?int $urbanizacionId)
    {
        return Cliente::where('urbanizacion_id', $urbanizacionId)
            ->orWhereHas('ventas.lote.manzano', fn (Builder $builder) => $builder->where('urbanizacion_id', $urbanizacionId))
            ->orderBy('nombre')
            ->get();
    }

    private function lotesCsv(Request $request): string
    {
        $query = UrbanizacionContext::lotes(Lote::with('manzano'), UrbanizacionContext::currentId());
        if ($request->filled('manzano_id')) {
            $query->where('manzano_id', $request->integer('manzano_id'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        return $this->csvFromRows(['manzano', 'lote', 'superficie', 'precio', 'cuota_inicial_tipo', 'cuota_inicial_valor', 'cuota_inicial', 'estado'], $query->get(), fn (Lote $lote) => [
            $lote->manzano->codigo,
            $lote->codigo,
            $lote->superficie,
            $lote->precio,
            $lote->cuota_inicial_tipo,
            $lote->cuota_inicial_valor,
            $lote->cuotaInicialTexto(),
            $lote->estado,
        ]);
    }

    private function reservasCsv(Request $request, ReservationVisibilityService $visibility): string
    {
        $reservas = $this->reservasReportQuery($request, $visibility)->orderByDesc('fecha_reserva')->get();

        return $this->csvFromRows(['fecha', 'cliente', 'documento', 'manzano', 'lote', 'tipo_operacion', 'estado', 'asesor', 'fecha_vencimiento'], $reservas, fn (Reserva $reserva) => [
            $reserva->fecha_reserva?->format('Y-m-d'),
            $reserva->cliente->nombre,
            $reserva->cliente->documento,
            $reserva->lote->manzano->codigo,
            $reserva->lote->codigo,
            $reserva->tipo_operacion,
            $reserva->estado,
            $reserva->usuario?->name,
            $reserva->fecha_vencimiento?->format('Y-m-d'),
        ]);
    }

    private function cuotasCsv(Request $request): string
    {
        $query = UrbanizacionContext::cuotas(Cuota::with('venta.cliente', 'venta.lote.manzano'), UrbanizacionContext::currentId());
        $this->applyDateRange($query, $request, 'fecha_vencimiento');
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }
        if ($request->filled('cliente_id')) {
            $query->whereHas('venta', fn (Builder $builder) => $builder->where('cliente_id', $request->integer('cliente_id')));
        }

        return $this->csvFromRows(['cliente', 'venta', 'lote', 'numero', 'vence', 'monto', 'pagado', 'saldo', 'estado'], $query->get(), fn (Cuota $cuota) => [
            $cuota->venta->cliente->nombre,
            $cuota->venta_id,
            $cuota->venta->lote->manzano->codigo.'-'.$cuota->venta->lote->codigo,
            $cuota->numero,
            $cuota->fecha_vencimiento?->format('Y-m-d'),
            $cuota->monto,
            $cuota->monto_pagado,
            $cuota->saldo_pendiente,
            $cuota->estado,
        ]);
    }

    private function ingresosCsv(Request $request): string
    {
        $query = UrbanizacionContext::cashMovements(CashMovement::with('cliente', 'user'), UrbanizacionContext::currentId())->where('tipo', 'ingreso');
        $this->applyDateRange($query, $request, 'fecha');
        if ($request->filled('metodo_pago')) {
            $query->where('metodo_pago', $request->query('metodo_pago'));
        }
        if ($request->filled('concepto')) {
            $query->where('concepto', $request->query('concepto'));
        }

        return $this->csvFromRows(['fecha', 'cliente', 'concepto', 'metodo_pago', 'monto', 'usuario', 'estado'], $query->get(), fn (CashMovement $movement) => [
            $movement->fecha?->format('Y-m-d'),
            $movement->cliente?->nombre,
            $movement->concepto,
            $movement->metodo_pago,
            $movement->monto,
            $movement->user?->name,
            $movement->estado,
        ]);
    }

    private function csvFromRows(array $headers, iterable $rows, callable $mapper): string
    {
        $csv = implode(',', $headers)."\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $mapper($row)))."\n";
        }

        return $csv;
    }
}
