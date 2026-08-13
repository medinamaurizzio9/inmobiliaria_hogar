<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cliente extends Model
{
    protected $fillable = ['urbanizacion_id', 'created_by', 'nombre', 'documento', 'telefono', 'email', 'direccion', 'foto'];

    public function urbanizacion(): BelongsTo
    {
        return $this->belongsTo(Urbanizacion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function saldoPendiente(): float
    {
        return (float) $this->ventas->flatMap->cuotas->sum('saldo_pendiente');
    }
}
