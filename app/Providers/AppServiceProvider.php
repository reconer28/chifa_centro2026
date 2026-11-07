<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        DB::prohibitDestructiveCommands(config('database.connections.'.config('database.default').'.database') === 'bd_chifa_centro');
        RateLimiter::for('acceso-web', fn (Request $solicitud) => [Limit::perMinute(30)->by('web-ip:'.$solicitud->ip()), Limit::perMinute(5)->by('web:'.hash('sha256', (is_string($solicitud->input('usuario')) ? mb_strtolower($solicitud->input('usuario')) : '').'|'.$solicitud->ip()))]);
        RateLimiter::for('acceso-app', function (Request $solicitud): array {
            $nombre = $solicitud->input('username');
            $clave = hash('sha256', (is_string($nombre) ? mb_strtolower($nombre) : '').'|'.$solicitud->ip());
            $respuesta = fn (Request $solicitud, array $cabeceras) => response()->json(['message' => 'Demasiados intentos. Espera un minuto para volver a ingresar.'], 429, $cabeceras);

            return [Limit::perMinute(30)->by('ip:'.$solicitud->ip())->response($respuesta), Limit::perMinute(5)->by('usuario:'.$clave)->response($respuesta)];
        });
        RateLimiter::for('consultas-app', fn (Request $solicitud) => Limit::perMinute(120)->by((string) $solicitud->user()->id)->response(fn (Request $solicitud, array $cabeceras) => response()->json(['message' => 'Realizaste muchas solicitudes. Espera un momento.'], 429, $cabeceras)));
    }
}
