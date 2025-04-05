<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestauranteController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\CombinacionMesaController;
use App\Http\Controllers\HorarioMesaController;
use App\Http\Controllers\HorarioCombinacionController;
use App\Http\Controllers\ClienteController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Ruta de prueba
Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando correctamente']);
});

// Rutas públicas
Route::get('restaurantes/activos', [RestauranteController::class, 'activos']);
Route::get('sedes/activas', [SedeController::class, 'activas']);
Route::get('mesas/disponibles', [MesaController::class, 'disponibles']);
Route::get('reservas/verificar-disponibilidad', [ReservaController::class, 'verificarDisponibilidad']);

// Rutas protegidas
//Route::middleware('auth:sanctum')->group(function () {
    // Rutas para Restaurantes
    Route::apiResource('restaurantes', RestauranteController::class);
    
    // Rutas para Sedes
    Route::apiResource('sedes', SedeController::class);
    
    // Rutas para Mesas
    Route::apiResource('mesas', MesaController::class);
    Route::get('/sedes/{sede}/disponibilidad', [MesaController::class, 'obtenerSimulacionDisponibilidad']);
    Route::get('/mesas/sede/{sede}', [MesaController::class, 'porSede']);
    //Ruta para actualizar una mesa
    Route::put('/mesas/{mesa}', [MesaController::class, 'update']);
    // Rutas para Combinaciones de Mesas
    Route::apiResource('combinaciones-mesas', CombinacionMesaController::class);
    
    // Rutas para Horarios de Mesas
    //Con ApiResource no funciona
    Route::get('/horarios-mesas/mesa/{mesa}', [HorarioMesaController::class, 'porMesa']);
    //Ruta para obtener todos los horarios de mesas
    Route::get('/horarios-mesas', [HorarioMesaController::class, 'index']);
    //Ruta para crear un horario de mesa
    Route::post('/horarios-mesas', [HorarioMesaController::class, 'store']);
    //Ruta para actualizar un horario de mesa
    Route::put('/horarios-mesas/{horario}', [HorarioMesaController::class, 'update']);
    //Ruta para eliminar un horario de mesa
    Route::delete('/horarios-mesas/{horario}', [HorarioMesaController::class, 'destroy']);
    //Ruta para sincronizar horarios de mesas
    Route::post('horarios/sync', [HorarioMesaController::class, 'syncHorarios']);



    // Rutas para Horarios de Combinaciones
    Route::apiResource('horarios-combinaciones', HorarioCombinacionController::class);
    
    // Rutas para Reservas
    Route::apiResource('reservas', ReservaController::class);
    Route::post('reservas/{reserva}/confirmar', [ReservaController::class, 'confirmar']);
    Route::post('reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar']);
    Route::get('reservas/por-fecha/{fecha}', [ReservaController::class, 'porFecha']);

    // Rutas de Clientes
    Route::apiResource('clientes', ClienteController::class);

    // Rutas del Motor de Asignación
    Route::prefix('motor')->group(function () {
        Route::post('buscar-mesas', [ReservaController::class, 'buscarMesasDisponibles']);
        Route::post('verificar-combinacion', [ReservaController::class, 'verificarCombinacion']);
        Route::get('horarios-servicio/{mesa}', [ReservaController::class, 'obtenerHorariosServicio']);
        Route::get('tipo-turno/{fecha}/{hora}', [ReservaController::class, 'determinarTipoTurno']);
    });
//}); 