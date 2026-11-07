<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IniciarSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['username' => ['required', 'string', 'max:50'], 'password' => ['required', 'string', 'max:255'], 'device_name' => ['required', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute no puede superar :max caracteres.'];
    }

    public function attributes(): array
    {
        return ['username' => 'usuario', 'password' => 'contraseña', 'device_name' => 'nombre del dispositivo'];
    }
}
