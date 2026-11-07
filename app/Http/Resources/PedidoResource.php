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
            'id' => (string) $this->id,
            'attention_id' => (string) $this->atencion_id,
            'version' => $this->version,
            'editable_until' => $this->created_at->copy()->addMinutes(config('chifa.edicion_minutos'))->toISOString(),
            'can_edit' => $this->resource->puedeEditar(),
            'modified_at' => $this->fecha_modificacion?->toISOString(),
            'kitchen_seen_version' => $this->version_vista_cocina,
            'table_id' => (string) $this->atencion->mesa_id, 'table_number' => $this->atencion->mesa->numero, 'waiter_id' => (string) $this->mozo_id,
            'created_at' => $this->created_at->toISOString(), 'closed_at' => $this->atencion->fecha_cierre?->toISOString(), 'status' => $this->estado->value, 'cancellation_reason' => $this->motivo_anulacion,
            'lines' => $this->detalles->map(fn (DetallePedido $detalle): array => ['product_id' => (string) $detalle->producto_id, 'name' => $detalle->nombre_producto, 'price_cents' => $detalle->precio_centimos, 'quantity' => $detalle->cantidad, 'notes' => $detalle->observaciones])->all(),
        ];
    }
}
