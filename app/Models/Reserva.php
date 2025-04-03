<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reserva extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reservas';
    protected $fillable = [
        'mesa_id',
        'cliente_id',
        'combinacion_mesa_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'num_personas',
        'estado',
        'notas',
        'origen'
    ];

    protected $casts = [
        'id' => 'integer',
        'mesa_id' => 'integer',
        'cliente_id' => 'integer',
        'combinacion_mesa_id' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'string',
        'hora_fin' => 'string',
        'num_personas' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function combinacionMesa()
    {
        return $this->belongsTo(CombinacionMesa::class, 'combinacion_mesa_id');
    }
} 