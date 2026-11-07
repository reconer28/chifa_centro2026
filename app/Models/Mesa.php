<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mesa extends Model
{
    use HasFactory;

    protected $table = 'mesas';

    protected $fillable = ['numero', 'capacidad', 'zona', 'mozo_id', 'activo', 'fecha_turno'];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'capacidad' => 'integer', 'activo' => 'boolean', 'fecha_turno' => 'immutable_date'];
    }

    public function mozo(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'mozo_id');
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class);
    }

    public function atencionActiva(): HasOne
    {
        return $this->hasOne(Atencion::class)->whereNull('fecha_cierre');
    }

    public function asignacionVigente(): bool
    {
        return $this->fecha_turno === null || $this->fecha_turno->format('Y-m-d') === now('America/Lima')->toDateString() || $this->atencionActiva()->exists();
    }
}
