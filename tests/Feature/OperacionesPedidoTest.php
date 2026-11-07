<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RegistroAutorizacion;
use App\Models\Usuario;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OperacionesPedidoTest extends TestCase
{
    private function preparar(): array
    {
        $administrador = Usuario::factory()->create(['rol' => RolUsuario::Administrador, 'password' => 'adminPrueba123']);
        $mozo = Usuario::factory()->create();
        $mesa = Mesa::factory()->create(['mozo_id' => $mozo->id]);
        $producto = Producto::factory()->create(['stock' => 10, 'precio_centimos' => 1851]);
        $this->como($mozo);

        return [$mozo, $mesa, $producto, $administrador];
    }

    private function como(Usuario $usuario): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($usuario->createToken('Celular de prueba')->plainTextToken);
    }

    private function cuerpo(Mesa $mesa, Producto $producto, int $cantidad = 2): array
    {
        return ['table_id' => (string) $mesa->id, 'items' => [['product_id' => (string) $producto->id, 'quantity' => $cantidad, 'notes' => 'Sin cebolla']]];
    }

    private function enviar(array $datos, string $clave = 'pedido_prueba_001'): TestResponse
    {
        return $this->postJson('/api/v1/orders', $datos, ['Idempotency-Key' => $clave]);
    }

    public function test_calcula_precios_en_servidor_y_reintentar_no_duplica_ni_descontar_stock_dos_veces(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $datos = $this->cuerpo($mesa, $producto);
        $datos['waiter_id'] = 999;
        $datos['total_cents'] = 1;
        $datos['items'][0]['price_cents'] = 1;
        $primero = $this->enviar($datos)->assertSuccessful()->assertJsonPath('data.waiter_id', (string) $mozo->id)->assertJsonPath('data.lines.0.price_cents', 1851)->assertJsonPath('data.lines.0.notes', 'Sin cebolla')->json('data.id');
        $producto->update(['precio_centimos' => 9999, 'disponible' => false]);
        $this->enviar($datos)->assertSuccessful()->assertJsonPath('data.id', $primero)->assertJsonPath('data.lines.0.price_cents', 1851);
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertSame(1, Pedido::query()->where('mozo_id', $mozo->id)->count());
        $this->assertSame(3702, Pedido::query()->findOrFail($primero)->total_centimos);
    }

    public function test_una_misma_clave_con_otro_contenido_se_rechaza(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $this->enviar($this->cuerpo($mesa, $producto))->assertSuccessful();
        $this->enviar($this->cuerpo($mesa, $producto, 3))->assertConflict();
        $this->assertSame(8, $producto->fresh()->stock);
    }

    public function test_rechaza_cantidad_notas_duplicados_y_clave_ausente_sin_crear_pedido(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $this->enviar($this->cuerpo($mesa, $producto, 100))->assertUnprocessable();
        $datos = $this->cuerpo($mesa, $producto);
        $datos['items'][0]['notes'] = str_repeat('a', 251);
        $this->enviar($datos)->assertUnprocessable();
        $datos = $this->cuerpo($mesa, $producto);
        $datos['items'][] = $datos['items'][0];
        $this->enviar($datos)->assertUnprocessable();
        $this->postJson('/api/v1/orders', $this->cuerpo($mesa, $producto))->assertUnprocessable();
        $this->assertSame(0, Pedido::query()->where('mozo_id', $mozo->id)->count());
        $this->assertSame(10, $producto->fresh()->stock);
    }

    public function test_sin_stock_o_categoria_inactiva_no_se_crea_atencion_ni_pedido(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $this->enviar($this->cuerpo($mesa, $producto, 11))->assertUnprocessable();
        $producto->categoriaProducto->update(['activo' => false]);
        $this->enviar($this->cuerpo($mesa, $producto))->assertUnprocessable();
        $this->assertFalse($mesa->atencionActiva()->exists());
        $this->assertSame(10, $producto->fresh()->stock);
    }

    public function test_asignacion_exige_clave_no_permite_robar_mesas_y_registra_la_autorizacion(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $otra = Mesa::factory()->create();
        $this->putJson('/api/v1/me/tables', ['table_ids' => [$mesa->id, $otra->id], 'admin_password' => 'incorrecta'])->assertForbidden();
        $this->assertNull($otra->fresh()->mozo_id);
        $this->putJson('/api/v1/me/tables', ['table_ids' => [$mesa->id, $otra->id], 'admin_password' => 'adminPrueba123'])->assertNoContent();
        $this->assertSame($mozo->id, $otra->fresh()->mozo_id);
        $otro = Usuario::factory()->create();
        $this->como($otro);
        $this->putJson('/api/v1/me/tables', ['table_ids' => [$otra->id], 'admin_password' => 'adminPrueba123'])->assertConflict();
        $this->assertTrue(RegistroAutorizacion::query()->where('usuario_id', $mozo->id)->where('autorizado', true)->exists());
    }

    public function test_turno_anterior_libre_puede_reasignarse_pero_una_mesa_ocupada_no(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $mesa->update(['fecha_turno' => now('America/Lima')->subDay()->toDateString()]);
        $this->enviar($this->cuerpo($mesa, $producto))->assertForbidden();
        $this->putJson('/api/v1/me/tables', ['table_ids' => [$mesa->id], 'admin_password' => 'adminPrueba123'])->assertNoContent();
        $this->enviar($this->cuerpo($mesa, $producto))->assertSuccessful();
        $this->putJson('/api/v1/me/tables', ['table_ids' => [], 'admin_password' => 'adminPrueba123'])->assertConflict();
        $this->assertSame($mozo->id, $mesa->fresh()->mozo_id);
    }

    public function test_anular_restaura_stock_una_sola_vez_y_la_mesa_sigue_ocupada(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $id = $this->enviar($this->cuerpo($mesa, $producto))->assertSuccessful()->json('data.id');
        for ($vez = 0; $vez < 2; $vez++) {
            $this->postJson('/api/v1/orders/'.$id.'/cancel', ['reason' => 'Cambio solicitado', 'admin_password' => 'adminPrueba123'])->assertNoContent();
        }
        $this->assertSame(10, $producto->fresh()->stock);
        $this->assertTrue($mesa->atencionActiva()->exists());
        $this->postJson('/api/v1/orders/'.$id.'/deliver')->assertConflict();
        $this->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$id]])->assertNoContent();
        $this->assertFalse($mesa->atencionActiva()->exists());
    }

    public function test_otro_mozo_no_puede_entregar_anular_ni_cerrar_el_pedido(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $id = $this->enviar($this->cuerpo($mesa, $producto))->assertSuccessful()->json('data.id');
        $this->como(Usuario::factory()->create());
        $this->postJson('/api/v1/orders/'.$id.'/deliver')->assertForbidden();
        $this->postJson('/api/v1/orders/'.$id.'/cancel', ['reason' => 'Cambio', 'admin_password' => 'adminPrueba123'])->assertForbidden();
        $this->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$id]])->assertConflict();
    }

    public function test_cierre_comprueba_el_conjunto_actual_y_reintentar_no_cierra_una_atencion_nueva(): void
    {
        [$mozo,$mesa,$producto] = $this->preparar();
        $datos = $this->cuerpo($mesa, $producto);
        $primero = $this->enviar($datos)->assertSuccessful()->json('data.id');
        $segundo = $this->enviar($datos, 'pedido_prueba_002')->assertSuccessful()->json('data.id');
        $this->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$primero]])->assertConflict();
        $this->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$primero, $segundo]])->assertConflict();
        foreach ([$primero, $segundo] as $id) {
            $this->postJson('/api/v1/orders/'.$id.'/deliver')->assertNoContent();
            $this->postJson('/api/v1/orders/'.$id.'/deliver')->assertNoContent();
        }
        $this->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$primero, $segundo]])->assertNoContent();
        $this->enviar($datos, 'pedido_prueba_003')->assertSuccessful();
        $this->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$primero, $segundo]])->assertNoContent();
        $this->assertTrue($mesa->atencionActiva()->exists());
        $this->enviar($datos)->assertSuccessful()->assertJsonPath('data.id', $primero);
        $this->assertSame(4, $producto->fresh()->stock);
    }

    public function test_fallos_de_autorizacion_tienen_limite_de_intentos(): void
    {
        [$mozo,$mesa] = $this->preparar();
        for ($intento = 0; $intento < 5; $intento++) {
            $this->putJson('/api/v1/me/tables',['table_ids' => [$mesa->id], 'admin_password' => 'incorrecta'])->assertForbidden();
        }
        $this->putJson('/api/v1/me/tables',['table_ids' => [$mesa->id], 'admin_password' => 'incorrecta'])->assertTooManyRequests();
    }
}
