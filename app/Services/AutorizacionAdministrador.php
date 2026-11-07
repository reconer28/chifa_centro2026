<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\RegistroAutorizacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\RateLimiter;

class AutorizacionAdministrador
{
    public function comprobar(Usuario $usuario, string $contrasena, string $accion): Usuario
    {
        $clave = 'autorizacion:'.$usuario->id;
        abort_if(RateLimiter::tooManyAttempts($clave, 5), 429, 'Demasiados intentos de autorización. Espera un minuto.');
        $administrador = Usuario::query()->where('rol', RolUsuario::Administrador->value)->where('activo', true)->get()->first(fn (Usuario $registro): bool => $registro->comprobarContrasena($contrasena));
        if (! $administrador) {
            RateLimiter::hit($clave, 60);
            $this->registrar($usuario, null, $accion, null, false);
            abort(403, 'La clave de administrador es incorrecta.');
        }
        RateLimiter::clear($clave);

        return $administrador;
    }

    public function registrar(Usuario $usuario, ?Usuario $administrador, string $accion, ?string $referencia, bool $autorizado = true): void
    {
        RegistroAutorizacion::query()->create(['usuario_id' => $usuario->id, 'administrador_id' => $administrador?->id, 'accion' => $accion, 'referencia' => $referencia, 'autorizado' => $autorizado, 'ip' => request()->ip()]);
    }
}
