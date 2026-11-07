<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    public function handle(Request $solicitud, Closure $siguiente, string ...$roles): Response
    {
        $usuario = $solicitud->user();
        if (! $usuario instanceof Usuario || ! $usuario->activo) {
            abort(401, 'Tu sesión ya no está disponible. Vuelve a iniciar sesión.');
        }
        if (! in_array($usuario->rol->value, $roles, true)) {
            abort(403, 'No tienes permiso para realizar esta operación.');
        }

        return $siguiente($solicitud);
    }
}
