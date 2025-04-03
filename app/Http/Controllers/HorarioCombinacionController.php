<?php

namespace App\Http\Controllers;

use App\Models\HorarioCombinacion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HorarioCombinacionController extends Controller
{
    public function index()
    {
        return HorarioCombinacion::with('combinacionMesa')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'combinacion_mesa_id' => 'required|exists:combinaciones_mesas,id',
            'tipo_turno' => 'required|in:comida,cena',
            'hora' => 'required|date_format:H:i'
        ]);

        $validated['id'] = Str::uuid();
        $horario = HorarioCombinacion::create($validated);

        return response()->json($horario->load('combinacionMesa'), 201);
    }

    public function show(HorarioCombinacion $horarioCombinacion)
    {
        return $horarioCombinacion->load('combinacionMesa');
    }

    public function update(Request $request, HorarioCombinacion $horarioCombinacion)
    {
        $validated = $request->validate([
            'combinacion_mesa_id' => 'exists:combinaciones_mesas,id',
            'tipo_turno' => 'in:comida,cena',
            'hora' => 'date_format:H:i'
        ]);

        $horarioCombinacion->update($validated);
        return response()->json($horarioCombinacion->load('combinacionMesa'));
    }

    public function destroy(HorarioCombinacion $horarioCombinacion)
    {
        $horarioCombinacion->delete();
        return response()->json(null, 204);
    }
} 