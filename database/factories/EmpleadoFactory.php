<?php

namespace Database\Factories;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Empleado> */
class EmpleadoFactory extends Factory
{
    public function definition(): array
    {
        return ['nombres' => fake()->firstName(), 'apellidos' => fake()->lastName(), 'dni' => fake()->unique()->numerify('########'), 'telefono' => fake()->numerify('9########'), 'cargo' => 'Mozo', 'activo' => true];
    }
}
