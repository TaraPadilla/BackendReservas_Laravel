<?php

namespace App\Http\Controllers;

use App\Models\SedeTextosLegales;
use Illuminate\Http\Request;
use App\Traits\LogTrait;

class SedeTextosLegalesController extends Controller
{
    use LogTrait;

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de textos legales de sedes');
            $textos = SedeTextosLegales::with('sede')->get();
            $this->logInfo('Lista obtenida', ['total' => $textos->count()]);
            return $textos;
        } catch (\Exception $e) {
            $this->logError('Error al obtener textos legales de sedes', $e);
            return response()->json(['message' => 'Error al obtener los textos legales de sedes'], 500);
        }
    }

    public function store(Request $request)
    {
        $this->logInfo('Contenido recibido RAW', ['raw' => file_get_contents('php://input')]);

        try {
            $this->logInfo('Iniciando creación de textos legales para sede', $request->all());
            $validated = $request->validate([
                'sede_id' => 'required|integer|exists:sedes,id|unique:sede_textos_legales,sede_id',
                'aviso_legal' => 'nullable|string',
                'politica_privacidad' => 'nullable|string',
                'politica_cookies' => 'nullable|string',
                'texto_reserva_final' => 'nullable|string',
            ]);
            $texto = SedeTextosLegales::create($validated);
            $this->logInfo('Textos legales creados', ['id' => $texto->id]);
            return response()->json($texto, 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear textos legales', $e);
            return response()->json(['message' => 'Error al crear los textos legales de la sede'], 500);
        }
    }

    public function show($id)
    {
        try {
            //Enviamos el id de la sede
            $texto = SedeTextosLegales::with('sede')->findOrFail($id);
            return $texto;
        } catch (\Exception $e) {
            $this->logError('Error al obtener texto legal', $e);
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            //Enviamos el id de la sede
            $this->logInfo('Actualizando textos legales para sede', ['id' => $id, 'data' => $request->all()]);
            $texto = SedeTextosLegales::findOrFail($id);
            $validated = $request->validate([
                'aviso_legal' => 'nullable|string',
                'politica_privacidad' => 'nullable|string',
                'politica_cookies' => 'nullable|string',
                'texto_reserva_final' => 'nullable|string',
            ]);
            $texto->update($validated);
            $this->logInfo('Textos legales actualizados', ['id' => $texto->id]);
            return response()->json($texto);
        } catch (\Exception $e) {
            $this->logError('Error al actualizar textos legales', $e);
            return response()->json(['message' => 'Error al actualizar los textos legales de la sede'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            //Enviamos el id de la sede
            $this->logInfo('Eliminando textos legales para sede', ['id' => $id]);
            $texto = SedeTextosLegales::findOrFail($id);
            $texto->delete();
            $this->logInfo('Textos legales eliminados', ['id' => $id]);
            return response()->json(['message' => 'Registro eliminado correctamente']);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar textos legales', $e);
            return response()->json(['message' => 'Error al eliminar los textos legales de la sede'], 500);
        }
    }

    public function porSede($sedeId)
    {
        try {
            $this->logInfo('Buscando textos legales para sede', ['sede_id' => $sedeId]);
            //Enviamos el id de la sede
            $texto = SedeTextosLegales::where('sede_id', $sedeId)->first(); 


            if (!$texto) {
                return response()->json([
                    'message' => 'No se encontraron textos legales para esta sede'
                ], 404);
            }

            return response()->json([
                'sede_id' => $texto->sede_id,
                'aviso_legal' => $texto->aviso_legal,
                'politica_privacidad' => $texto->politica_privacidad,
                'politica_cookies' => $texto->politica_cookies,
                'texto_reserva_final' => $texto->texto_reserva_final,
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al obtener textos por sede', $e);
            return response()->json(['message' => 'Error interno al consultar los textos'], 500);
        }
    }

    public function updatePorSede($sedeId, Request $request)
    {
        $validated = $request->validate([
            'aviso_legal' => 'nullable|string',
            'politica_privacidad' => 'nullable|string',
            'politica_cookies' => 'nullable|string',
            'texto_reserva_final' => 'nullable|string',
        ]);

        $texto = SedeTextosLegales::where('sede_id', $sedeId)->firstOrFail();
        $texto->update($validated);

        return response()->json($texto);
    }


}
