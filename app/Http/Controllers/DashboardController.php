<?php

namespace App\Http\Controllers;

use App\Models\Asesor;
use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Services\ReservationVisibilityService;
use App\Services\SystemSettingsService;
use App\Support\UrbanizacionContext;
use App\Support\WhatsAppLink;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReservationVisibilityService $visibility, SystemSettingsService $settings): View
    {
        $urbanizacionId = UrbanizacionContext::currentId();
        $user = request()->user();

        if ($user->hasRole('supervisor') && ! $user->hasAnyRole(['super administrador', 'administrador', 'gerente', 'cajero'])) {
            return $this->supervisorDashboard($user);
        }

        $visibleIds = $visibility->visibleUserIds($user);
        $lotesQuery = fn () => UrbanizacionContext::lotes(Lote::query(), $urbanizacionId);
        $cashQuery = fn () => UrbanizacionContext::cashMovements(CashMovement::query(), $urbanizacionId);
        $ventasQuery = fn () => UrbanizacionContext::ventas(Venta::query(), $urbanizacionId)
            ->when($user->hasRole('supervisor') && $visibleIds !== null, fn ($query) => $query->whereIn('user_id', $visibleIds));
        $cuotasQuery = fn () => UrbanizacionContext::cuotas(Cuota::query(), $urbanizacionId);
        $reservasQuery = fn () => $visibility->apply(UrbanizacionContext::reservas(Reserva::query(), $urbanizacionId), $user);
        $pendingPayments = $user->can('cobrar cuotas')
            ? $cashQuery()->where('estado', 'pendiente_verificacion')->count()
            : 0;

        $lotesPorEstado = $lotesQuery()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $totalLotes = (int) $lotesPorEstado->sum();
        $ventasResumen = $ventasQuery()->whereIn('estado', ['activa', 'completada'])
            ->selectRaw('count(*) as total, coalesce(sum(precio_final), 0) as monto')
            ->first();
        $reservasPorEstado = $reservasQuery()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $ingresosPorMes = $cashQuery()->where('tipo', 'ingreso')
            ->where('estado', 'confirmado')
            ->whereDate('fecha', '>=', now()->subMonths(5)->startOfMonth())
            ->get()
            ->groupBy(fn (CashMovement $movement) => $movement->fecha->format('M Y'))
            ->map(fn (Collection $items) => $items->sum('monto'));

        $canContactDebtors = $user->can('ver clientes') && $user->can('cobrar cuotas');
        $overdueInstallments = $cuotasQuery()->with('venta.cliente', 'venta.lote.manzano')
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->whereDate('fecha_programada', '<', now())
            ->orderBy('fecha_programada')
            ->take(6)
            ->get();

        if ($canContactDebtors) {
            $companyName = $settings->get('company_name') ?: $settings->get('system_name') ?: 'la inmobiliaria';
            $overdueInstallments->each(function (Cuota $cuota) use ($companyName): void {
                $cliente = $cuota->venta->cliente;
                $lote = $cuota->venta->lote;
                $message = "Hola {$cliente->nombre}, le contactamos de {$companyName} para informarle que registra una cuota pendiente correspondiente al lote {$lote->manzano->codigo}-{$lote->codigo}. Puede comunicarse con nosotros para coordinar su pago. Gracias.";
                $cuota->setAttribute('whatsapp_url', WhatsAppLink::urlWithMessage($cliente->telefono, $message));
            });
        }

        return view('dashboard', [
            'totalLotes' => $totalLotes,
            'lotesDisponibles' => (int) ($lotesPorEstado['disponible'] ?? 0),
            'lotesVendidos' => (int) ($lotesPorEstado['vendido'] ?? 0),
            'lotesReservados' => (int) ($lotesPorEstado['reservado'] ?? 0),
            'lotesBloqueados' => (int) ($lotesPorEstado['bloqueado'] ?? 0),
            'ingresosDia' => $cashQuery()->where('tipo', 'ingreso')->where('estado', 'confirmado')->whereDate('fecha', today())->sum('monto'),
            'ingresosMes' => $cashQuery()->where('tipo', 'ingreso')->where('estado', 'confirmado')->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])->sum('monto'),
            'clientes' => Cliente::count(),
            'montoVendido' => (float) ($ventasResumen?->monto ?? 0),
            'ventas' => $ventasQuery()->with('cliente', 'lote.manzano')->latest()->take(6)->get(),
            'cuotasVencidas' => $cuotasQuery()->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->whereDate('fecha_programada', '<', now())->count(),
            'reservasVencidas' => $reservasQuery()->where('estado', 'activa')->whereDate('fecha_vencimiento', '<', now())->count(),
            'pendingPayments' => $pendingPayments,
            'operationsCenter' => $user->hasAnyRole(['super administrador', 'administrador', 'gerente', 'cajero']),
            'advisorDashboard' => $user->hasRole('vendedor') && ! $user->hasAnyRole(['super administrador', 'administrador', 'gerente', 'cajero']),
            'lotesPorEstado' => $lotesPorEstado,
            'ingresosPorMes' => $ingresosPorMes,
            'cuotasVencidasLista' => $overdueInstallments,
            'canContactDebtors' => $canContactDebtors,
            'reservasPorVencer' => $reservasQuery()->with('cliente', 'lote.manzano')
                ->where('estado', 'activa')
                ->whereBetween('fecha_vencimiento', [now()->startOfDay(), now()->addDays(10)->endOfDay()])
                ->orderBy('fecha_vencimiento')
                ->take(6)
                ->get(),
            'supervisorDashboard' => false,
            'reservasActivasEquipo' => (int) ($reservasPorEstado['activa'] ?? 0),
            'reservasCanceladasEquipo' => (int) ($reservasPorEstado['cancelada'] ?? 0),
            'reservasConvertidasEquipo' => (int) ($reservasPorEstado['convertida'] ?? 0),
            'ventasCerradasEquipo' => (int) ($ventasResumen?->total ?? 0),
            'montoVendidoEquipo' => (float) ($ventasResumen?->monto ?? 0),
            'rankingAsesoresEquipo' => collect(),
        ]);
    }

    private function supervisorDashboard(User $user): View
    {
        $urbanizaciones = UrbanizacionContext::accessibleUrbanizaciones($user);
        $urbanizacionIds = $urbanizaciones->pluck('id');
        $asesores = Asesor::query()
            ->where('supervisor_id', $user->id)
            ->with(['user.urbanizacionesAsignadas:id,nombre', 'grupo:id,nombre'])
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();
        $asesorIds = $asesores->pluck('user_id')->filter()->values();
        $teamIds = $asesorIds->push($user->id)->unique()->values();

        $ventasBase = Venta::query()
            ->whereIn('user_id', $teamIds)
            ->whereHas('lote.manzano', fn ($query) => $query->whereIn('urbanizacion_id', $urbanizacionIds));
        $reservasBase = Reserva::query()
            ->whereIn('usuario_id', $teamIds)
            ->whereHas('lote.manzano', fn ($query) => $query->whereIn('urbanizacion_id', $urbanizacionIds));

        $ventasPorAsesor = (clone $ventasBase)
            ->whereIn('estado', ['activa', 'completada'])
            ->whereBetween('fecha_venta', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('user_id, count(*) as total, coalesce(sum(precio_final), 0) as monto')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
        $reservasPorAsesor = (clone $reservasBase)
            ->where('estado', 'activa')
            ->selectRaw('usuario_id, count(*) as total')
            ->groupBy('usuario_id')
            ->pluck('total', 'usuario_id');

        $equipo = $asesores->map(fn (Asesor $asesor) => [
            'nombre' => trim($asesor->nombre.' '.$asesor->apellido),
            'grupo' => $asesor->grupo?->nombre ?? $asesor->grupo_comercial ?? 'Sin grupo',
            'urbanizaciones' => $asesor->user?->urbanizacionesAsignadas->pluck('nombre')->join(', '),
            'ventas' => (int) ($ventasPorAsesor->get($asesor->user_id)?->total ?? 0),
            'monto' => (float) ($ventasPorAsesor->get($asesor->user_id)?->monto ?? 0),
            'reservas' => (int) ($reservasPorAsesor[$asesor->user_id] ?? 0),
            'activo' => $asesor->activo,
        ]);

        $urbanizaciones->loadCount([
            'lotes as disponibles_count' => fn ($query) => $query->where('estado', 'disponible'),
            'lotes as reservados_count' => fn ($query) => $query->where('estado', 'reservado'),
            'lotes as vendidos_count' => fn ($query) => $query->where('estado', 'vendido'),
        ]);
        $ventasMesResumen = (clone $ventasBase)
            ->whereIn('estado', ['activa', 'completada'])
            ->whereBetween('fecha_venta', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('count(*) as total, coalesce(sum(precio_final), 0) as monto')
            ->first();

        return view('dashboard-supervisor', [
            'asesoresActivos' => $asesores->where('activo', true)->count(),
            'ventasMes' => (int) ($ventasMesResumen?->total ?? 0),
            'montoVendidoMes' => (float) ($ventasMesResumen?->monto ?? 0),
            'reservasActivas' => (clone $reservasBase)->where('estado', 'activa')->count(),
            'clientesAtendidos' => Cliente::query()->whereIn('created_by', $teamIds)->whereIn('urbanizacion_id', $urbanizacionIds)->count(),
            'reservasPorVencer' => (clone $reservasBase)->with('cliente', 'lote.manzano')->where('estado', 'activa')->whereBetween('fecha_vencimiento', [today(), today()->addDays(10)])->orderBy('fecha_vencimiento')->limit(6)->get(),
            'reservasVencidas' => (clone $reservasBase)->where('estado', 'activa')->whereDate('fecha_vencimiento', '<', today())->count(),
            'equipo' => $equipo,
            'urbanizacionesEquipo' => $urbanizaciones,
        ]);
    }
}
