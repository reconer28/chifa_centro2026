<?php

namespace Tests\Feature;

use App\Enums\EstadoPedido;
use App\Events\PedidosActualizados;
use App\Models\Mesa;
use App\Models\ModificacionPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EdicionPedidosTest extends TestCase
{
    private function preparar(): array
    {
        Event::fake([PedidosActualizados::class]);
        $mozo = Usuario::factory()->create();
        $mesa = Mesa::factory()->create(['mozo_id' => $mozo->id]);
        $producto = Producto::factory()->create(['stock' => 10, 'precio_centimos' => 1851]);
        $this->withToken($mozo->createToken('Android de prueba')->plainTextToken);
        $datos = ['table_id' => (string) $mesa->id, 'items' => [['product_id' => (string) $producto->id, 'quantity' => 2, 'notes' => 'Sin cebolla']]];
        $id = $this->postJson('/api/v1/orders', $datos, ['Idempotency-Key' => 'crear_edicion_prueba'])->assertSuccessful()->json('data.id');

        return [$mozo, $mesa, $producto, Pedido::query()->findOrFail($id)];
    }

    private function editar(Pedido $pedido, array $lineas, string $clave = 'editar_prueba_001', ?int $version = null): TestResponse
    {
        return $this->putJson('/api/v1/orders/'.$pedido->id, ['items' => $lineas, 'expected_version' => $version ?? $pedido->version], ['Idempotency-Key' => $clave]);
    }

    public function test_edita_cantidad_y_notas_sin_cambiar_el_precio_original_y_reintenta_sin_duplicar(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $producto->update(['precio_centimos' => 9999]);
        $lineas = [['product_id' => $producto->id, 'quantity' => 3, 'notes' => 'Sin sal']];
        $this->editar($pedido, $lineas)->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.lines.0.price_cents', 1851)->assertJsonPath('data.lines.0.quantity', 3)->assertJsonPath('data.can_edit', true);
        $this->assertSame(7, $producto->fresh()->stock);
        $this->assertSame(5553, $pedido->fresh()->total_centimos);
        $this->travel(6)->minutes();
        $this->editar($pedido, $lineas)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame(7, $producto->fresh()->stock);
        $this->assertSame(1, ModificacionPedido::query()->where('pedido_id', $pedido->id)->count());
        Event::assertDispatchedTimes(PedidosActualizados::class, 2);
    }

    public function test_el_limite_de_edicion_es_exactamente_cinco_minutos(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $lineas = [['product_id' => $producto->id, 'quantity' => 1, 'notes' => '']];
        $this->travelTo($pedido->created_at->copy()->addMinutes(5));
        $this->editar($pedido, $lineas)->assertUnprocessable();
        $this->assertSame(8, $producto->fresh()->stock);
        $this->travelTo($pedido->created_at->copy()->addSeconds(299));
        $this->editar($pedido, $lineas)->assertOk();
        $this->assertSame(9, $producto->fresh()->stock);
    }

    public function test_se_pueden_agregar_adicionales_despues_de_cinco_minutos(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $this->postJson('/api/v1/orders/'.$pedido->id.'/deliver')->assertNoContent();
        $this->travel(20)->minutes();
        $this->postJson('/api/v1/orders', ['table_id' => $mesa->id, 'items' => [['product_id' => $producto->id, 'quantity' => 1, 'notes' => 'Adicional']]], ['Idempotency-Key' => 'adicional_despues_5min'])->assertSuccessful()->assertJsonPath('data.attention_id', (string) $pedido->atencion_id)->assertJsonPath('data.status', 'sent');
        $this->assertSame(EstadoPedido::Entregado, $pedido->fresh()->estado);
    }

    public function test_agregar_y_retirar_productos_recalcula_stock_total_y_guarda_ambos_detalles(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $nuevo = Producto::factory()->create(['stock' => 5, 'precio_centimos' => 1001]);
        $this->editar($pedido, [['product_id' => $nuevo->id, 'quantity' => 2, 'notes' => 'Nueva entrada']])->assertOk();
        $this->assertSame(10, $producto->fresh()->stock);
        $this->assertSame(3, $nuevo->fresh()->stock);
        $this->assertSame(2002, $pedido->fresh()->total_centimos);
        $cambio = $pedido->modificaciones()->firstOrFail();
        $this->assertSame($producto->id, $cambio->antes[0]['product_id']);
        $this->assertSame($nuevo->id, $cambio->despues[0]['product_id']);
        $this->assertStringContainsString('Retirado', $cambio->resumen);
        $this->assertStringContainsString('Agregado', $cambio->resumen);
    }

    public function test_sin_stock_no_se_aplican_cambios_parciales_ni_se_publica_un_evento(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $otro = Producto::factory()->create(['stock' => 0]);
        $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 1], ['product_id' => $otro->id, 'quantity' => 2]])->assertUnprocessable();
        $this->assertSame(8, $producto->fresh()->stock);
        $this->assertSame(2, $pedido->detalles()->firstOrFail()->cantidad);
        $this->assertSame(1, $pedido->fresh()->version);
        Event::assertDispatchedTimes(PedidosActualizados::class, 1);
    }

    public function test_version_antigua_o_clave_reutilizada_no_sobrescriben_la_edicion(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $lineas = [['product_id' => $producto->id, 'quantity' => 1]];
        $this->editar($pedido, $lineas)->assertOk();
        $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 3]])->assertConflict();
        $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 4]], 'otra_clave_edicion', 1)->assertConflict();
        $this->assertSame(9, $producto->fresh()->stock);
    }

    public function test_no_se_edita_lo_que_ya_esta_listo_entregado_o_anulado(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        foreach ([EstadoPedido::Listo, EstadoPedido::Entregado, EstadoPedido::Anulado] as $estado) {
            $pedido->update(['estado' => $estado]);
            $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 1]])->assertUnprocessable();
        }
        $pedido->update(['estado' => EstadoPedido::EnPreparacion]);
        $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 1]])->assertOk();
        $this->assertSame(EstadoPedido::EnPreparacion, $pedido->fresh()->estado);
    }

    public function test_otro_mozo_no_edita_y_una_edicion_sin_cambios_no_crea_avisos(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 2, 'notes' => 'Sin cebolla']])->assertOk()->assertJsonPath('data.version', 1);
        Event::assertDispatchedTimes(PedidosActualizados::class, 1);
        $otro = Usuario::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->withToken($otro->createToken('Otro Android')->plainTextToken);
        $this->editar($pedido, [['product_id' => $producto->id, 'quantity' => 1]])->assertForbidden();
    }

    public function test_validacion_exige_version_clave_y_cantidades_validas(): void
    {
        [$mozo,$mesa,$producto,$pedido] = $this->preparar();
        $this->putJson('/api/v1/orders/'.$pedido->id,['items' => [['product_id' => $producto->id, 'quantity' => 100]]])->assertUnprocessable()->assertJsonValidationErrors(['expected_version', 'clave_idempotencia', 'items.0.quantity']);
    }
}
