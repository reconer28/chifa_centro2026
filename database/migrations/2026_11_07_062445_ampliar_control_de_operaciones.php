<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', fn (Blueprint $tabla) => $tabla->date('fecha_turno')->nullable());
        Schema::table('detalle_pedido', fn (Blueprint $tabla) => $tabla->boolean('stock_descontado')->default(false));
        Schema::table('pedidos', function (Blueprint $tabla): void {
            $tabla->timestamp('fecha_cobro')->nullable();
            $tabla->integer('cobrado_por')->nullable();
            $tabla->foreign('cobrado_por')->references('id')->on('usuarios')->restrictOnDelete();
        });
        Schema::table('usuarios', fn (Blueprint $tabla) => $tabla->unique('empleado_id'));
        Schema::create('registros_autorizacion', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->integer('usuario_id');
            $tabla->integer('administrador_id')->nullable();
            $tabla->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
            $tabla->foreign('administrador_id')->references('id')->on('usuarios')->restrictOnDelete();
            $tabla->string('accion', 50);
            $tabla->text('referencia')->nullable();
            $tabla->boolean('autorizado');
            $tabla->string('ip', 45)->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Conserva el historial de autorizaciones y cobros mediante una migración correctiva.');
    }
};
