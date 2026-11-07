<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Administrador = 'Administrador';
    case Cocina = 'Cocina';
    case Mozo = 'Mozo';

    public static function desdeTexto(string $texto): ?self
    {
        return match (mb_strtolower(trim($texto))) {
            'administrador' => self::Administrador,
            'cocina' => self::Cocina,
            'mozo' => self::Mozo,
            default => null,
        };
    }
}
