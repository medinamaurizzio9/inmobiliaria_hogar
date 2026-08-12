<?php

namespace App\Support;

use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\Lote;
use App\Models\Reserva;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UrbanizacionContext
{
    public static function currentId(): ?int
    {
        return session('urbanizacion_id') ? (int) session('urbanizacion_id') : null;
    }

    public static function current(): ?Urbanizacion
    {
        $id = self::currentId();

        return $id ? Urbanizacion::find($id) : null;
    }

    public static function accessibleUrbanizaciones(User $user): Collection
    {
        $query = Urbanizacion::query()->where('estado', 'activa')->orderBy('nombre');

        if ($user->hasAnyRole(['vendedor', 'supervisor'])) {
            $query->whereHas('asesores', fn (Builder $builder) => $builder
                ->where('users.id', $user->id)
                ->where('urbanizacion_user.activo', true));
        }

        return $query->get();
    }

    public static function filtroUrbanizacion(?User $user, mixed $valor): ?int
    {
        if ($user === null || ! is_numeric($valor) || (int) $valor <= 0) {
            return null;
        }

        $id = (int) $valor;

        return self::accessibleUrbanizaciones($user)->pluck('id')->contains($id) ? $id : null;
    }

    public static function userCanAccess(User $user, int $urbanizacionId): bool
    {
        if ($user->hasAnyRole(['super administrador', 'administrador', 'gerente', 'cajero'])) {
            return Urbanizacion::whereKey($urbanizacionId)->where('estado', 'activa')->exists();
        }

        if ($user->hasAnyRole(['vendedor', 'supervisor'])) {
            return $user->urbanizacionesAsignadas()
                ->where('urbanizaciones.id', $urbanizacionId)
                ->where('urbanizaciones.estado', 'activa')
                ->exists();
        }

        return false;
    }

    public static function lotes(Builder $query, ?int $urbanizacionId = null): Builder
    {
        $id = $urbanizacionId ?? self::currentId();

        return $query->whereHas('manzano', fn (Builder $builder) => $builder->where('urbanizacion_id', $id));
    }

    public static function ventas(Builder $query, ?int $urbanizacionId = null): Builder
    {
        $id = $urbanizacionId ?? self::currentId();

        return $query->whereHas('lote.manzano', fn (Builder $builder) => $builder->where('urbanizacion_id', $id));
    }

    public static function reservas(Builder $query, ?int $urbanizacionId = null): Builder
    {
        $id = $urbanizacionId ?? self::currentId();

        return $query->whereHas('lote.manzano', fn (Builder $builder) => $builder->where('urbanizacion_id', $id));
    }

    public static function cuotas(Builder $query, ?int $urbanizacionId = null): Builder
    {
        $id = $urbanizacionId ?? self::currentId();

        return $query->whereHas('venta.lote.manzano', fn (Builder $builder) => $builder->where('urbanizacion_id', $id));
    }

    public static function cashMovements(Builder $query, ?int $urbanizacionId = null): Builder
    {
        $id = $urbanizacionId ?? self::currentId();

        return $query->where(function (Builder $builder) use ($id): void {
            $builder->whereHas('venta.lote.manzano', fn (Builder $nested) => $nested->where('urbanizacion_id', $id))
                ->orWhereHas('reserva.lote.manzano', fn (Builder $nested) => $nested->where('urbanizacion_id', $id))
                ->orWhereHas('cuota.venta.lote.manzano', fn (Builder $nested) => $nested->where('urbanizacion_id', $id));
        });
    }

    public static function clientes(Builder $query, ?int $urbanizacionId = null): Builder
    {
        $id = $urbanizacionId ?? self::currentId();

        return $query->where('urbanizacion_id', $id);
    }

    public static function loteBelongsToCurrent(Lote $lote): bool
    {
        return (int) $lote->manzano()->value('urbanizacion_id') === self::currentId();
    }

    public static function clienteBelongsToCurrent(Cliente $cliente): bool
    {
        return (int) $cliente->urbanizacion_id === self::currentId();
    }

    public static function ventaBelongsToCurrent(Venta $venta): bool
    {
        return (int) $venta->lote->manzano->urbanizacion_id === self::currentId();
    }

    public static function reservaBelongsToCurrent(Reserva $reserva): bool
    {
        return (int) $reserva->lote->manzano->urbanizacion_id === self::currentId();
    }

    public static function cuotaBelongsToCurrent(Cuota $cuota): bool
    {
        return (int) $cuota->venta->lote->manzano->urbanizacion_id === self::currentId();
    }

    public static function cashMovementBelongsToCurrent(CashMovement $cashMovement): bool
    {
        $cashMovement->loadMissing('venta.lote.manzano', 'reserva.lote.manzano', 'cuota.venta.lote.manzano');

        return (int) ($cashMovement->venta?->lote?->manzano?->urbanizacion_id
            ?? $cashMovement->reserva?->lote?->manzano?->urbanizacion_id
            ?? $cashMovement->cuota?->venta?->lote?->manzano?->urbanizacion_id) === self::currentId();
    }
}
