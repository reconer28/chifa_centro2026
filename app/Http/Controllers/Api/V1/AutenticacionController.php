<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\IniciarSesionRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AutenticacionController extends Controller
{
    public function iniciarSesion(IniciarSesionRequest $solicitud): JsonResponse
    {
        $datos = $solicitud->validated();
        $usuario = Usuario::query()->where('usuario', $datos['username'])->first();
        if (! $usuario || ! $usuario->comprobarContrasena($datos['password']) || ! $usuario->activo) {
            abort(401, 'Usuario o contraseña incorrectos.');
        }
        if ($usuario->rol !== RolUsuario::Mozo) {
            abort(403, 'El acceso de la aplicación está reservado para mozos.');
        }
        $token = $usuario->createToken($datos['device_name'], ['app:mozo'], now()->addHours(config('chifa.duracion_token_horas')));

        return response()->json(['data' => ['token' => $token->plainTextToken, 'user' => new UsuarioResource($usuario)]]);
    }

    public function cerrarSesion(Request $solicitud): Response
    {
        $solicitud->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
