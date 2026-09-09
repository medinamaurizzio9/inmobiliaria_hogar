<?php

namespace App\Services;

use App\Models\CommercialSetting;
use App\Models\Urbanizacion;
use App\Models\UrbanizacionCommercialSetting;
use App\Support\UrbanizacionContext;
use Illuminate\Support\Carbon;

class CommercialSettingsService
{
    /** @var array<int, array<string, mixed>> */
    private array $resolvedSettings = [];

    public const RESERVA_DIAS_HABILES_ASESOR = 'reserva_dias_habiles_asesor';

    public const TIPO_CAMBIO_USD_BS = 'tipo_cambio_usd_bs';

    public const INCREMENTO_CREDITO_TIPO = 'incremento_credito_tipo';

    public const INCREMENTO_CREDITO_VALOR = 'incremento_credito_valor';

    public const MAX_CUOTAS_SEMICONTADO = 'max_cuotas_semicontado';

    public const MAX_CUOTAS_CREDITO = 'max_cuotas_credito';

    public const INICIAL_MINIMA_USD = 'inicial_minima_usd';

    public const PLAZO_12_HABILITADO = 'plazo_12_habilitado';

    public const PLAZO_24_HABILITADO = 'plazo_24_habilitado';

    public const PLAZO_36_HABILITADO = 'plazo_36_habilitado';

    public const DESCUENTO_CONTADO_ACTIVO = 'descuento_contado_activo';

    public const DESCUENTO_CONTADO_TIPO = 'descuento_contado_tipo';

    public const DESCUENTO_CONTADO_VALOR = 'descuento_contado_valor';

    public const DESCUENTO_PROMO_ACTIVO = 'descuento_promo_activo';

    public const DESCUENTO_PROMO_TIPO = 'descuento_promo_tipo';

    public const DESCUENTO_PROMO_VALOR = 'descuento_promo_valor';

    public const DESCUENTO_PROMO_NOMBRE = 'descuento_promo_nombre';

    public const DESCUENTO_PROMO_DESCRIPCION = 'descuento_promo_descripcion';

    public function reservaDiasHabilesAsesor(?int $urbanizacionId = null): int
    {
        return max(1, $this->settings($urbanizacionId)['reserva_dias_habiles_asesor']);
    }

    public function setReservaDiasHabilesAsesor(int $days, ?int $urbanizacionId = null): UrbanizacionCommercialSetting
    {
        $setting = $this->settingModel($urbanizacionId);
        $setting->update(['reserva_dias_habiles_asesor' => max(1, $days)]);
        $this->forgetResolvedSettings($setting->urbanizacion_id);

        return $setting;
    }

    public function tipoCambioUsdBs(?int $urbanizacionId = null): float
    {
        return max(0, (float) $this->settings($urbanizacionId)['tipo_cambio_usd_bs']);
    }

    public function incrementoCreditoTipo(?int $urbanizacionId = null): string
    {
        $tipo = $this->settings($urbanizacionId)['incremento_credito_tipo'];

        return in_array($tipo, ['monto', 'porcentaje'], true) ? $tipo : 'monto';
    }

    public function incrementoCreditoValor(?int $urbanizacionId = null): float
    {
        return max(0, (float) $this->settings($urbanizacionId)['incremento_credito_valor']);
    }

    public function maxCuotasSemicontado(?int $urbanizacionId = null): int
    {
        return max(1, (int) $this->settings($urbanizacionId)['max_cuotas_semicontado']);
    }

    public function maxCuotasCredito(?int $urbanizacionId = null): int
    {
        return max(1, (int) $this->settings($urbanizacionId)['max_cuotas_credito']);
    }

    public function setFinancingLimits(int $semicontado, int $credito, ?int $urbanizacionId = null): UrbanizacionCommercialSetting
    {
        $setting = $this->settingModel($urbanizacionId);
        $setting->update([
            'max_cuotas_semicontado' => min(120, max(1, $semicontado)),
            'max_cuotas_credito' => min(120, max(1, $credito)),
        ]);
        $this->forgetResolvedSettings($setting->urbanizacion_id);

        return $setting;
    }

    public function priceSettings(?int $urbanizacionId = null): array
    {
        return [
            'tipo_cambio_usd_bs' => $this->tipoCambioUsdBs($urbanizacionId),
            'incremento_credito_tipo' => $this->incrementoCreditoTipo($urbanizacionId),
            'incremento_credito_valor' => $this->incrementoCreditoValor($urbanizacionId),
            'max_cuotas_semicontado' => $this->maxCuotasSemicontado($urbanizacionId),
            'max_cuotas_credito' => $this->maxCuotasCredito($urbanizacionId),
            ...$this->calculatorSettings($urbanizacionId),
        ];
    }

    public function setPriceSettings(array $data, ?int $urbanizacionId = null): UrbanizacionCommercialSetting
    {
        $setting = $this->settingModel($urbanizacionId);
        $setting->update([
            'tipo_cambio_usd_bs' => max(0, (float) $data[self::TIPO_CAMBIO_USD_BS]),
            'incremento_credito_tipo' => in_array($data[self::INCREMENTO_CREDITO_TIPO], ['monto', 'porcentaje'], true)
                ? $data[self::INCREMENTO_CREDITO_TIPO]
                : 'monto',
            'incremento_credito_valor' => max(0, (float) $data[self::INCREMENTO_CREDITO_VALOR]),
            'max_cuotas_semicontado' => max(1, (int) ($data[self::MAX_CUOTAS_SEMICONTADO] ?? $setting->max_cuotas_semicontado ?? 36)),
            'max_cuotas_credito' => max(1, (int) ($data[self::MAX_CUOTAS_CREDITO] ?? $setting->max_cuotas_credito ?? 36)),
        ]);
        $this->forgetResolvedSettings($setting->urbanizacion_id);

        return $setting;
    }

    public function updateForUrbanizacion(int $urbanizacionId, array $data): UrbanizacionCommercialSetting
    {
        $setting = $this->settingModel($urbanizacionId);
        $setting->update([
            'reserva_dias_habiles_asesor' => max(1, (int) $data[self::RESERVA_DIAS_HABILES_ASESOR]),
            'tipo_cambio_usd_bs' => max(0, (float) $data[self::TIPO_CAMBIO_USD_BS]),
            'incremento_credito_tipo' => $data[self::INCREMENTO_CREDITO_TIPO],
            'incremento_credito_valor' => max(0, (float) $data[self::INCREMENTO_CREDITO_VALOR]),
            'max_cuotas_semicontado' => max(1, (int) ($data[self::MAX_CUOTAS_SEMICONTADO] ?? $setting->max_cuotas_semicontado ?? 36)),
            'max_cuotas_credito' => max(1, (int) ($data[self::MAX_CUOTAS_CREDITO] ?? $setting->max_cuotas_credito ?? 36)),
            'inicial_minima_usd' => max(0, (float) ($data[self::INICIAL_MINIMA_USD] ?? $setting->inicial_minima_usd ?? 0)),
            'plazo_12_habilitado' => (bool) ($data[self::PLAZO_12_HABILITADO] ?? $setting->plazo_12_habilitado ?? true),
            'plazo_24_habilitado' => (bool) ($data[self::PLAZO_24_HABILITADO] ?? $setting->plazo_24_habilitado ?? true),
            'plazo_36_habilitado' => (bool) ($data[self::PLAZO_36_HABILITADO] ?? $setting->plazo_36_habilitado ?? true),
            'descuento_contado_activo' => (bool) ($data[self::DESCUENTO_CONTADO_ACTIVO] ?? $setting->descuento_contado_activo ?? false),
            'descuento_contado_tipo' => $this->discountType($data[self::DESCUENTO_CONTADO_TIPO] ?? $setting->descuento_contado_tipo ?? 'monto'),
            'descuento_contado_valor' => max(0, (float) ($data[self::DESCUENTO_CONTADO_VALOR] ?? $setting->descuento_contado_valor ?? 0)),
            'descuento_promo_activo' => (bool) ($data[self::DESCUENTO_PROMO_ACTIVO] ?? $setting->descuento_promo_activo ?? false),
            'descuento_promo_tipo' => $this->discountType($data[self::DESCUENTO_PROMO_TIPO] ?? $setting->descuento_promo_tipo ?? 'monto'),
            'descuento_promo_valor' => max(0, (float) ($data[self::DESCUENTO_PROMO_VALOR] ?? $setting->descuento_promo_valor ?? 0)),
            'descuento_promo_nombre' => $data[self::DESCUENTO_PROMO_NOMBRE] ?? null,
            'descuento_promo_descripcion' => $data[self::DESCUENTO_PROMO_DESCRIPCION] ?? null,
        ]);
        $this->forgetResolvedSettings($setting->urbanizacion_id);

        return $setting;
    }

    public function calculatorSettings(?int $urbanizacionId = null): array
    {
        $settings = $this->settings($urbanizacionId);

        return [
            'inicial_minima_usd' => $settings['inicial_minima_usd'],
            'plazo_12_habilitado' => $settings['plazo_12_habilitado'],
            'plazo_24_habilitado' => $settings['plazo_24_habilitado'],
            'plazo_36_habilitado' => $settings['plazo_36_habilitado'],
            'descuento_contado_activo' => $settings['descuento_contado_activo'],
            'descuento_contado_tipo' => $settings['descuento_contado_tipo'],
            'descuento_contado_valor' => $settings['descuento_contado_valor'],
            'descuento_promo_activo' => $settings['descuento_promo_activo'],
            'descuento_promo_tipo' => $settings['descuento_promo_tipo'],
            'descuento_promo_valor' => $settings['descuento_promo_valor'],
            'descuento_promo_nombre' => $settings['descuento_promo_nombre'],
            'descuento_promo_descripcion' => $settings['descuento_promo_descripcion'],
        ];
    }

    public function calculatorPayload(?int $urbanizacionId = null): array
    {
        $settings = $this->settings($urbanizacionId);

        return [
            'tipoCambio' => $settings['tipo_cambio_usd_bs'],
            'inicialMinimaUsd' => $settings['inicial_minima_usd'],
            'plazos' => collect([
                12 => $settings['plazo_12_habilitado'],
                24 => $settings['plazo_24_habilitado'],
                36 => $settings['plazo_36_habilitado'],
            ])->filter()->keys()->values()->all(),
            'descuentoContado' => [
                'activo' => $settings['descuento_contado_activo'],
                'tipo' => $settings['descuento_contado_tipo'],
                'valor' => $settings['descuento_contado_valor'],
            ],
            'descuentoPromo' => [
                'activo' => $settings['descuento_promo_activo'],
                'tipo' => $settings['descuento_promo_tipo'],
                'valor' => $settings['descuento_promo_valor'],
                'nombre' => $settings['descuento_promo_nombre'],
                'descripcion' => $settings['descuento_promo_descripcion'],
            ],
        ];
    }

    public function addBusinessDays(Carbon $date, int $days): Carbon
    {
        $current = $date->copy();
        $added = 0;

        while ($added < $days) {
            $current->addDay();
            if (! $current->isWeekend()) {
                $added++;
            }
        }

        return $current;
    }

    public function settings(?int $urbanizacionId = null): array
    {
        $urbanizacionId ??= UrbanizacionContext::currentId();
        $cacheKey = (int) ($urbanizacionId ?? 0);

        if (array_key_exists($cacheKey, $this->resolvedSettings)) {
            return $this->resolvedSettings[$cacheKey];
        }

        if (! $urbanizacionId) {
            return $this->resolvedSettings[$cacheKey] = $this->globalDefaults();
        }

        $setting = $this->settingModel($urbanizacionId);

        return $this->resolvedSettings[$cacheKey] = [
            'reserva_dias_habiles_asesor' => max(1, (int) $setting->reserva_dias_habiles_asesor),
            'tipo_cambio_usd_bs' => max(0, (float) $setting->tipo_cambio_usd_bs),
            'incremento_credito_tipo' => in_array($setting->incremento_credito_tipo, ['monto', 'porcentaje'], true) ? $setting->incremento_credito_tipo : 'monto',
            'incremento_credito_valor' => max(0, (float) $setting->incremento_credito_valor),
            'max_cuotas_semicontado' => max(1, (int) ($setting->max_cuotas_semicontado ?? 36)),
            'max_cuotas_credito' => max(1, (int) ($setting->max_cuotas_credito ?? 36)),
            'inicial_minima_usd' => max(0, (float) ($setting->inicial_minima_usd ?? 0)),
            'plazo_12_habilitado' => (bool) ($setting->plazo_12_habilitado ?? true),
            'plazo_24_habilitado' => (bool) ($setting->plazo_24_habilitado ?? true),
            'plazo_36_habilitado' => (bool) ($setting->plazo_36_habilitado ?? true),
            'descuento_contado_activo' => (bool) ($setting->descuento_contado_activo ?? false),
            'descuento_contado_tipo' => $this->discountType($setting->descuento_contado_tipo ?? 'monto'),
            'descuento_contado_valor' => max(0, (float) ($setting->descuento_contado_valor ?? 0)),
            'descuento_promo_activo' => (bool) ($setting->descuento_promo_activo ?? false),
            'descuento_promo_tipo' => $this->discountType($setting->descuento_promo_tipo ?? 'monto'),
            'descuento_promo_valor' => max(0, (float) ($setting->descuento_promo_valor ?? 0)),
            'descuento_promo_nombre' => $setting->descuento_promo_nombre,
            'descuento_promo_descripcion' => $setting->descuento_promo_descripcion,
        ];
    }

    public function settingModel(?int $urbanizacionId = null): UrbanizacionCommercialSetting
    {
        $urbanizacionId ??= UrbanizacionContext::currentId();
        abort_unless($urbanizacionId, 404, 'Urbanizacion no encontrada.');

        $setting = UrbanizacionCommercialSetting::where('urbanizacion_id', $urbanizacionId)->first();
        if ($setting) {
            return $setting;
        }

        abort_unless(Urbanizacion::whereKey($urbanizacionId)->exists(), 404, 'Urbanizacion no encontrada.');

        return UrbanizacionCommercialSetting::firstOrCreate(
            ['urbanizacion_id' => $urbanizacionId],
            $this->globalDefaults(),
        );
    }

    private function forgetResolvedSettings(int $urbanizacionId): void
    {
        unset($this->resolvedSettings[$urbanizacionId]);
    }

    private function globalDefaults(): array
    {
        $global = CommercialSetting::query()->pluck('value', 'key');
        $tipo = $global[self::INCREMENTO_CREDITO_TIPO] ?? 'monto';

        return [
            'reserva_dias_habiles_asesor' => max(1, (int) ($global[self::RESERVA_DIAS_HABILES_ASESOR] ?? 5)),
            'tipo_cambio_usd_bs' => max(0, (float) ($global[self::TIPO_CAMBIO_USD_BS] ?? 6.96)),
            'incremento_credito_tipo' => in_array($tipo, ['monto', 'porcentaje'], true) ? $tipo : 'monto',
            'incremento_credito_valor' => max(0, (float) ($global[self::INCREMENTO_CREDITO_VALOR] ?? 0)),
            'max_cuotas_semicontado' => 36,
            'max_cuotas_credito' => 36,
            'inicial_minima_usd' => 0,
            'plazo_12_habilitado' => true,
            'plazo_24_habilitado' => true,
            'plazo_36_habilitado' => true,
            'descuento_contado_activo' => false,
            'descuento_contado_tipo' => 'monto',
            'descuento_contado_valor' => 0,
            'descuento_promo_activo' => false,
            'descuento_promo_tipo' => 'monto',
            'descuento_promo_valor' => 0,
            'descuento_promo_nombre' => null,
            'descuento_promo_descripcion' => null,
        ];
    }

    private function discountType(?string $tipo): string
    {
        return in_array($tipo, ['monto', 'porcentaje'], true) ? $tipo : 'monto';
    }
}
