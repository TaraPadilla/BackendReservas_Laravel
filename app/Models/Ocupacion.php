<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ocupacion extends Model
{
    use SoftDeletes;

    protected $table = 'ocupaciones';

    protected $fillable = [
        'reserva_id',
        'elemento_id',
        'sede_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'mesa_id',
        'estado',
        'tipo',
        'origen',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'hora_inicio' => 'datetime:H:i:s',
        'hora_fin' => 'datetime:H:i:s',
    ];

    public $timestamps = true;
}
