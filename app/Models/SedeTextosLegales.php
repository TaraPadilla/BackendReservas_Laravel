<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SedeTextosLegales extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sede_textos_legales';

    protected $fillable = [
        'sede_id',
        'aviso_legal',
        'politica_privacidad',
        'politica_cookies',
        'texto_reserva_final',
    ];

    protected $casts = [
        'id' => 'integer',
        'sede_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
}
