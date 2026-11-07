<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $solicitud): array
    {
        return ['id' => (string) $this->id, 'name' => $this->nombre, 'description' => $this->descripcion ?? '', 'category' => $this->categoriaProducto->nombre, 'price_cents' => $this->precio_centimos, 'available' => $this->resource->estaDisponible(), 'featured' => $this->destacado];
    }
}
