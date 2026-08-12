<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanizacionCommercialSetting extends Model
{
    protected $fillable = [
        'urbanizacion_id',
        'reserva_dias_habiles_asesor',
        'tipo_cambio_usd_bs',
        'incremento_credito_tipo',
        'incremento_credito_valor',
        'max_cuotas_semicontado',
        'max_cuotas_credito',
        'inicial_minima_usd',
        'plazo_12_habilitado',
        'plazo_24_habilitado',
        'plazo_36_habilitado',
        'descuento_contado_activo',
        'descuento_contado_tipo',
        'descuento_contado_valor',
        'descuento_promo_activo',
        'descuento_promo_tipo',
        'descuento_promo_valor',
        'descuento_promo_nombre',
        'descuento_promo_descripcion',
    ];

    protected function casts(): array
    {
        return [
            'reserva_dias_habiles_asesor' => 'integer',
            'tipo_cambio_usd_bs' => 'decimal:2',
            'incremento_credito_valor' => 'decimal:2',
            'max_cuotas_semicontado' => 'integer',
            'max_cuotas_credito' => 'integer',
            'inicial_minima_usd' => 'decimal:2',
            'plazo_12_habilitado' => 'boolean',
            'plazo_24_habilitado' => 'boolean',
            'plazo_36_habilitado' => 'boolean',
            'descuento_contado_activo' => 'boolean',
            'descuento_contado_valor' => 'decimal:2',
            'descuento_promo_activo' => 'boolean',
            'descuento_promo_valor' => 'decimal:2',
        ];
    }

    public function urbanizacion(): BelongsTo
    {
        return $this->belongsTo(Urbanizacion::class);
    }
}
