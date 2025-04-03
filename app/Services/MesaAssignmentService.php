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
        string $horaFin,
        int $numPersonas,
        int $sedeId,
        ?string $ubicacion = null
    ): Collection {
        try {
            $this->logInfo('Iniciando búsqueda de mesas disponibles', [
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'num_personas' => $numPersonas,
                'sede_id' => $sedeId,
                'ubicacion' => $ubicacion
            ]);

            $query = Mesa::where('sede_id', $sedeId)
                ->where('activa', true)
                ->where('estado', 'disponible')
                ->where('capacidad_min', '<=', $numPersonas)
                ->where('capacidad_max', '>=', $numPersonas);

            if ($ubicacion) {
                $query->where('ubicacion', $ubicacion);
            }

            $mesasDisponibles = $query->whereDoesntHave('reservas', function ($query) use ($fecha, $horaInicio, $horaFin) {
                $query->where('fecha', $fecha)
                    ->where('estado', '!=', 'cancelada')
                    ->where(function ($q) use ($horaInicio, $horaFin) {
                        $q->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                            ->orWhereBetween('hora_fin', [$horaInicio, $horaFin]);
                    });
            })
            ->get();

            if ($mesasDisponibles->isEmpty()) {
                $this->logWarning('No se encontraron mesas individuales disponibles, buscando combinaciones');
                $mesasDisponibles = $this->buscarCombinacionesDisponibles($fecha, $horaInicio, $horaFin, $numPersonas, $sedeId, $ubicacion);
            }

            $this->logInfo('Búsqueda de mesas completada', [
                'mesas_encontradas' => $mesasDisponibles->count()
            ]);

            return $mesasDisponibles;
        } catch (\Exception $e) {
            $this->logError('Error al buscar mesas disponibles', $e);
            throw $e;
        }
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
    private function buscarCombinacionesDisponibles(
        string $fecha,
        string $horaInicio,
        string $horaFin,
        int $numPersonas,
        int $sedeId,
        ?string $ubicacion = null
    ): Collection {
        try {
            $this->logInfo('Iniciando búsqueda de combinaciones disponibles');

            $query = CombinacionMesa::where('sede_id', $sedeId)
                ->where('activa', true)
                ->where('capacidad_min', '<=', $numPersonas)
                ->where('capacidad_max', '>=', $numPersonas);

            if ($ubicacion) {
                $query->whereHas('mesas', function ($q) use ($ubicacion) {
                    $q->where('ubicacion', $ubicacion);
                });
            }

            return $query->whereDoesntHave('reservas', function ($query) use ($fecha, $horaInicio, $horaFin) {
                $query->where('fecha', $fecha)
                    ->where('estado', '!=', 'cancelada')
                    ->where(function ($q) use ($horaInicio, $horaFin) {
                        $q->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                            ->orWhereBetween('hora_fin', [$horaInicio, $horaFin]);
                    });
            })
            ->get();
        } catch (\Exception $e) {
            $this->logError('Error al buscar combinaciones disponibles', $e);
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
                $asignacion->update(['estado' => 'reservada']);
            } else {
                $reserva->mesa_id = null;
                $reserva->combinacion_mesa_id = $asignacion->id;
                // Actualizar estado de todas las mesas de la combinación
                foreach ($asignacion->mesas as $mesa) {
                    $mesa->update(['estado' => 'reservada']);
                }
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
