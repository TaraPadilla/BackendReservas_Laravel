<?php

namespace App\Http\Controllers;

use App\Models\CombinacionMesa;
use Illuminate\Http\Request;
use App\Traits\LogTrait;

class CombinacionMesaController extends Controller
{
    use LogTrait;

    public function index($sedeId = null)
    {
        //si no hay sedeId, se obtienen todas las combinaciones
        if (!$sedeId) {
            return CombinacionMesa::all()->load(['mesaPrincipal']);
        }   
        //si hay sedeId, se obtienen las combinaciones de la sede con la mesa principal
        else {
            return CombinacionMesa::where('sede_id', $sedeId)->get()->load(['mesaPrincipal']);
        }
    }

    public function store(Request $request)
    {
        $this->logInfo('CombinacionMesaController::store');
        
        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'mesa_id' => 'required|exists:mesas,id',
            'mesas_combinadas_ids' => 'required|array|min:1',
            'mesas_combinadas_ids.*' => 'exists:mesas,id|different:mesa_id',
            'capacidad_min' => 'required|integer',
            'capacidad_max' => 'required|integer',
            'duracion_turno_minutos' => 'required|integer',
            'es_excepcional' => 'boolean'
        ]);

        $this->logInfo('Campos validados', $validated);

        if ($this->validarCombinacion($validated['sede_id'], $validated['mesa_id'], $validated['mesas_combinadas_ids'], $validated['capacidad_min'], $validated['capacidad_max'])) {
            return response()->json(['message' => 'La combinación de mesas ya existe'], 400);
        }

        $combinacion = CombinacionMesa::create([
            'sede_id' => $validated['sede_id'],
            'mesa_id' => $validated['mesa_id'],
            'mesas_combinadas_ids' => $validated['mesas_combinadas_ids'],
            'capacidad_min' => $validated['capacidad_min'],
            'capacidad_max' => $validated['capacidad_max'],
            'duracion_turno_minutos' => $validated['duracion_turno_minutos'],
            'es_excepcional' => $validated['es_excepcional'] ?? false
        ]);

        return response()->json($combinacion->load(['sede', 'mesaPrincipal']), 201);
    }

    //Funcion para validar si la sede y la mesa principal existen con el mismo arreglo de mesas combinadas 
    // y con la misma capacidad minima y maxima
    private function validarCombinacion($sedeId, $mesaId, $mesasCombinadasIds, $capacidadMin, $capacidadMax)
    {
        $this->logInfo('Validando combinación', ['sedeId' => $sedeId, 'mesaId' => $mesaId, 'mesasCombinadasIds' => $mesasCombinadasIds, 'capacidadMin' => $capacidadMin, 'capacidadMax' => $capacidadMax]);
        
        //Se debe validar todos los registros de sede y mesa para comprobar si existe una combinación
        $combinaciones = CombinacionMesa::where('sede_id', $sedeId)->where('mesa_id', $mesaId)->get();
        foreach ($combinaciones as $combinacion) {
            if ($combinacion->mesas_combinadas_ids == $mesasCombinadasIds && $combinacion->capacidad_min == $capacidadMin && $combinacion->capacidad_max == $capacidadMax) {
                $this->logInfo('Combinación encontrada', ['combinacion' => $combinacion]);
                return true;
            }
        }
        $this->logInfo('Combinación no encontrada');
        return false;
    }
    
    public function show(CombinacionMesa $combinacionMesa)
    {
        return $combinacionMesa->load(['sede', 'mesaPrincipal']);
    }

    public function update(Request $request, CombinacionMesa $combinacionMesa)
    {
        $validated = $request->validate([
            'sede_id' => 'exists:sedes,id',
            'mesa_id' => 'exists:mesas,id',
            'mesas_combinadas_ids' => 'array|min:1',
            'mesas_combinadas_ids.*' => 'exists:mesas,id|different:mesa_id',
            'capacidad_min' => 'integer',
            'capacidad_max' => 'integer',
            'duracion_turno_minutos' => 'integer',
            'es_excepcional' => 'boolean'
        ]);

        $combinacionMesa->update([
            'sede_id' => $validated['sede_id'] ?? $combinacionMesa->sede_id,
            'mesa_id' => $validated['mesa_id'] ?? $combinacionMesa->mesa_id,
            'mesas_combinadas_ids' => $validated['mesas_combinadas_ids'] ?? $combinacionMesa->mesas_combinadas_ids,
            'capacidad_min' => $validated['capacidad_min'] ?? $combinacionMesa->capacidad_min,
            'capacidad_max' => $validated['capacidad_max'] ?? $combinacionMesa->capacidad_max,
            'duracion_turno_minutos' => $validated['duracion_turno_minutos'] ?? $combinacionMesa->duracion_turno_minutos,
            'es_excepcional' => $validated['es_excepcional'] ?? $combinacionMesa->es_excepcional
        ]);

        return response()->json($combinacionMesa->load(['sede', 'mesaPrincipal']));
    }

    //Funcion para eliminar una combinación de mesa recibiendo el id de la combinación
    public function destroy($id)
    {
        $this->logInfo('Eliminando combinación de mesa');
        $combinacionMesa = CombinacionMesa::find($id);
        if (!$combinacionMesa) {
            return response()->json(['message' => 'Combinación de mesa no encontrada'], 404);
        }
        $combinacionMesa->delete();
        return response()->json(['message' => 'Combinación de mesa eliminada correctamente'], 200);
    }
    
}
