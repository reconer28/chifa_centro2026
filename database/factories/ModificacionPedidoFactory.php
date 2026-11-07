<?php

namespace Database\Factories;

use App\Models\ModificacionPedido;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ModificacionPedido> */
class ModificacionPedidoFactory extends Factory
{
    public function definition(): array
    {
        return ['pedido_id' => PedidoFactory::new(), 'usuario_id' => fn (array $datos) => Pedido::query()->findOrFail($datos['pedido_id'])->mozo_id, 'clave' => (string) Str::uuid(), 'solicitud_hash' => hash('sha256', 'prueba'), 'version' => 2, 'antes' => [], 'despues' => [], 'resumen' => 'Cambio de prueba'];
    }
}
