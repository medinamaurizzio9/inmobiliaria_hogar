<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = ['venta_id', 'cliente_id', 'monto_pagado', 'monto_devuelto', 'monto_retenido_empresa', 'motivo', 'observaciones', 'responsable_id', 'fecha', 'estado'];

    protected function casts(): array
    {
        return ['monto_pagado' => 'decimal:2', 'monto_devuelto' => 'decimal:2', 'monto_retenido_empresa' => 'decimal:2', 'fecha' => 'date'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
