<?php

namespace App\Http\Requests;

use App\Models\Reserva;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'lote_id' => ['required', 'exists:lotes,id'],
            'cliente_id' => ['required', 'exists:clientes,id'],
            'tipo_operacion' => ['required', 'in:'.implode(',', Reserva::TIPOS_OPERACION)],
            'fecha_venta' => ['required', 'date'],
            'precio_final' => ['required', 'numeric', 'min:0'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'lte:precio_final'],
            'cuota_inicial' => ['nullable', 'numeric', 'min:0', 'lte:precio_final'],
            'numero_cuotas' => ['nullable', 'integer', 'min:0'],
            'fecha_primer_vencimiento' => ['nullable', 'date'],
            'estado' => ['required', 'in:activa,completada,anulada'],
            'observaciones' => ['nullable', 'string'],
            'metodo_pago' => ['nullable', 'in:efectivo,transferencia,QR,banco,otro'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'admin_confirma_reserva' => ['nullable', 'boolean'],
            'motivo_cambio' => [$this->isMethod('post') ? 'nullable' : 'required', 'string', 'max:1000'],
        ];
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
            'motivo_cambio.required' => 'Debes explicar el motivo del cambio de esta venta.',
        ];
    }
}
