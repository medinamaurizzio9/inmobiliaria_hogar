<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayCuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cobrar cuotas') ?? false;
    }

    public function rules(): array
    {
        return [
            'monto_pagado' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['required', 'in:efectivo,transferencia,QR,banco,otro'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'tipo_aplicacion' => ['nullable', 'in:cuotas,amortizacion'],
        ];
    }

    public function messages(): array
    {
        return ['monto_pagado.min' => 'El pago parcial debe ser mayor a cero.'];
    }
}
