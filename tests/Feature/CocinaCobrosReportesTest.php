<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Enums\RolUsuario;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Tests\TestCase;

class CocinaCobrosReportesTest extends TestCase
{
    public function test_cocina_recibe_observaciones_y_avanza_sin_saltar_ni_retroceder_estados(): void
    {
        $cocina = Usuario::factory()->create(['rol' => RolUsuario::Cocina]);
        $pedido = Pedido::factory()->create();
        DetallePedido::factory()->create(['pedido_id' => $pedido->id, 'observaciones' => 'Sin cebolla de prueba']);
        $this->actingAs($cocina, 'web')->get('/cocina')->assertOk()->assertSee('Sin cebolla de prueba');
        $this->get('/cocina', ['X-Tablero' => '1'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->patchJson(route('cocina.cambiar', $pedido), ['estado' => 'ready'])->assertConflict();
        $this->patch(route('cocina.cambiar', $pedido), ['estado' => 'preparing'])->assertRedirect();
        $this->assertSame(EstadoPedido::EnPreparacion, $pedido->fresh()->estado);
        $this->patch(route('cocina.cambiar', $pedido), ['estado' => 'preparing'])->assertRedirect();
        $this->patch(route('cocina.cambiar', $pedido), ['estado' => 'ready'])->assertRedirect();
        $this->assertSame(EstadoPedido::Listo, $pedido->fresh()->estado);
        $this->patchJson(route('cocina.cambiar', $pedido), ['estado' => 'preparing'])->assertConflict();
    }

    public function test_no_se_puede_anular_un_pedido_en_preparacion_y_cobrar_es_idempotente(): void
    {
        $admin = Usuario::factory()->create(['rol' => RolUsuario::Administrador, 'password' => 'adminPrueba123']);
        $pedido = Pedido::factory()->create(['estado' => EstadoPedido::EnPreparacion]);
        $mozo = Usuario::query()->findOrFail($pedido->mozo_id);
        $this->withToken($mozo->createToken('Android')->plainTextToken)->postJson('/api/v1/orders/'.$pedido->id.'/cancel', ['reason' => 'Prueba', 'admin_password' => 'adminPrueba123'])->assertConflict();
        $this->actingAs($admin, 'web')->postJson(route('admin.cobrar', $pedido))->assertConflict();
        $pedido->update(['estado' => EstadoPedido::Entregado]);
        $this->post(route('admin.cobrar', $pedido))->assertRedirect();
        $fecha = $pedido->fresh()->fecha_cobro;
        $this->post(route('admin.cobrar', $pedido))->assertRedirect();
        $this->assertSame('Pagado', $pedido->fresh()->estado_cobro);
        $this->assertTrue($fecha->equalTo($pedido->fresh()->fecha_cobro));
        $this->assertSame($admin->id, $pedido->fresh()->cobrado_por);
        $this->get(route('admin.detalle', $pedido))->assertOk()->assertSee('Resumen de atención');
    }

    public function test_reporte_solo_cuenta_entregados_con_filtros_y_precios_historicos(): void
    {
        $admin = Usuario::factory()->create(['rol' => RolUsuario::Administrador]);
        $pedido = Pedido::factory()->create(['estado' => EstadoPedido::Entregado, 'estado_cobro' => 'Pagado', 'fecha_cobro' => now(), 'total_centimos' => 3702]);
        $detalle = DetallePedido::factory()->create(['pedido_id' => $pedido->id, 'precio_centimos' => 1851, 'cantidad' => 2, 'nombre_producto' => 'Plato de reporte de prueba']);
        $anulado = Pedido::factory()->create(['mozo_id' => $pedido->mozo_id, 'estado' => EstadoPedido::Anulado]);
        DetallePedido::factory()->create(['pedido_id' => $anulado->id, 'producto_id' => $detalle->producto_id, 'cantidad' => 5]);
        $this->actingAs($admin, 'web')->get(route('admin.reportes', ['desde' => now('America/Lima')->toDateString(), 'hasta' => now('America/Lima')->toDateString(), 'mozo_id' => $pedido->mozo_id, 'producto_id' => $detalle->producto_id]))->assertOk()->assertViewHas('total', 3702)->assertViewHas('cobrados', 3702)->assertSee('Plato de reporte de prueba');
        $this->get(route('admin.reportes', ['desde' => '2026-11-10', 'hasta' => '2026-11-01']))->assertSessionHasErrors('hasta');
    }

    public function test_historial_de_cocina_escapa_el_contenido_y_no_muestra_entregados_en_curso(): void
    {
        $cocina = Usuario::factory()->create(['rol' => RolUsuario::Cocina]);
        $pedido = Pedido::factory()->create(['estado' => EstadoPedido::Entregado]);
        DetallePedido::factory()->create(['pedido_id' => $pedido->id, 'nombre_producto' => '<script>alert(1)</script>']);
        $this->actingAs($cocina, 'web')->get('/cocina')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/cocina?historial=delivered')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_flujo_completo_de_la_app_cocina_entrega_cobro_y_cierre(): void
    {
        $administrador = Usuario::factory()->create(['rol' => RolUsuario::Administrador, 'password' => 'adminFlujo123']);
        $cocinero = Usuario::factory()->create(['rol' => RolUsuario::Cocina]);
        $mozo = Usuario::factory()->create();
        $mesa = Mesa::factory()->create();
        $producto = Producto::factory()->create(['stock' => 5, 'precio_centimos' => 1851]);
        $acceso = $this->postJson('/api/v1/auth/login', ['username' => $mozo->usuario, 'password' => 'clave123', 'device_name' => 'Flutter Android de prueba'])->assertOk();
        $token = $acceso->json('data.token');
        $this->withToken($token)->putJson('/api/v1/me/tables', ['table_ids' => [$mesa->id], 'admin_password' => 'adminFlujo123'])->assertNoContent();
        $datos = ['table_id' => (string) $mesa->id, 'items' => [['product_id' => (string) $producto->id, 'quantity' => 2, 'notes' => 'Sin sal de prueba']]];
        $id = $this->postJson('/api/v1/orders', $datos, ['Idempotency-Key' => 'flujo_completo_prueba'])->assertSuccessful()->json('data.id');
        $this->actingAs($cocinero, 'web')->get('/cocina')->assertOk()->assertSee('Sin sal de prueba');
        $this->patch('/cocina/pedidos/'.$id, ['estado' => 'preparing'])->assertRedirect();
        $this->patch('/cocina/pedidos/'.$id, ['estado' => 'ready'])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/orders/'.$id.'/deliver')->assertNoContent();
        $this->actingAs($administrador, 'web')->post('/administracion/pedidos/'.$id.'/cobrar')->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/tables/'.$mesa->id.'/close', ['order_ids' => [$id]])->assertNoContent();
        $this->postJson('/api/v1/orders', $datos, ['Idempotency-Key' => 'flujo_completo_prueba'])->assertSuccessful()->assertJsonPath('data.id', $id);
        $pedido = Pedido::query()->findOrFail($id);
        $this->assertSame('Pagado', $pedido->estado_cobro);
        $this->assertNotNull($pedido->atencion->fecha_cierre);
        $this->assertSame(3702, $pedido->total_centimos);
        $this->assertSame(3, $producto->fresh()->stock);
    }

    public function test_el_reporte_usa_el_dia_de_lima_en_el_limite_de_medianoche(): void
    {
        $admin = Usuario::factory()->create(['rol' => RolUsuario::Administrador]);
        $pedido = Pedido::factory()->create(['estado' => EstadoPedido::Entregado, 'created_at' => '2026-11-07 04:30:00']);
        DetallePedido::factory()->create(['pedido_id' => $pedido->id, 'precio_centimos' => 1001, 'cantidad' => 1]);
        $this->actingAs($admin, 'web')->get(route('admin.reportes', ['desde' => '2026-11-06', 'hasta' => '2026-11-06', 'mozo_id' => $pedido->mozo_id]))->assertOk()->assertViewHas('total',1001);
        $this->get(route('admin.reportes',['desde' => '2026-11-07', 'hasta' => '2026-11-07', 'mozo_id' => $pedido->mozo_id]))->assertOk()->assertViewHas('total',0);
    }
}
