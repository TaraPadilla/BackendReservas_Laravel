<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RestauranteController extends Controller
{
    public function index()
    {
        return Restaurante::with('sedes')->get();
    }

    public function activos()
    {
        return Restaurante::with('sedes')->where('activo', true)->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'logo' => 'nullable|string|max:255',
            'activo' => 'boolean'
        ]);

        $validated['id'] = Str::uuid();
        $restaurante = Restaurante::create($validated);

        return response()->json($restaurante, 201);
    }

    public function show(Restaurante $restaurante)
    {
        return $restaurante->load('sedes');
    }

    public function update(Request $request, Restaurante $restaurante)
    {
        $validated = $request->validate([
            'nombre' => 'string|max:100',
            'logo' => 'nullable|string|max:255',
            'activo' => 'boolean'
        ]);

        $restaurante->update($validated);
        return response()->json($restaurante);
    }

    public function destroy(Restaurante $restaurante)
    {
        $restaurante->delete();
        return response()->json(null, 204);
    }
} 