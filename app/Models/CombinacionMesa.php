<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CombinacionMesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'combinaciones_mesas';

    protected $fillable = [
        'sede_id',
        'mesa_id',
        'mesas_combinadas_ids',
        'capacidad_min',
        'capacidad_max',
        'duracion_turno_minutos',
        'es_excepcional',
        'activa'
    ];

    protected $casts = [
        'mesas_combinadas_ids' => 'array',
        'es_excepcional' => 'boolean',
        'activa' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con la sede
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con la mesa principal
     */
    public function mesaPrincipal()
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }
}
