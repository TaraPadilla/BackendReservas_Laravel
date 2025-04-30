<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmación de reserva de mesa</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f5f5f5;
      margin: 0;
      padding: 20px;
    }
    .email-container {
      max-width: 600px;
      margin: auto;
      background: #ffffff;
      padding: 20px;
      border-radius: 8px;
    }
    .section {
      margin-bottom: 20px;
    }
    h2 {
      text-align: center;
      color: #333;
      margin-top: 0;
    }
    p {
      margin: 5px 0;
      color: #555;
    }
    strong {
      color: #222;
    }
    .button {
      display: inline-block;
      background-color: #000000;
      color: #ffffff;
      text-decoration: none;
      padding: 12px 25px;
      border-radius: 4px;
      margin: 20px 0;
      font-weight: bold;
    }
    .footer {
      text-align: center;
      font-size: 12px;
      color: #999;
      margin-top: 30px;
      border-top: 1px solid #eee;
      padding-top: 10px;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <h2>Confirmación de reserva de mesa - {{ $sede->nombre }}</h2>

    <div class="section">
      <p>Hola {{ $cliente->nombre }},</p>

      <p>Tu reserva de mesa se ha confirmado con éxito en {{ $sede->nombre }}.</p>

      <p>Aquí tienes los detalles de tu reserva:</p>

      <p><strong>Dirección:</strong> {{ $sede->direccion }}, {{ $sede->ciudad }}</p>
      <p><strong>Fecha reserva:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
      <p><strong>Hora reserva:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
      <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>
    </div>

    <div class="section" style="text-align: center;">
      <p>Si necesitas cancelar tu reserva, podrás hacerlo hasta un máximo de 2 horas antes del inicio de la reserva pulsando el siguiente botón:</p>

      <a href="{{ $cancelarUrl }}" class="button">CANCELAR RESERVA</a>
    </div>

    <div class="section">
      <p>Si necesitas contactar con nosotros, llámanos al {{ $sede->telefono }}.</p>
    </div>

    <div class="section">
      <p>¡Te esperamos en {{ $sede->nombre }}!</p>
    </div>

    <div class="footer">
      &copy; {{ date('Y') }}. Todos los derechos reservados.
    </div>
  </div>
</body>
</html>
