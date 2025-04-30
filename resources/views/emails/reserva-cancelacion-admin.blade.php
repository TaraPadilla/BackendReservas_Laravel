<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cancelación de reserva de mesa </title>
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
    .section {
      margin-bottom: 20px;
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
    <h2>Cancelación de reserva de mesa en {{ $sede->nombre }}</h2>

    <div class="section">
      <p>Hola {{ $sede->admin_nombre}},</p>

      <p>Se ha cancelado una reserva de mesa.</p>

      <p><strong>Dirección:</strong> {{ $sede->direccion }}, {{ $sede->ciudad }}</p>
      <p><strong>Fecha reserva:</strong> {{ \Carbon\Carbon::parse($reserva->fecha)->format('d/m/Y') }}</p>
      <p><strong>Hora reserva:</strong> {{ $reserva->hora_inicio }} - {{ $reserva->hora_fin }}</p>
      <p><strong>Número de personas:</strong> {{ $reserva->num_personas }}</p>

      @if(isset($mesasCombinadas) && $mesasCombinadas->isNotEmpty())
        <p><strong>Mesas de la reserva:</strong> Mesa {{ $mesasCombinadas->pluck('numero')->implode(' y Mesa ') }}</p>
      @else
        <p><strong>Mesa de la reserva:</strong> Mesa {{ $mesa->numero }}</p>
      @endif
    </div>

    <div class="section">
      <h3>Información del Cliente</h3>
      <p><strong>Nombre:</strong> {{ $cliente->nombre }}</p>
      <p><strong>Teléfono:</strong> {{ $cliente->telefono }}</p>
      <p><strong>Email:</strong> {{ $cliente->email }}</p>
    </div>

    <div class="footer">
      &copy; {{ date('Y') }}. Todos los derechos reservados.
    </div>
  </div>
</body>
</html>
