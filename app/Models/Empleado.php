<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empleado extends Model
{
    use HasFactory;

    protected $table = 'empleados';

    protected $fillable = ['nombres', 'apellidos', 'dni', 'telefono', 'cargo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class);
    }
}
