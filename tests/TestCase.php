<?php

namespace Tests;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'Las pruebas deben utilizar MySQL.');
        $this->assertSame('bd_chifa_centro', DB::selectOne('SELECT DATABASE() AS nombre')->nombre);
        DB::connection()->beforeExecuting(function (string $consulta, array $parametros, Connection $conexion): void {
            if (preg_match('/^\s*(DROP|TRUNCATE|ALTER|CREATE|RENAME)\b/i', $consulta)) {
                throw new RuntimeException('Las pruebas no pueden cambiar ni vaciar la estructura de bd_chifa_centro. Ejecuta las migraciones por separado.');
            }
        });
    }
}
