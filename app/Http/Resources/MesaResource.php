<?php

namespace App\Http\Resources;

use App\Support\Importe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MesaResource extends JsonResource
{
    public function toArray(Request $solicitud): array
    {
        return ['id' => (string) $this->id, 'number' => $this->numero, 'seats' => $this->capacidad, 'zone' => $this->zona, 'waiter_id' => ($this->mozo_id === null || ! $this->resource->asignacionVigente()) ? null : (string) $this->mozo_id, 'occupied' => $this->atencionActiva !== null, 'total_cents' => Importe::aCentimos((string) ($this->atencionActiva?->total_atencion ?? '0.00'))];
    }
}
