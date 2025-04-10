<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\LogTrait;

class SedeController extends Controller
{
    use LogTrait;

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de sedes');
            $sedes = Sede::with(['restaurante', 'mesas', 'combinacionesMesas'])->get();
            $this->logInfo('Lista de sedes obtenida', ['total' => $sedes->count()]);
            return $sedes;
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de sedes', $e);
            return response()->json(['message' => 'Error al obtener las sedes'], 500);
        }
    }

    public function activas()
    {
        try {
            $this->logInfo('Obteniendo sedes activas');
            $sedes = Sede::with(['restaurante'])
                ->where('activo', true)
                ->get();
            $this->logInfo('Sedes activas obtenidas', ['total' => $sedes->count()]);
            return $sedes;
        } catch (\Exception $e) {
            $this->logError('Error al obtener sedes activas', $e);
            return response()->json(['message' => 'Error al obtener las sedes activas'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->logInfo('Iniciando creación de sede', $request->except(['image_card', 'image_banner']));

            $this->logInfo('Archivos recibidos', [
                'image_card' => $request->file('image_card'),
                'image_banner' => $request->file('image_banner')
            ]);

            // Validar campos de texto
            $validated = $request->validate([
                'nombre' => 'required|string|max:100',
                'direccion' => 'required|string',
                'ciudad' => 'required|string|max:100',
                'telefono' => 'nullable|string|regex:/^[0-9]{8,15}$/',
                'admin_nombre' => 'nullable|string|max:100',
                'admin_email' => 'nullable|email|max:100',
                'descripcion' => 'nullable|string',
                'activo' => 'boolean|in:0,1,true,false,"true","false"'
            ]);

            // restaurante_id es 1 por defecto
            $validated['restaurante_id'] = 1;
            
            // Validar y guardar image_card si viene
            if ($request->hasFile('image_card')) {
                $file = $request->file('image_card');
                if (!$file->isValid() || !$file->isFile()) {
                    throw new \Exception('Imagen de tarjeta inválida');
                }
                $filename = 'card_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images'), $filename);
                $validated['image_card'] = '/assets/images/' . $filename;
            }

            // Validar y guardar image_banner si viene
            if ($request->hasFile('image_banner')) {
                $file = $request->file('image_banner');
                if (!$file->isValid() || !$file->isFile()) {
                    throw new \Exception('Imagen de banner inválida');
                }
                $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images'), $filename);
                $validated['image_banner'] = '/assets/images/' . $filename;
            }

            $sede = Sede::create($validated);

            $this->logInfo('Sede creada exitosamente', ['sede_id' => $sede->id]);

            return response()->json($sede, 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear sede', $e);
            return response()->json(['message' => 'Error al crear la sede'], 500);
        }
    }

    public function show(Sede $sede)
    {
        try {
            $this->logInfo('Obteniendo detalles de sede', ['sede_id' => $sede->id]);
            return $sede->load(['restaurante', 'mesas', 'combinacionesMesas']);
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de sede', $e);
            return response()->json(['message' => 'Error al obtener los detalles de la sede'], 500);
        }
    }

    public function update(Request $request, Sede $sede)
    {
        try {
            $this->logInfo('Iniciando actualización de sede', [
                'sede_id' => $sede->id,
                'datos' => $request->except(['image_card', 'image_banner'])
            ]);

            $this->logInfo('Archivos recibidos', [
                'image_card' => $request->file('image_card'),
                'image_banner' => $request->file('image_banner')
            ]);

            // Validar campos de texto
            $validated = $request->validate([
                'restaurante_id' => 'exists:restaurantes,id',
                'nombre' => 'string|max:100',
                'direccion' => 'string',
                'ciudad' => 'string|max:100',
                'telefono' => 'nullable|string|regex:/^[0-9]{8,15}$/',
                'admin_nombre' => 'nullable|string|max:100',
                'admin_email' => 'nullable|email|max:100',
                'descripcion' => 'nullable|string',
                'activo' => 'boolean|in:0,1,true,false,"true","false"'
            ]);

            // Validar y guardar image_card si viene
            if ($request->hasFile('image_card')) {
                $file = $request->file('image_card');
                if (!$file->isValid() || !$file->isFile()) {
                    throw new \Exception('Imagen de tarjeta inválida');
                }
                $filename = 'card_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images'), $filename);
                $validated['image_card'] = '/assets/images/' . $filename;
            }

            // Validar y guardar image_banner si viene
            if ($request->hasFile('image_banner')) {
                $file = $request->file('image_banner');
                if (!$file->isValid() || !$file->isFile()) {
                    throw new \Exception('Imagen de banner inválida');
                }
                $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images'), $filename);
                $validated['image_banner'] = '/assets/images/' . $filename;
            }

            $sede->update($validated);

            $this->logInfo('Sede actualizada exitosamente', ['sede_id' => $sede->id]);

            return response()->json($sede);
        } catch (\Exception $e) {
            $this->logError('Error al actualizar sede', $e);
            return response()->json(['message' => 'Error al actualizar la sede'], 500);
        }
    }

    public function destroy(Sede $sede)
    {
        try {
            $this->logInfo('Iniciando eliminación de sede', ['sede_id' => $sede->id]);
            $sede->delete();
            $this->logInfo('Sede eliminada exitosamente', ['sede_id' => $sede->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar sede', $e);
            return response()->json(['message' => 'Error al eliminar la sede'], 500);
        }
    }
} 