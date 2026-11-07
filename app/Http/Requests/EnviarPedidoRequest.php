<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnviarPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['clave_idempotencia' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return ['table_id' => ['required', 'integer', 'exists:mesas,id'], 'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:productos,id'], 'items.*.quantity' => ['required', 'integer', 'between:1,99'], 'items.*.notes' => ['nullable', 'string', 'max:250'], 'clave_idempotencia' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/']];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'present' => 'Debes enviar el campo :attribute.', 'exists' => 'La selección de :attribute ya no está disponible.', 'distinct' => 'No repitas valores en :attribute.', 'between' => 'La cantidad debe estar entre :min y :max.', 'max' => 'El campo :attribute supera el máximo permitido (:max).', 'integer' => 'El campo :attribute debe ser un número entero.', 'array' => 'El campo :attribute debe ser una lista.', 'min' => 'El campo :attribute necesita al menos :min elemento.', 'date_format' => 'La fecha debe tener el formato año-mes-día.', 'after_or_equal' => 'La fecha final no puede ser anterior a la inicial.'];
    }
}
