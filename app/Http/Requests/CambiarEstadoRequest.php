<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['estado' => ['required', Rule::in(['preparing', 'ready'])], 'expected_version' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['expected_version.required' => 'Actualiza el pedido antes de cambiar su estado.', 'required' => 'Selecciona el nuevo estado del pedido.', 'in' => 'El estado seleccionado no está permitido.'];
    }
}
