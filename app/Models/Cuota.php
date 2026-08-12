<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    protected $fillable = [
        'venta_id',
        'numero',
        'fecha_programada',
        'fecha_vencimiento',
        'monto',
        'fecha_pago',
        'monto_pagado',
        'saldo_pendiente',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cashMovements()
    {
        return $this->hasMany(CashMovement::class, 'installment_id');
    }

    public function pagoAplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class);
    }
}
