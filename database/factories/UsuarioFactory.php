<?php

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Usuario> */
class UsuarioFactory extends Factory
{
    public function definition(): array
    {
        return ['usuario' => 'prueba_'.strtolower(Str::random(16)), 'nombre' => 'Mozo de prueba', 'password' => 'clave123', 'rol' => RolUsuario::Mozo, 'empleado_id' => null, 'activo' => true];
    }
}
