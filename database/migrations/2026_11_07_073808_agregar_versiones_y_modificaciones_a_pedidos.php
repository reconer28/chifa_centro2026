<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('version')->default(1);
            $tabla->unsignedInteger('version_vista_cocina')->default(1);
            $tabla->timestamp('fecha_modificacion')->nullable();
        });
        Schema::create('modificaciones_pedido', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->integer('pedido_id');
            $tabla->integer('usuario_id');
            $tabla->foreign('pedido_id')->references('id')->on('pedidos')->restrictOnDelete();
            $tabla->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
            $tabla->string('clave', 100);
            $tabla->char('solicitud_hash', 64);
            $tabla->unsignedInteger('version');
            $tabla->json('antes');
            $tabla->json('despues');
            $tabla->text('resumen');
            $tabla->timestamps();
            $tabla->unique(['usuario_id', 'clave']);
            $tabla->index(['pedido_id', 'version']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('El historial de cambios se conserva mediante migraciones correctivas.');
    }
};
