<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use App\Support\Importe;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = ['atencion_id', 'mozo_id', 'clave_idempotencia', 'solicitud_hash', 'estado', 'total_centimos', 'motivo_anulacion', 'administrador_anulacion_id', 'fecha_anulacion', 'estado_cobro', 'fecha_cobro', 'cobrado_por', 'version', 'version_vista_cocina', 'fecha_modificacion'];

    protected $hidden = ['clave_idempotencia', 'solicitud_hash'];

    protected function casts(): array
    {
        return ['estado' => EstadoPedido::class, 'total' => 'decimal:2', 'fecha_anulacion' => 'immutable_datetime', 'fecha_cobro' => 'immutable_datetime', 'version' => 'integer', 'version_vista_cocina' => 'integer', 'fecha_modificacion' => 'immutable_datetime'];
    }

    protected function totalCentimos(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $valor, array $atributos): int => Importe::aCentimos((string) ($atributos['total'] ?? '0.00')),
            set: fn (int $valor): array => ['total' => Importe::aDecimal($valor)],
        );
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(Atencion::class);
    }

    public function mozoUsuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mozo_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Pedido $pedido): void {
            $pedido->setAttribute('mesa', (string) $pedido->atencion->mesa->numero);
            $pedido->setAttribute('mozo', $pedido->mozoUsuario->usuario);
            $pedido->setAttribute('fecha', now());
        });
    }

    public function modificaciones(): HasMany
    {
        return $this->hasMany(ModificacionPedido::class)->orderBy('version')->orderBy('id');
    }

    public function puedeEditar(): bool
    {
        return $this->created_at !== null
            && now()->lt($this->created_at->copy()->addMinutes(config('chifa.edicion_minutos')))
            && in_array($this->estado, [EstadoPedido::Enviado, EstadoPedido::EnPreparacion], true)
            && $this->estado_cobro !== 'Pagado'
            && $this->atencion->fecha_cierre === null;
    }

    public function pendienteDeEntrega(): bool
    {
        return in_array($this->estado, [EstadoPedido::Enviado, EstadoPedido::EnPreparacion, EstadoPedido::Listo], true)
            && $this->atencion->fecha_cierre === null;
    }
}
