<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Configuración raíz de la aplicación
|--------------------------------------------------------------------------
|
| Este arranque declara dónde viven las rutas (API y web), la URL de salud
| para monitoreo (/up) y el comportamiento de las excepciones.
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',       // API JSON (Sanctum)
        web: __DIR__.'/../routes/web.php',       // SPA Vue shell
        commands: __DIR__.'/../routes/console.php',
        health: '/up',                           // Endpoint de salud para load balancers
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las peticiones al API (o que pidan JSON) responden errores en JSON
        // en lugar de renderizar páginas HTML.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
