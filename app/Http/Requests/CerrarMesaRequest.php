<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarMesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['order_ids' => ['required', 'array', 'min:1', 'max:1000'], 'order_ids.*' => ['required', 'integer', 'distinct', 'exists:pedidos,id']];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'present' => 'Debes enviar el campo :attribute.', 'exists' => 'La selección de :attribute ya no está disponible.', 'distinct' => 'No repitas valores en :attribute.', 'between' => 'La cantidad debe estar entre :min y :max.', 'max' => 'El campo :attribute supera el máximo permitido (:max).', 'integer' => 'El campo :attribute debe ser un número entero.', 'array' => 'El campo :attribute debe ser una lista.', 'min' => 'El campo :attribute necesita al menos :min elemento.', 'date_format' => 'La fecha debe tener el formato año-mes-día.', 'after_or_equal' => 'La fecha final no puede ser anterior a la inicial.'];
    }
}
