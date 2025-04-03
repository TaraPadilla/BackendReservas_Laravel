<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SedeController extends Controller
{
    public function index()
    {
        return Sede::with(['restaurante', 'mesas', 'combinacionesMesas'])->get();
    }

    public function activas()
    {
        return Sede::with(['restaurante'])
            ->where('activo', true)
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurante_id' => 'required|exists:restaurantes,id',
            'nombre' => 'required|string|max:100',
            'direccion' => 'required|string',
            'ciudad' => 'required|string|max:100',
            'activo' => 'boolean'
        ]);

        $validated['id'] = Str::uuid();
        $sede = Sede::create($validated);

        return response()->json($sede, 201);
    }

    public function show(Sede $sede)
    {
        return $sede->load(['restaurante', 'mesas', 'combinacionesMesas']);
    }

    public function update(Request $request, Sede $sede)
    {
        $validated = $request->validate([
            'restaurante_id' => 'exists:restaurantes,id',
            'nombre' => 'string|max:100',
            'direccion' => 'string',
            'ciudad' => 'string|max:100',
            'activo' => 'boolean'
        ]);

        $sede->update($validated);
        return response()->json($sede);
    }

    public function destroy(Sede $sede)
    {
        $sede->delete();
        return response()->json(null, 204);
    }
} 