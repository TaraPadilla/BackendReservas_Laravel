<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sede extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sedes';
    protected $fillable = [
        'restaurante_id',
        'nombre',
        'direccion',
        'ciudad',
        'activo'
    ];

    protected $casts = [
        'id' => 'integer',
        'restaurante_id' => 'integer',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function restaurante()
    {
        return $this->belongsTo(Restaurante::class, 'restaurante_id');
    }

    public function mesas()
    {
        return $this->hasMany(Mesa::class, 'sede_id');
    }

    public function combinacionesMesas()
    {
        return $this->hasMany(CombinacionMesa::class, 'sede_id');
    }
} 