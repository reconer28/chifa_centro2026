<?php

namespace Database\Factories;

use App\Models\DetallePedido;
use App\Support\Importe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DetallePedido> */
class DetallePedidoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pedido_id' => PedidoFactory::new(),
            'producto_id' => ProductoFactory::new(),
            'nombre_producto' => 'Arroz chaufa de prueba',
            'precio_centimos' => 1800,
            'cantidad' => 1,
            'observaciones' => '',
            'subtotal' => fn (array $atributos): string => Importe::aDecimal($atributos['precio_centimos'] * $atributos['cantidad']),
        ];
    }
}
