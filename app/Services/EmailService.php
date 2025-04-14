<?php

namespace App\Services;

use App\Models\Reserva;
use App\Traits\LogTrait;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailService
{
    use LogTrait;

    /**
     * Envía un correo de confirmación de reserva al cliente
     *
     * @param Reserva $reserva
     * @return bool
     */
    public function enviarCorreoConfirmacionCliente(Reserva $reserva): bool
    {
        try {
            $this->logInfo('Enviando correo de confirmación al cliente', [
                'reserva_id' => $reserva->id,
                'cliente_id' => $reserva->cliente_id
            ]);

            $cliente = $reserva->cliente;
            $mesa = $reserva->mesa;
            $sede = $mesa->sede;
            $restaurante = $sede->restaurante;

            // Generar URL para cancelar la reserva
            $cancelarUrl = URL::signedRoute(
                'reservas.cancelar',
                ['id' => $reserva->id]
            );

            //Consola
            $this->logInfo('URL de cancelación generada', ['url' => $cancelarUrl]);
            
            // Asegurarnos de que la URL sea absoluta y use el dominio correcto
            if (!Str::startsWith($cancelarUrl, ['http://', 'https://'])) {
                $cancelarUrl = config('app.url') . $cancelarUrl;
            }

            $this->logInfo('Información del cliente', ['cliente' => $cliente->toArray()]);

            Mail::send('emails.reserva-confirmacion-cliente', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'sede' => $sede,
                'restaurante' => $restaurante,
                'cancelarUrl' => $cancelarUrl
            ], function ($message) use ($cliente, $restaurante) {
                $message->to($cliente->email, $cliente->nombre)
                    ->subject('Confirmación de tu reserva en ' . $restaurante->nombre);
            });

            $this->logInfo('Correo de confirmación enviado al cliente exitosamente');
            return true;
        } catch (\Exception $e) {
            $this->logError('Error al enviar correo de confirmación al cliente', $e);
            return false;
        }
    }

    /**
     * Envía un correo de notificación de reserva al administrador de la sede
     *
     * @param Reserva $reserva
     * @return bool
     */
    public function enviarCorreoNotificacionAdmin(Reserva $reserva): bool
    {
        try {
            $this->logInfo('Enviando correo de notificación al administrador', [
                'reserva_id' => $reserva->id,
                'sede_id' => $reserva->mesa->sede_id
            ]);

            $cliente = $reserva->cliente;
            $mesa = $reserva->mesa;
            $sede = $mesa->sede;
            $restaurante = $sede->restaurante;
            $adminEmail = $sede->admin_email;

            Mail::send('emails.reserva-notificacion-admin', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'sede' => $sede,
                'restaurante' => $restaurante
            ], function ($message) use ($adminEmail, $restaurante, $sede) {
                $message->to($adminEmail)
                    ->subject('Nueva reserva en ' . $restaurante->nombre . ' - ' . $sede->nombre);
            });

            $this->logInfo('Correo de notificación enviado al administrador exitosamente');
            return true;
        } catch (\Exception $e) {
            $this->logError('Error al enviar correo de notificación al administrador', $e);
            return false;
        }
    }

    /**
     * Envía un correo de confirmación de cancelación al cliente
     *
     * @param Reserva $reserva
     * @return bool
     */
    public function enviarCorreoCancelacionCliente(Reserva $reserva): bool
    {
        try {
            $this->logInfo('Enviando correo de cancelación al cliente', [
                'reserva_id' => $reserva->id,
                'cliente_id' => $reserva->cliente_id
            ]);

            $cliente = $reserva->cliente;
            $mesa = $reserva->mesa;
            $sede = $mesa->sede;
            $restaurante = $sede->restaurante;

            Mail::send('emails.reserva-cancelacion-cliente', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'sede' => $sede,
                'restaurante' => $restaurante
            ], function ($message) use ($cliente, $restaurante) {
                $message->to($cliente->email, $cliente->nombre)
                    ->subject('Tu reserva ha sido cancelada - ' . $restaurante->nombre);
            });

            $this->logInfo('Correo de cancelación enviado al cliente exitosamente');
            return true;
        } catch (\Exception $e) {
            $this->logError('Error al enviar correo de cancelación al cliente', $e);
            return false;
        }
    }
} 