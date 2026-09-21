<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('post')
            ? ($this->user()?->can('crear clientes') ?? false)
            : ($this->user()?->can('editar clientes') ?? false);
    }

    public function rules(): array
    {
        $documentoRules = ['nullable', 'string', 'max:80'];

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'documento' => $documentoRules,
            'telefono' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string'],
            'lote_id' => ['nullable', 'integer', 'exists:lotes,id'],
            'confirm_potential_match' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'documento.unique' => 'Este cliente ya existe en la urbanizacion actual.',
        ];
    }
}
