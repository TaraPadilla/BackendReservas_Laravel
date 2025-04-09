<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Reserva</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .content {
            padding: 30px 20px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
        }
        .button {
            display: inline-block;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: bold;
        }
        .details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .details p {
            margin: 5px 0;
        }
        .details strong {
            color: #555;
        }
        .thank-you {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($restaurante->logo)
                <img src="{{ $restaurante->logo }}" alt="{{ $restaurante->nombre }}" class="logo">
            @else
                <h1>{{ $restaurante->nombre }}</h1>
            @endif
            <h2>Confirmación de Reserva</h2>
        </div>
        
        <div class="content">
            <p class="thank-you">¡Hola {{ $cliente->nombre }}!</p>
            
            <p>Tu reserva ha sido confirmada con éxito. A continuación encontrarás los detalles:</p>
            
            <div class="details">
                <p><strong>Restaurante:</strong> {{ $restaurante->nombre }}</p>
                <p><strong>Sede:</strong> {{ $sede->nombre }}</p>
                <p><strong>Dirección:</strong> {{ $sede->direccion }}, {{ $sede->ciudad }}</p>
                <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
                <p><strong>Hora:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
                <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>
                @if($reserva->combinacionMesa)
                    <p><strong>Tipo de mesa:</strong> Combinación de mesas</p>
                @else
                    <p><strong>Mesa:</strong> {{ $mesa->numero }}</p>
                @endif
                @if($reserva->notas)
                    <p><strong>Notas adicionales:</strong> {{ $reserva->notas }}</p>
                @endif
            </div>
            
            <p>Si necesitas cancelar tu reserva, puedes hacerlo haciendo clic en el siguiente botón:</p>
            
            <div style="text-align: center;">
                <a href="{{ $cancelarUrl }}" class="button">Cancelar mi reserva</a>
            </div>
            
            <p>Este enlace para cancelar la reserva estará disponible durante las próximas 24 horas.</p>
            
            <p>Si tienes alguna pregunta o necesitas hacer cambios en tu reserva, por favor contáctanos.</p>
            
            <p>¡Esperamos darte la bienvenida pronto!</p>
        </div>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} {{ $restaurante->nombre }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html> 