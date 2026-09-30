<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web / SPA
|--------------------------------------------------------------------------
|
| El frontend es una SPA en Vue 3 montada sobre el shell Blade (welcome).
| La raíz sirve la aplicación y el catch-all {any} envía cualquier ruta de
| la SPA, como /login o /dashboard, al mismo shell: el enrutado lo resuelve
| Vue Router en el cliente. Las peticiones al API se sirven bajo /api y no
| pasan por estas rutas.
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Cualquier otra ruta de la SPA vuelve al shell para que Vue Router la procese.
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
