<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\RolUsuario;
use App\Events\PedidosActualizados;
use App\Models\Atencion;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\ModificacionPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\GestionPedidos;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TiempoRealPedidosTest extends TestCase
{
    public function test_el_evento_utiliza_un_canal_privado_y_solo_contiene_identificadores(): void
    {
        $evento = new PedidosActualizados('modificado', 123, 45, 2);
        $this->assertSame('private-restaurante.pedidos', $evento->broadcastOn()[0]->name);
        $this->assertSame('pedidos.actualizados', $evento->broadcastAs());
        $this->assertSame('avisos', $evento->broadcastQueue());
        $this->assertSame(2, $evento->broadcastWith()['version']);
        $this->assertArrayNotHasKey('password', $evento->broadcastWith());
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $evento);
    }

    public function test_canal_solo_autoriza_administrador_y_cocina_activos(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        foreach ([RolUsuario::Administrador, RolUsuario::Cocina] as $rol) {
            $this->actingAs(Usuario::factory()->create(['rol' => $rol]), 'web')
                ->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-restaurante.pedidos'])
                ->assertOk()->assertJsonStructure(['auth']);
        }
        $this->actingAs(Usuario::factory()->create(), 'web')
            ->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-restaurante.pedidos'])->assertForbidden();
        $this->actingAs(Usuario::factory()->create(['rol' => RolUsuario::Cocina, 'activo' => false]), 'web')
            ->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-restaurante.pedidos'])->assertForbidden();
    }

    public function test_adicional_muestra_el_envio_entregado_atenuado_y_el_nuevo_destacado(): void
    {
        $cocina = Usuario::factory()->create(['rol' => RolUsuario::Cocina]);
        $atencion = Atencion::factory()->create();
        $entregado = Pedido::factory()->create(['atencion_id' => $atencion->id, 'estado' => EstadoPedido::Entregado]);
        DetallePedido::factory()->create(['pedido_id' => $entregado->id, 'nombre_producto' => 'Plato ya entregado de prueba']);
        $adicional = Pedido::factory()->create(['atencion_id' => $atencion->id, 'version_vista_cocina' => 0]);
        DetallePedido::factory()->create(['pedido_id' => $adicional->id, 'nombre_producto' => 'Nuevo adicional de prueba']);
        $this->actingAs($cocina, 'web')->get('/cocina')->assertOk()->assertSee('Plato ya entregado de prueba')->assertSee('Nuevo adicional de prueba')->assertSee('envio-entregado', false)->assertSee('envio-nuevo', false)->assertSee('Nuevos platos')->assertSee('data-atencion="'.$atencion->id.'"', false);
    }

    public function test_cocina_ve_el_historial_y_no_confirma_una_version_que_ya_cambio(): void
    {
        Event::fake([PedidosActualizados::class]);
        $cocina = Usuario::factory()->create(['rol' => RolUsuario::Cocina]);
        $pedido = Pedido::factory()->create(['version' => 2, 'version_vista_cocina' => 1, 'estado' => EstadoPedido::EnPreparacion]);
        DetallePedido::factory()->create(['pedido_id' => $pedido->id]);
        ModificacionPedido::factory()->create(['pedido_id' => $pedido->id, 'usuario_id' => $pedido->mozo_id, 'version' => 2, 'resumen' => 'Cantidad de chaufa 2 a 3, sin cebolla']);
        $this->actingAs($cocina, 'web')->get('/cocina')->assertOk()->assertSee('Cantidad de chaufa 2 a 3, sin cebolla')->assertSee('Detalle modificado');
        $this->postJson('/cocina/pedidos/'.$pedido->id.'/visto', ['version' => 1])->assertConflict();
        $this->patchJson('/cocina/pedidos/'.$pedido->id, ['estado' => 'ready', 'expected_version' => 1])->assertConflict();
        $this->post('/cocina/pedidos/'.$pedido->id.'/visto', ['version' => 2])->assertRedirect();
        $this->assertSame(2, $pedido->fresh()->version_vista_cocina);
        $this->get('/cocina')->assertDontSee('Cambios pendientes de revisión');
        Event::assertDispatchedTimes(PedidosActualizados::class, 1);
    }

    public function test_el_panel_carga_echo_sin_consultas_programadas_cada_cinco_segundos(): void
    {
        $usuario = Usuario::factory()->create(['rol' => RolUsuario::Cocina]);
        $this->actingAs($usuario, 'web')->get('/cocina')->assertOk()->assertSee('data-tiempo-real', false)->assertSee('Activar sonido')->assertSee('data-amarillo="12"', false)->assertSee('data-rojo="15"', false)->assertDontSee('Actualización cada 5 segundos');
        $usuario->update(['rol' => RolUsuario::Administrador]);
        $this->actingAs($usuario->fresh(), 'web')->get('/administracion')->assertOk()->assertSee('data-tiempo-real', false);
    }

    public function test_una_transaccion_revertida_no_publica_el_pedido(): void
    {
        Event::fake([PedidosActualizados::class]);
        $mozo = Usuario::factory()->create();
        $mesa = Mesa::factory()->create(['mozo_id' => $mozo->id]);
        $producto = Producto::factory()->create(['stock' => 10]);
        try {
            DB::transaction(function () use ($mozo, $mesa, $producto): void {
                app(GestionPedidos::class)->enviar($mozo, ['table_id' => $mesa->id, 'items' => [['product_id' => $producto->id, 'quantity' => 2, 'notes' => '']]], 'rollback_evento_prueba');
                throw new \RuntimeException('Revertir esta prueba');
            });
        } catch (\RuntimeException $excepcion) {
            $this->assertSame('Revertir esta prueba', $excepcion->getMessage());
        }
        Event::assertNotDispatched(PedidosActualizados::class);
        $this->assertSame(10, $producto->fresh()->stock);
        $this->assertSame(0,Pedido::query()->where('mozo_id',$mozo->id)->count());
    }
}
