<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Mesa;
use App\Models\Cliente;
use App\Models\CombinacionMesa;
use App\Services\MesaAssignmentService;
use App\Traits\LogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReservaController extends Controller
{
    use LogTrait;

    protected $mesaAssignmentService;

    public function __construct(MesaAssignmentService $mesaAssignmentService)
    {
        $this->mesaAssignmentService = $mesaAssignmentService;
    }

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de reservas');
            $reservas = Reserva::with(['mesa', 'cliente', 'combinacionMesa'])->get();
            $this->logInfo('Lista de reservas obtenida', ['total' => $reservas->count()]);
            return $reservas;
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de reservas', $e);
            return response()->json(['message' => 'Error al obtener las reservas'], 500);
        }
    }

    public function verificarDisponibilidad(Request $request)
    {
        try {
            $this->logInfo('Iniciando verificación de disponibilidad', $request->all());

            $validated = $request->validate([
                'mesa_id' => 'required|exists:mesas,id',
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i|after:hora_inicio'
            ]);

            $mesa = Mesa::findOrFail($validated['mesa_id']);
            $disponible = $this->mesaAssignmentService->verificarDisponibilidad(
                $mesa,
                $validated['fecha'],
                $validated['hora_inicio'],
                $validated['hora_fin']
            );

            $this->logInfo('Verificación de disponibilidad completada', [
                'mesa_id' => $mesa->id,
                'disponible' => $disponible
            ]);

            return response()->json(['disponible' => $disponible]);
        } catch (\Exception $e) {
            $this->logError('Error al verificar disponibilidad', $e);
            return response()->json(['message' => 'Error al verificar disponibilidad'], 500);
        }
    }

    public function porFecha($fecha)
    {
        try {
            $this->logInfo('Obteniendo reservas por fecha', ['fecha' => $fecha]);
            
            $reservas = Reserva::with(['mesa', 'cliente', 'combinacionMesa'])
                ->where('fecha', $fecha)
                ->get();

            $this->logInfo('Reservas por fecha obtenidas', [
                'fecha' => $fecha,
                'total' => $reservas->count()
            ]);

            return $reservas;
        } catch (\Exception $e) {
            $this->logError('Error al obtener reservas por fecha', $e);
            return response()->json(['message' => 'Error al obtener las reservas'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->logInfo('Iniciando creación de reserva', $request->all());

            $validated = $request->validate([
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
                'num_personas' => 'required|integer|min:1',
                'sede_id' => 'required|exists:sedes,id',
                'cliente_id' => 'required|exists:clientes,id',
                'ubicacion' => 'nullable|in:interior,exterior',
                'notas' => 'nullable|string'
            ]);

            // Calcular hora fin basada en la duración del turno
            $mesa = Mesa::where('sede_id', $validated['sede_id'])
                ->where('activa', true)
                ->where('estado', 'disponible')
                ->first();

            if (!$mesa) {
                return response()->json(['message' => 'No hay mesas disponibles en esta sede'], 422);
            }

            $horaFin = Carbon::parse($validated['hora_inicio'])
                ->addMinutes($mesa->duracion_turno_minutos)
                ->format('H:i');

            // Buscar mesas disponibles
            $mesasDisponibles = $this->mesaAssignmentService->encontrarMesasDisponibles(
                $validated['fecha'],
                $validated['hora_inicio'],
                $horaFin,
                $validated['num_personas'],
                $validated['sede_id'],
                $validated['ubicacion'] ?? null
            );

            if ($mesasDisponibles->isEmpty()) {
                return response()->json(['message' => 'No hay mesas disponibles para los criterios especificados'], 422);
            }

            $this->logInfo('Mesas disponibles encontradas', ['mesas' => $mesasDisponibles]);
            // Obtener el id de la mesa
            $mesaId = $mesasDisponibles->first()->id;
            $this->logInfo('Mesa disponible encontrada', ['mesa_id' => $mesaId]);

            // Crear la reserva
            $reserva = Reserva::create([
                'mesa_id' => $mesaId,
                'fecha' => $validated['fecha'],
                'hora_inicio' => $validated['hora_inicio'],
                'hora_fin' => $horaFin,
                'num_personas' => $validated['num_personas'],
                'cliente_id' => $validated['cliente_id'],
                'notas' => $validated['notas'] ?? null,
                'estado' => 'confirmada'
            ]);

            // Asignar la primera mesa disponible
            $this->mesaAssignmentService->asignarMesa($reserva, $mesasDisponibles->first());

            $this->logInfo('Reserva creada exitosamente', ['reserva_id' => $reserva->id]);

            return response()->json($reserva->load(['mesa', 'cliente', 'combinacionMesa']), 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear reserva', $e);
            return response()->json(['message' => 'Error al crear la reserva'], 500);
        }
    }

    public function show(Reserva $reserva)
    {
        try {
            $this->logInfo('Obteniendo detalles de reserva', ['reserva_id' => $reserva->id]);
            return $reserva->load(['mesa', 'cliente', 'combinacionMesa']);
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de reserva', $e);
            return response()->json(['message' => 'Error al obtener los detalles de la reserva'], 500);
        }
    }

    public function update(Request $request, Reserva $reserva)
    {
        try {
            $this->logInfo('Iniciando actualización de reserva', [
                'reserva_id' => $reserva->id,
                'datos' => $request->all()
            ]);

            $validated = $request->validate([
                'fecha' => 'date',
                'hora_inicio' => 'date_format:H:i',
                'num_personas' => 'integer|min:1',
                'estado' => 'in:pendiente,confirmada,en_progreso,completada,cancelada,no_show',
                'notas' => 'nullable|string'
            ]);

            // Si se está cancelando la reserva, liberar la mesa
            if (isset($validated['estado']) && $validated['estado'] === 'cancelada') {
                if ($reserva->mesa) {
                    $reserva->mesa->update(['estado' => 'disponible']);
                } elseif ($reserva->combinacionMesa) {
                    foreach ($reserva->combinacionMesa->mesas as $mesa) {
                        $mesa->update(['estado' => 'disponible']);
                    }
                }
            }

            $reserva->update($validated);

            $this->logInfo('Reserva actualizada exitosamente', ['reserva_id' => $reserva->id]);

            return response()->json($reserva->load(['mesa', 'cliente', 'combinacionMesa']));
        } catch (\Exception $e) {
            $this->logError('Error al actualizar reserva', $e);
            return response()->json(['message' => 'Error al actualizar la reserva'], 500);
        }
    }

    public function destroy(Reserva $reserva)
    {
        try {
            $this->logInfo('Iniciando cancelación de reserva', ['reserva_id' => $reserva->id]);

            // Liberar la mesa antes de eliminar la reserva
            if ($reserva->mesa) {
                $reserva->mesa->update(['estado' => 'disponible']);
            } elseif ($reserva->combinacionMesa) {
                foreach ($reserva->combinacionMesa->mesas as $mesa) {
                    $mesa->update(['estado' => 'disponible']);
                }
            }

            $reserva->delete();

            $this->logInfo('Reserva eliminada exitosamente', ['reserva_id' => $reserva->id]);

            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar reserva', $e);
            return response()->json(['message' => 'Error al eliminar la reserva'], 500);
        }
    }

    public function confirmar(Reserva $reserva)
    {
        $reserva->update(['estado' => 'confirmada']);
        return response()->json($reserva);
    }

    public function cancelar(Reserva $reserva)
    {
        $reserva->update(['estado' => 'cancelada']);
        return response()->json($reserva);  
    }

    //Cancelar reserva con el id de la reserva
    public function cancelarReserva($id)
    {
        $reserva = Reserva::findOrFail($id);
        $reserva->update(['estado' => 'cancelada']);
        $reserva->deleted_at = now();
        $reserva->save();
        return response()->json($reserva);
    }

    /**
     * Busca mesas disponibles según los criterios especificados
     */
    public function buscarMesasDisponibles(Request $request)
    {
        try {
            $this->logInfo('Iniciando búsqueda de mesas disponibles', $request->all());

            $validated = $request->validate([
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
                'num_personas' => 'required|integer|min:1',
                'sede_id' => 'required|exists:sedes,id'
            ]);

            $mesasDisponibles = $this->mesaAssignmentService->encontrarMesasDisponibles(
                $validated['fecha'],
                $validated['hora_inicio'],
                $validated['hora_fin'],
                $validated['num_personas'],
                $validated['sede_id']
            );

            $this->logInfo('Búsqueda de mesas completada', [
                'mesas_encontradas' => $mesasDisponibles->count()
            ]);

            return response()->json([
                'mesas_disponibles' => $mesasDisponibles
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al buscar mesas disponibles', $e);
            return response()->json(['message' => 'Error al buscar mesas disponibles'], 500);
        }
    }

    /**
     * Verifica si una combinación de mesas está disponible
     */
    public function verificarCombinacion(Request $request)
    {
        try {
            $this->logInfo('Iniciando verificación de combinación', $request->all());

            $validated = $request->validate([
                'combinacion_id' => 'required|exists:combinaciones_mesas,id',
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i|after:hora_inicio'
            ]);

            $combinacion = CombinacionMesa::findOrFail($validated['combinacion_id']);
            $disponible = $this->mesaAssignmentService->verificarDisponibilidad(
                $combinacion,
                $validated['fecha'],
                $validated['hora_inicio'],
                $validated['hora_fin']
            );

            $this->logInfo('Verificación de combinación completada', [
                'combinacion_id' => $combinacion->id,
                'disponible' => $disponible
            ]);

            return response()->json([
                'disponible' => $disponible
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al verificar combinación', $e);
            return response()->json(['message' => 'Error al verificar la combinación'], 500);
        }
    }

    /**
     * Obtiene los horarios de servicio para una mesa específica
     */
    public function obtenerHorariosServicio(Mesa $mesa)
    {
        try {
            $this->logInfo('Obteniendo horarios de servicio', [
                'mesa_id' => $mesa->id,
                'fecha' => request('fecha')
            ]);

            $fecha = request('fecha', now()->format('Y-m-d'));
            $horarios = $this->mesaAssignmentService->obtenerHorarioServicio($mesa, $fecha);

            $this->logInfo('Horarios de servicio obtenidos', [
                'mesa_id' => $mesa->id,
                'horarios' => $horarios
            ]);

            return response()->json([
                'mesa_id' => $mesa->id,
                'fecha' => $fecha,
                'horarios' => $horarios
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al obtener horarios de servicio', $e);
            return response()->json(['message' => 'Error al obtener los horarios de servicio'], 500);
        }
    }

    /**
     * Determina el tipo de turno para una fecha y hora específicas
     */
    public function determinarTipoTurno($fecha, $hora)
    {
        try {
            $this->logInfo('Determinando tipo de turno', [
                'fecha' => $fecha,
                'hora' => $hora
            ]);

            $tipoTurno = $this->mesaAssignmentService->determinarTipoTurno($fecha, $hora);

            $this->logInfo('Tipo de turno determinado', [
                'fecha' => $fecha,
                'hora' => $hora,
                'tipo_turno' => $tipoTurno
            ]);

            return response()->json([
                'fecha' => $fecha,
                'hora' => $hora,
                'tipo_turno' => $tipoTurno
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al determinar tipo de turno', $e);
            return response()->json(['message' => 'Error al determinar el tipo de turno'], 500);
        }
    }
} 