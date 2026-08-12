<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoAplicacion extends Model
{
    protected $table = 'pago_aplicaciones';

    protected $fillable = [
        'cash_movement_id',
        'cuota_id',
        'monto_aplicado',
    ];

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }
}
