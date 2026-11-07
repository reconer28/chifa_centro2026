<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Atencion extends Model
{
    use HasFactory;

    protected $table = 'atenciones';

    protected $fillable = ['mesa_id', 'mozo_id', 'fecha_apertura', 'fecha_cierre'];

    protected function casts(): array
    {
        return ['fecha_apertura' => 'immutable_datetime', 'fecha_cierre' => 'immutable_datetime'];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function mozo(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mozo_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
