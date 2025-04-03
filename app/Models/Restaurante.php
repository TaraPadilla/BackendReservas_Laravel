<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurante extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'restaurantes';
    protected $fillable = [
        'id',
        'nombre',
        'logo',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function sedes()
    {
        return $this->hasMany(Sede::class, 'restaurante_id');
    }
} 