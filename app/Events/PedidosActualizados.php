<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class PedidosActualizados implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public string $identificador;

    public string $fecha;

    public function __construct(public string $tipo, public ?int $idPedido = null, public ?int $idAtencion = null, public int $version = 1)
    {
        $this->identificador = (string) Str::uuid();
        $this->fecha = now()->toISOString();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('restaurante.pedidos')];
    }

    public function broadcastAs(): string
    {
        return 'pedidos.actualizados';
    }

    public function broadcastQueue(): string
    {
        return 'avisos';
    }

    public function broadcastWith(): array
    {
        return ['evento' => $this->identificador, 'tipo' => $this->tipo, 'pedido_id' => $this->idPedido, 'atencion_id' => $this->idAtencion, 'version' => $this->version, 'ocurrido_en' => $this->fecha];
    }
}
