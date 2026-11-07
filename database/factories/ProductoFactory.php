<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Producto> */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return ['categoria_id' => CategoriaFactory::new(), 'nombre' => fake()->words(3, true), 'descripcion' => 'Plato preparado al wok.', 'precio_centimos' => 1800, 'stock' => null, 'disponible' => true, 'destacado' => false, 'activo' => true];
    }
}
