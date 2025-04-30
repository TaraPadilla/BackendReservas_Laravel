<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\CombinacionMesa;
use App\Models\Ocupacion;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Traits\LogTrait;
use App\Models\Mesa;

class OcupacionController extends Controller
{
    use LogTrait;

    /**
     * Crea ocupaciones asociadas a una reserva ya existente.
     *
     * @param Reserva $reserva
     * @param mixed $mesaSeleccionada
     * @return void
     */
    public function crearOcupacionesParaReserva(Reserva $reserva, $mesaSeleccionada): void
    {
        try {
            if ($reserva->combinacion_mesa_id) {
                // Reserva combinada
                $combinacion = CombinacionMesa::findOrFail($reserva->combinacion_mesa_id);


                // Crear ocupación principal para la combinación
                Ocupacion::create([
                    'reserva_id'   => $reserva->id,
                    'elemento_id'  => $reserva->combinacion_mesa_id,
                    'sede_id'      => $combinacion->sede_id,
                    'fecha'        => $reserva->fecha,
                    'hora_inicio'  => $reserva->hora_inicio,
                    'hora_fin'     => $reserva->hora_fin,
                    'tipo'         => 'combinacion'
                ]);

                Log::info('✅ Ocupación creada (combinación)', [
                    'reserva_id' => $reserva->id,
                    'combinacion_id' => $reserva->combinacion_mesa_id
                ]);

                // Crear ocupaciones individuales por cada mesa
                $mesas = $combinacion->obtenerMesasCombinadas();

                foreach ($mesas as $mesa) {
                    Ocupacion::create([
                        'reserva_id'   => $reserva->id,
                        'elemento_id'  => $mesa->id,
                        'sede_id'      => $combinacion->sede_id,
                        'fecha'        => $reserva->fecha,
                        'hora_inicio'  => $reserva->hora_inicio,
                        'hora_fin'     => $reserva->hora_fin,
                        'tipo'         => 'mesa'
                    ]);

                    Log::info('✅ Ocupación creada (mesa combinada)', [
                        'reserva_id' => $reserva->id,
                        'mesa_id' => $mesa->id
                    ]);
                }

            } else {
                // Reserva individual (una sola mesa)

                $mesa = Mesa::findOrFail($reserva->mesa_id);

                Ocupacion::create([
                    'reserva_id'   => $reserva->id,
                    'elemento_id'  => $reserva->mesa_id,
                    'sede_id'      => $mesa->sede_id,
                    'fecha'        => $reserva->fecha,
                    'hora_inicio'  => $reserva->hora_inicio,
                    'hora_fin'     => $reserva->hora_fin,
                    'tipo'         => 'mesa'
                ]);

                Log::info('✅ Ocupación creada (mesa individual)', [
                    'reserva_id' => $reserva->id,
                    'mesa_id' => $reserva->mesa_id
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('❌ Error creando ocupaciones para la reserva', [
                'reserva_id' => $reserva->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cancelar todas las ocupaciones asociadas a una reserva.
     *
     * @param int $reservaId
     * @return void
     */
    public function cancelarOcupacionesPorReserva(int $reservaId): void
    {
        try {
            $ocupaciones = Ocupacion::where('reserva_id', $reservaId)->get();
    
            if ($ocupaciones->isEmpty()) {
                \Log::info('No se encontraron ocupaciones para cancelar', ['reserva_id' => $reservaId]);
                return;
            }
    
            foreach ($ocupaciones as $ocupacion) {
                $ocupacion->delete();
    
                \Log::info('✅ Ocupación cancelada correctamente', [
                    'ocupacion_id' => $ocupacion->id,
                    'reserva_id' => $reservaId
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('❌ Error al cancelar ocupaciones para reserva', [
                'reserva_id' => $reservaId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
