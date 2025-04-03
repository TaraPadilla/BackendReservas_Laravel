<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HorarioMesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'horarios_mesas';
    protected $fillable = [
        'mesa_id',
        'tipo_turno',
        'hora'
    ];

    protected $casts = [
        'id' => 'integer',
        'mesa_id' => 'integer',
        'hora' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function mesa()
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }
} 