<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Models\Atencion;
use App\Models\Categoria;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Tests\TestCase;

class ConsultasApiTest extends TestCase
{
    private function autenticarMozo(): Usuario
    {
        $usuario = Usuario::factory()->create();
        $this->withToken($usuario->createToken('Android')->plainTextToken);

        return $usuario;
    }

    public function test_las_consultas_exigen_autenticacion(): void
    {
        foreach (['tables', 'menu', 'orders'] as $ruta) {
            $this->get('/api/v1/'.$ruta)->assertUnauthorized()->assertJsonStructure(['message']);
        }
    }

    public function test_la_carta_respeta_los_precios_decimales_y_conserva_los_agotados(): void
    {
        $this->autenticarMozo();
        $categoria = Categoria::factory()->create();
        $producto = Producto::factory()->create(['categoria_id' => $categoria->id, 'precio_centimos' => 1851, 'descripcion' => null, 'destacado' => true]);
        $agotado = Producto::factory()->create(['categoria_id' => $categoria->id, 'stock' => 0]);
        $noDisponible = Producto::factory()->create(['categoria_id' => $categoria->id, 'disponible' => false]);
        $inactivo = Producto::factory()->create(['categoria_id' => $categoria->id, 'activo' => false]);
        $categoriaInactiva = Categoria::factory()->create(['activo' => false]);
        $otroInactivo = Producto::factory()->create(['categoria_id' => $categoriaInactiva->id]);
        $respuesta = $this->getJson('/api/v1/menu')->assertOk();
        $datos = collect($respuesta->json('data'))->keyBy('id');
        $this->assertSame(['id' => (string) $producto->id, 'name' => $producto->nombre, 'description' => '', 'category' => $categoria->nombre, 'price_cents' => 1851, 'available' => true, 'featured' => true], $datos[(string) $producto->id]);
        $this->assertSame('18.51', $producto->fresh()->precio);
        $this->assertFalse($datos[(string) $agotado->id]['available']);
        $this->assertFalse($datos[(string) $noDisponible->id]['available']);
        $this->assertArrayNotHasKey((string) $inactivo->id, $datos->all());
        $this->assertArrayNotHasKey((string) $otroInactivo->id, $datos->all());
    }

    public function test_las_mesas_excluyen_anulados_y_atenciones_cerradas_del_total(): void
    {
        $usuario = $this->autenticarMozo();
        $mesa = Mesa::factory()->create(['mozo_id' => $usuario->id]);
        $libre = Mesa::factory()->create();
        $inactiva = Mesa::factory()->create(['activo' => false]);
        $atencion = Atencion::factory()->create(['mesa_id' => $mesa->id, 'mozo_id' => $usuario->id]);
        Pedido::factory()->create(['atencion_id' => $atencion->id, 'total_centimos' => 3651, 'estado' => EstadoPedido::Entregado]);
        Pedido::factory()->create(['atencion_id' => $atencion->id, 'total_centimos' => 1000, 'estado' => EstadoPedido::Anulado]);
        $anterior = Atencion::factory()->create(['mesa_id' => $mesa->id, 'mozo_id' => $usuario->id, 'fecha_cierre' => now()->subHour()]);
        Pedido::factory()->create(['atencion_id' => $anterior->id, 'total_centimos' => 9000]);
        $respuesta = $this->getJson('/api/v1/tables')->assertOk();
        $datos = collect($respuesta->json('data'))->keyBy('id');
        $this->assertSame((string) $usuario->id, $datos[(string) $mesa->id]['waiter_id']);
        $this->assertTrue($datos[(string) $mesa->id]['occupied']);
        $this->assertSame(3651, $datos[(string) $mesa->id]['total_cents']);
        $this->assertFalse($datos[(string) $libre->id]['occupied']);
        $this->assertSame(0, $datos[(string) $libre->id]['total_cents']);
        $this->assertNull($datos[(string) $libre->id]['waiter_id']);
        $this->assertArrayNotHasKey((string) $inactiva->id, $datos->all());
        $numeros = array_column($respuesta->json('data'), 'number');
        $ordenados = $numeros;
        sort($ordenados);
        $this->assertSame($ordenados, $numeros);
    }

    public function test_anular_todo_no_libera_la_mesa_hasta_cerrar_la_atencion(): void
    {
        $usuario = $this->autenticarMozo();
        $mesa = Mesa::factory()->create(['mozo_id' => $usuario->id]);
        $atencion = Atencion::factory()->create(['mesa_id' => $mesa->id, 'mozo_id' => $usuario->id]);
        Pedido::factory()->create(['atencion_id' => $atencion->id, 'estado' => EstadoPedido::Anulado]);
        $datos = collect($this->getJson('/api/v1/tables')->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($datos[(string) $mesa->id]['occupied']);
        $this->assertSame(0, $datos[(string) $mesa->id]['total_cents']);
        $atencion->update(['fecha_cierre' => now()]);
        $datos = collect($this->getJson('/api/v1/tables')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($datos[(string) $mesa->id]['occupied']);
        $this->assertSame((string) $usuario->id, $datos[(string) $mesa->id]['waiter_id']);
    }

    public function test_pedidos_solo_devuelve_los_del_mozo_y_conserva_el_detalle_historico(): void
    {
        $usuario = $this->autenticarMozo();
        $atencion = Atencion::factory()->create(['mozo_id' => $usuario->id, 'fecha_cierre' => now()]);
        $pedido = Pedido::factory()->create(['atencion_id' => $atencion->id, 'estado' => EstadoPedido::Entregado, 'total_centimos' => 3600]);
        $producto = Producto::factory()->create(['nombre' => 'Nombre nuevo de prueba', 'precio_centimos' => 9900]);
        DetallePedido::factory()->create(['pedido_id' => $pedido->id, 'producto_id' => $producto->id, 'nombre_producto' => 'Chaufa original', 'precio_centimos' => 1800, 'cantidad' => 2, 'observaciones' => 'Sin cebolla']);
        Pedido::factory()->create();
        $respuesta = $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $pedido->id)->assertJsonPath('data.0.waiter_id', (string) $usuario->id)->assertJsonPath('data.0.table_number', $atencion->mesa->numero)->assertJsonPath('data.0.status', 'delivered')->assertJsonPath('data.0.lines.0.name', 'Chaufa original')->assertJsonPath('data.0.lines.0.price_cents', 1800)->assertJsonPath('data.0.lines.0.quantity', 2)->assertJsonPath('data.0.lines.0.notes', 'Sin cebolla')->assertJsonMissingPath('data.0.clave_idempotencia')->assertJsonMissingPath('data.0.solicitud_hash');
        $this->assertStringEndsWith('Z', $respuesta->json('data.0.created_at'));
        $this->assertStringEndsWith('Z', $respuesta->json('data.0.closed_at'));
    }

    public function test_los_estados_y_el_orden_de_pedidos_coinciden_con_flutter(): void
    {
        $usuario = $this->autenticarMozo();
        $atencion = Atencion::factory()->create(['mozo_id' => $usuario->id]);
        foreach (EstadoPedido::cases() as $estado) {
            Pedido::factory()->create(['atencion_id' => $atencion->id, 'estado' => $estado]);
        }
        $respuesta = $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(5, 'data');
        $this->assertSame(['cancelled', 'delivered', 'ready', 'preparing', 'sent'], array_column($respuesta->json('data'), 'status'));
    }
}
