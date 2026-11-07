<?php

namespace App\Services;

use App\Events\PedidosActualizados;
use App\Models\ModificacionPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class EdicionPedidos
{
    public function __construct(private GestionPedidos $gestion) {}

    public function editar(Usuario $usuario, int $id, array $datos, string $clave): Pedido
    {
        $lineas = array_map(fn (array $linea): array => ['product_id' => (int) $linea['product_id'], 'quantity' => (int) $linea['quantity'], 'notes' => trim($linea['notes'] ?? '')], $datos['items']);
        $huella = hash('sha256', json_encode(['pedido_id' => $id, 'expected_version' => (int) $datos['expected_version'], 'items' => $lineas], JSON_THROW_ON_ERROR));
        $pedido = DB::transaction(function () use ($usuario, $id, $datos, $clave, $lineas, $huella): Pedido {
            $pedido = $this->gestion->bloquearPedido($usuario, $id);
            $recibo = ModificacionPedido::query()->where('usuario_id', $usuario->id)->where('clave', $clave)->lockForUpdate()->first();
            if ($recibo) {
                abort_unless($recibo->pedido_id === $id && hash_equals($recibo->solicitud_hash, $huella), 409, 'La clave de edición ya corresponde a otro contenido.');

                return $pedido;
            }
            abort_unless($pedido->puedeEditar(), 422, 'La edición solo está disponible durante los primeros 5 minutos, antes de que el pedido esté listo o entregado.');
            abort_unless($pedido->version === (int) $datos['expected_version'], 409, 'El pedido cambió. Actualiza su detalle antes de editar.');
            $anteriores = $pedido->detalles()->orderBy('producto_id')->get()->keyBy('producto_id');
            $antes = $anteriores->map(fn ($detalle): array => ['product_id' => $detalle->producto_id, 'name' => $detalle->nombre_producto, 'price_cents' => $detalle->precio_centimos, 'quantity' => $detalle->cantidad, 'notes' => $detalle->observaciones])->values()->all();
            $ids = array_unique([...$anteriores->keys()->all(), ...array_column($lineas, 'product_id')]);
            sort($ids);
            $productos = Producto::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->with('categoriaProducto')->get()->keyBy('id');
            $nuevos = collect($lineas)->keyBy('product_id');
            $despues = [];
            $ajustesStock = [];
            $total = 0;
            foreach ($ids as $idProducto) {
                $producto = $productos->get($idProducto);
                abort_unless($producto, 422, 'Un producto ya no está disponible.');
                $anterior = $anteriores->get($idProducto);
                $nueva = $nuevos->get($idProducto);
                $cantidad = $nueva['quantity'] ?? 0;
                $reservada = $anterior && $anterior->stock_descontado ? $anterior->cantidad : 0;
                $delta = $producto->stock !== null ? $cantidad - $reservada : 0;
                if ($cantidad > ($anterior?->cantidad ?? 0)) {
                    abort_unless($producto->estaDisponible() && $producto->categoriaProducto?->activo, 422, 'No se puede agregar más de '.$producto->nombre.' porque no está disponible.');
                }
                abort_if($delta > 0 && $producto->stock < $delta, 422, 'No hay unidades suficientes de '.$producto->nombre.'.');
                if ($producto->stock !== null && $delta !== 0) {
                    abort_if($producto->stock - $delta > 2147483647, 422, 'Revisa el stock de '.$producto->nombre.' antes de devolver unidades.');
                    $ajustesStock[$idProducto] = $producto->stock - $delta;
                }
                if ($nueva) {
                    $precio = $anterior?->precio_centimos ?? $producto->precio_centimos;
                    $nombre = $anterior?->nombre_producto ?? $producto->nombre;
                    $despues[] = ['product_id' => $idProducto, 'name' => $nombre, 'price_cents' => $precio, 'quantity' => $cantidad, 'notes' => $nueva['notes']];
                    $total += $precio * $cantidad;
                }
            }
            abort_if($total > 9999999999, 422, 'El total supera el importe permitido.');
            $cambio = $antes !== $despues;
            if ($cambio) {
                foreach ($ajustesStock as $idProducto => $stock) {
                    $productos[$idProducto]->update(['stock' => $stock]);
                }
                $pedido->detalles()->whereNotIn('producto_id', array_column($despues, 'product_id'))->delete();
                foreach ($despues as $detalle) {
                    $pedido->detalles()->updateOrCreate(['producto_id' => $detalle['product_id']], ['nombre_producto' => $detalle['name'], 'precio_centimos' => $detalle['price_cents'], 'cantidad' => $detalle['quantity'], 'observaciones' => $detalle['notes'], 'stock_descontado' => $productos[$detalle['product_id']]->stock !== null]);
                }
                $pedido->update(['version' => $pedido->version + 1, 'fecha_modificacion' => now(), 'total_centimos' => $total]);
            }
            ModificacionPedido::query()->create(['pedido_id' => $id, 'usuario_id' => $usuario->id, 'clave' => $clave, 'solicitud_hash' => $huella, 'version' => $pedido->version, 'antes' => $antes, 'despues' => $despues, 'resumen' => $this->describir($antes, $despues)]);
            if ($cambio) {
                PedidosActualizados::dispatch('modificado', $pedido->id, $pedido->atencion_id, $pedido->version);
            }

            return $pedido;
        }, 3);

        return $pedido->load(['atencion.mesa', 'detalles']);
    }

    private function describir(array $antes, array $despues): string
    {
        $anteriores = collect($antes)->keyBy('product_id');
        $actuales = collect($despues)->keyBy('product_id');
        $mensajes = [];
        foreach ($anteriores as $id => $anterior) {
            $actual = $actuales->get($id);
            if (! $actual) {
                $mensajes[] = 'Retirado: '.$anterior['name'].' ('.$anterior['quantity'].' unidades).';

                continue;
            }
            if ($anterior['quantity'] !== $actual['quantity']) {
                $mensajes[] = $anterior['name'].': cantidad '.$anterior['quantity'].' → '.$actual['quantity'].'.';
            }
            if ($anterior['notes'] !== $actual['notes']) {
                $mensajes[] = $anterior['name'].': observación «'.($anterior['notes'] ?: 'Sin observación').'» → «'.($actual['notes'] ?: 'Sin observación').'».';
            }
        }
        foreach ($actuales as $id => $actual) {
            if (! $anteriores->has($id)) {
                $mensajes[] = 'Agregado: '.$actual['quantity'].' × '.$actual['name'].'.';
            }
        }

        return implode(' ',$mensajes) ?: 'Sin cambios en los platos.';
    }
}
