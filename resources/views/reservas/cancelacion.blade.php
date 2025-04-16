<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelación de Reserva</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            width: 90%;
            margin: 20px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .header {
            margin-bottom: 30px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .status-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        .success {
            color: #2ecc71;
        }
        .warning {
            color: #f39c12;
        }
        .error {
            color: #e74c3c;
        }
        .title {
            font-size: 24px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .message {
            font-size: 16px;
            margin-bottom: 25px;
            color: #555;
        }
        .details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: left;
        }
        .details p {
            margin: 5px 0;
        }
        .details strong {
            color: #555;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($estado === 'cancelada')
            <div class="status-icon success">✓</div>
            <h2 class="title">¡Reserva Cancelada!</h2>
            <p class="message">Tu reserva ha sido cancelada exitosamente.</p>

            <div class="details">
                <p><strong>Restaurante:</strong> {{ $reserva->mesa->sede->restaurante->nombre }}</p>
                <p><strong>Sede:</strong> {{ $reserva->mesa->sede->nombre }}</p>
                <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
                <p><strong>Hora:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
                <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>
            </div>

            <p>Se ha enviado un correo de confirmación a tu dirección de email.</p>
        @elseif($estado === 'ya_cancelada')
            <div class="status-icon warning">!</div>
            <h2 class="title">Reserva Ya Cancelada</h2>
            <p class="message">Esta reserva ya había sido cancelada anteriormente.</p>

            <div class="details">
                <p><strong>Restaurante:</strong> {{ $reserva->mesa->sede->restaurante->nombre }}</p>
                <p><strong>Sede:</strong> {{ $reserva->mesa->sede->nombre }}</p>
                <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
                <p><strong>Hora:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
                <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>
            </div>
        @elseif($estado === 'fuera_de_tiempo')
            <div class="status-icon warning">⏱</div>
            <h2 class="title">Cancelación No Permitida</h2>
            <p class="message">La reserva solo podía cancelarse con al menos 2 horas de antelación.</p>

            <div class="details">
                <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
                <p><strong>Hora de inicio:</strong> {{ $reserva->hora_inicio }}</p>
            </div>

            <p>Si necesitas ayuda, por favor contacta directamente con el restaurante.</p>

        @else
            <div class="status-icon error">✕</div>
            <h2 class="title">Error al Cancelar la Reserva</h2>
            <p class="message">{{ $mensaje ?? 'Ha ocurrido un error al procesar tu solicitud.' }}</p>

            <p>Por favor, contacta con el restaurante para obtener ayuda.</p>
        @endif

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ isset($reserva) ? $reserva->mesa->sede->restaurante->nombre : 'Restaurante' }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
