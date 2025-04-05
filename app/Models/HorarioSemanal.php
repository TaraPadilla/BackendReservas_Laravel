<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioSemanal extends Model
{
    use HasFactory;

    protected $table = 'horarios_semanales';
    
    protected $fillable = [
        'day_of_week',
        'is_closed',
        'lunch_start',
        'lunch_end',
        'dinner_start',
        'dinner_end'
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
        'lunch_start' => 'datetime:H:i',
        'lunch_end' => 'datetime:H:i',
        'dinner_start' => 'datetime:H:i',
        'dinner_end' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'is_closed' => false
    ];

    /**
     * Obtiene el nombre del día de la semana
     */
    public function getDayNameAttribute()
    {
        $days = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];
        
        return $days[$this->day_of_week] ?? 'Desconocido';
    }

    /**
     * Verifica si el horario es válido según las restricciones
     */
    public function isValid()
    {
        // Verificar que el día de la semana esté entre 0 y 6
        if ($this->day_of_week < 0 || $this->day_of_week > 6) {
            return false;
        }

        // Si está cerrado, todos los horarios deben ser null
        if ($this->is_closed) {
            return $this->lunch_start === null && 
                   $this->lunch_end === null && 
                   $this->dinner_start === null && 
                   $this->dinner_end === null;
        }

        // Si no está cerrado, al menos un turno debe estar definido
        $lunchDefined = $this->lunch_start !== null && $this->lunch_end !== null;
        $dinnerDefined = $this->dinner_start !== null && $this->dinner_end !== null;

        return $lunchDefined || $dinnerDefined;
    }
} 