<?php

namespace Database\Factories;

use App\Enums\EstadoPedido;
use App\Models\Atencion;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Pedido> */
class PedidoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'atencion_id' => AtencionFactory::new(),
            'mozo_id' => fn (array $atributos) => Atencion::query()->findOrFail($atributos['atencion_id'])->mozo_id,
            'clave_idempotencia' => (string) Str::uuid(),
            'solicitud_hash' => hash('sha256', (string) Str::uuid()),
            'estado' => EstadoPedido::Enviado, 'total_centimos' => 1800,
        ];
    }
}
