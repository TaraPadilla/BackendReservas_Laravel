<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mesas';
    protected $fillable = [
        'sede_id',
        'numero',
        'capacidad_min',
        'capacidad_max',
        'duracion_turno_minutos',
        'ubicacion',
        'forma',
        'estado',
        'posicion_x',
        'posicion_y',
        'activa'
    ];

    protected $casts = [
        'numero' => 'integer',
        'capacidad_min' => 'integer',
        'capacidad_max' => 'integer',
        'duracion_turno_minutos' => 'integer',
        'posicion_x' => 'decimal:2',
        'posicion_y' => 'decimal:2',
        'activa' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'estado' => 'disponible',
        'ubicacion' => 'interior',
        'forma' => 'rectangulo',
        'activa' => true
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioMesa::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function combinaciones(): BelongsToMany
    {
        return $this->belongsToMany(CombinacionMesa::class, 'combinacion_mesa_mesa', 'mesa_id', 'combinacion_mesa_id');
    }
} 