<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Http\Requests\AccesoWebRequest;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SesionController extends Controller
{
    public function destino(Usuario $usuario): string
    {
        return match ($usuario->rol) {
            RolUsuario::Administrador => route('admin.resumen'),RolUsuario::Cocina => route('cocina.index'),default => route('acceso.movil')
        };
    }

    public function inicio(Request $solicitud): RedirectResponse
    {
        return redirect($solicitud->user() ? $this->destino($solicitud->user()) : route('login'));
    }

    public function formulario(Request $solicitud): View|RedirectResponse
    {
        if ($solicitud->user()) {
            return redirect($this->destino($solicitud->user()));
        }

        return view('sesion.ingresar');
    }

    public function ingresar(AccesoWebRequest $solicitud): RedirectResponse
    {
        $datos = $solicitud->validated();
        $usuario = Usuario::query()->where('usuario', $datos['usuario'])->first();
        if (! $usuario || ! $usuario->activo || ! $usuario->comprobarContrasena($datos['password'])) {
            throw ValidationException::withMessages(['usuario' => 'Usuario o contraseña incorrectos.']);
        }
        Auth::guard('web')->login($usuario);
        $solicitud->session()->regenerate();

        return redirect($this->destino($usuario));
    }

    public function salir(Request $solicitud): RedirectResponse
    {
        Auth::guard('web')->logout();
        $solicitud->session()->invalidate();
        $solicitud->session()->regenerateToken();

        return redirect()->route('login');
    }
}
