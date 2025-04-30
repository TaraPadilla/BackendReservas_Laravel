<?php

namespace App\Services;

use App\Models\Mesa;
use App\Models\CombinacionMesa;
use App\Models\Ocupacion;
use App\Models\HorarioSemanal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Traits\LogTrait;

class SimulacionDisponibilidadService
{
    use LogTrait;

    private Collection $mesas;
    private Collection $combinaciones;
    private Collection $ocupaciones;
    private array $horariosOcupados = [];

    public function obtenerSimulacionDisponibilidad(Request $request, int $sedeId)
    {
        $this->logInfo('Obteniendo simulación de disponibilidad', ['sede_id' => $sedeId]);

        try {
            $fecha = $request->input('fecha');
            $turno = $request->input('turno');

            if (!$fecha || !$turno) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debe proporcionar fecha y turno.'
                ], 400);
            }

            $this->cargarDatosBasicos($sedeId, $turno);
            $this->filtrarCombinacionesValidas();
            $this->cargarOcupaciones($fecha, $sedeId);
            $this->determinarHorariosOcupados($fecha, $turno, $sedeId);

            $data = $this->generarRespuestaFinal($fecha, $turno, $sedeId);

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en simulación de disponibilidad', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener la disponibilidad.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function filtrarCombinacionesValidas(): void
    {
        $this->combinaciones = $this->combinaciones->filter(function (CombinacionMesa $combinacion) {
            if (!$combinacion->mesaPrincipal 
                || !$combinacion->mesaPrincipal->activa 
                || $combinacion->mesaPrincipal->estado !== 'disponible' 
                || $combinacion->mesaPrincipal->deleted_at !== null) {
                return false;
            }

            $mesas = $combinacion->obtenerMesasCombinadas();

            foreach ($mesas as $mesa) {
                if (!$mesa->activa || $mesa->estado !== 'disponible' || $mesa->deleted_at !== null) {
                    return false;
                }
            }

            return true;
        })->values(); // Resetear índices
    }

    private function cargarDatosBasicos(int $sedeId, string $turno): void
    {
        $this->mesas = Mesa::where('sede_id', $sedeId)
            ->where('activa', true)
            ->where('estado', 'disponible')
            ->with(['horarios' => fn($q) => $q->where('tipo_turno', $turno)])
            ->get();

        $this->combinaciones = CombinacionMesa::where('sede_id', $sedeId)
            ->where('activa', true)
            ->with(['mesaPrincipal.horarios' => fn($q) => $q->where('tipo_turno', $turno)])
            ->get();
    }

    private function cargarOcupaciones(string $fecha, int $sedeId): void
    {
        $this->ocupaciones = Ocupacion::where('fecha', $fecha)
            ->where('sede_id', $sedeId)
            ->get();
        $this->logInfo('Ocupaciones cargadas', ['ocupaciones' => $this->ocupaciones]);
    }

    private function determinarHorariosOcupados(string $fecha, string $turno, int $sedeId): void
    {
        foreach ($this->mesas as $mesa) {
            foreach ($mesa->horarios as $horario) {
                //$this->logInfo('Verificando horario', ['mesa_id' => $mesa->id, 'hora' => $horario->hora]);
                if ($this->estaHorarioOcupado($mesa->id, 'mesa', $horario->hora)) {
                    $this->logInfo('Horario ocupado', ['mesa_id' => $mesa->id, 'hora' => $horario->hora]);
                    $this->horariosOcupados[$mesa->id][] = $horario->hora;
                }
            }
        }

        foreach ($this->combinaciones as $combinacion) {
            $mesasInvolucradas = array_merge([$combinacion->mesa_id], $combinacion->mesas_combinadas_ids ?? []);
            foreach ($combinacion->mesaPrincipal->horarios as $horario) {
                $ocupado = false;

                foreach ($mesasInvolucradas as $mesaId) {
                    if ($this->estaHorarioOcupado($mesaId, 'mesa', $horario->hora)) {
                        $ocupado = true;
                        break;
                    }
                }

                if ($ocupado || $this->estaHorarioOcupado($combinacion->id, 'combinacion', $horario->hora)) {
                    $this->horariosOcupados[$combinacion->id][] = $horario->hora;
                }
            }
        }

        $this->logInfo('Horarios ocupados determinados', ['sede_id' => $sedeId, 'turno' => $turno]);

        foreach ($this->horariosOcupados as &$horas) {
            $horas = array_values(array_unique($horas));
            $this->logInfo('Horarios ocupados', ['elemento_id' => $horas]);
        }
    }

    private function estaHorarioOcupado(int $elementoId, string $tipo, string $hora): bool
    {
        $hora = substr($hora, 0, 5);

        return $this->ocupaciones->contains(function ($ocupacion) use ($elementoId, $tipo, $hora) {
            if (empty($ocupacion->hora_inicio) || strlen($ocupacion->hora_inicio) < 5 || empty($ocupacion->hora_fin) || strlen($ocupacion->hora_fin) < 5) {
                \Log::warning('⚠️ Ocupación con hora inválida detectada', ['ocupacion' => $ocupacion]);
                return false;
            }

            try {
                $inicio = Carbon::parse($ocupacion->hora_inicio);
                $fin = Carbon::parse($ocupacion->hora_fin);

                if ($fin->lte($inicio)) {                          // Si la hora de fin es menor o igual que la hora de inicio
                    $fin->addDay();                                // Se asume que es al día siguiente y se suma 1 día
                }
                
                $horaActual = Carbon::createFromFormat('H:i', $hora);
            
                $resultado = $ocupacion->elemento_id == $elementoId
                && $ocupacion->tipo === $tipo
                && $inicio->lte($horaActual)
                && $fin->gt($horaActual);

                $this->logInfo('🔍 Verificación de horario ocupado', [
                'elemento_id' => $elementoId,
                'tipo' => $tipo,
                'hora' => $hora,
                'inicio' => $ocupacion->hora_inicio,
                'fin' => $ocupacion->hora_fin,
                'resultado' => $resultado
            ]);

            return $resultado;
            } catch (\Exception $e) {
                \Log::error('❌ Error interpretando horas de ocupación', [
                    'ocupacion_id' => $ocupacion->id,
                    'hora_inicio_original' => $ocupacion->hora_inicio,
                    'hora_fin_original' => $ocupacion->hora_fin,
                    'hora_procesada' => $hora,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    private function generarRespuestaFinal(string $fecha, string $turno, int $sedeId): array
    {
        $horariosPorDia = HorarioSemanal::where('sede_id', $sedeId)->get()->keyBy('day_of_week');

        $mesasFormateadas = $this->mesas->map(function ($mesa) use ($fecha, $turno, $sedeId, $horariosPorDia) {
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
                'horarios' => $horariosVisibles,
            ];
        });

        $combinacionesFormateadas = $this->combinaciones->map(function ($combinacion) use ($fecha, $turno, $sedeId, $horariosPorDia) {
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
                'horarios' => $horariosVisibles,
            ];
        });

        return [
            'mesas' => $mesasFormateadas->merge($combinacionesFormateadas)->values(),
            'horarios_ocupados' => $this->horariosOcupados,
        ];
    }

    private function filtrarHorariosSegunHorarioSemanal(array $horarios, string $fecha, string $turno, int $sedeId, $horariosPorDia): array
    {
        if (!HorarioSemanal::estaAbierto($fecha, $turno, $sedeId)) {
            return [];
        }

        $diaSemana = Carbon::parse($fecha)->dayOfWeek;
        $horario = $horariosPorDia->get($diaSemana);

        $inicio = $turno === 'comida' ? $horario->lunch_start : $horario->dinner_start;
        $fin = $turno === 'comida' ? $horario->lunch_end : $horario->dinner_end;

        $inicioC = Carbon::createFromFormat('H:i', $inicio->format('H:i'))->setDate(2000, 1, 1);
        $finC = Carbon::createFromFormat('H:i', $fin->format('H:i'))->setDate(2000, 1, 1);
        if ($finC->lte($inicioC)) $finC->addDay();

        return array_values(array_filter($horarios, function ($hora) use ($inicioC, $finC) {
            try {
                $h = Carbon::createFromFormat('H:i', substr($hora, 0, 5))->setDate(2000, 1, 1);
                return $h->between($inicioC, $finC);
            } catch (\Exception $e) {
                \Log::warning('⚠️ Hora inválida encontrada', ['hora' => $hora]);
                return false;
            }
        }));
    }

    private function filtrarHorariosPasados(array $horarios): array
    {
        if (request()->input('fecha') !== now()->toDateString()) {
            return $horarios;
        }

        $horaActual = now()->format('H:i');

        return array_values(array_filter($horarios, fn($hora) => $hora > $horaActual));
    }
}
