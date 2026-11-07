<?php

namespace App\Models;

use App\Support\Importe;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $fillable = ['categoria_id', 'nombre', 'descripcion', 'precio_centimos', 'stock', 'imagen', 'disponible', 'destacado', 'activo'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2', 'stock' => 'integer', 'disponible' => 'boolean', 'destacado' => 'boolean', 'activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Producto $producto): void {
            if ($producto->isDirty('categoria_id')) {
                $producto->setAttribute('categoria', Categoria::query()->findOrFail($producto->categoria_id)->nombre);
            }
        });
    }

    protected function precioCentimos(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $valor, array $atributos): int => Importe::aCentimos((string) ($atributos['precio'] ?? '0.00')),
            set: fn (int $valor): array => ['precio' => Importe::aDecimal($valor)],
        );
    }

    public function categoriaProducto(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function estaDisponible(): bool
    {
        return $this->activo && $this->disponible && ($this->stock === null || $this->stock > 0);
    }
}
