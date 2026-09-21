<?php

namespace App\Models;

use App\Services\ClientMatchingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cliente extends Model
{
    protected $fillable = [
        'urbanizacion_id',
        'created_by',
        'responsible_user_id',
        'nombre',
        'documento',
        'documento_normalizado',
        'telefono',
        'telefono_normalizado',
        'email',
        'email_normalizado',
        'direccion',
        'foto',
        'protected_at',
        'protection_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'protected_at' => 'datetime',
            'protection_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Cliente $cliente): void {
            $matching = app(ClientMatchingService::class);
            if ($cliente->isDirty('documento')) {
                $cliente->documento_normalizado = $matching->normalizeDocument($cliente->documento);
            }
            if ($cliente->isDirty('telefono')) {
                $cliente->telefono_normalizado = $matching->normalizePhone($cliente->telefono);
            }
            if ($cliente->isDirty('email')) {
                $cliente->email_normalizado = $matching->normalizeEmail($cliente->email);
            }
        });
    }

    public function urbanizacion(): BelongsTo
    {
        return $this->belongsTo(Urbanizacion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsibleAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
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

    public function commercialEvents(): HasMany
    {
        return $this->hasMany(ClientCommercialEvent::class);
    }

    public function saldoPendiente(): float
    {
        return (float) $this->ventas->flatMap->cuotas->sum('saldo_pendiente');
    }
}
