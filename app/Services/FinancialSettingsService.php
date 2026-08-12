<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FinancialSettingsService
{
    public const DEFAULTS = [
        'qr_institucional_imagen' => '',
        'qr_institucional_nombre' => '',
        'qr_institucional_activo' => '0',
        'banco_nombre' => '',
        'banco_titular' => '',
        'banco_numero_cuenta' => '',
        'banco_tipo_cuenta' => '',
        'banco_moneda' => 'BOB',
        'banco_instrucciones' => '',
        'banco_activo' => '0',
        'dias_aviso_vencimiento' => '3',
        'mora_habilitada' => '0',
        'tipo_mora' => '',
        'valor_mora' => '0',
        'dias_gracia' => '0',
    ];

    public function __construct(private CommercialSettingsService $commercialSettings) {}

    public function all(?int $urbanizacionId = null): array
    {
        return [
            ...$this->globalValues(),
            'max_cuotas_semicontado' => $this->commercialSettings->maxCuotasSemicontado($urbanizacionId),
            'max_cuotas_credito' => $this->commercialSettings->maxCuotasCredito($urbanizacionId),
        ];
    }

    private function globalValues(): array
    {
        return Cache::remember('financial_settings.global', 60, function (): array {
            $stored = SystemSetting::query()
                ->whereIn('key', array_keys(self::DEFAULTS))
                ->pluck('value', 'key')
                ->all();
            $values = [...self::DEFAULTS, ...$stored];

            $qrPath = ltrim((string) $values['qr_institucional_imagen'], '/');
            $qrPath = str_starts_with($qrPath, 'storage/') ? substr($qrPath, 8) : $qrPath;

            return [
                'qr_institucional_imagen' => (string) $values['qr_institucional_imagen'],
                'qr_institucional_url' => $qrPath !== '' && Storage::disk('public')->exists($qrPath)
                    ? Storage::disk('public')->url($qrPath)
                    : null,
                'qr_institucional_nombre' => (string) $values['qr_institucional_nombre'],
                'qr_institucional_activo' => filter_var($values['qr_institucional_activo'], FILTER_VALIDATE_BOOLEAN),
                'banco_nombre' => (string) $values['banco_nombre'],
                'banco_titular' => (string) $values['banco_titular'],
                'banco_numero_cuenta' => (string) $values['banco_numero_cuenta'],
                'banco_tipo_cuenta' => (string) $values['banco_tipo_cuenta'],
                'banco_moneda' => (string) $values['banco_moneda'],
                'banco_instrucciones' => (string) $values['banco_instrucciones'],
                'banco_activo' => filter_var($values['banco_activo'], FILTER_VALIDATE_BOOLEAN),
                'dias_aviso_vencimiento' => min(30, max(0, (int) $values['dias_aviso_vencimiento'])),
                'mora_habilitada' => filter_var($values['mora_habilitada'], FILTER_VALIDATE_BOOLEAN),
                'tipo_mora' => (string) $values['tipo_mora'],
                'valor_mora' => max(0, (float) $values['valor_mora']),
                'dias_gracia' => min(365, max(0, (int) $values['dias_gracia'])),
            ];
        });
    }

    public function update(array $data, int $urbanizacionId): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $data)) {
                SystemSetting::updateOrCreate(['key' => $key], ['value' => $this->storageValue($data[$key])]);
            }
        }

        $this->commercialSettings->setFinancingLimits(
            (int) $data['max_cuotas_semicontado'],
            (int) $data['max_cuotas_credito'],
            $urbanizacionId
        );

        Cache::forget('financial_settings.global');
    }

    public function paymentInstructions(): array
    {
        $settings = $this->globalValues();

        return [
            'qr' => $settings['qr_institucional_activo'] && $settings['qr_institucional_url'] ? [
                'imagen' => $settings['qr_institucional_imagen'],
                'url' => $settings['qr_institucional_url'],
                'nombre' => $settings['qr_institucional_nombre'],
            ] : null,
            'cuenta_bancaria' => $settings['banco_activo'] ? [
                'banco' => $settings['banco_nombre'],
                'titular' => $settings['banco_titular'],
                'numero_cuenta' => $settings['banco_numero_cuenta'],
                'tipo_cuenta' => $settings['banco_tipo_cuenta'],
                'moneda' => $settings['banco_moneda'],
                'instrucciones' => $settings['banco_instrucciones'],
            ] : null,
        ];
    }

    public function diasAvisoVencimiento(): int
    {
        return $this->globalValues()['dias_aviso_vencimiento'];
    }

    private function storageValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) ($value ?? '');
    }
}
