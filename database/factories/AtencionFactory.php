<?php

namespace Database\Factories;

use App\Models\Atencion;
use App\Models\Mesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Atencion> */
class AtencionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mesa_id' => MesaFactory::new()->state(['mozo_id' => UsuarioFactory::new()]),
            'mozo_id' => fn (array $atributos) => Mesa::query()->findOrFail($atributos['mesa_id'])->mozo_id,
            'fecha_apertura' => now(), 'fecha_cierre' => null,
        ];
    }
}
