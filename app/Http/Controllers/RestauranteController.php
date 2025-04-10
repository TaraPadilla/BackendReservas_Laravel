<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\LogTrait;

class RestauranteController extends Controller
{
    use LogTrait;

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de restaurantes');
            $restaurantes = Restaurante::with('sedes')->get();
            $this->logInfo('Lista de restaurantes obtenida', ['total' => $restaurantes->count()]);
            return $restaurantes;
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de restaurantes', $e);
            return response()->json(['message' => 'Error al obtener los restaurantes'], 500);
        }
    }

    public function activos()
    {
        try {
            $this->logInfo('Obteniendo restaurantes activos');
            $restaurantes = Restaurante::with('sedes')->where('activo', true)->get();
            $this->logInfo('Restaurantes activos obtenidos', ['total' => $restaurantes->count()]);
            return $restaurantes;
        } catch (\Exception $e) {
            $this->logError('Error al obtener restaurantes activos', $e);
            return response()->json(['message' => 'Error al obtener los restaurantes activos'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->logInfo('Iniciando creación de restaurante', $request->all());

            $validated = $request->validate([
                'nombre' => 'required|string|max:100',
                'nombre_admin' => 'nullable|string|max:100',
                'email_admin' => 'nullable|email|max:100',
                'descripcion_corta' => 'nullable|string|max:255',
                'logo' => 'nullable|string|max:255',
                'imagen_banner' => 'nullable|string|max:255',
                'activo' => 'boolean'
            ]);

            $restaurante = Restaurante::create($validated);

            $this->logInfo('Restaurante creado exitosamente', ['restaurante_id' => $restaurante->id]);

            return response()->json($restaurante, 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear restaurante', $e);
            return response()->json(['message' => 'Error al crear el restaurante'], 500);
        }
    }

    public function show(Restaurante $restaurante)
    {
        try {
            $this->logInfo('Obteniendo detalles de restaurante', ['restaurante_id' => $restaurante->id]);
            return $restaurante->load('sedes');
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de restaurante', $e);
            return response()->json(['message' => 'Error al obtener los detalles del restaurante'], 500);
        }
    }

    public function update(Request $request, Restaurante $restaurante)
    {
        try {
            $this->logInfo('Iniciando actualización de restaurante', [
                'restaurante_id' => $restaurante->id,
                'datos' => $request->except(['logo', 'imagen_banner']),
            ]);

            $this->logInfo('Archivos recibidos', [
                'logo' => $request->file('logo'),
                'imagen_banner' => $request->file('imagen_banner')
            ]);
            
    
            // Validar campos de texto
            $validated = $request->validate([
                'nombre' => 'string|max:100',
                'nombre_admin' => 'nullable|string|max:100',
                'email_admin' => 'nullable|email|max:100',
                'descripcion_corta' => 'nullable|string|max:255',
                'activo' => 'boolean'
            ]);
    
            // Validar y guardar logo si viene
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                if (!$file->isValid() || !$file->isFile()) {
                    throw new \Exception('Logo inválido');
                }
                $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images'), $filename);
                $validated['logo'] = '/assets/images/' . $filename;
            }
    
            // Validar y guardar banner si viene
            if ($request->hasFile('imagen_banner')) {
                $file = $request->file('imagen_banner');
                if (!$file->isValid() || !$file->isFile()) {
                    throw new \Exception('Banner inválido');
                }
                $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images'), $filename);
                $validated['imagen_banner'] = '/assets/images/' . $filename;
            }
    
            $restaurante->update($validated);
    
            $this->logInfo('Restaurante actualizado exitosamente', ['restaurante_id' => $restaurante->id]);
    
            return response()->json($restaurante);
        } catch (\Exception $e) {
            $this->logError('Error al actualizar restaurante', $e);
            return response()->json(['message' => 'Error al actualizar el restaurante'], 500);
        }
    }
    

    

    public function destroy(Restaurante $restaurante)
    {
        try {
            $this->logInfo('Iniciando eliminación de restaurante', ['restaurante_id' => $restaurante->id]);
            $restaurante->delete();
            $this->logInfo('Restaurante eliminado exitosamente', ['restaurante_id' => $restaurante->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar restaurante', $e);
            return response()->json(['message' => 'Error al eliminar el restaurante'], 500);
        }
    }
} 