<?php

namespace App\Jobs;

use App\Models\Reserva;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarCorreosReservaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reserva;

    public function __construct(Reserva $reserva)
    {
        $this->reserva = $reserva;
    }

    public function handle(EmailService $emailService): void
    {
        try {
            Log::info('Iniciando envío de correos de confirmación', [
                'reserva_id' => $this->reserva->id
            ]);

            $emailService->enviarCorreoConfirmacionCliente($this->reserva);
            $emailService->enviarCorreoNotificacionAdmin($this->reserva);

            Log::info('Correos de confirmación enviados exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al enviar correos de confirmación: ' . $e->getMessage(), [
                'reserva_id' => $this->reserva->id
            ]);
        }
    }
}
