<?php

namespace App\Http\Controllers;

use App\Models\HorarioMesa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Traits\LogTrait;


class HorarioMesaController extends Controller
{
    use LogTrait;

    public function index()
    {       
        $horarios = HorarioMesa::with('mesa')->get();
        $this->logInfo('Horarios de mesas obtenidos', ['total' => $horarios->count()]);
        return response()->json($horarios);
    }

    public function porMesa($mesaId)
    {
        $horarios = HorarioMesa::where('mesa_id', $mesaId)
            ->whereNull('deleted_at')
            ->orderBy('hora')
            ->get()
            ->map(function ($horario) {
                return [
                    'id' => $horario->id,
                    'hora' => substr($horario->hora, 0, 5), // ← "13:00:00" → "13:00"
                    'tipo_turno' => $horario->tipo_turno,
                    'mesa_id' => $horario->mesa_id,
                ];
            });
    
        return response()->json($horarios);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'tipo_turno' => 'required|in:comida,cena',
            'horas' => 'required|array|min:1',
            'horas.*' => 'required|date_format:H:i'
        ]);
    
        $horarios = collect($validated['horas'])->map(function ($hora) use ($validated) {
            return [
                'mesa_id' => $validated['mesa_id'],
                'tipo_turno' => $validated['tipo_turno'],
                'hora' => $hora,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });
    
        HorarioMesa::insert($horarios->toArray());
    
        return response()->json(['message' => 'Horarios creados correctamente'], 201);
    }
    

    public function show(HorarioMesa $horarioMesa)
    {
        return $horarioMesa->load('mesa');
    }

    public function update(Request $request, HorarioMesa $horarioMesa)
    {
        $validated = $request->validate([
            'mesa_id' => 'exists:mesas,id',
            'tipo_turno' => 'in:comida,cena',
            'hora' => 'date_format:H:i'
        ]);

        $horarioMesa->update($validated);
        return response()->json($horarioMesa->load('mesa'));
    }

    public function destroy(HorarioMesa $horarioMesa)
    {
        $horarioMesa->delete();
        return response()->json(null, 204);
    }

    public function syncHorarios(Request $request)
    {
        $validated = $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'comida' => 'array',
            'comida.*' => 'date_format:H:i',
            'cena' => 'array',
            'cena.*' => 'date_format:H:i',
        ]);

        $mesaId = $validated['mesa_id'];
        $turnos = ['comida', 'cena'];

        foreach ($turnos as $tipoTurno) {
            if (!isset($validated[$tipoTurno])) continue;

            $nuevasHoras = collect($validated[$tipoTurno]);

            // Obtener horarios actuales (activos y soft deleted)
            $horariosDB = HorarioMesa::withTrashed()
                ->where('mesa_id', $mesaId)
                ->where('tipo_turno', $tipoTurno)
                ->get()
                ->mapWithKeys(fn($h) => [substr($h->hora, 0, 5) => $h]);

            $horasDB = $horariosDB->keys();

            $insertados = 0;
            $restaurados = 0;
            $eliminados = 0;

            // Insertar nuevos o restaurar
            foreach ($nuevasHoras as $hora) {
                if (isset($horariosDB[$hora])) {
                    if ($horariosDB[$hora]->trashed()) {
                        $horariosDB[$hora]->restore();
                        $restaurados++;
                    }
                } else {
                    HorarioMesa::create([
                        'mesa_id' => $mesaId,
                        'tipo_turno' => $tipoTurno,
                        'hora' => $hora,
                    ]);
                    $insertados++;
                }
            }

            // Eliminar los que no vienen en el nuevo listado
            $aEliminar = $horasDB->diff($nuevasHoras);

            foreach ($aEliminar as $hora) {
                if (!$horariosDB[$hora]->trashed()) {
                    $horariosDB[$hora]->delete();
                    $eliminados++;
                }
            }

            $this->logInfo("Sincronización de horarios para mesa {$mesaId} ({$tipoTurno})", [
                'insertados' => $insertados,
                'restaurados' => $restaurados,
                'eliminados' => $eliminados,
            ]);
        }

        return response()->json(['message' => 'Horarios sincronizados correctamente']);
    }
} 