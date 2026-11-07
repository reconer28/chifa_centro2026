<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\Atencion;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Usuario;
use App\Support\Importe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionSoporte
{
    public function tipos(): array
    {
        return [
            'productos' => ['titulo' => 'Productos', 'modelo' => Producto::class, 'campos' => ['nombre', 'categoria_id', 'precio_centimos', 'stock', 'descripcion', 'disponible', 'destacado', 'activo', 'imagen'], 'buscar' => ['nombre']],
            'categorias' => ['titulo' => 'Categorías', 'modelo' => Categoria::class, 'campos' => ['nombre', 'activo'], 'buscar' => ['nombre']],
            'empleados' => ['titulo' => 'Empleados', 'modelo' => Empleado::class, 'campos' => ['nombres', 'apellidos', 'dni', 'telefono', 'cargo', 'activo'], 'buscar' => ['nombres', 'apellidos', 'dni']],
            'usuarios' => ['titulo' => 'Usuarios', 'modelo' => Usuario::class, 'campos' => ['usuario', 'nombre', 'password', 'rol', 'empleado_id', 'activo'], 'buscar' => ['usuario', 'nombre']],
            'mesas' => ['titulo' => 'Mesas', 'modelo' => Mesa::class, 'campos' => ['numero', 'capacidad', 'zona', 'activo'], 'buscar' => ['zona', 'numero']],
        ];
    }

    public function modelo(string $tipo): Model
    {
        $clase = $this->tipos()[$tipo]['modelo'] ?? null;
        abort_unless($clase, 404);

        return new $clase;
    }

    private function comprobarCuenta(Usuario $actor, Usuario $cuenta, array $datos): void
    {
        $rol = isset($datos['rol']) ? RolUsuario::from($datos['rol']) : $cuenta->rol;
        $activa = (bool) ($datos['activo'] ?? $cuenta->activo);
        if ($cuenta->id === $actor->id && (! $activa || $rol !== RolUsuario::Administrador)) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivar ni quitar el rol de tu propia cuenta.']);
        }
        if ((! $activa || $rol !== $cuenta->rol) && Atencion::query()->where('mozo_id', $cuenta->id)->whereNull('fecha_cierre')->exists()) {
            throw ValidationException::withMessages(['activo' => 'El mozo tiene atenciones abiertas. Debe cerrarlas primero.']);
        }
        if ($cuenta->rol === RolUsuario::Administrador && (! $activa || $rol !== RolUsuario::Administrador) && Usuario::query()->where('rol', RolUsuario::Administrador->value)->where('activo', true)->count() <= 1) {
            throw ValidationException::withMessages(['activo' => 'Debe quedar al menos un administrador activo.']);
        }
        if (! $activa || $rol !== RolUsuario::Mozo) {
            Mesa::query()->where('mozo_id', $cuenta->id)->update(['mozo_id' => null, 'fecha_turno' => null]);
        }
    }

    public function guardar(Usuario $actor, string $tipo, array $datos, ?int $id): Model
    {
        return DB::transaction(function () use ($actor, $tipo, $datos, $id): Model {
            Usuario::query()->orderBy('id')->lockForUpdate()->get();
            $registro = $id ? $this->modelo($tipo)->newQuery()->lockForUpdate()->findOrFail($id) : $this->modelo($tipo);
            if ($tipo === 'productos') {
                $datos['precio_centimos'] = Importe::aCentimos((string) $datos['precio']);
            }
            if ($tipo === 'usuarios') {
                if (empty($datos['password'])) {
                    unset($datos['password']);
                }
                if ($registro->exists) {
                    $this->comprobarCuenta($actor, $registro, $datos);
                }
            }
            if ($tipo === 'mesas' && $registro->exists && $registro->atencionActiva()->exists() && (! (bool) $datos['activo'] || (int) $datos['numero'] !== $registro->numero)) {
                throw ValidationException::withMessages(['activo' => 'No puedes desactivar ni renumerar una mesa ocupada.']);
            }
            if ($tipo === 'empleados' && $registro->exists && ! (bool) $datos['activo'] && $registro->usuario) {
                $this->comprobarCuenta($actor, $registro->usuario, ['activo' => false]);
                $registro->usuario->update(['activo' => false]);
                $registro->usuario->tokens()->delete();
            }
            $registro->fill(Arr::only($datos, $this->tipos()[$tipo]['campos']));
            $registro->save();
            if ($tipo === 'usuarios' && (isset($datos['password']) || ! $registro->activo || $registro->wasChanged('rol'))) {
                $registro->tokens()->delete();
            }
            if ($tipo === 'mesas' && ! $registro->activo) {
                $registro->update(['mozo_id' => null, 'fecha_turno' => null]);
            }
            if ($tipo === 'categorias' && $registro->wasChanged('nombre')) {
                DB::table('productos')->where('categoria_id', $registro->id)->update(['categoria' => $registro->nombre]);
            }
            if ($tipo === 'empleados' && ! $id && ($datos['crear_usuario'] ?? false)) {
                Usuario::query()->create(['usuario' => $datos['usuario'], 'nombre' => mb_substr($registro->nombres.' '.$registro->apellidos, 0, 150), 'password' => $datos['password'], 'rol' => $datos['rol'], 'empleado_id' => $registro->id, 'activo' => $registro->activo]);
            }

            return $registro;
        }, 3);
    }

    public function cambiarDisponibilidad(Usuario $actor, string $tipo, int $id): void
    {
        DB::transaction(function () use ($actor, $tipo, $id): void {
            Usuario::query()->orderBy('id')->lockForUpdate()->get();
            $registro = $this->modelo($tipo)->newQuery()->lockForUpdate()->findOrFail($id);
            $datos = $registro->only($this->tipos()[$tipo]['campos']);
            unset($datos['password']);
            $datos['activo'] = ! $registro->activo;
            if ($tipo === 'usuarios') {
                $datos['rol'] = $registro->rol->value;
            }
            if ($tipo === 'productos') {
                $datos['precio'] = $registro->precio;
            }
            $this->guardar($actor,$tipo,$datos,$id);
        }, 3);
    }
}
