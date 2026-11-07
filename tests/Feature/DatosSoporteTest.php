<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Support\Importe;
use Database\Seeders\DatosPruebaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatosSoporteTest extends TestCase
{
    public function test_utiliza_la_base_solicitada_y_el_catalogo_original_tiene_categorias(): void
    {
        $this->assertGreaterThanOrEqual(6, DB::table('migrations')->count());
        $this->assertGreaterThan(0, Producto::query()->count());
        $this->assertSame(0, Producto::query()->whereNull('categoria_id')->count());
        $producto = Producto::query()->firstOrFail();
        $this->assertSame(Importe::aCentimos($producto->precio), $producto->precio_centimos);
        $this->assertNotNull($producto->categoriaProducto);
    }

    public function test_los_importes_no_pierden_centimos_al_convertirse(): void
    {
        foreach (['0.00' => 0, '0.01' => 1, '18.51' => 1851, '99999999.99' => 9999999999] as $decimal => $centimos) {
            $this->assertSame($centimos, Importe::aCentimos($decimal));
            $this->assertSame($decimal, Importe::aDecimal($centimos));
        }
    }

    public function test_cargar_mesas_iniciales_no_duplica_ni_cambia_datos_existentes(): void
    {
        $this->seed(DatosPruebaSeeder::class);
        $mesa = Mesa::query()->where('numero', 1)->firstOrFail();
        $mesa->update(['zona' => 'Zona temporal de prueba']);
        $cantidad = Mesa::query()->count();
        $this->seed(DatosPruebaSeeder::class);
        $this->assertSame($cantidad, Mesa::query()->count());
        $this->assertSame('Zona temporal de prueba', $mesa->fresh()->zona);
    }

    public function test_los_nombres_de_usuario_son_unicos(): void
    {
        $usuario = Usuario::factory()->create();
        $this->expectException(QueryException::class);
        Usuario::factory()->create(['usuario' => $usuario->usuario]);
    }

    public function test_la_clave_idempotente_es_unica_por_mozo(): void
    {
        $pedido = Pedido::factory()->create(['clave_idempotencia' => 'clave-de-prueba']);
        Pedido::factory()->create(['clave_idempotencia' => 'clave-de-prueba']);
        $this->expectException(QueryException::class);
        Pedido::factory()->create(['mozo_id' => $pedido->mozo_id, 'atencion_id' => $pedido->atencion_id, 'clave_idempotencia' => 'clave-de-prueba']);
    }

    public function test_no_se_eliminan_productos_con_detalles_historicos(): void
    {
        $producto = Producto::factory()->create();
        DetallePedido::factory()->create(['producto_id' => $producto->id]);
        $this->expectException(QueryException::class);
        $producto->delete();
    }

    public function test_el_comando_crea_usuarios_con_clave_sin_hash_en_esta_etapa(): void
    {
        $nombre = 'prueba_'.strtolower(Str::random(12));
        $this->artisan('chifa:crear-usuario', ['usuario' => $nombre, '--rol' => 'mozo', '--nombre' => 'Nuevo Mozo'])->expectsQuestion('Contraseña del nuevo usuario', 'clave123')->expectsQuestion('Repite la contraseña', 'clave123')->expectsOutput('Usuario creado correctamente.')->assertSuccessful();
        $this->assertDatabaseHas('usuarios', ['usuario' => $nombre, 'rol' => RolUsuario::Mozo->value, 'password' => 'clave123']);
    }

    public function test_el_comando_rechaza_duplicados_roles_invalidos_y_claves_distintas(): void
    {
        $usuario = Usuario::factory()->create();
        $nuevo = 'prueba_'.strtolower(Str::random(12));
        $this->artisan('chifa:crear-usuario', ['usuario' => $usuario->usuario])->assertFailed();
        $this->artisan('chifa:crear-usuario', ['usuario' => $nuevo, '--rol' => 'otro'])->assertFailed();
        $this->artisan('chifa:crear-usuario', ['usuario' => $nuevo])->expectsQuestion('Contraseña del nuevo usuario', 'clave123')->expectsQuestion('Repite la contraseña', 'otra123')->assertFailed();
        $this->assertDatabaseMissing('usuarios', ['usuario' => $nuevo]);
    }
}
