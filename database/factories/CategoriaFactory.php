<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Categoria> */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => 'Prueba '.Str::random(16), 'activo' => true];
    }
}
