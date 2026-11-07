<?php

use App\Http\Middleware\VerificarRol;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $configuracion): void {
        $configuracion->alias(['rol' => VerificarRol::class]);
        $configuracion->redirectGuestsTo(fn (Request $solicitud): ?string => $solicitud->is('api/*') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $excepciones): void {
        $excepciones->dontFlash(['admin_password', 'password', 'password_confirmation']);
        $excepciones->shouldRenderJsonWhen(fn (Request $solicitud): bool => $solicitud->is('api/*') || $solicitud->expectsJson());
        $excepciones->render(function (AuthenticationException $excepcion, Request $solicitud): ?JsonResponse {
            if ($solicitud->is('api/*')) {
                return response()->json(['message' => 'Tu sesión venció. Vuelve a iniciar sesión.'], 401);
            }

            return null;
        });
    })->create();
