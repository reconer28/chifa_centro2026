<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroAutorizacion extends Model
{
    use HasFactory;

    protected $table = 'registros_autorizacion';

    protected $fillable = ['usuario_id', 'administrador_id', 'accion', 'referencia', 'autorizado', 'ip'];

    protected function casts(): array
    {
        return ['autorizado' => 'boolean'];
    }
}
