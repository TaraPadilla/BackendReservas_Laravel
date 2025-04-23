<?php

namespace App\Services;

use App\Models\Mesa;
use App\Models\CombinacionMesa;
use App\Models\Reserva;
use App\Traits\LogTrait;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class MesaAssignmentService
{
    use LogTrait;

    /**
     * Encuentra mesas disponibles para una reserva
     *
     * @param string $fecha
     * @param string $horaInicio
     * @param string $horaFin
     * @param int $numPersonas
     * @param int $sedeId
     * @param ?string $ubicacion
     * @return Collection
     */
    public function encontrarMesasDisponibles(
        string $fecha,
        string $horaInicio,
        int $numPersonas,
        int $sedeId,
        ?string $ubicacion = null
    ): Collection {
        try {
            $query = Mesa::where('sede_id', $sedeId)
                ->where('activa', true)
                ->where('estado', 'disponible')
                ->where('capacidad_min', '<=', $numPersonas)
                ->where('capacidad_max', '>=', $numPersonas)
                ->when($ubicacion, function ($query) use ($ubicacion) {
                    $query->where('ubicacion', $ubicacion);
                })
                ->whereHas('horarios', function ($query) use ($horaInicio) {
                    $query->where('hora', $horaInicio);
                })
                ->orderBy('capacidad_min')
                ->orderBy('capacidad_max')
                ->orderBy('id');
    
            $this->logInfo('Consulta interpolada', [
                'raw_sql' => $this->interpolateQuery($query->toSql(), $query->getBindings())
            ]);
    
            $posiblesMesas = $query->get();
    
            // Filtro adicional por solapamiento y combinaciones
            $mesasFiltradas = $posiblesMesas->filter(function ($mesa) use ($fecha, $horaInicio) {
                // Calcula hora fin según duración de la mesa
                $horaFin = Carbon::parse($horaInicio)
                    ->addMinutes($mesa->duracion_turno_minutos)
                    ->format('H:i');
    
                // Verifica que no tenga solapamientos
                $tieneSolape = $mesa->reservas()
                    ->where('fecha', $fecha)
                    ->where('estado', '!=', 'cancelada')
                    ->where(function ($q) use ($horaInicio, $horaFin) {
                        $q->where(function ($q) use ($horaInicio, $horaFin) {
                            $q->where('hora_inicio', '<', $horaFin)
                              ->where('hora_fin', '>', $horaInicio);
                        }); 
                    })
                    ->exists();
    
                // Verifica si está ocupada en una combinación
                $ocupadaEnCombinacion = $this->mesaOcupadaPorCombinacion($mesa, $fecha, $horaInicio, $horaFin);
    
                return !$tieneSolape && !$ocupadaEnCombinacion;
            });
    
            return $mesasFiltradas->values();
    
        } catch (\Exception $e) {
            $this->logError('Error al buscar mesas individuales disponibles', $e);
            throw $e;
        }
    }
    
    
    public function mesaOcupadaPorCombinacion(Mesa $mesa, string $fecha, string $horaInicio, string $horaFin): bool
    {
        $this->logInfo('Verificando si la mesa está involucrada en una combinación ya reservada', [
            'mesa_id' => $mesa->id,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin
        ]);
    
        // Armamos la query
        $query = Reserva::whereNotNull('combinacion_mesa_id')
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'cancelada')
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->where(function ($q) use ($horaInicio, $horaFin) {
                    $q->where('hora_inicio', '<', $horaFin)
                      ->where('hora_fin', '>', $horaInicio);
                });                
            })
            ->whereHas('combinacionMesa', function ($query) use ($mesa) {
                $query->where('mesa_id', $mesa->id)
                      ->orWhereJsonContains('mesas_combinadas_ids', $mesa->id);
            })
            ->whereNull('reservas.deleted_at');
    
        // 🔍 Registrar SQL interpolado
        $sqlInterpolado = $this->interpolateQuery($query->toSql(), $query->getBindings());
        $this->logInfo('Consulta interpolada', ['raw_sql' => $sqlInterpolado]);
        // ✅ Ejecutar la consulta
        return $query->exists();
    }

    private function interpolateQuery(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            // Si es una cadena, la envolvemos entre comillas
            $value = is_numeric($binding) ? $binding : "'{$binding}'";
            // Reemplaza el primer signo de interrogación por el valor
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        return $sql;
    }
    /**
     * Busca combinaciones de mesas disponibles
     *
     * @param string $fecha
     * @param string $horaInicio
     * @param string $horaFin
     * @param int $numPersonas
     * @param int $sedeId
     * @param ?string $ubicacion
     * @return Collection
     */
    public function buscarCombinacionesDisponibles(
        string $fecha,
        string $horaInicio,
        int $numPersonas,
        int $sedeId,
        ?string $ubicacion = null
    ): Collection {
        try {
            $this->logInfo('🔍 Iniciando búsqueda de combinaciones disponibles', [
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'num_personas' => $numPersonas,
                'sede_id' => $sedeId,
                'ubicacion' => $ubicacion
            ]);
    
            $query = CombinacionMesa::with('mesaPrincipal')
                ->where('sede_id', $sedeId)
                ->where('activa', true)
                ->where('capacidad_min', '<=', $numPersonas)
                ->where('capacidad_max', '>=', $numPersonas);
    
            if ($ubicacion) {
                $query->whereJsonContains('mesas_combinadas_ids', $ubicacion); // opcional
            }
    
            $this->logInfo('🧱 Consulta base de combinaciones construida', [
                'raw_sql' => $this->interpolateQuery($query->toSql(), $query->getBindings())
            ]);
    
            $combinaciones = $query->get();
    
            $this->logInfo('📦 Combinaciones candidatas obtenidas', [
                'total' => $combinaciones->count()
            ]);
    
            if ($combinaciones->isEmpty()) {
                $this->logWarning('⚠️ No se encontraron combinaciones candidatas');
                return collect();
            }
    
            $combinacionesDisponibles = $combinaciones->filter(function ($combinacion) use ($fecha, $horaInicio) {
                // Calcular la hora de fin según duración de la mesa principal
                $mesaPrincipal = $combinacion->mesaPrincipal;
    
                if (!$mesaPrincipal || !$mesaPrincipal->duracion_turno_minutos) {
                    return false;
                }
    
                $horaFin = Carbon::parse($horaInicio)
                    ->addMinutes($mesaPrincipal->duracion_turno_minutos)
                    ->format('H:i');
    
                foreach ($combinacion->obtenerMesasCombinadas() as $mesa) {
                    $haySolape = $mesa->reservas()
                        ->where('fecha', $fecha)
                        ->where('estado', '!=', 'cancelada')
                        ->where(function ($q) use ($horaInicio, $horaFin) {
                            $q->where(function ($q) use ($horaInicio, $horaFin) {
                                $q->where('hora_inicio', '<', $horaFin)
                                  ->where('hora_fin', '>', $horaInicio);
                            });                            
                        })->exists();
    
                    if ($haySolape) {
                        $this->logInfo("❌ Mesa {$mesa->id} tiene solapamiento entre $horaInicio y $horaFin");
                        return false;
                    }
                }
    
                return true;
            })->values();
    
            $this->logInfo('✅ Combinaciones disponibles finales', [
                'total' => $combinacionesDisponibles->count(),
                'ids' => $combinacionesDisponibles->pluck('id')->all()
            ]);
    
            return $combinacionesDisponibles;
    
        } catch (\Exception $e) {
            $this->logError('❌ Error al buscar combinaciones disponibles', $e);
            throw $e;
        }
    }
    /**
     * Asigna una mesa o combinación a una reserva
     *
     * @param Reserva $reserva
     * @param Mesa|CombinacionMesa $asignacion
     * @return bool
     */
    public function asignarMesa(Reserva $reserva, Model $asignacion): bool
    {
        try {
            $this->logInfo('Iniciando asignación de mesa', [
                'reserva_id' => $reserva->id,
                'asignacion_tipo' => get_class($asignacion),
                'asignacion_id' => $asignacion->id
            ]);

            if ($asignacion instanceof Mesa) {
                $reserva->mesa_id = $asignacion->id;
                $reserva->combinacion_mesa_id = null;
            } elseif ($asignacion instanceof CombinacionMesa) {
                $reserva->mesa_id = null;
                $reserva->combinacion_mesa_id = $asignacion->id;
            } else {
                throw new \Exception('Tipo de asignación no válido: debe ser Mesa o CombinacionMesa');
            }

            $reserva->save();

            $this->logInfo('Mesa asignada exitosamente');
            return true;
        } catch (\Exception $e) {
            $this->logError('Error al asignar mesa', $e);
            return false;
        }
    }

    /**
     * Verifica si una mesa o combinación está disponible en un horario específico
     *
     * @param Mesa|CombinacionMesa $asignacion
     * @param string $fecha
     * @param string $horaInicio
     * @param string $horaFin
     * @return bool
     */
    public function verificarDisponibilidad(
        Model $asignacion,
        string $fecha,
        string $horaInicio,
        string $horaFin
    ): bool {
        try {
            $this->logInfo('Verificando disponibilidad', [
                'asignacion_tipo' => get_class($asignacion),
                'asignacion_id' => $asignacion->id,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin
            ]);

            if ($asignacion instanceof Mesa && $asignacion->estado !== 'disponible') {
                return false;
            }

            $reservasExistentes = $asignacion->reservas()
                ->where('fecha', $fecha)
                ->where('estado', '!=', 'cancelada')
                ->where(function ($query) use ($horaInicio, $horaFin) {
                    $query->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                        ->orWhereBetween('hora_fin', [$horaInicio, $horaFin]);
                })
                ->exists();

            $this->logInfo('Verificación de disponibilidad completada', [
                'disponible' => !$reservasExistentes
            ]);

            return !$reservasExistentes;
        } catch (\Exception $e) {
            $this->logError('Error al verificar disponibilidad', $e);
            throw $e;
        }
    }

    /**
     * Obtiene el horario de servicio para una mesa
     *
     * @param Mesa $mesa
     * @param string $fecha
     * @return array
     */
    public function obtenerHorarioServicio(Mesa $mesa, string $fecha): array
    {
        try {
            $this->logInfo('Obteniendo horario de servicio', [
                'mesa_id' => $mesa->id,
                'fecha' => $fecha
            ]);

            $tipoTurno = $this->determinarTipoTurno($fecha, now()->format('H:i'));
            $horarios = $mesa->horarios()
                ->where('tipo_turno', $tipoTurno)
                ->orderBy('hora')
                ->get()
                ->pluck('hora')
                ->toArray();

            $this->logInfo('Horario de servicio obtenido', [
                'horarios' => $horarios
            ]);

            return $horarios;
        } catch (\Exception $e) {
            $this->logError('Error al obtener horario de servicio', $e);
            throw $e;
        }
    }

    /**
     * Determina el tipo de turno basado en la fecha
     *
     * @param string $fecha
     * @param string $hora
     * @return string
     */
    private function determinarTipoTurno(string $fecha, string $hora): string
    {
        try {
            $this->logInfo('Determinando tipo de turno', [
                'fecha' => $fecha,
                'hora' => $hora
            ]);

            $horaObj = Carbon::parse($hora);
            $tipoTurno = $horaObj->hour >= 12 && $horaObj->hour < 20 ? 'comida' : 'cena';

            $this->logInfo('Tipo de turno determinado', [
                'tipo_turno' => $tipoTurno
            ]);

            return $tipoTurno;
        } catch (\Exception $e) {
            $this->logError('Error al determinar tipo de turno', $e);
            throw $e;
        }
    }
}
