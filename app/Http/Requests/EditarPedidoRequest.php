<?php

namespace App\Http\Requests;

class EditarPedidoRequest extends EnviarPedidoRequest
{
    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['table_id']);
        $reglas['expected_version'] = ['required', 'integer', 'min:1'];

        return $reglas;
    }
}
