<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'usuarios';

    protected $fillable = ['usuario', 'nombre', 'password', 'rol', 'empleado_id', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['rol' => RolUsuario::class, 'activo' => 'boolean'];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /** La protección mediante hash se incorporará al finalizar la etapa de desarrollo. */
    public function comprobarContrasena(string $contrasena): bool
    {
        return is_string($this->password) && $this->password !== '' && hash_equals($this->password, $contrasena);
    }
}
