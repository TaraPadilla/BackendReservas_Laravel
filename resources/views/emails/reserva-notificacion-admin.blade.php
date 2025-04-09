<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Reserva</title>
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
        .client-info {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .client-info p {
            margin: 5px 0;
        }
        .client-info strong {
            color: #2980b9;
        }
        .reservation-id {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
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
            <h2>Nueva Reserva Registrada</h2>
        </div>
        
        <div class="content">
            <p class="reservation-id">ID de Reserva: #{{ $reserva->id }}</p>
            
            <p>Se ha registrado una nueva reserva en tu restaurante. A continuación encontrarás los detalles:</p>
            
            <div class="details">
                <p><strong>Sede:</strong> {{ $sede->nombre }}</p>
                <p><strong>Dirección:</strong> {{ $sede->direccion }}, {{ $sede->ciudad }}</p>
                <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
                <p><strong>Hora:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
                <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>
                @if($reserva->combinacionMesa)
                    <p><strong>Tipo de mesa:</strong> Combinación de mesas</p>
                    <p><strong>ID de combinación:</strong> {{ $reserva->combinacionMesa->id }}</p>
                @else
                    <p><strong>Mesa:</strong> {{ $mesa->numero }}</p>
                @endif
                @if($reserva->notas)
                    <p><strong>Notas adicionales:</strong> {{ $reserva->notas }}</p>
                @endif
            </div>
            
            <div class="client-info">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> {{ $cliente->nombre }}</p>
                <p><strong>Email:</strong> {{ $cliente->email }}</p>
                <p><strong>Teléfono:</strong> {{ $cliente->telefono }}</p>
                @if($cliente->preferencias)
                    <p><strong>Preferencias:</strong> {{ $cliente->preferencias }}</p>
                @endif
            </div>
            
            <p>Esta reserva ha sido confirmada automáticamente. El cliente recibirá un correo de confirmación con los detalles de su reserva.</p>
        </div>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} {{ $restaurante->nombre }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html> 