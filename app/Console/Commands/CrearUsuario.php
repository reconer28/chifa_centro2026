<?php

namespace App\Console\Commands;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrearUsuario extends Command
{
    protected $signature = 'chifa:crear-usuario {usuario} {--rol=administrador} {--nombre=}';

    protected $description = 'Crea un usuario inicial de Chifa Centro sin mostrar su contraseña';

    public function handle(): int
    {
        $datos = ['usuario' => $this->argument('usuario'), 'rol' => RolUsuario::desdeTexto((string) $this->option('rol'))?->value, 'nombre' => $this->option('nombre') ?: $this->argument('usuario')];
        $validacion = Validator::make($datos, ['usuario' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:usuarios,usuario'], 'nombre' => ['required', 'string', 'max:150'], 'rol' => ['required', Rule::enum(RolUsuario::class)]], ['unique' => 'Ese usuario ya existe.', 'regex' => 'El usuario solo admite letras sin tildes, números, puntos, guiones y guion bajo.', 'enum' => 'El rol debe ser administrador, cocina o mozo.']);
        if ($validacion->fails()) {
            $this->error($validacion->errors()->first());

            return self::FAILURE;
        }
        $contrasena = $this->secret('Contraseña del nuevo usuario');
        if (! is_string($contrasena) || mb_strlen($contrasena) < 6 || mb_strlen($contrasena) > 255) {
            $this->error('La contraseña debe tener entre 6 y 255 caracteres.');

            return self::FAILURE;
        }
        if ($contrasena !== $this->secret('Repite la contraseña')) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }
        Usuario::query()->create([...$datos, 'password' => $contrasena, 'activo' => true]);
        $this->info('Usuario creado correctamente.');

        return self::SUCCESS;
    }
}
