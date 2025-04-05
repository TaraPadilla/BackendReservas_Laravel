<?php

namespace App\Http\Controllers;

use App\Models\HorarioSemanal;
use App\Traits\LogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HorarioSemanalController extends Controller
{
    use LogTrait;

    /**
     * Obtiene todos los horarios semanales
     */
    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de horarios semanales');
            $horarios = HorarioSemanal::orderBy('day_of_week')->get();
            $this->logInfo('Lista de horarios obtenida', ['total' => $horarios->count()]);
            return response()->json($horarios);
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de horarios semanales', $e);
            return response()->json(['message' => 'Error al obtener los horarios semanales'], 500);
        }
    }

    /**
     * Almacena un nuevo horario semanal
     */
    public function store(Request $request)
    {
        try {
            $this->logInfo('Creando nuevo horario semanal', $request->all());

            $validated = $request->validate([
                'day_of_week' => ['required', 'integer', 'between:0,6', Rule::unique('horarios_semanales')],
                'is_closed' => 'required|boolean',
                'lunch_start' => 'nullable|required_if:is_closed,false|date_format:H:i',
                'lunch_end' => 'nullable|required_if:is_closed,false|date_format:H:i|after:lunch_start',
                'dinner_start' => 'nullable|required_if:is_closed,false|date_format:H:i',
                'dinner_end' => 'nullable|required_if:is_closed,false|date_format:H:i|after:dinner_start'
            ]);

            // Validar que al menos un turno esté definido si no está cerrado
            if (!$validated['is_closed']) {
                $lunchDefined = isset($validated['lunch_start']) && isset($validated['lunch_end']);
                $dinnerDefined = isset($validated['dinner_start']) && isset($validated['dinner_end']);
                
                if (!$lunchDefined && !$dinnerDefined) {
                    return response()->json([
                        'message' => 'Debe definir al menos un turno (almuerzo o cena) si el local no está cerrado'
                    ], 422);
                }
            } else {
                // Si está cerrado, asegurarse de que todos los horarios sean null
                $validated['lunch_start'] = null;
                $validated['lunch_end'] = null;
                $validated['dinner_start'] = null;
                $validated['dinner_end'] = null;
            }

            $horario = HorarioSemanal::create($validated);
            
            $this->logInfo('Horario semanal creado exitosamente', ['id' => $horario->id]);
            return response()->json($horario, 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear horario semanal', $e);
            return response()->json(['message' => 'Error al crear el horario semanal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un horario semanal específico
     */
    public function show(HorarioSemanal $horarioSemanal)
    {
        try {
            $this->logInfo('Obteniendo detalles de horario semanal', ['id' => $horarioSemanal->id]);
            return response()->json($horarioSemanal);
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de horario semanal', $e);
            return response()->json(['message' => 'Error al obtener los detalles del horario semanal'], 500);
        }
    }

    /**
     * Actualiza un horario semanal existente
     */
    public function update(Request $request, HorarioSemanal $horarioSemanal)
    {
        try {
            $this->logInfo('Actualizando horario semanal', ['id' => $horarioSemanal->id, 'data' => $request->all()]);

            $validated = $request->validate([
                'day_of_week' => ['required', 'integer', 'between:0,6', Rule::unique('horarios_semanales')->ignore($horarioSemanal->id)],
                'is_closed' => 'required|boolean',
                'lunch_start' => 'nullable|required_if:is_closed,false|date_format:H:i',
                'lunch_end' => 'nullable|required_if:is_closed,false|date_format:H:i|after:lunch_start',
                'dinner_start' => 'nullable|required_if:is_closed,false|date_format:H:i',
                'dinner_end' => 'nullable|required_if:is_closed,false|date_format:H:i|after:dinner_start'
            ]);

            // Validar que al menos un turno esté definido si no está cerrado
            if (!$validated['is_closed']) {
                $lunchDefined = isset($validated['lunch_start']) && isset($validated['lunch_end']);
                $dinnerDefined = isset($validated['dinner_start']) && isset($validated['dinner_end']);
                
                if (!$lunchDefined && !$dinnerDefined) {
                    return response()->json([
                        'message' => 'Debe definir al menos un turno (almuerzo o cena) si el local no está cerrado'
                    ], 422);
                }
            } else {
                // Si está cerrado, asegurarse de que todos los horarios sean null
                $validated['lunch_start'] = null;
                $validated['lunch_end'] = null;
                $validated['dinner_start'] = null;
                $validated['dinner_end'] = null;
            }

            $horarioSemanal->update($validated);
            
            $this->logInfo('Horario semanal actualizado exitosamente', ['id' => $horarioSemanal->id]);
            return response()->json($horarioSemanal);
        } catch (\Exception $e) {
            $this->logError('Error al actualizar horario semanal', $e);
            return response()->json(['message' => 'Error al actualizar el horario semanal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un horario semanal
     */
    public function destroy(HorarioSemanal $horarioSemanal)
    {
        try {
            $this->logInfo('Eliminando horario semanal', ['id' => $horarioSemanal->id]);
            $horarioSemanal->delete();
            $this->logInfo('Horario semanal eliminado exitosamente', ['id' => $horarioSemanal->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar horario semanal', $e);
            return response()->json(['message' => 'Error al eliminar el horario semanal'], 500);
        }
    }

    /**
     * Inicializa los horarios semanales con valores por defecto
     */
    public function initialize()
    {
        try {
            $this->logInfo('Inicializando horarios semanales');
            
            DB::beginTransaction();
            
            // Eliminar horarios existentes
            HorarioSemanal::truncate();
            
            // Crear horarios por defecto para cada día de la semana
            $defaultHorarios = [
                // Lunes a Viernes: almuerzo 12:00-15:00, cena 19:00-23:00
                // Sábado: almuerzo 13:00-16:00, cena 20:00-00:00
                // Domingo: cerrado
                [
                    'day_of_week' => 0, // Domingo
                    'is_closed' => true
                ],
                [
                    'day_of_week' => 1, // Lunes
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'day_of_week' => 2, // Martes
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'day_of_week' => 3, // Miércoles
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'day_of_week' => 4, // Jueves
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'day_of_week' => 5, // Viernes
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'day_of_week' => 6, // Sábado
                    'is_closed' => false,
                    'lunch_start' => '13:00',
                    'lunch_end' => '16:00',
                    'dinner_start' => '20:00',
                    'dinner_end' => '00:00'
                ]
            ];
            
            foreach ($defaultHorarios as $horario) {
                HorarioSemanal::create($horario);
            }
            
            DB::commit();
            
            $this->logInfo('Horarios semanales inicializados exitosamente');
            return response()->json(['message' => 'Horarios semanales inicializados correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al inicializar horarios semanales', $e);
            return response()->json(['message' => 'Error al inicializar los horarios semanales: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifica si un horario específico está dentro del horario de servicio
     */
    public function verificarHorarioServicio(Request $request)
    {
        try {
            $this->logInfo('Verificando horario de servicio', $request->all());

            $validated = $request->validate([
                'fecha' => 'required|date',
                'hora' => 'required|date_format:H:i'
            ]);

            // Obtener el día de la semana (0 = Domingo, 1 = Lunes, ..., 6 = Sábado)
            $fecha = \Carbon\Carbon::parse($validated['fecha']);
            $dayOfWeek = $fecha->dayOfWeek;
            $hora = $validated['hora'];

            // Obtener el horario para ese día
            $horario = HorarioSemanal::where('day_of_week', $dayOfWeek)->first();

            if (!$horario) {
                return response()->json([
                    'dentro_horario' => false,
                    'mensaje' => 'No hay horario definido para este día'
                ]);
            }

            // Si está cerrado, no hay servicio
            if ($horario->is_closed) {
                return response()->json([
                    'dentro_horario' => false,
                    'mensaje' => 'El local está cerrado este día'
                ]);
            }

            // Verificar si la hora está dentro del horario de almuerzo
            $dentroAlmuerzo = false;
            if ($horario->lunch_start && $horario->lunch_end) {
                $dentroAlmuerzo = $hora >= $horario->lunch_start && $hora <= $horario->lunch_end;
            }

            // Verificar si la hora está dentro del horario de cena
            $dentroCena = false;
            if ($horario->dinner_start && $horario->dinner_end) {
                $dentroCena = $hora >= $horario->dinner_start && $hora <= $horario->dinner_end;
            }

            $dentroHorario = $dentroAlmuerzo || $dentroCena;
            $turno = $dentroAlmuerzo ? 'almuerzo' : ($dentroCena ? 'cena' : null);

            return response()->json([
                'dentro_horario' => $dentroHorario,
                'turno' => $turno,
                'mensaje' => $dentroHorario 
                    ? "El horario está dentro del turno de {$turno}" 
                    : "El horario está fuera del horario de servicio"
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al verificar horario de servicio', $e);
            return response()->json(['message' => 'Error al verificar el horario de servicio: ' . $e->getMessage()], 500);
        }
    }
} 