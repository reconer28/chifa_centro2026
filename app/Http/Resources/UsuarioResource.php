<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $solicitud): array
    {
        return ['id' => (string) $this->id, 'name' => $this->nombre, 'username' => $this->usuario];
    }
}
