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
            $cancelarUrl = URL::signedRoute('reservas.cancelar', ['id' => $reserva->id]);

            if (!Str::startsWith($cancelarUrl, ['http://', 'https://'])) {
                $cancelarUrl = config('app.url') . $cancelarUrl;
            }

            $this->logInfo('URL de cancelación generada', ['url' => $cancelarUrl]);
            $this->logInfo('Información del cliente', ['cliente' => $cliente->toArray()]);

            Mail::send('emails.reserva-confirmacion-cliente', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'sede' => $sede,
                'restaurante' => $restaurante,
                'cancelarUrl' => $cancelarUrl
            ], function ($message) use ($cliente, $sede) {
                $message->to($cliente->email, $cliente->nombre)
                        ->subject('Confirmación de reserva de mesa ' . $sede->nombre);
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

            // Cargamos mesas combinadas si aplica
            $mesasCombinadas = null;
            if ($reserva->combinacionMesa) {
                $mesasCombinadas = $reserva->combinacionMesa->obtenerMesasCombinadas()
                    ->map(fn($mesa) => (object)[
                        'id' => $mesa->id,
                        'numero' => $mesa->numero
                    ]);
            }

            Mail::send('emails.reserva-notificacion-admin', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'mesasCombinadas' => $mesasCombinadas,
                'sede' => $sede,
                'restaurante' => $restaurante
            ], function ($message) use ($adminEmail, $sede) {
                $message->to($adminEmail)
                    ->subject('Nueva reserva en ' . $sede->nombre);
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

            // Construir URL al formulario de nueva reserva para esa sede
            $nuevaReservaUrl = rtrim(env('URL_FRONT'), '/') . '/' . $sede->slug;

            $this->logInfo('URL de nueva reserva generada', ['url' => $nuevaReservaUrl]);
            $this->logInfo('Información del cliente', ['cliente' => $cliente->toArray()]);

            Mail::send('emails.reserva-cancelacion-cliente', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'sede' => $sede,
                'restaurante' => $restaurante,
                'nuevaReservaUrl' => $nuevaReservaUrl
            ], function ($message) use ($cliente, $sede) {
                $message->to($cliente->email, $cliente->nombre)
                        ->subject('Cancelación de reserva de mesa - ' . $sede->nombre);
            });

            $this->logInfo('Correo de cancelación enviado al cliente exitosamente');
            return true;
        } catch (\Exception $e) {
            $this->logError('Error al enviar correo de cancelación al cliente', $e);
            return false;
        }
    }

    public function enviarCorreoCancelacionAdmin(Reserva $reserva): bool
    {
        try {
            $this->logInfo('Enviando correo de cancelación al administrador', [
                'reserva_id' => $reserva->id,
                'sede_id' => $reserva->mesa->sede_id
            ]);

            $cliente = $reserva->cliente;
            $mesa = $reserva->mesa;
            $sede = $mesa->sede;
            $restaurante = $sede->restaurante;
            $adminEmail = $sede->admin_email;

            // Cargar mesas combinadas si es reserva compuesta
            $mesasCombinadas = null;
            if ($reserva->combinacionMesa) {
                $mesasCombinadas = $reserva->combinacionMesa->obtenerMesasCombinadas()
                    ->map(fn($mesa) => (object)[
                        'id' => $mesa->id,
                        'numero' => $mesa->numero
                    ]);
            }

            Mail::send('emails.reserva-cancelacion-admin', [
                'reserva' => $reserva,
                'cliente' => $cliente,
                'mesa' => $mesa,
                'mesasCombinadas' => $mesasCombinadas,
                'sede' => $sede,
                'restaurante' => $restaurante
            ], function ($message) use ($adminEmail, $sede) {
                $message->to($adminEmail)
                        ->subject('Cancelación de reserva de mesa - ' . $sede->nombre);
            });

            $this->logInfo('Correo de cancelación enviado al administrador exitosamente');
            return true;
        } catch (\Exception $e) {
            $this->logError('Error al enviar correo de cancelación al administrador', $e);
            return false;
        }
    }
} 