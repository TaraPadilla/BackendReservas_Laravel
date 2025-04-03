<?php

namespace App\Http\Controllers;

use App\Models\HorarioMesa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HorarioMesaController extends Controller
{
    public function index()
    {
        return HorarioMesa::with('mesa')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'tipo_turno' => 'required|in:comida,cena',
            'hora' => 'required|date_format:H:i'
        ]);

        $horario = HorarioMesa::create($validated);

        return response()->json($horario->load('mesa'), 201);
    }

    public function show(HorarioMesa $horarioMesa)
    {
        return $horarioMesa->load('mesa');
    }

    public function update(Request $request, HorarioMesa $horarioMesa)
    {
        $validated = $request->validate([
            'mesa_id' => 'exists:mesas,id',
            'tipo_turno' => 'in:comida,cena',
            'hora' => 'date_format:H:i'
        ]);

        $horarioMesa->update($validated);
        return response()->json($horarioMesa->load('mesa'));
    }

    public function destroy(HorarioMesa $horarioMesa)
    {
        $horarioMesa->delete();
        return response()->json(null, 204);
    }
} 