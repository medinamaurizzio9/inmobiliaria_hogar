<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $fillable = [
        'lote_id',
        'cliente_id',
        'user_id',
        'reserva_id',
        'tipo_operacion',
        'precio_base_usd',
        'incremento_credito_tipo',
        'incremento_credito_valor',
        'incremento_credito_aplicado',
        'fecha_venta',
        'precio_final',
        'precio_final_usd',
        'precio_final_bs',
        'tipo_cambio_usd_bs',
        'cuota_inicial',
        'saldo_financiar',
        'numero_cuotas',
        'fecha_primer_vencimiento',
        'descuento',
        'descuento_autorizado_por',
        'descuento_autorizado_en',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'fecha_primer_vencimiento' => 'date',
            'descuento_autorizado_en' => 'datetime',
            'descuento' => 'decimal:2',
            'precio_final' => 'decimal:2',
            'precio_base_usd' => 'decimal:2',
            'incremento_credito_valor' => 'decimal:2',
            'incremento_credito_aplicado' => 'decimal:2',
            'precio_final_usd' => 'decimal:2',
            'precio_final_bs' => 'decimal:2',
            'tipo_cambio_usd_bs' => 'decimal:2',
            'cuota_inicial' => 'decimal:2',
            'saldo_financiar' => 'decimal:2',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function descuentoAutorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'descuento_autorizado_por');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'sale_id');
    }

    public function saldo(): float
    {
        return (float) $this->saldo_financiar;
    }
}
