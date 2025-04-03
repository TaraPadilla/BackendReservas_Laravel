<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CombinacionMesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'combinaciones_mesas';
    protected $fillable = [
        'sede_id',
        'capacidad_min',
        'capacidad_max',
        'duracion_turno_minutos',
        'mesas_ids',
        'es_excepcional',
        'activa'
    ];

    protected $casts = [
        'id' => 'integer',
        'sede_id' => 'integer',
        'capacidad_min' => 'integer',
        'capacidad_max' => 'integer',
        'duracion_turno_minutos' => 'integer',
        'mesas_ids' => 'array',
        'es_excepcional' => 'boolean',
        'activa' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'combinacion_mesa_id');
    }
} 