<?php

namespace App\Http\Requests;

use App\Models\Reserva;
use App\Models\Lote;
use App\Services\CommercialSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? ($this->user()?->can('crear ventas') ?? false)
            : ($this->user()?->hasRole('administrador') && $this->user()?->can('editar ventas'));
    }

    public function rules(): array
    {
        $maxCuotas = $this->maxCuotasParaModalidad();
        $minCuotas = in_array($this->input('tipo_operacion'), ['semicontado', 'credito'], true) ? 1 : 0;

        return [
            'lote_id' => ['required', 'exists:lotes,id'],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tipo_operacion' => ['required', 'in:'.implode(',', Reserva::TIPOS_OPERACION)],
            'fecha_venta' => ['required', 'date'],
            'precio_final' => ['required', 'numeric', 'min:0'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'lte:precio_final'],
            'cuota_inicial' => ['required_if:tipo_operacion,semicontado,credito', 'nullable', 'numeric', 'min:0'],
            'numero_cuotas' => ['required_if:tipo_operacion,semicontado,credito', 'nullable', 'integer', 'min:'.$minCuotas, 'max:'.$maxCuotas],
            'fecha_primer_vencimiento' => ['required_if:tipo_operacion,semicontado,credito', 'nullable', 'date'],
            'estado' => ['required', 'in:activa,completada,anulada'],
            'observaciones' => ['nullable', 'string'],
            'metodo_pago' => ['nullable', 'in:efectivo,transferencia,QR,banco,otro'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'admin_confirma_reserva' => ['nullable', 'boolean'],
            'motivo_cambio' => [$this->isMethod('post') ? 'nullable' : 'required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! in_array($this->input('tipo_operacion'), ['semicontado', 'credito'], true)) {
                return;
            }

            $lote = Lote::with('manzano')->find($this->integer('lote_id'));
            if (! $lote) {
                return;
            }

            $precio = app(\App\Services\LotPricingService::class)->operationUsd($lote, $this->string('tipo_operacion')->toString());
            $precioPactado = $precio - max(0, (float) $this->input('descuento', 0));
            $inicial = (float) $this->input('cuota_inicial', 0);

            if ($inicial > $precioPactado) {
                $validator->errors()->add('cuota_inicial', 'La cuota inicial no puede superar el precio final pactado.');
            } elseif ($precioPactado - $inicial <= 0) {
                $validator->errors()->add('cuota_inicial', 'La modalidad financiada debe dejar un saldo mayor a cero.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('tipo_operacion')) {
            $this->merge([
                'tipo_operacion' => (int) $this->input('numero_cuotas', 0) > 0 ? 'credito' : 'contado',
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'lote_id.required' => 'Selecciona el lote que se vendera.',
            'cliente_id.required' => 'Selecciona el cliente comprador.',
            'precio_final.min' => 'El precio final no puede ser negativo.',
            'cuota_inicial.lte' => 'La cuota inicial no puede superar el precio final.',
            'numero_cuotas.max' => 'El número de cuotas supera el máximo configurado para la modalidad.',
            'fecha_primer_vencimiento.required_if' => 'La primera fecha de vencimiento es obligatoria para ventas financiadas.',
            'motivo_cambio.required' => 'Debes explicar el motivo del cambio de esta venta.',
        ];
    }

    private function maxCuotasParaModalidad(): int
    {
        $lote = Lote::with('manzano')->find($this->integer('lote_id'));
        $urbanizacionId = $lote?->manzano?->urbanizacion_id;
        $settings = app(CommercialSettingsService::class);

        return $this->input('tipo_operacion') === 'semicontado'
            ? $settings->maxCuotasSemicontado($urbanizacionId)
            : $settings->maxCuotasCredito($urbanizacionId);
    }
}
