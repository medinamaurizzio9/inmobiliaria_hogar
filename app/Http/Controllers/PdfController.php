<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\Venta;
use App\Services\ReceiptQrService;
use App\Services\ReservationVisibilityService;
use App\Services\SystemSettingsService;
use App\Support\UrbanizacionContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function reservationReceipt(Request $request, Reserva $reserva, SystemSettingsService $settings, ReservationVisibilityService $visibility, ReceiptQrService $qrService)
    {
        abort_unless($request->user()->can('ver recibo reserva'), 403, 'No tienes permiso para ver este recibo.');
        abort_unless(UrbanizacionContext::reservaBelongsToCurrent($reserva), 403, 'No tienes acceso a esta urbanizacion');

        $visibleUserIds = $visibility->visibleUserIds($request->user());
        abort_unless($visibleUserIds === null || in_array((int) $reserva->usuario_id, $visibleUserIds, true), 403, 'No tienes acceso a este recibo.');

        $reserva->load([
            'cliente',
            'usuario',
            'lote.manzano.urbanizacion',
            'cashMovements.user',
        ]);
        $movimiento = $reserva->cashMovements
            ->first(fn (CashMovement $movement) => $movement->concepto === 'reserva' && $movement->estado !== 'anulado');

        abort_unless($movimiento, 404, 'Esta reserva no tiene un recibo asociado.');

        return Pdf::loadView('pdf.recibo', [
            'movimiento' => $movimiento,
            'lote' => $reserva->lote,
            'reserva' => $reserva,
            'qrDataUri' => $qrService->dataUri($movimiento, $reserva->lote),
            'numeroRecibo' => $qrService->number($movimiento),
            'settings' => $settings->all(),
        ])
            ->setPaper('a4')
            ->stream('recibo-reserva-'.$reserva->id.'.pdf');
    }

    public function clientProfile(Request $request, Cliente $cliente, SystemSettingsService $settings)
    {
        $this->authorizeClientPdf($request, $cliente);
        $cliente->load([
            'urbanizacion',
            'createdBy',
            'reservas.lote.manzano',
            'reservas.usuario',
            'ventas.lote.manzano',
            'ventas.cuotas',
            'cashMovements.user',
        ]);

        return Pdf::loadView('pdf.cliente-ficha', [
            'cliente' => $cliente,
            'settings' => $settings->all(),
            'resumen' => $this->clientSummary($cliente),
        ])->stream('ficha-cliente-'.$cliente->id.'.pdf');
    }

    public function clientAccountStatement(Request $request, Cliente $cliente, SystemSettingsService $settings)
    {
        $this->authorizeClientPdf($request, $cliente);
        $cliente->load([
            'urbanizacion',
            'ventas.lote.manzano',
            'ventas.cuotas.cashMovements',
            'cashMovements.user',
        ]);

        return Pdf::loadView('pdf.cliente-estado-cuenta', [
            'cliente' => $cliente,
            'settings' => $settings->all(),
            'resumen' => $this->clientSummary($cliente),
        ])->stream('estado-cuenta-cliente-'.$cliente->id.'.pdf');
    }

    public function clientReservations(Request $request, Cliente $cliente, SystemSettingsService $settings)
    {
        $this->authorizeClientPdf($request, $cliente);
        $cliente->load([
            'urbanizacion',
            'reservas.lote.manzano',
            'reservas.usuario',
        ]);

        return Pdf::loadView('pdf.cliente-reservas', [
            'cliente' => $cliente,
            'settings' => $settings->all(),
        ])->stream('reservas-cliente-'.$cliente->id.'.pdf');
    }

    public function receipt(Request $request, CashMovement $cashMovement, SystemSettingsService $settings, ReceiptQrService $qrService)
    {
        abort_if($request->user()->hasAnyRole(['vendedor', 'supervisor']), 403, 'No tienes permiso para ver recibos de Caja.');
        $this->authorizeClientOrPermission($request, $cashMovement->cliente_id, 'cobrar cuotas');
        if (! $request->user()->hasRole('cliente')) {
            abort_unless(UrbanizacionContext::cashMovementBelongsToCurrent($cashMovement), 403, 'No tienes acceso a esta urbanizacion');
        }
        abort_unless($cashMovement->estado === 'confirmado', 422, 'Solo pagos confirmados tienen recibo.');

        $cashMovement->load([
            'cliente',
            'user',
            'reserva.lote.manzano.urbanizacion',
            'venta.lote.manzano.urbanizacion',
            'cuota.venta.lote.manzano.urbanizacion',
            'pagoAplicaciones.cuota',
        ]);
        $lote = $cashMovement->reserva?->lote
            ?? $cashMovement->venta?->lote
            ?? $cashMovement->cuota?->venta?->lote;
        $ventaPago = $cashMovement->venta ?? $cashMovement->cuota?->venta;
        $saldoRestanteTerreno = $ventaPago
            ? max(0.0, (float) $ventaPago->cuotas()->sum('saldo_pendiente'))
            : 0.0;

        return Pdf::loadView('pdf.recibo', [
            'movimiento' => $cashMovement,
            'lote' => $lote,
            'qrDataUri' => $qrService->dataUri($cashMovement, $lote),
            'numeroRecibo' => $qrService->number($cashMovement),
            'saldoRestanteTerreno' => $saldoRestanteTerreno,
            'settings' => $settings->all(),
        ])
            ->setPaper('a4')
            ->stream('recibo-'.$cashMovement->id.'.pdf');
    }

    public function paymentPlan(Request $request, Venta $venta, SystemSettingsService $settings)
    {
        $this->authorizeClientOrPermission($request, $venta->cliente_id, 'ver ventas');
        if (! $request->user()->hasRole('cliente')) {
            abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403, 'No tienes acceso a esta urbanizacion');
        }

        return Pdf::loadView('pdf.plan-pagos', ['venta' => $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas'), 'settings' => $settings->all()])
            ->stream('plan-pagos-'.$venta->id.'.pdf');
    }

    public function contract(Request $request, Venta $venta, SystemSettingsService $settings)
    {
        $this->authorizeClientOrPermission($request, $venta->cliente_id, 'ver ventas');
        if (! $request->user()->hasRole('cliente')) {
            abort_unless(UrbanizacionContext::ventaBelongsToCurrent($venta), 403, 'No tienes acceso a esta urbanizacion');
        }

        return Pdf::loadView('pdf.contrato', ['venta' => $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas'), 'settings' => $settings->all()])
            ->stream('contrato-'.$venta->id.'.pdf');
    }

    public function saleAccountStatement(Request $request, Venta $venta, SystemSettingsService $settings)
    {
        $this->authorizeClientOrPermission($request, $venta->cliente_id, 'ver ventas');
        $venta->load('cliente', 'lote.manzano.urbanizacion', 'cuotas.pagoAplicaciones.cashMovement', 'cashMovements');

        return Pdf::loadView('pdf.venta-estado-cuenta', [
            'venta' => $venta,
            'settings' => $settings->all(),
            'totalPagado' => $venta->cashMovements->where('tipo', 'ingreso')->where('estado', 'confirmado')->sum('monto'),
            'saldo' => $venta->cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->sum('saldo_pendiente'),
        ])->stream('estado-cuenta-terreno-'.$venta->id.'.pdf');
    }

    private function authorizeClientOrPermission(Request $request, ?int $clienteId, string $permission): void
    {
        $user = $request->user();

        abort_unless($user->can($permission) || ($user->hasRole('cliente') && $user->cliente_id === $clienteId), 403);
    }

    private function authorizeClientPdf(Request $request, Cliente $cliente): void
    {
        $user = $request->user();

        if ($user->hasRole('cliente')) {
            abort_unless((int) $user->cliente_id === (int) $cliente->id, 403, 'No tienes acceso a este cliente.');

            return;
        }

        abort_unless(UrbanizacionContext::clienteBelongsToCurrent($cliente), 403, 'No tienes acceso a este cliente.');

        if ($user->hasRole('vendedor')) {
            $assigned = UrbanizacionContext::userCanAccess($user, (int) $cliente->urbanizacion_id);
            $related = (int) $cliente->created_by === (int) $user->id
                || $cliente->reservas()->where('usuario_id', $user->id)->exists()
                || $cliente->ventas()->where('user_id', $user->id)->exists();

            abort_unless($assigned && $related, 403, 'No tienes acceso a este cliente.');

            return;
        }

        abort_unless($user->hasAnyRole(['administrador', 'gerente']) || $user->can('ver clientes'), 403);
    }

    private function clientSummary(Cliente $cliente): array
    {
        $cuotas = $cliente->ventas->flatMap->cuotas;
        $pagos = $cliente->cashMovements
            ->where('tipo', 'ingreso')
            ->where('estado', '!=', 'anulado');

        return [
            'total_vendido' => (float) $cliente->ventas->where('estado', '!=', 'anulada')->sum('precio_final'),
            'total_pagado' => (float) $pagos->sum('monto'),
            'total_pendiente' => (float) $cuotas->sum('saldo_pendiente'),
            'cuotas_pagadas' => $cuotas->where('estado', 'pagada')->count(),
            'cuotas_pendientes' => $cuotas->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->count(),
        ];
    }
}
