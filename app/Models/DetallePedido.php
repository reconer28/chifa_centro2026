<?php

namespace App\Models;

use App\Support\Importe;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedido extends Model
{
    use HasFactory;

    protected $table = 'detalle_pedido';

    protected $fillable = ['pedido_id', 'producto_id', 'nombre_producto', 'precio_centimos', 'cantidad', 'observaciones', 'stock_descontado'];

    protected function casts(): array
    {
        return ['precio_centimos' => 'integer', 'cantidad' => 'integer', 'stock_descontado' => 'boolean'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    protected static function booted(): void
    {
        static::saving(function (DetallePedido $detalle): void {
            $detalle->setAttribute('subtotal', Importe::aDecimal($detalle->precio_centimos * $detalle->cantidad));
        });
    }
}
