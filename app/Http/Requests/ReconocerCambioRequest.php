<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconocerCambioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['required' => 'Actualiza el pedido antes de confirmar sus cambios.', 'integer' => 'La versión del pedido no es válida.'];
    }
}
