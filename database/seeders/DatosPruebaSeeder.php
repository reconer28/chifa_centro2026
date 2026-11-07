<?php

namespace Database\Seeders;

use App\Models\Mesa;
use Illuminate\Database\Seeder;
use LogicException;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Las mesas iniciales de prueba solo pueden cargarse en desarrollo o pruebas.');
        }
        for ($numero = 1; $numero <= 12; $numero++) {
            Mesa::query()->firstOrCreate(['numero' => $numero], ['capacidad' => 4, 'zona' => $numero <= 8 ? 'Salón principal' : 'Terraza']);
        }
    }
}
