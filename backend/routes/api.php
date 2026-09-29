<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TallaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AutorizarPorRuta;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

// El login es POST /oauth/token (password grant), lo registra Passport.

// Autorización por nombre de ruta (config/permisos.php): el nombre de cada
// ruta ES el permiso que exige, y lo que no está permitido se rechaza. Toda
// ruta nueva de este grupo necesita nombre y después `php artisan permisos:sync`.
Route::middleware(['auth:api', AutorizarPorRuta::ALIAS])->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::middleware(HandlePrecognitiveRequests::class)->group(function () {
        Route::apiResource('roles', RolController::class);

        // Los permisos son rutas: se crean eligiendo entre las rutas sin
        // permiso (nunca tipeando el nombre) y no se borran por API.
        // rutas-disponibles va antes del resource: si no, {permiso} la captura.
        Route::get('permisos/rutas-disponibles', [PermisoController::class, 'rutasDisponibles'])
            ->name('permisos.rutas-disponibles');
        Route::apiResource('permisos', PermisoController::class)->only(['index', 'store', 'show', 'update']);

        Route::apiResource('usuarios', UserController::class);
        Route::apiResource('colores', ColorController::class)->parameters(['colores' => 'color']);
        Route::apiResource('categorias', CategoriaController::class);
        Route::apiResource('tallas', TallaController::class);
        Route::apiResource('productos', ProductoController::class);
    });

    Route::patch('usuarios/{usuario}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('usuarios.toggle-active');
    Route::get('usuarios/{usuario}/sesiones', [UserController::class, 'sesiones'])
        ->name('usuarios.sesiones');
    Route::delete('usuarios/{usuario}/sesiones/{token}', [UserController::class, 'revocarSesion'])
        ->name('usuarios.sesiones.revocar');
});
