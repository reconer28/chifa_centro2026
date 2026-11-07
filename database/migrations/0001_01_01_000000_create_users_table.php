<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuarios') && DB::table('usuarios')->select('usuario')->groupBy('usuario')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen usuarios duplicados. Corrígelos antes de migrar.');
        }
        if (! Schema::hasTable('empleados')) {
            Schema::create('empleados', function (Blueprint $tabla): void {
                $tabla->integer('id', true);
                $tabla->string('nombres', 100)->nullable();
                $tabla->string('apellidos', 100)->nullable();
                $tabla->string('dni', 15)->nullable();
                $tabla->string('telefono', 20)->nullable();
                $tabla->string('cargo', 50)->nullable();
            });
        }
        if (! Schema::hasColumn('empleados', 'activo')) {
            Schema::table('empleados', function (Blueprint $tabla): void {
                $tabla->boolean('activo')->default(true);
                $tabla->timestamps();
            });
        }
        if (! Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $tabla): void {
                $tabla->integer('id', true);
                $tabla->string('usuario', 50)->nullable();
                $tabla->string('password')->nullable();
                $tabla->string('rol', 30)->nullable();
                $tabla->integer('empleado_id')->nullable();
                $tabla->foreign('empleado_id')->references('id')->on('empleados')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('usuarios', 'nombre')) {
            Schema::table('usuarios', function (Blueprint $tabla): void {
                $tabla->string('nombre', 150)->nullable();
                $tabla->boolean('activo')->default(true);
                $tabla->rememberToken();
                $tabla->timestamps();
            });
        }
        if (! Schema::hasIndex('usuarios', 'usuarios_usuario_unique')) {
            Schema::table('usuarios', fn (Blueprint $tabla) => $tabla->unique('usuario'));
        }
        $usuarios = DB::table('usuarios')->leftJoin('empleados', 'usuarios.empleado_id', '=', 'empleados.id')->whereNull('usuarios.nombre')->get(['usuarios.id', 'usuarios.usuario', 'empleados.nombres', 'empleados.apellidos']);
        foreach ($usuarios as $usuario) {
            $nombre = trim(($usuario->nombres ?? '').' '.($usuario->apellidos ?? ''));
            DB::table('usuarios')->where('id', $usuario->id)->update(['nombre' => $nombre !== '' ? $nombre : $usuario->usuario]);
        }
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $tabla): void {
                $tabla->string('id')->primary();
                $tabla->integer('user_id')->nullable()->index();
                $tabla->string('ip_address', 45)->nullable();
                $tabla->text('user_agent')->nullable();
                $tabla->longText('payload');
                $tabla->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Esta adaptación conserva tablas anteriores. Usa una migración correctiva para revertir cambios sin borrar datos.');
    }
};
