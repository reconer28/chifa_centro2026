<?php

namespace App\Services;

use App\Enums\EstadoPedido;
use App\Enums\RolUsuario;
use App\Models\Atencion;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class GestionPedidos
{
    public function __construct(private AutorizacionAdministrador $autorizacion) {}

    private function bloquearMozo(Usuario $usuario): Usuario
    {
        $actual = Usuario::query()->lockForUpdate()->findOrFail($usuario->id);
        abort_unless($actual->activo && $actual->rol === RolUsuario::Mozo, 401, 'Tu acceso de mozo ya no está disponible.');

        return $actual;
    }

    public function asignarMesas(Usuario $usuario, array $ids, string $contrasena): void
    {
        $administrador = $this->autorizacion->comprobar($usuario, $contrasena, 'asignar_mesas');
        DB::transaction(function () use ($usuario, $ids, $administrador): void {
            $this->bloquearMozo($usuario);
            $mesas = Mesa::query()->orderBy('id')->lockForUpdate()->get();
            $seleccion = array_map('intval', $ids);
            foreach ($mesas as $mesa) {
                $elegida = in_array($mesa->id, $seleccion, true);
                $propia = $mesa->mozo_id === $usuario->id;
                $abierta = $mesa->atencionActiva()->exists();
                if ($elegida) {
                    abort_unless($mesa->activo, 409, 'Una de las mesas ya no está disponible.');
                    abort_if(! $propia && ($abierta || ($mesa->mozo_id !== null && $mesa->asignacionVigente())), 409, 'Una mesa está asignada a otro mozo. Actualiza la lista.');
                    $mesa->update(['mozo_id' => $usuario->id, 'fecha_turno' => now('America/Lima')->toDateString()]);
                } elseif ($propia) {
                    abort_if($abierta, 409, 'No puedes quitar una mesa con atención abierta.');
                    $mesa->update(['mozo_id' => null, 'fecha_turno' => null]);
                }
            }
            $this->autorizacion->registrar($usuario, $administrador, 'asignar_mesas', implode(',', $seleccion));
        }, 3);
    }

    public function enviar(Usuario $usuario, array $datos, string $clave): Pedido
    {
        $cuerpo = ['table_id' => (int) $datos['table_id'], 'items' => array_map(fn (array $linea): array => ['product_id' => (int) $linea['product_id'], 'quantity' => (int) $linea['quantity'], 'notes' => trim($linea['notes'] ?? '')], $datos['items'])];
        $huella = hash('sha256', json_encode($cuerpo, JSON_THROW_ON_ERROR));
        $pedido = DB::transaction(function () use ($usuario, $cuerpo, $clave, $huella): Pedido {
            $this->bloquearMozo($usuario);
            $anterior = Pedido::query()->where('mozo_id', $usuario->id)->where('clave_idempotencia', $clave)->lockForUpdate()->first();
            if ($anterior) {
                abort_unless(hash_equals($anterior->solicitud_hash, $huella), 409, 'La clave del pedido ya se utilizó con otro contenido.');

                return $anterior;
            }
            $mesa = Mesa::query()->lockForUpdate()->findOrFail($cuerpo['table_id']);
            abort_unless($mesa->activo && $mesa->mozo_id === $usuario->id && $mesa->asignacionVigente(), 403, 'La mesa no está asignada a tu turno.');
            $atencion = $mesa->atencionActiva()->first();
            abort_if($atencion && $atencion->mozo_id !== $usuario->id, 409, 'La mesa tiene una atención de otro mozo.');
            $productos = Producto::query()->whereIn('id', array_column($cuerpo['items'], 'product_id'))->orderBy('id')->lockForUpdate()->with('categoriaProducto')->get()->keyBy('id');
            $total = 0;
            foreach ($cuerpo['items'] as $linea) {
                $producto = $productos->get($linea['product_id']);
                abort_unless($producto && $producto->estaDisponible() && $producto->categoriaProducto?->activo, 422, 'Un producto ya no está disponible. Actualiza la carta.');
                abort_if($producto->stock !== null && $producto->stock < $linea['quantity'], 422, 'No hay cantidad suficiente de '.$producto->nombre.'.');
                $total += $producto->precio_centimos * $linea['quantity'];
            }
            abort_if($total > 9999999999, 422, 'El total supera el importe permitido.');
            $atencion ??= Atencion::query()->create(['mesa_id' => $mesa->id, 'mozo_id' => $usuario->id, 'fecha_apertura' => now()]);
            $nuevo = Pedido::query()->create(['atencion_id' => $atencion->id, 'mozo_id' => $usuario->id, 'clave_idempotencia' => $clave, 'solicitud_hash' => $huella, 'estado' => EstadoPedido::Enviado, 'total_centimos' => $total]);
            foreach ($cuerpo['items'] as $linea) {
                $producto = $productos[$linea['product_id']];
                $descontar = $producto->stock !== null;
                $nuevo->detalles()->create(['producto_id' => $producto->id, 'nombre_producto' => $producto->nombre, 'precio_centimos' => $producto->precio_centimos, 'cantidad' => $linea['quantity'], 'observaciones' => $linea['notes'], 'stock_descontado' => $descontar]);
                if ($descontar) {
                    $producto->update(['stock' => $producto->stock - $linea['quantity']]);
                }
            }

            return $nuevo;
        }, 3);

        return $pedido->load(['atencion.mesa', 'detalles']);
    }

    private function bloquearPedido(Usuario $usuario, int $id): Pedido
    {
        $this->bloquearMozo($usuario);
        $referencia = Pedido::query()->with('atencion')->findOrFail($id);
        abort_unless($referencia->mozo_id === $usuario->id, 403, 'Este pedido pertenece a otro mozo.');
        Mesa::query()->whereKey($referencia->atencion->mesa_id)->lockForUpdate()->firstOrFail();

        return Pedido::query()->with('atencion')->lockForUpdate()->findOrFail($id);
    }

    public function entregar(Usuario $usuario, int $id): void
    {
        DB::transaction(function () use ($usuario, $id): void {
            $pedido = $this->bloquearPedido($usuario, $id);
            if ($pedido->estado === EstadoPedido::Entregado) {
                return;
            }
            abort_if($pedido->atencion->fecha_cierre || $pedido->estado === EstadoPedido::Anulado, 409, 'Este pedido ya no puede marcarse como entregado.');
            $pedido->update(['estado' => EstadoPedido::Entregado]);
        }, 3);
    }

    public function anular(Usuario $usuario, int $id, string $motivo, string $contrasena): void
    {
        $administrador = $this->autorizacion->comprobar($usuario, $contrasena, 'anular_pedido');
        DB::transaction(function () use ($usuario, $id, $motivo, $administrador): void {
            $pedido = $this->bloquearPedido($usuario, $id);
            if ($pedido->estado === EstadoPedido::Anulado) {
                abort_unless($pedido->motivo_anulacion === $motivo, 409, 'El pedido ya fue anulado con otro motivo.');

                return;
            }
            abort_unless($pedido->estado === EstadoPedido::Enviado && ! $pedido->atencion->fecha_cierre, 409, 'Solo puedes anular pedidos que cocina todavía no ha empezado.');
            $detalles = $pedido->detalles()->orderBy('producto_id')->get();
            foreach ($detalles as $detalle) {
                $producto = Producto::query()->lockForUpdate()->findOrFail($detalle->producto_id);
                if ($detalle->stock_descontado && $producto->stock !== null) {
                    $producto->update(['stock' => $producto->stock + $detalle->cantidad]);
                }
            }
            $pedido->update(['estado' => EstadoPedido::Anulado, 'motivo_anulacion' => $motivo, 'administrador_anulacion_id' => $administrador->id, 'fecha_anulacion' => now()]);
            $this->autorizacion->registrar($usuario, $administrador, 'anular_pedido', (string) $id);
        }, 3);
    }

    public function cerrar(Usuario $usuario, int $idMesa, array $ids): void
    {
        DB::transaction(function () use ($usuario, $idMesa, $ids): void {
            $this->bloquearMozo($usuario);
            $mesa = Mesa::query()->lockForUpdate()->findOrFail($idMesa);
            $esperados = Pedido::query()->with('atencion')->whereIn('id', $ids)->lockForUpdate()->get();
            abort_if($esperados->count() !== count($ids) || $esperados->contains(fn (Pedido $pedido): bool => $pedido->mozo_id !== $usuario->id || $pedido->atencion->mesa_id !== $mesa->id), 409, 'Actualiza los pedidos de la mesa antes de cerrarla.');
            if ($esperados->every(fn (Pedido $pedido): bool => $pedido->atencion->fecha_cierre !== null)) {
                return;
            }
            abort_unless($mesa->mozo_id === $usuario->id, 403, 'La mesa no te corresponde.');
            $atencion = $mesa->atencionActiva()->first();
            abort_unless($atencion, 409, 'La atención de la mesa cambió.');
            $actuales = $atencion->pedidos()->orderBy('id')->lockForUpdate()->get();
            $solicitados = array_map('intval', $ids);
            sort($solicitados);
            abort_unless($actuales->modelKeys() === $solicitados, 409, 'Los pedidos cambiaron. Actualiza la mesa antes de cerrar.');
            abort_if($actuales->contains(fn (Pedido $pedido): bool => ! in_array($pedido->estado, [EstadoPedido::Entregado, EstadoPedido::Anulado], true)), 409, 'Primero entrega todos los pedidos pendientes.');
            $atencion->update(['fecha_cierre' => now()]);
        }, 3);
    }

    public function avanzarCocina(int $id, EstadoPedido $estado): void
    {
        DB::transaction(function () use ($id, $estado): void {
            $pedido = Pedido::query()->with('atencion')->lockForUpdate()->findOrFail($id);
            if ($pedido->estado === $estado) {
                return;
            }
            $anterior = $estado === EstadoPedido::EnPreparacion ? EstadoPedido::Enviado : EstadoPedido::EnPreparacion;
            abort_unless(! $pedido->atencion->fecha_cierre && $pedido->estado === $anterior, 409, 'El pedido cambió de estado. Actualiza el tablero.');
            $pedido->update(['estado' => $estado]);
        }, 3);
    }

    public function cobrar(Usuario $usuario, int $id): void
    {
        DB::transaction(function () use ($usuario, $id): void {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($id);
            if ($pedido->estado_cobro === 'Pagado') {
                return;
            }
            abort_unless($pedido->estado === EstadoPedido::Entregado,409,'Solo puedes cobrar pedidos entregados.');
            $pedido->update(['estado_cobro' => 'Pagado', 'fecha_cobro' => now(), 'cobrado_por' => $usuario->id]);
        }, 3);
    }
}
