<?php

namespace App\Enums;

enum EstadoPedido: string
{
    case Enviado = 'sent';
    case EnPreparacion = 'preparing';
    case Listo = 'ready';
    case Entregado = 'delivered';
    case Anulado = 'cancelled';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Enviado => 'Enviado', self::EnPreparacion => 'En preparación', self::Listo => 'Listo', self::Entregado => 'Entregado', self::Anulado => 'Anulado'
        };
    }
}
