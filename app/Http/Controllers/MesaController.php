<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Sede;
use App\Models\HorarioCombinacion;
use App\Models\HorarioMesa;
use App\Models\Horario;
use App\Traits\LogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MesaController extends Controller
{
    use LogTrait;

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de mesas');
            $mesas = Mesa::with(['sede', 'horarios'])->get();
            $this->logInfo('Lista de mesas obtenida', ['total' => $mesas->count()]);
            return $mesas;
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de mesas', $e);
            return response()->json(['message' => 'Error al obtener las mesas'], 500);
        }
    }

    public function disponibles(Request $request)
    {
        try {
            $this->logInfo('Obteniendo mesas disponibles', $request->all());

            $validated = $request->validate([
                'sede_id' => 'required|exists:sedes,id',
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
                'num_personas' => 'required|integer|min:1',
                'ubicacion' => 'nullable|in:interior,exterior'
            ]);

            $query = Mesa::where('sede_id', $validated['sede_id'])
                ->where('activa', true)
                ->where('estado', 'disponible')
                ->where('capacidad_min', '<=', $validated['num_personas'])
                ->where('capacidad_max', '>=', $validated['num_personas']);

            if (isset($validated['ubicacion'])) {
                $query->where('ubicacion', $validated['ubicacion']);
            }

            $mesas = $query->whereDoesntHave('reservas', function ($query) use ($validated) {
                $query->where('fecha', $validated['fecha'])
                    ->where('estado', '!=', 'cancelada')
                    ->where(function ($q) use ($validated) {
                        $q->whereBetween('hora_inicio', [$validated['hora_inicio'], $validated['hora_fin']])
                            ->orWhereBetween('hora_fin', [$validated['hora_inicio'], $validated['hora_fin']]);
                    });
            })
            ->with(['sede', 'horarios'])
            ->get();

            $this->logInfo('Mesas disponibles obtenidas', [
                'sede_id' => $validated['sede_id'],
                'total' => $mesas->count()
            ]);

            return response()->json($mesas);
        } catch (\Exception $e) {
            $this->logError('Error al obtener mesas disponibles', $e);
            return response()->json(['message' => 'Error al obtener las mesas disponibles'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->logInfo('Iniciando creación de mesa', $request->all());

            $validated = $request->validate([
                'sede_id' => 'required|exists:sedes,id',
                'numero' => 'required|integer',
                'capacidad_min' => 'required|integer|min:1',
                'capacidad_max' => 'required|integer|gt:capacidad_min',
                'duracion_turno_minutos' => 'required|integer|min:30',
                'ubicacion' => 'required|in:interior,exterior',
                'forma' => 'required|in:rectangulo,cuadrado,circulo,ovalada',
                'estado' => 'required|in:disponible,reservada,ocupada,mantenimiento',
                'posicion_x' => 'nullable|numeric',
                'posicion_y' => 'nullable|numeric',
                'activa' => 'boolean'
            ]);

            $mesa = Mesa::create($validated);

            $this->logInfo('Mesa creada exitosamente', ['mesa_id' => $mesa->id]);

            return response()->json($mesa->load(['sede', 'horarios']), 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear mesa', $e);
            return response()->json(['message' => 'Error al crear la mesa'], 500);
        }
    }

    public function show(Mesa $mesa)
    {
        try {
            $this->logInfo('Obteniendo detalles de mesa', ['mesa_id' => $mesa->id]);
            return $mesa->load(['sede', 'horarios', 'reservas']);
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de mesa', $e);
            return response()->json(['message' => 'Error al obtener los detalles de la mesa'], 500);
        }
    }

    public function update(Request $request, Mesa $mesa)
    {
        try {
            $this->logInfo('Iniciando actualización de mesa', [
                'mesa_id' => $mesa->id,
                'datos' => $request->all()
            ]);

            $validated = $request->validate([
                'sede_id' => 'exists:sedes,id',
                'numero' => 'integer',
                'capacidad_min' => 'integer|min:1',
                'capacidad_max' => 'integer|gt:capacidad_min',
                'duracion_turno_minutos' => 'integer|min:30',
                'ubicacion' => 'in:interior,exterior',
                'forma' => 'in:rectangulo,cuadrado,circulo,ovalada',
                'estado' => 'in:disponible,reservada,ocupada,mantenimiento',
                'posicion_x' => 'nullable|numeric',
                'posicion_y' => 'nullable|numeric',
                'activa' => 'boolean'
            ]);

            $mesa->update($validated);

            $this->logInfo('Mesa actualizada exitosamente', ['mesa_id' => $mesa->id]);

            return response()->json($mesa->load(['sede', 'horarios']));
        } catch (\Exception $e) {
            $this->logError('Error al actualizar mesa', $e);
            return response()->json(['message' => 'Error al actualizar la mesa'], 500);
        }
    }

    public function destroy(Mesa $mesa)
    {
        try {
            $this->logInfo('Iniciando eliminación de mesa', ['mesa_id' => $mesa->id]);

            if ($mesa->reservas()->where('estado', '!=', 'cancelada')->exists()) {
                $this->logWarning('No se puede eliminar la mesa porque tiene reservas activas', [
                    'mesa_id' => $mesa->id
                ]);
                return response()->json([
                    'message' => 'No se puede eliminar la mesa porque tiene reservas activas'
                ], 422);
            }

            $mesa->delete();

            $this->logInfo('Mesa eliminada exitosamente', ['mesa_id' => $mesa->id]);

            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar mesa', $e);
            return response()->json(['message' => 'Error al eliminar la mesa'], 500);
        }
    }

    public function porSede($sedeId)
    {
        try {
            $this->logInfo('Obteniendo mesas por sede', ['sede_id' => $sedeId]);

            $mesas = Mesa::where('sede_id', $sedeId)
                ->with(['sede', 'horarios'])
                ->get();

            $this->logInfo('Mesas por sede obtenidas', [
                'sede_id' => $sedeId,
                'total' => $mesas->count()
            ]);

            return response()->json($mesas);
        } catch (\Exception $e) {
            $this->logError('Error al obtener mesas por sede', $e);
            return response()->json(['message' => 'Error al obtener las mesas de la sede'], 500);
        }
    }

    public function cambiarEstado(Mesa $mesa, Request $request)
    {
        try {
            $this->logInfo('Iniciando cambio de estado de mesa', [
                'mesa_id' => $mesa->id,
                'estado' => $request->estado
            ]);

            $validated = $request->validate([
                'estado' => 'required|in:disponible,reservada,ocupada,mantenimiento'
            ]);

            $mesa->update(['estado' => $validated['estado']]);

            $this->logInfo('Estado de mesa actualizado exitosamente', [
                'mesa_id' => $mesa->id,
                'estado' => $validated['estado']
            ]);

            return response()->json($mesa);
        } catch (\Exception $e) {
            $this->logError('Error al cambiar estado de mesa', $e);
            return response()->json(['message' => 'Error al cambiar el estado de la mesa'], 500);
        }
    }

    public function obtenerSimulacionDisponibilidad(Request $request, $sedeId)
    {
        try {
            $fecha = $request->input('fecha');
            $turno = $request->input('turno');

            if (!$fecha || !$turno) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar fecha y turno.'
                ], 400);
            }

            $this->logInfo('Simulación de disponibilidad iniciada', [
                'sede_id' => $sedeId,
                'fecha' => $fecha,
                'tipo_turno' => $turno
            ]);

            // Obtener mesas activas con horarios del turno
            $mesas = Mesa::where('sede_id', $sedeId)
                ->where('activa', true)
                ->with(['horarios' => function ($query) use ($turno) {
                    $query->where('tipo_turno', $turno);
                }])
                ->get();

            // Obtener reservas activas para esas mesas en la fecha y turno
            $reservas = Reserva::whereIn('mesa_id', $mesas->pluck('id'))
            ->whereDate('fecha', $fecha)
            ->where('estado', 'confirmada')
            ->get();
        
            // Formatear mesas
            $mesasFormateadas = $mesas->map(function ($mesa) {
                return [
                    'id' => $mesa->id,
                    'numero' => $mesa->numero,
                    'capacidad_min' => $mesa->capacidad_min,
                    'capacidad_max' => $mesa->capacidad_max,
                    'combinable' => $mesa->combinable,
                    'horarios' => $mesa->horarios->pluck('hora')->toArray()
                ];
            });

            // Agrupar horarios ocupados por mesa
            $horariosOcupados = $reservas->groupBy('mesa_id')->map(function ($reservasMesa) {
                return $reservasMesa->pluck('hora_inicio')->toArray();
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'mesas' => $mesasFormateadas,
                    'horarios_ocupados' => $horariosOcupados
                ]
            ]);

        } catch (\Exception $e) {
            $this->logError('Error en simulación de disponibilidad', $e);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener la disponibilidad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


} 