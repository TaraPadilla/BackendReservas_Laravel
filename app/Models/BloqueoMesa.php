<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BloqueoMesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bloqueos_mesas';

    protected $fillable = [
        'start_date',
        'end_date',
        'is_full_day',
        'start_time',
        'end_time',
        'reason',
        'affected_tables',
        'specific_tables'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_full_day' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'specific_tables' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'is_full_day' => true,
        'affected_tables' => 'all'
    ];

    /**
     * Relación opcional con mesas específicas
     * Solo se debe utilizar cuando affected_tables = 'specific'
     */
    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(
            Mesa::class,
            'bloqueo_mesa_mesa',
            'bloqueo_id',
            'mesa_id'
        );
    }
}
