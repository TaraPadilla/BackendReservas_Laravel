<?php

namespace App\Http\Controllers;

use App\Models\BloqueoMesa;
use App\Models\Mesa;
use App\Traits\LogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class BloqueoMesaController extends Controller
{
    use LogTrait;

    /**
     * Obtiene todos los bloqueos de mesas
     */
    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de bloqueos de mesas');
            
            $bloqueos = BloqueoMesa::all()->map(function ($bloqueo) {
                if ($bloqueo->affected_tables === 'specific') {
                    $bloqueo->load('mesas');
                }
                return $bloqueo;
            });
    
            $this->logInfo('Lista de bloqueos obtenida', ['total' => $bloqueos->count()]);
            return response()->json($bloqueos);
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de bloqueos', $e);
            return response()->json(['message' => 'Error al obtener los bloqueos'], 500);
        }
    }
    

    /**
     * Almacena un nuevo bloqueo de mesa
     */
    public function store(Request $request)
    {
        try {
            $this->logInfo('Creando nuevo bloqueo de mesa', $request->all());
    
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_full_day' => 'required|boolean',
                'start_time' => 'nullable|required_if:is_full_day,false|date_format:H:i',
                'end_time' => 'nullable|required_if:is_full_day,false|date_format:H:i|after:start_time',
                'reason' => 'nullable|string|max:255',
                'affected_tables' => ['required', Rule::in(['all', 'interior', 'exterior', 'specific'])],
                'specific_tables' => [
                    'nullable',
                    Rule::requiredIf(fn () => $request->input('affected_tables') === 'specific'),
                    'array'
                ],
                'specific_tables.*' => [
                    'integer',
                    Rule::exists('mesas', 'id')->when(
                        $request->input('affected_tables') === 'specific',
                        fn ($rule) => $rule
                    )
                ],
            ]);
    
            $this->verificarSolapamiento($validated);
    
            DB::beginTransaction();
    
            $bloqueo = BloqueoMesa::create([
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_full_day' => $validated['is_full_day'],
                'start_time' => $validated['is_full_day'] ? null : $validated['start_time'],
                'end_time' => $validated['is_full_day'] ? null : $validated['end_time'],
                'reason' => $validated['reason'] ?? null,
                'affected_tables' => $validated['affected_tables'],
                'specific_tables' => $validated['affected_tables'] === 'specific'
                    ? $validated['specific_tables']
                    : null,
            ]);
    
            DB::commit();
    
            $this->logInfo('Bloqueo creado exitosamente', ['id' => $bloqueo->id]);
    
            return response()->json($bloqueo, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al crear bloqueo', $e);
            return response()->json(['message' => 'Error al crear el bloqueo: ' . $e->getMessage()], 500);
        }
    }
    


    /**
     * Muestra un bloqueo específico
     */
    public function show(BloqueoMesa $bloqueoMesa)
    {
        try {
            $this->logInfo('Obteniendo detalles de bloqueo', ['id' => $bloqueoMesa->id]);
            if ($bloqueoMesa->affected_tables === 'specific') {
                $bloqueoMesa->load('mesas');
            }
            return response()->json($bloqueoMesa);
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de bloqueo', $e);
            return response()->json(['message' => 'Error al obtener los detalles del bloqueo'], 500);
        }
    }    

    /**
     * Actualiza un bloqueo existente
     */
    public function update(Request $request, BloqueoMesa $bloqueoMesa)
    {
        try {
            $this->logInfo('Actualizando bloqueo', ['id' => $bloqueoMesa->id, 'data' => $request->all()]);
    
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_full_day' => 'required|boolean',
                'start_time' => 'nullable|required_if:is_full_day,false|date_format:H:i',
                'end_time' => 'nullable|required_if:is_full_day,false|date_format:H:i|after:start_time',
                'reason' => 'nullable|string|max:255',
                'affected_tables' => ['required', Rule::in(['all', 'interior', 'exterior', 'specific'])],
                'specific_tables' => [
                    'nullable',
                    Rule::requiredIf(fn () => $request->input('affected_tables') === 'specific'),
                    'array'
                ],
                'specific_tables.*' => [
                    'integer',
                    Rule::exists('mesas', 'id')->when(
                        $request->input('affected_tables') === 'specific',
                        fn ($rule) => $rule
                    )
                ],
            ]);
    
            // Verificar que las fechas no se solapan con otros bloqueos (excluyendo el actual)
            $this->verificarSolapamiento($validated, $bloqueoMesa->id);
    
            DB::beginTransaction();
    
            $bloqueoMesa->update([
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'is_full_day' => $validated['is_full_day'],
                'start_time' => $validated['is_full_day'] ? null : $validated['start_time'],
                'end_time' => $validated['is_full_day'] ? null : $validated['end_time'],
                'reason' => $validated['reason'] ?? null,
                'affected_tables' => $validated['affected_tables'],
                'specific_tables' => $validated['affected_tables'] === 'specific'
                    ? $validated['specific_tables']
                    : null,
            ]);
    
            DB::commit();
    
            $this->logInfo('Bloqueo actualizado exitosamente', ['id' => $bloqueoMesa->id]);
    
            return response()->json($bloqueoMesa);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Error al actualizar bloqueo', $e);
            return response()->json(['message' => 'Error al actualizar el bloqueo: ' . $e->getMessage()], 500);
        }
    }
    

    /**
     * Elimina un bloqueo
     */
    public function destroy(BloqueoMesa $bloqueoMesa)
    {
        try {
            $this->logInfo('Eliminando bloqueo', ['id' => $bloqueoMesa->id]);
            
            // Eliminar relaciones con mesas
            if ($bloqueoMesa->affected_tables === 'specific') {
                $bloqueoMesa->mesas()->detach();
            }
            
            // Eliminar el bloqueo
            $bloqueoMesa->delete();
            
            $this->logInfo('Bloqueo eliminado exitosamente', ['id' => $bloqueoMesa->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar bloqueo', $e);
            return response()->json(['message' => 'Error al eliminar el bloqueo'], 500);
        }
    }

    /**
     * Verifica si hay solapamiento con otros bloqueos
     */
    private function verificarSolapamiento(array $data, ?int $excludeId = null)
    {
        $query = BloqueoMesa::where(function ($q) use ($data) {
            $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                ->orWhere(function ($q) use ($data) {
                    $q->where('start_date', '<=', $data['start_date'])
                        ->where('end_date', '>=', $data['end_date']);
                });
        });
    
        // Si es un bloqueo parcial (no todo el día), verificar solapamiento de horas
        if (!$data['is_full_day']) {
            $query->where(function ($q) use ($data) {
                $q->where('is_full_day', true)
                    ->orWhere(function ($q) use ($data) {
                        $q->where('is_full_day', false)
                            ->where(function ($q) use ($data) {
                                $q->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                                    ->orWhere(function ($q) use ($data) {
                                        $q->where('start_time', '<=', $data['start_time'])
                                            ->where('end_time', '>=', $data['end_time']);
                                    });
                            });
                    });
            });
        }
    
        // Excluir el bloqueo actual si se está actualizando
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
    
        // Verificar solapamiento por alcance
        if ($data['affected_tables'] === 'specific') {
            $mesaIds = $data['specific_tables'];
    
            $query->where(function ($q) use ($mesaIds) {
                $q->where('affected_tables', 'all')
                  ->orWhere(function ($q) use ($mesaIds) {
                      $q->where('affected_tables', 'specific')
                        ->where(function ($q) use ($mesaIds) {
                            foreach ($mesaIds as $mesaId) {
                                $q->orWhereJsonContains('specific_tables', $mesaId);
                            }
                        });
                  });
            });
        } else {
            $query->where(function ($q) use ($data) {
                $q->where('affected_tables', 'all')
                  ->orWhere('affected_tables', $data['affected_tables']);
            });
        }
    
        if ($query->exists()) {
            throw new \Exception('Existe un solapamiento con otro bloqueo en el mismo período');
        }
    }
    

    /**
     * Obtiene los bloqueos para un rango de fechas
     */
    public function porRangoFechas(Request $request)
    {
        try {
            $this->logInfo('Obteniendo bloqueos por rango de fechas', $request->all());

            $validated = $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'sede_id' => 'nullable|exists:sedes,id'
            ]);

            $query = BloqueoMesa::where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['fecha_inicio'], $validated['fecha_fin']])
                    ->orWhereBetween('end_date', [$validated['fecha_inicio'], $validated['fecha_fin']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_date', '<=', $validated['fecha_inicio'])
                            ->where('end_date', '>=', $validated['fecha_fin']);
                    });
            });

            // Filtrar por sede si se especifica
            if (isset($validated['sede_id'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('affected_tables', 'all')
                      ->orWhereHas('mesas', function ($q) use ($validated) {
                          $q->where('sede_id', $validated['sede_id']);
                      });
                });
            }            

            $bloqueos = $query->get()->map(function ($bloqueo) {
                if ($bloqueo->affected_tables === 'specific') {
                    $bloqueo->load('mesas');
                }
                return $bloqueo;
            });
            
            $this->logInfo('Bloqueos obtenidos por rango de fechas', ['total' => $bloqueos->count()]);
            return response()->json($bloqueos);
        } catch (\Exception $e) {
            $this->logError('Error al obtener bloqueos por rango de fechas', $e);
            return response()->json(['message' => 'Error al obtener los bloqueos: ' . $e->getMessage()], 500);
        }
    }
} 