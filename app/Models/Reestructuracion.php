<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reestructuracion extends Model
{
    protected $table = 'reestructuraciones';

    protected $fillable = ['venta_id', 'saldo_antes', 'numero_cuotas_pendientes_antes', 'plazo_anterior', 'nuevo_plazo', 'cuota_referencia', 'fecha', 'fecha_primer_vencimiento', 'administrador_id', 'motivo', 'observaciones', 'snapshot_antes', 'snapshot_despues'];

    protected function casts(): array
    {
        return ['saldo_antes' => 'decimal:2', 'cuota_referencia' => 'decimal:2', 'fecha' => 'date', 'fecha_primer_vencimiento' => 'date', 'snapshot_antes' => 'array', 'snapshot_despues' => 'array'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }
}
