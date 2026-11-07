<?php

use App\Http\Controllers\Api\V1\AutenticacionController;
use App\Http\Controllers\Api\V1\ConsultaController;
use App\Http\Controllers\Api\V1\PedidoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AutenticacionController::class, 'iniciarSesion'])->middleware('throttle:acceso-app')->name('iniciar-sesion');
    Route::middleware(['auth:sanctum', 'rol:Mozo', 'throttle:consultas-app'])->group(function (): void {
        Route::post('/auth/logout', [AutenticacionController::class, 'cerrarSesion'])->name('cerrar-sesion');
        Route::get('/tables', [ConsultaController::class, 'mesas'])->name('mesas');
        Route::get('/menu', [ConsultaController::class, 'carta'])->name('carta');
        Route::get('/orders', [ConsultaController::class, 'pedidos'])->name('pedidos');
        Route::put('/me/tables', [PedidoController::class, 'asignar'])->name('asignar');
        Route::post('/orders', [PedidoController::class, 'enviar'])->name('enviar');
        Route::post('/orders/{pedido}/deliver', [PedidoController::class, 'entregar'])->whereNumber('pedido')->name('entregar');
        Route::post('/orders/{pedido}/cancel', [PedidoController::class, 'anular'])->whereNumber('pedido')->name('anular');
        Route::post('/tables/{mesa}/close', [PedidoController::class, 'cerrar'])->whereNumber('mesa')->name('cerrar');
    });
});
