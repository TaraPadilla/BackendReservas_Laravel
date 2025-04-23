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
use App\Models\CombinacionMesa;
use App\Models\HorarioSemanal;


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
                'capacidad_max' => 'required|integer|gte:capacidad_min',
                'duracion_turno_minutos' => 'required|integer|min:30',
                'ubicacion' => 'required|in:interior,exterior',
                'forma' => 'required|in:rectangulo,cuadrado,circulo,ovalada',
                'posicion_x' => 'nullable|numeric',
                'posicion_y' => 'nullable|numeric',
                'activa' => 'boolean'
            ]);

            $mesa = Mesa::create($validated);

            $this->logInfo('Mesa creada exitosamente', ['mesa_id' => $mesa->id]);

            return response()->json($mesa->load(['sede', 'horarios']), 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                $this->logError('Intento de duplicado al crear mesa', $e);
                return response()->json([
                    'message' => 'Número de mesa ya creado previamente.'
                ], 409); // 409 Conflict
            }
        
            $this->logError('Error al crear mesa', $e);
            return response()->json(['message' => 'Error al crear la mesa'], 500);
        
        } catch (\Exception $e) {
            $this->logError('Error inesperado al crear mesa', $e);
            return response()->json(['message' => 'Error inesperado al crear la mesa'], 500);
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
                'capacidad_max' => 'integer|gte:capacidad_min',
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
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                $this->logError('Intento de duplicado al actualizar mesa', $e);
                return response()->json([
                    'message' => 'Número de mesa ya creado previamente.'
                ], 409);
            }
    
            $this->logError('Error al actualizar mesa (query)', $e);
            return response()->json(['message' => 'Error al actualizar la mesa'], 500);
    
        } catch (\Exception $e) {
            $this->logError('Error inesperado al actualizar mesa', $e);
            return response()->json(['message' => 'Error inesperado al actualizar la mesa'], 500);
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

             //Obtener las mesas activas de la sede
             $mesas = Mesa::where('sede_id', $sedeId)
                ->where('activa', true)
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

    private function filtrarHorariosPasados(array $horarios): array
    {
        //$this->logInfo('Filtrando horarios pasados', ['horarios' => $horarios]);
        if (!$this->esHoy()) {
            //$this->logInfo('No es hoy, devolviendo todos los horarios', ['horarios' => $horarios]);
            return $horarios;
        }
    
        //$this->logInfo('Zona horaria actual', ['timezone' => date_default_timezone_get()]);
        //$this->logInfo('Hora actual', ['hora_actual' => date('H:i')]);

        $horaActual = (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->format('H:i');
        //$this->logInfo('Hora actual', ['hora_actual' => $horaActual]);  
    
        return array_values(array_filter($horarios, fn($hora) => $hora > $horaActual));
    }

    private function esHoy(): bool
    {
        return request()->input('fecha') === now()->toDateString();
    }

    private function obtenerHorariosSolapados(
        array $horariosDefinidos,
        string $horaInicioReserva,
        string $horaFinReserva,
        array $horasReservadas = [],
        int $duracionMinutos = null
    ): array {
        $inicio = Carbon::createFromFormat('H:i:s', $horaInicioReserva);
        $fin = Carbon::createFromFormat('H:i:s', $horaFinReserva)->subMinute(); // opcional, para excluir el fin exacto
    
        $ocupadosPorReserva = collect($horariosDefinidos)->filter(function ($hora) use ($inicio, $fin) {
            $horaComparar = Carbon::createFromFormat('H:i:s', $hora);
            return $horaComparar->between($inicio, $fin);
        })->values()->all();
    
        $bloqueadosPorSolapamiento = [];
    
        // Validar contra otras reservas en conflicto
        if (!empty($horasReservadas) && $duracionMinutos !== null) {
            foreach ($horariosDefinidos as $horaCandidata) {
                $inicioCandidato = Carbon::createFromFormat('H:i:s', $horaCandidata);
                $finCandidato = (clone $inicioCandidato)->addMinutes($duracionMinutos);
    
                foreach ($horasReservadas as $horaReservada) {
                    $inicioReserva = Carbon::createFromFormat('H:i:s', $horaReservada);
                    $finReserva = (clone $inicioReserva)->addMinutes($duracionMinutos);
    
                    if (
                        $inicioCandidato->lt($finReserva) &&
                        $finCandidato->gt($inicioReserva)
                    ) {
                        $bloqueadosPorSolapamiento[] = $horaCandidata;
    
                        $this->logInfo('🟡 Conflicto con otra reserva', [
                            'hora_candidata' => $horaCandidata,
                            'inicio_candidato' => $inicioCandidato->format('H:i'),
                            'fin_candidato' => $finCandidato->format('H:i'),
                            'inicio_reserva' => $inicioReserva->format('H:i'),
                            'fin_reserva' => $finReserva->format('H:i'),
                        ]);
                        break;
                    }
                }
            }
        }
    
        // Validar contra esta misma reserva
        if ($duracionMinutos !== null) {
            foreach ($horariosDefinidos as $horaCandidata) {
                $inicioCandidato = Carbon::createFromFormat('H:i:s', $horaCandidata);
                $finCandidato = (clone $inicioCandidato)->addMinutes($duracionMinutos);
    
                if (
                    $inicioCandidato->lt($fin) &&
                    $finCandidato->gt($inicio)
                ) {
                    $bloqueadosPorSolapamiento[] = $horaCandidata;
    
                    $this->logInfo('🔴 Conflicto con la propia reserva', [
                        'hora_candidata' => $horaCandidata,
                        'inicio_candidato' => $inicioCandidato->format('H:i'),
                        'fin_candidato' => $finCandidato->format('H:i'),
                        'inicio_reserva' => $inicio->format('H:i'),
                        'fin_reserva' => $fin->format('H:i'),
                    ]);
                }
            }
        }
    
        return array_values(array_unique(array_merge($ocupadosPorReserva, $bloqueadosPorSolapamiento)));
    }
    

    public function obtenerSimulacionDisponibilidad(Request $request, $sedeId)
    {
        try {
            $fecha = $request->input('fecha');
            $this->logInfo('Fecha de simulación', ['fecha' => $fecha]);
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
                ->where('estado', 'disponible')
                ->with(['horarios' => function ($query) use ($turno) {
                    $query->where('tipo_turno', $turno);
                }])
                ->get();
    
            // Obtener combinaciones activas con horarios de la mesa principal
            $combinaciones = CombinacionMesa::with([
                'mesaPrincipal.horarios' => function ($query) use ($turno) {
                    $query->where('tipo_turno', $turno);
                }
            ])
            ->where('sede_id', $sedeId)
            ->where('activa', true)
            ->get();
    
            // Obtener reservas directas
            $reservas = Reserva::whereIn('mesa_id', $mesas->pluck('id'))
                ->whereDate('fecha', $fecha)
                ->where('estado', 'confirmada')
                ->get();
    
            // Agrupar horarios ocupados por mesa directa
            $horariosOcupadosUnificados = [];

            foreach ($reservas as $reserva) {
                $mesaId = $reserva->mesa_id;
                $mesa = $mesas->firstWhere('id', $mesaId);
            
                if (!$mesa || !$mesa->horarios) continue;
            
                $horariosDefinidos = $mesa->horarios->pluck('hora')->toArray();
                $horariosSolapados = $this->obtenerHorariosSolapados($horariosDefinidos, $reserva->hora_inicio, $reserva->hora_fin);
            
                foreach ($horariosSolapados as $hora) {
                    $horariosOcupadosUnificados[$mesaId][] = $hora;
                }
            }
            
            // Obtener reservas por combinaciones
            $reservasCombinadas = Reserva::whereNotNull('combinacion_mesa_id')
                ->whereDate('fecha', $fecha)
                ->where('estado', 'confirmada')
                ->with('combinacionMesa')
                ->get();
    
            foreach ($reservasCombinadas as $reserva) {
                $combinacion = $reserva->combinacionMesa;
            
                if (!$combinacion) continue;
            
                $this->logInfo("💡 Procesando reserva combinada", [
                    'reserva_id' => $reserva->id,
                    'combinacion_id' => $combinacion->id ?? null,
                    'mesas_combinadas' => $combinacion->mesas_combinadas_ids ?? []
                ]);
            
                // Obtener horarios definidos en la mesa principal
                $horariosDefinidos = collect($combinacion->mesaPrincipal->horarios)->pluck('hora')->toArray();
            
                // Obtener horarios ya ocupados por esta combinación (si existen)
                $horasReservadas = $reservasCombinadas
                    ->filter(fn($r) => $r->combinacion_mesa_id === $combinacion->id && $r->id !== $reserva->id)
                    ->pluck('hora_inicio')
                    ->toArray();
            
                // Duración del turno de la combinación
                $duracion = $combinacion->duracion_turno_minutos;
                //log duracion
                $this->logInfo("Duración del turno de la combinación", ['duracion' => $duracion, 'reserva_id' => $reserva->id]);
            
                // Obtener todos los horarios que deben marcarse como ocupados
                $horariosSolapados = $this->obtenerHorariosSolapados(
                    $horariosDefinidos,
                    $reserva->hora_inicio,
                    $reserva->hora_fin,
                    $horasReservadas,
                    $duracion
                );
            
                // Marcar horarios como ocupados en combinación y sus mesas
                foreach ($horariosSolapados as $hora) {
                    $horariosOcupadosUnificados[$combinacion->id][] = $hora;
                    $horariosOcupadosUnificados[$combinacion->mesa_id][] = $hora;
                    foreach ($combinacion->obtenerMesasCombinadas() as $mesa) {
                        $horariosOcupadosUnificados[$mesa->id][] = $hora;
                    }
                }
            }
                
    
            // Eliminar duplicados
            foreach ($horariosOcupadosUnificados as $id => $horas) {
                $horariosOcupadosUnificados[$id] = array_values(array_unique($horas));
            }

            // Marcar como ocupados en la combinación todos los horarios ocupados de sus mesas
            foreach ($combinaciones as $combinacion) {
                $mesaPrincipalId = $combinacion->mesa_id;
                $mesasInvolucradas = array_merge([$mesaPrincipalId], $combinacion->mesas_combinadas_ids ?? []);

                $horariosOcupados = [];

                foreach ($mesasInvolucradas as $mesaId) {
                    if (!empty($horariosOcupadosUnificados[$mesaId])) {
                        $horariosOcupados = array_merge($horariosOcupados, $horariosOcupadosUnificados[$mesaId]);
                    }
                }

                if (!empty($horariosOcupados)) {
                    $horariosOcupadosUnificados[$combinacion->id] = array_values(array_unique(
                        array_merge($horariosOcupadosUnificados[$combinacion->id] ?? [], $horariosOcupados)
                    ));
                }
            }

            $horariosPorDia = HorarioSemanal::where('sede_id', $sedeId)
                ->get()
                ->keyBy('day_of_week');

            // Formatear mesas
            $mesasFormateadas = $mesas->map(function ($mesa) use ($fecha, $turno, $sedeId, $horariosPorDia) {
                $horariosOriginales = $mesa->horarios->pluck('hora')->toArray();
                $horariosValidos = $this->filtrarHorariosSegunHorarioSemanal($horariosOriginales, $fecha, $turno, $sedeId, $horariosPorDia);
                $horariosVisibles = $this->filtrarHorariosPasados($horariosValidos);
            
                return [
                    'id' => $mesa->id,
                    'numero' => $mesa->numero,
                    'capacidad_min' => $mesa->capacidad_min,
                    'capacidad_max' => $mesa->capacidad_max,
                    'combinable' => $mesa->combinable,
                    'es_combinacion' => false,
                    'horarios' => $horariosVisibles
                ];
            });
    
            // Formatear combinaciones
            $combinacionesFormateadas = $combinaciones->map(function ($combinacion) use ($fecha, $turno, $sedeId, $horariosPorDia) {
                $horariosOriginales = $combinacion->mesaPrincipal->horarios->pluck('hora')->toArray();
                $horariosValidos = $this->filtrarHorariosSegunHorarioSemanal($horariosOriginales, $fecha, $turno, $sedeId, $horariosPorDia);
                $horariosVisibles = $this->filtrarHorariosPasados($horariosValidos);
            
                return [
                    'id' => $combinacion->id,
                    'numero' => $combinacion->id,
                    'capacidad_min' => $combinacion->capacidad_min,
                    'capacidad_max' => $combinacion->capacidad_max,
                    'combinable' => false,
                    'es_combinacion' => true,
                    'horarios' => $horariosVisibles
                ];
            });
            
    
            // Unir mesas + combinaciones
            $mesasYCombinaciones = $mesasFormateadas->merge($combinacionesFormateadas)->values();
            $this->logInfo('Mesas y combinaciones', ['mesas' => $mesasYCombinaciones]);
            $this->logInfo('Horarios ocupados', ['horarios' => $horariosOcupadosUnificados]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'mesas' => $mesasYCombinaciones,
                    'horarios_ocupados' => $horariosOcupadosUnificados
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

    private function filtrarHorariosSegunHorarioSemanal(array $horarios, string $fecha, string $turno, int $sedeId, $horariosPorDia): array
    {
        //$this->logInfo('Filtrando horarios según horario semanal', compact('horarios', 'fecha', 'turno', 'sedeId'));
    
        if (!\App\Models\HorarioSemanal::estaAbierto($fecha, $turno, $sedeId)) {
            //$this->logInfo('⛔ Turno no disponible según horario semanal', compact('fecha', 'turno', 'sedeId'));
            return [];
        }
    
        $diaSemana = \Carbon\Carbon::parse($fecha)->dayOfWeek;
        $horario = $horariosPorDia->get($diaSemana);
    
        $inicio = $turno === 'comida' ? $horario->lunch_start : $horario->dinner_start;
        $fin    = $turno === 'comida' ? $horario->lunch_end   : $horario->dinner_end;
    
        try {
            $inicioC = \Carbon\Carbon::createFromFormat('H:i', $inicio->format('H:i'))->setDate(2000, 1, 1);
            $finC    = \Carbon\Carbon::createFromFormat('H:i', $fin->format('H:i'))->setDate(2000, 1, 1);
            if ($finC->lte($inicioC)) $finC->addDay();
        } catch (\Exception $e) {
            \Log::error('❌ Error al procesar rango del turno', ['inicio' => $inicio, 'fin' => $fin, 'error' => $e->getMessage()]);
            return [];
        }
    
        $filtrados = array_filter($horarios, function ($hora) use ($inicioC, $finC) {
            try {
                $limpia = substr(trim((string) $hora), 0, 5);
                $h = \Carbon\Carbon::createFromFormat('H:i', $limpia)->setDate(2000, 1, 1);
                return $h->between($inicioC, $finC);
            } catch (\Exception $e) {
                return false;
            }
        });
    
        return array_values($filtrados);
    }
    
} 