<?php

namespace App\Support;

use InvalidArgumentException;

class Importe
{
    public static function aCentimos(string $importe): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $importe)) {
            throw new InvalidArgumentException('El importe debe ser positivo y tener hasta dos decimales.');
        }
        [$entero, $decimal] = array_pad(explode('.', $importe, 2), 2, '');

        return ((int) $entero * 100) + (int) str_pad($decimal, 2, '0');
    }

    public static function aDecimal(int $centimos): string
    {
        if ($centimos < 0 || $centimos > 9999999999) {
            throw new InvalidArgumentException('El importe está fuera del rango permitido.');
        }

        return intdiv($centimos, 100).'.'.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
    }
}
