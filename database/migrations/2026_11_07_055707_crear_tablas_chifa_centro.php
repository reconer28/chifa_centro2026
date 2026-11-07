<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedidos') && ! Schema::hasColumn('pedidos', 'atencion_id') && DB::table('pedidos')->exists()) {
            throw new RuntimeException('Hay pedidos antiguos. Deben trasladarse a atenciones antes de continuar esta migración.');
        }
        if (! Schema::hasTable('categorias')) {
            Schema::create('categorias', function (Blueprint $tabla): void {
                $tabla->id();
                $tabla->string('nombre', 80)->unique();
                $tabla->boolean('activo')->default(true);
                $tabla->timestamps();
            });
        }
        if (! Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $tabla): void {
                $tabla->integer('id', true);
                $tabla->string('nombre', 100)->nullable();
                $tabla->string('categoria', 50)->nullable();
                $tabla->decimal('precio', 10, 2)->nullable();
                $tabla->integer('stock')->nullable();
                $tabla->string('imagen')->nullable();
            });
        }
        if (! Schema::hasColumn('productos', 'categoria_id')) {
            Schema::table('productos', function (Blueprint $tabla): void {
                $tabla->foreignId('categoria_id')->nullable()->constrained('categorias')->restrictOnDelete();
                $tabla->text('descripcion')->nullable();
                $tabla->boolean('disponible')->default(true);
                $tabla->boolean('destacado')->default(false);
                $tabla->boolean('activo')->default(true);
                $tabla->timestamps();
            });
        }
        foreach (DB::table('productos')->whereNull('categoria_id')->get(['id', 'categoria']) as $producto) {
            $nombre = trim($producto->categoria ?? '') ?: 'Sin categoría';
            DB::table('categorias')->insertOrIgnore(['nombre' => $nombre, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
            $categoria = DB::table('categorias')->where('nombre', $nombre)->value('id');
            DB::table('productos')->where('id', $producto->id)->update(['categoria_id' => $categoria]);
        }
        if (! Schema::hasTable('mesas')) {
            Schema::create('mesas', function (Blueprint $tabla): void {
                $tabla->id();
                $tabla->unsignedSmallInteger('numero')->unique();
                $tabla->unsignedSmallInteger('capacidad')->default(4);
                $tabla->string('zona', 100)->default('Salón principal');
                $tabla->integer('mozo_id')->nullable();
                $tabla->foreign('mozo_id')->references('id')->on('usuarios')->restrictOnDelete();
                $tabla->boolean('activo')->default(true);
                $tabla->timestamps();
            });
        }
        if (! Schema::hasTable('atenciones')) {
            Schema::create('atenciones', function (Blueprint $tabla): void {
                $tabla->id();
                $tabla->foreignId('mesa_id')->constrained('mesas')->restrictOnDelete();
                $tabla->integer('mozo_id');
                $tabla->foreign('mozo_id')->references('id')->on('usuarios')->restrictOnDelete();
                $tabla->timestamp('fecha_apertura');
                $tabla->timestamp('fecha_cierre')->nullable();
                $tabla->timestamps();
                $tabla->index(['mesa_id', 'fecha_cierre']);
            });
        }
        if (! Schema::hasTable('pedidos')) {
            Schema::create('pedidos', function (Blueprint $tabla): void {
                $tabla->integer('id', true);
                $tabla->string('mesa', 10)->nullable();
                $tabla->string('mozo', 100)->nullable();
                $tabla->decimal('total', 10, 2)->nullable();
                $tabla->string('estado', 30)->nullable();
                $tabla->timestamp('fecha')->useCurrent();
                $tabla->text('comentario')->nullable();
                $tabla->string('estado_cobro', 20)->default('Pendiente');
            });
        }
        if (! Schema::hasColumn('pedidos', 'atencion_id')) {
            Schema::table('pedidos', function (Blueprint $tabla): void {
                $tabla->foreignId('atencion_id')->constrained('atenciones')->restrictOnDelete();
                $tabla->integer('mozo_id');
                $tabla->foreign('mozo_id')->references('id')->on('usuarios')->restrictOnDelete();
                $tabla->string('clave_idempotencia', 100);
                $tabla->char('solicitud_hash', 64);
                $tabla->string('motivo_anulacion', 500)->nullable();
                $tabla->integer('administrador_anulacion_id')->nullable();
                $tabla->foreign('administrador_anulacion_id')->references('id')->on('usuarios')->restrictOnDelete();
                $tabla->timestamp('fecha_anulacion')->nullable();
                $tabla->timestamps();
                $tabla->unique(['mozo_id', 'clave_idempotencia']);
            });
        }
        if (! Schema::hasTable('detalle_pedido')) {
            Schema::create('detalle_pedido', function (Blueprint $tabla): void {
                $tabla->integer('id', true);
                $tabla->integer('pedido_id');
                $tabla->integer('producto_id');
                $tabla->foreign('pedido_id')->references('id')->on('pedidos')->restrictOnDelete();
                $tabla->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete();
                $tabla->integer('cantidad');
                $tabla->decimal('subtotal', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('detalle_pedido', 'nombre_producto')) {
            Schema::table('detalle_pedido', function (Blueprint $tabla): void {
                $tabla->string('nombre_producto', 100);
                $tabla->unsignedBigInteger('precio_centimos');
                $tabla->string('observaciones', 250)->default('');
                $tabla->timestamps();
                $tabla->unique(['pedido_id', 'producto_id']);
            });
        }
        foreach (Schema::getForeignKeys('detalle_pedido') as $relacion) {
            if ($relacion['columns'] === ['producto_id'] && strtolower($relacion['on_delete']) === 'cascade') {
                Schema::table('detalle_pedido', fn (Blueprint $tabla) => $tabla->dropForeign($relacion['name']));
                Schema::table('detalle_pedido', fn (Blueprint $tabla) => $tabla->foreign('producto_id')->references('id')->on('productos')->restrictOnDelete());
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Esta adaptación conserva el catálogo original. Usa una migración correctiva; no borres las tablas.');
    }
};
