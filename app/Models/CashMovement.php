<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashMovement extends Model
{
    public const TIPOS = ['ingreso', 'egreso'];

    public const CONCEPTOS = ['reserva', 'anticipo', 'contado', 'cuota', 'amortizacion', 'ajuste'];

    public const METODOS = ['efectivo', 'transferencia', 'QR', 'banco', 'otro'];

    public const ESTADOS = ['pendiente_verificacion', 'confirmado', 'rechazado', 'anulado', 'devolucion'];

    public const ESTADOS_PAGO = ['pendiente_verificacion', 'confirmado', 'rechazado', 'anulado'];

    public const MODALIDADES = ['contado', 'semicontado', 'credito'];

    public const METODO_LABELS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'QR' => 'QR',
        'banco' => 'Banco',
        'otro' => 'Otro',
    ];

    public const ESTADO_LABELS = [
        'pendiente_verificacion' => 'Pendiente de verificacion',
        'confirmado' => 'Confirmado',
        'rechazado' => 'Rechazado',
        'anulado' => 'Anulado',
        'devolucion' => 'Devolucion',
    ];

    public const MODALIDAD_LABELS = [
        'contado' => 'Contado',
        'semicontado' => 'Semicontado',
        'credito' => 'Credito',
    ];

    protected $fillable = [
        'user_id',
        'cliente_id',
        'sale_id',
        'reservation_id',
        'installment_id',
        'tipo',
        'concepto',
        'metodo_pago',
        'monto',
        'fecha',
        'referencia',
        'banco',
        'motivo_rechazo',
        'confirmado_por',
        'confirmado_en',
        'observaciones',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'confirmado_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'sale_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reservation_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'installment_id');
    }

    public function confirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function pagoAplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacion::class);
    }

    public function scopeFiltered(Builder $query, array $filtros): Builder
    {
        $search = trim((string) ($filtros['q'] ?? ''));
        $cliente = trim((string) ($filtros['cliente'] ?? ''));
        $documento = trim((string) ($filtros['documento'] ?? ''));
        $referencia = trim((string) ($filtros['referencia'] ?? ''));
        $lote = trim((string) ($filtros['lote'] ?? ''));
        $modalidad = trim((string) ($filtros['modalidad'] ?? ''));
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        $concepto = trim((string) ($filtros['concepto'] ?? ''));
        $metodo = trim((string) ($filtros['metodo_pago'] ?? ''));
        $estado = trim((string) ($filtros['estado'] ?? ''));
        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        $montoMin = trim((string) ($filtros['monto_min'] ?? ''));
        $montoMax = trim((string) ($filtros['monto_max'] ?? ''));
        $usuarioId = (int) ($filtros['usuario_id'] ?? 0);

        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested->where('concepto', 'like', "%{$search}%")
                        ->orWhere('referencia', 'like', "%{$search}%")
                        ->orWhereHas('cliente', function (Builder $clienteQuery) use ($search): void {
                            $clienteQuery->where('nombre', 'like', "%{$search}%")
                                ->orWhere('documento', 'like', "%{$search}%");
                        });
                });
            })
            ->when($cliente !== '', fn (Builder $builder) => $builder->whereHas('cliente', fn (Builder $clienteQuery) => $clienteQuery->where('nombre', 'like', "%{$cliente}%")))
            ->when($documento !== '', fn (Builder $builder) => $builder->whereHas('cliente', fn (Builder $clienteQuery) => $clienteQuery->where('documento', 'like', "%{$documento}%")))
            ->when($referencia !== '', fn (Builder $builder) => $builder->where('referencia', 'like', "%{$referencia}%"))
            ->when($lote !== '', function (Builder $builder) use ($lote): void {
                $builder->where(function (Builder $nested) use ($lote): void {
                    $nested->whereHas('venta.lote', fn (Builder $loteQuery) => self::coincideLote($loteQuery, $lote))
                        ->orWhereHas('reserva.lote', fn (Builder $loteQuery) => self::coincideLote($loteQuery, $lote))
                        ->orWhereHas('cuota.venta.lote', fn (Builder $loteQuery) => self::coincideLote($loteQuery, $lote));
                });
            })
            ->when($modalidad !== '', function (Builder $builder) use ($modalidad): void {
                $builder->where(function (Builder $nested) use ($modalidad): void {
                    $nested->whereHas('venta', fn (Builder $ventaQuery) => $ventaQuery->where('tipo_operacion', $modalidad))
                        ->orWhereHas('reserva', fn (Builder $reservaQuery) => $reservaQuery->where('tipo_operacion', $modalidad))
                        ->orWhereHas('cuota.venta', fn (Builder $ventaQuery) => $ventaQuery->where('tipo_operacion', $modalidad));
                });
            })
            ->when($tipo !== '', fn (Builder $builder) => $builder->where('tipo', $tipo))
            ->when($concepto !== '', fn (Builder $builder) => $builder->where('concepto', $concepto))
            ->when($metodo !== '', fn (Builder $builder) => $builder->where('metodo_pago', $metodo))
            ->when($estado !== '', fn (Builder $builder) => $builder->where('estado', $estado))
            ->when($montoMin !== '', fn (Builder $builder) => $builder->where('monto', '>=', (float) $montoMin))
            ->when($montoMax !== '', fn (Builder $builder) => $builder->where('monto', '<=', (float) $montoMax))
            ->when($usuarioId > 0, fn (Builder $builder) => $builder->where('user_id', $usuarioId))
            ->when($fechaDesde !== '', fn (Builder $builder) => $builder->whereDate('fecha', '>=', $fechaDesde))
            ->when($fechaHasta !== '', fn (Builder $builder) => $builder->whereDate('fecha', '<=', $fechaHasta));

        return $query;
    }

    private static function coincideLote(Builder $loteQuery, string $busqueda): void
    {
        $loteQuery->where('codigo', 'like', "%{$busqueda}%")
            ->orWhereHas('manzano', fn (Builder $manzanoQuery) => $manzanoQuery->where('codigo', 'like', "%{$busqueda}%"));
    }
}
