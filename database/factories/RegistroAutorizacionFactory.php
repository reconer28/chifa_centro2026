<?php

namespace Database\Factories;

use App\Models\RegistroAutorizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistroAutorizacion> */
class RegistroAutorizacionFactory extends Factory
{
    public function definition(): array
    {
        return ['usuario_id' => UsuarioFactory::new(), 'accion' => 'asignar_mesas', 'autorizado' => true, 'ip' => '127.0.0.1'];
    }
}
