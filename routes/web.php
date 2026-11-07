<?php

use App\Http\Controllers\AdministracionController;
use App\Http\Controllers\CocinaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\SoporteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SesionController::class, 'inicio'])->name('inicio');
Route::get('/ingresar', [SesionController::class, 'formulario'])->name('login');
Route::post('/ingresar', [SesionController::class, 'ingresar'])->middleware('throttle:acceso-web')->name('sesion.ingresar');
Route::middleware('auth')->group(function (): void {
    Route::post('/salir', [SesionController::class, 'salir'])->name('sesion.salir');
    Route::view('/aplicacion', 'sesion.movil')->middleware('rol:Mozo')->name('acceso.movil');
    Route::prefix('administracion')->name('admin.')->middleware('rol:Administrador')->group(function (): void {
        Route::get('/', [AdministracionController::class, 'resumen'])->name('resumen');
        Route::get('/soporte/{tipo}', [SoporteController::class, 'index'])->whereIn('tipo', ['productos', 'categorias', 'empleados', 'usuarios', 'mesas'])->name('soporte');
        Route::post('/soporte/{tipo}/{registro?}', [SoporteController::class, 'guardar'])->whereIn('tipo', ['productos', 'categorias', 'empleados', 'usuarios', 'mesas'])->whereNumber('registro')->name('soporte.guardar');
        Route::patch('/soporte/{tipo}/{registro}', [SoporteController::class, 'disponibilidad'])->whereIn('tipo', ['productos', 'categorias', 'empleados', 'usuarios', 'mesas'])->whereNumber('registro')->name('soporte.disponibilidad');
        Route::get('/pedidos', [AdministracionController::class, 'pedidos'])->name('pedidos');
        Route::get('/pedidos/{pedido}', [AdministracionController::class, 'detalle'])->whereNumber('pedido')->name('detalle');
        Route::post('/pedidos/{pedido}/cobrar', [AdministracionController::class, 'cobrar'])->whereNumber('pedido')->name('cobrar');
        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes');
    });
    Route::prefix('cocina')->name('cocina.')->middleware('rol:Cocina,Administrador')->group(function (): void {
        Route::get('/', [CocinaController::class, 'index'])->name('index');
        Route::patch('/pedidos/{pedido}', [CocinaController::class, 'cambiar'])->whereNumber('pedido')->name('cambiar');
    });
});
