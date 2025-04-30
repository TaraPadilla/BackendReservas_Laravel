<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cancelación de reserva de mesa</title>
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
    .footer {
      text-align: center;
      font-size: 12px;
      color: #999;
      margin-top: 30px;
      border-top: 1px solid #eee;
      padding-top: 10px;
    }
    .link-button {
      display: inline-block;
      background-color: #000;
      color: #fff;
      padding: 10px 20px;
      text-decoration: none;
      border-radius: 4px;
      margin-top: 20px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <h2>Cancelación de reserva de mesa</h2>

    <div class="section">
      <p>Hola {{ $cliente->nombre }},</p>

      <p>Tu reserva de mesa se ha cancelado con éxito en {{ $sede->nombre }}.</p>

      <p>Aquí tienes los detalles de tu reserva cancelada:</p>

      <p><strong>Dirección:</strong> {{ $sede->direccion }}, {{ $sede->ciudad }}</p>
      <p><strong>Fecha reserva:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
      <p><strong>Hora reserva:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
      <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>
    </div>

    <div class="section">
      <p>Si se trata de un error en esta cancelación, por favor contáctanos llamándonos al {{ $sede->telefono }}.</p>

      <p>O realiza otra reserva aquí:</p>

      <div style="text-align: center;">
        <a href="{{ env('URL_FRONT') }}/{{ $sede->slug }}" class="link-button">Hacer nueva reserva</a>
      </div>
    </div>

    <div class="section">
      <p>¡Esperamos volver a verte pronto en {{ $sede->nombre }}!</p>
    </div>

    <div class="footer">
      &copy; {{ date('Y') }}. Todos los derechos reservados.
    </div>
  </div>
</body>
</html>
