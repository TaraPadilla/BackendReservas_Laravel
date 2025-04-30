<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Mesa;
use App\Models\Cliente;
use App\Models\CombinacionMesa;
use App\Services\MesaAssignmentService;
use App\Services\EmailService;
use App\Traits\LogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Models\SedeTextosLegales;
use App\Http\Controllers\OcupacionController;


class ReservaController extends Controller
{
    use LogTrait;

    protected $mesaAssignmentService;
    protected $emailService;

    public function __construct(MesaAssignmentService $mesaAssignmentService, EmailService $emailService)
    {
        $this->mesaAssignmentService = $mesaAssignmentService;
        $this->emailService = $emailService;
    }

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de reservas');
            $reservas = Reserva::with(['mesa', 'cliente', 'combinacionMesa'])->get();
            $this->logInfo('Lista de reservas obtenida', ['total' => $reservas->count()]);
            // Agregar mesas_combinadas manualmente si aplica
            foreach ($reservas as $reserva) {
                if ($reserva->combinacionMesa) {
                    $mesas = $reserva->combinacionMesa->obtenerMesasCombinadas()
                        ->map(fn($mesa) => ['id' => $mesa->id, 'numero' => $mesa->numero])
                        ->values();
                    $reserva->combinacionMesa->mesas_combinadas = $mesas;
                }
            }

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

            $mesa = Mesa::where('sede_id', $validated['sede_id'])
                ->where('activa', true)
                ->where('estado', 'disponible')
                ->first();

            if (!$mesa) {
                return response()->json(['message' => 'No hay mesas disponibles en esta sede'], 422);
            }

            // Buscar mesas individuales
            $mesasDisponibles = $this->mesaAssignmentService->encontrarMesasDisponibles(
                $validated['fecha'],
                $validated['hora_inicio'],
                $validated['num_personas'],
                $validated['sede_id'],
                $validated['ubicacion'] ?? null
            );

            // Si no hay individuales, buscar combinaciones
            if ($mesasDisponibles->isEmpty()) {
                $this->logWarning('No se encontraron mesas individuales, buscando combinaciones');

                $mesasDisponibles = $this->mesaAssignmentService->buscarCombinacionesDisponibles(
                    $validated['fecha'],
                    $validated['hora_inicio'],
                    $validated['num_personas'],
                    $validated['sede_id'],
                    $validated['ubicacion'] ?? null
                );
            }

            if ($mesasDisponibles->isEmpty()) {
                return response()->json(['message' => 'No hay mesas disponibles para los criterios especificados'], 422);
            }

            $this->logInfo('Mesas disponibles encontradas', ['mesas' => $mesasDisponibles]);
            $mesaSeleccionada = $mesasDisponibles->first();

            if ($mesaSeleccionada instanceof \App\Models\CombinacionMesa) {
                $this->logInfo('Creando reserva con combinación', [
                    'combinacion_id' => $mesaSeleccionada->id,
                    'mesa_principal' => $mesaSeleccionada->mesa_id
                ]);

                //Calcula la hora fin de la combinación
                $horaFin = Carbon::parse($validated['hora_inicio'])
                    ->addMinutes($mesaSeleccionada->duracion_turno_minutos)
                    ->format('H:i');

                $reserva = Reserva::create([
                    'mesa_id' => $mesaSeleccionada->mesa_id, // <- esta es la mesa real
                    'combinacion_mesa_id' => $mesaSeleccionada->id,
                    'fecha' => $validated['fecha'],
                    'hora_inicio' => $validated['hora_inicio'],
                    'hora_fin' => $horaFin,
                    'num_personas' => $validated['num_personas'],
                    'cliente_id' => $validated['cliente_id'],
                    'notas' => $validated['notas'] ?? null,
                    'estado' => 'confirmada'
                ]);

                //$this->mesaAssignmentService->asignarMesa($reserva, $mesaSeleccionada);
            } else {
                //calcula la hora fin
                $horaFin = Carbon::parse($validated['hora_inicio'])
                    ->addMinutes($mesaSeleccionada->duracion_turno_minutos)
                    ->format('H:i');

                $reserva = Reserva::create([
                    'mesa_id' => $mesaSeleccionada->id,
                    'fecha' => $validated['fecha'],
                    'hora_inicio' => $validated['hora_inicio'],
                    'hora_fin' => $horaFin,
                    'num_personas' => $validated['num_personas'],
                    'cliente_id' => $validated['cliente_id'],
                    'notas' => $validated['notas'] ?? null,
                    'estado' => 'confirmada'
                ]);

                //$this->mesaAssignmentService->asignarMesa($reserva, $mesaSeleccionada);
            }

            $this->logInfo('Reserva creada exitosamente', ['reserva_id' => $reserva->id]);

            // Crear ocupaciones asociadas a la reserva recién creada
            $ocupacionController = new OcupacionController();
            $ocupacionController->crearOcupacionesParaReserva($reserva, $mesaSeleccionada);


            $textos = SedeTextosLegales::where('sede_id', $reserva->sede_id ?? $mesaSeleccionada->sede_id ?? null)->first();

            $responseData = $reserva->load(['mesa', 'cliente', 'combinacionMesa']);
            $responseData->texto_reserva_final = $textos?->texto_reserva_final;
            $response = response()->json($responseData, 201);

            // Enviar correos de confirmación de forma segura (no afecta al cliente si falla)
            try {
                \App\Jobs\EnviarCorreosReservaJob::dispatchSync($reserva);
            } catch (\Throwable $e) {
               //Captura con log normal
               Log::error('Error en envío de correo post-reserva', [
                'reserva_id' => $reserva->id,
                'error' => $e->getMessage()
                ]);
            }

            return $response;

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

    //Cancelar reserva con el id de la reserva del admin
    public function cancelarPorAdmin($id)
    {
        $reserva = Reserva::with(['mesa', 'cliente'])->findOrFail($id);
    
        if ($reserva->estado === 'cancelada') {
            return response()->json([
                'message' => 'La reserva ya fue cancelada previamente.',
                'reserva' => $reserva
            ], 200);
        }
    
        // Marcar la reserva como cancelada
        $reserva->estado = 'cancelada';
        //$reserva->deleted_at = now();
        $reserva->save();
    
        //Cancelar las ocupaciones
        $ocupacionController = new OcupacionController();
        $ocupacionController->cancelarOcupacionesPorReserva($id);   
        // Enviar correo de confirmación de cancelación
        $this->emailService->enviarCorreoCancelacionCliente($reserva);
        $this->emailService->enviarCorreoCancelacionAdmin($reserva);

        return response()->json([
            'message' => 'Reserva cancelada exitosamente por el administrador.',
            'reserva' => $reserva
        ]);
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

    /**
     * Cancela una reserva mediante el enlace enviado por correo
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function cancelarReservaPorEnlace($id)
    {
        try {
            $this->logInfo('Iniciando cancelación de reserva por enlace', ['reserva_id' => $id]);
    
            $reserva = Reserva::findOrFail($id);
    
            // Verificar que la reserva no esté ya cancelada
            if ($reserva->estado === 'cancelada') {
                $this->logWarning('La reserva ya está cancelada', ['reserva_id' => $id]);
                return view('reservas.cancelacion', [
                    'estado' => 'ya_cancelada',
                    'reserva' => $reserva
                ]);
            }
    
            // Validar que se esté cancelando al menos 2 horas antes del inicio
            $fecha = Carbon::parse($reserva->fecha)->toDateString(); // solo "2025-04-13"
            $horaInicio = Carbon::parse("$fecha {$reserva->hora_inicio}");
            $limiteCancelacion = $horaInicio->copy()->subHours(2);
    
            if (now()->greaterThanOrEqualTo($limiteCancelacion)) {
                $this->logWarning('Intento de cancelación fuera de plazo', [
                    'reserva_id' => $id,
                    'hora_inicio' => $horaInicio->toDateTimeString(),
                    'limite' => $limiteCancelacion->toDateTimeString(),
                    'ahora' => now()->toDateTimeString(),
                ]);
    
                return view('reservas.cancelacion', [
                    'estado' => 'fuera_de_tiempo',
                    'reserva' => $reserva
                ]);
            }
    
            // Cancelar la reserva
            $reserva->estado = 'cancelada';
            $reserva->save();
    
            $this->logInfo('Reserva cancelada exitosamente', ['reserva_id' => $id]);

            //Cancelar las ocupaciones
            $ocupacionController = new OcupacionController();
            $ocupacionController->cancelarOcupacionesPorReserva($id);   
            //Busca la sede de la reserva
            $sede = $reserva->mesa->sede;
    
            // Enviar correo de confirmación de cancelación
            $this->emailService->enviarCorreoCancelacionCliente($reserva);
            $this->emailService->enviarCorreoCancelacionAdmin($reserva);

            return view('reservas.cancelacion', [
                'estado' => 'cancelada',
                'reserva' => $reserva,
                'sede' => $sede
            ]);
        } catch (\Exception $e) {
            $this->logError('Error al cancelar reserva por enlace', $e);
            return view('reservas.cancelacion', [
                'estado' => 'error',
                'mensaje' => 'Ha ocurrido un error al cancelar la reserva. Por favor, contacta con el restaurante.'
            ]);
        }
    }
    
} 