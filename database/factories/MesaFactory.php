<?php

namespace Database\Factories;

use App\Models\Mesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mesa> */
class MesaFactory extends Factory
{
    public function definition(): array
    {
        return ['numero' => fake()->unique()->numberBetween(40000, 60000), 'capacidad' => 4, 'zona' => 'Zona de prueba', 'mozo_id' => null, 'activo' => true];
    }
}
