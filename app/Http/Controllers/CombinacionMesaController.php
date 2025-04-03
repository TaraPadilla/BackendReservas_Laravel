<?php

namespace App\Http\Controllers;

use App\Models\CombinacionMesa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CombinacionMesaController extends Controller
{
    public function index()
    {
        return CombinacionMesa::with(['sede', 'mesas', 'horarios'])->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'capacidad_min' => 'required|integer',
            'capacidad_max' => 'required|integer',
            'duracion_turno_minutos' => 'required|integer',
            'mesas' => 'required|array',
            'mesas.*' => 'exists:mesas,id'
        ]);

        $combinacion = CombinacionMesa::create([
            'sede_id' => $validated['sede_id'],
            'capacidad_min' => $validated['capacidad_min'],
            'capacidad_max' => $validated['capacidad_max'],
            'duracion_turno_minutos' => $validated['duracion_turno_minutos']
        ]);

        $combinacion->mesas()->attach($validated['mesas']);

        return response()->json($combinacion->load('mesas'), 201);
    }

    public function show(CombinacionMesa $combinacionMesa)
    {
        return $combinacionMesa->load(['sede', 'mesas', 'horarios']);
    }

    public function update(Request $request, CombinacionMesa $combinacionMesa)
    {
        $validated = $request->validate([
            'sede_id' => 'exists:sedes,id',
            'capacidad_min' => 'integer',
            'capacidad_max' => 'integer',
            'duracion_turno_minutos' => 'integer',
            'mesas' => 'array',
            'mesas.*' => 'exists:mesas,id'
        ]);

        $combinacionMesa->update([
            'sede_id' => $validated['sede_id'] ?? $combinacionMesa->sede_id,
            'capacidad_min' => $validated['capacidad_min'] ?? $combinacionMesa->capacidad_min,
            'capacidad_max' => $validated['capacidad_max'] ?? $combinacionMesa->capacidad_max,
            'duracion_turno_minutos' => $validated['duracion_turno_minutos'] ?? $combinacionMesa->duracion_turno_minutos
        ]);

        if (isset($validated['mesas'])) {
            $combinacionMesa->mesas()->sync($validated['mesas']);
        }

        return response()->json($combinacionMesa->load('mesas'));
    }

    public function destroy(CombinacionMesa $combinacionMesa)
    {
        $combinacionMesa->delete();
        return response()->json(null, 204);
    }
} 