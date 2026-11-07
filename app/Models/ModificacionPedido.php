<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModificacionPedido extends Model
{
    use HasFactory;

    protected $table = 'modificaciones_pedido';

    protected $fillable = ['pedido_id', 'usuario_id', 'clave', 'solicitud_hash', 'version', 'antes', 'despues', 'resumen'];

    protected $hidden = ['clave', 'solicitud_hash'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'despues' => 'array', 'version' => 'integer'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
