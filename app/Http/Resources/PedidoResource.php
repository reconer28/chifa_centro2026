<?php

namespace App\Http\Resources;

use App\Models\DetallePedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => (string) $this->id, 'table_id' => (string) $this->atencion->mesa_id, 'table_number' => $this->atencion->mesa->numero, 'waiter_id' => (string) $this->mozo_id,
            'created_at' => $this->created_at->toISOString(), 'closed_at' => $this->atencion->fecha_cierre?->toISOString(), 'status' => $this->estado->value, 'cancellation_reason' => $this->motivo_anulacion,
            'lines' => $this->detalles->map(fn (DetallePedido $detalle): array => ['product_id' => (string) $detalle->producto_id, 'name' => $detalle->nombre_producto, 'price_cents' => $detalle->precio_centimos, 'quantity' => $detalle->cantidad, 'notes' => $detalle->observaciones])->all(),
        ];
    }
}
