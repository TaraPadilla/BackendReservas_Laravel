<?php

namespace App\Http\Controllers;

use App\Models\HorarioSemanal;
use App\Models\Sede;
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
    public function index($sede_id = null)
    {
        try {
            $this->logInfo('Obteniendo lista de horarios semanales');
            
            $query = HorarioSemanal::with('sede')->orderBy('day_of_week');
            
            // Filtrar por sede si se proporciona
            if ($sede_id) {
                $query->where('sede_id', $sede_id);
            }
            
            $horarios = $query->get();
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
                'sede_id' => 'required|exists:sedes,id',
                'day_of_week' => ['required', 'integer', 'between:0,6', Rule::unique('horarios_semanales')->where(function ($query) use ($request) {
                    return $query->where('sede_id', $request->sede_id);
                })],
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
            return response()->json($horarioSemanal->load('sede'));
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de horario semanal', $e);
            return response()->json(['message' => 'Error al obtener los detalles del horario semanal'], 500);
        }
    }

    /**
     * Actualiza un horario semanal existente
     */
    public function update(Request $request, $id)
    {
        try {
            $horarioSemanal = HorarioSemanal::findOrFail($id);
            $this->logInfo('Actualizando horario semanal', ['id' => $id, 'data' => $request->all()]);

            // Solo verificar duplicados si se está cambiando la sede o el día de la semana
            if ($request->sede_id != $horarioSemanal->sede_id || $request->day_of_week != $horarioSemanal->day_of_week) {
                // Verificar si ya existe un horario con la misma sede y día de la semana
                $horarioExistente = HorarioSemanal::where('sede_id', $request->sede_id)
                    ->where('day_of_week', $request->day_of_week)
                    ->where('id', '!=', $id)
                    ->first();

                if ($horarioExistente) {
                    return response()->json([
                        'message' => 'Ya existe un horario para esta sede y día de la semana',
                        'horario_existente' => $horarioExistente
                    ], 422);
                }
            }

            $validated = $request->validate([
                'sede_id' => 'required|exists:sedes,id',
                'day_of_week' => 'required|integer|between:0,6',
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
            
            $this->logInfo('Horario semanal actualizado exitosamente', ['id' => $id]);
            return response()->json($horarioSemanal->load('sede'));
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
    public function initialize($sede_id)
    {
        try {
            $this->logInfo('Inicializando horarios semanales', ['sede_id' => $sede_id]);
            
            // Verificar que la sede existe
            $sede = Sede::findOrFail($sede_id);
            
            DB::beginTransaction();
            
            // Eliminar horarios existentes para esta sede
            HorarioSemanal::where('sede_id', $sede_id)->delete();
            
            // Crear horarios por defecto para cada día de la semana
            $defaultHorarios = [
                // Lunes a Viernes: almuerzo 12:00-15:00, cena 19:00-23:00
                // Sábado: almuerzo 13:00-16:00, cena 20:00-00:00
                // Domingo: cerrado
                [
                    'sede_id' => $sede_id,
                    'day_of_week' => 0, // Domingo
                    'is_closed' => true
                ],
                [
                    'sede_id' => $sede_id,
                    'day_of_week' => 1, // Lunes
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'sede_id' => $sede_id,
                    'day_of_week' => 2, // Martes
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'sede_id' => $sede_id,
                    'day_of_week' => 3, // Miércoles
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'sede_id' => $sede_id,
                    'day_of_week' => 4, // Jueves
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'sede_id' => $sede_id,
                    'day_of_week' => 5, // Viernes
                    'is_closed' => false,
                    'lunch_start' => '12:00',
                    'lunch_end' => '15:00',
                    'dinner_start' => '19:00',
                    'dinner_end' => '23:00'
                ],
                [
                    'sede_id' => $sede_id,
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
            
            $this->logInfo('Horarios semanales inicializados exitosamente', ['sede_id' => $sede_id]);
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
            $validated = $request->validate([
                'sede_id' => 'required|exists:sedes,id',
                'fecha' => 'required|date',
                'hora' => 'nullable|date_format:H:i'
            ]);
    
            $fecha = \Carbon\Carbon::parse($validated['fecha']);
            $horario = HorarioSemanal::where('sede_id', $validated['sede_id'])
                ->where('day_of_week', $fecha->dayOfWeek)
                ->first();
    
            if (!$horario || $horario->is_closed) {
                return response()->json([
                    'dentro_horario' => false,
                    'mensaje' => 'El local está cerrado o sin horario definido ese día'
                ]);
            }
    
            if (empty($validated['hora'])) {
                return response()->json([
                    'dentro_horario' => true,
                    'mensaje' => 'El local está abierto ese día',
                    'horario' => [
                        'almuerzo' => [$horario->lunch_start, $horario->lunch_end],
                        'cena' => [$horario->dinner_start, $horario->dinner_end],
                    ]
                ]);
            }
    
            // Convertimos todo a Carbon y normalizamos fecha a evitar problemas
            $hora = \Carbon\Carbon::createFromFormat('H:i', $validated['hora'])->setDate(2000, 1, 1);
            $lunchStart = $horario->lunch_start ? \Carbon\Carbon::parse($horario->lunch_start)->setDate(2000, 1, 1) : null;
            $lunchEnd = $horario->lunch_end ? \Carbon\Carbon::parse($horario->lunch_end)->setDate(2000, 1, 1) : null;
            $dinnerStart = $horario->dinner_start ? \Carbon\Carbon::parse($horario->dinner_start)->setDate(2000, 1, 1) : null;
            $dinnerEnd = $horario->dinner_end ? \Carbon\Carbon::parse($horario->dinner_end)->setDate(2000, 1, 1) : null;
    
            $dentroAlmuerzo = $lunchStart && $lunchEnd && $hora->between($lunchStart, $lunchEnd);
            $dentroCena = $dinnerStart && $dinnerEnd && $hora->between($dinnerStart, $dinnerEnd);
    
            $dentro = $dentroAlmuerzo || $dentroCena;
            $turno = $dentroAlmuerzo ? 'almuerzo' : ($dentroCena ? 'cena' : null);
    
            return response()->json([
                'dentro_horario' => $dentro,
                'turno' => $turno,
                'mensaje' => $dentro
                    ? "El horario está dentro del turno de {$turno}"
                    : "El horario está fuera del horario de servicio"
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al verificar horario: ' . $e->getMessage()], 500);
        }
    }
    


    
} 