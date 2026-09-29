<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
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

        // Inventario: el libro sólo se consulta y se le agregan movimientos
        // (no hay update ni destroy: un error se corrige con otro movimiento).
        Route::post('inventario/entradas', [InventarioController::class, 'entradas'])->name('inventario.entradas');
        Route::post('inventario/salidas', [InventarioController::class, 'salidas'])->name('inventario.salidas');
        Route::post('inventario/ajustes', [InventarioController::class, 'ajustes'])->name('inventario.ajustes');

        // Va antes del resource: si no, {cliente} captura "consultar-documento".
        Route::get('clientes/consultar-documento', [ClienteController::class, 'consultarDocumento'])
            ->name('clientes.consultar-documento');
        Route::apiResource('clientes', ClienteController::class);

        // Sin destroy: un pedido es historial de ventas, se cancela.
        Route::apiResource('pedidos', PedidoController::class)->except(['destroy']);

        // Dinero: siempre contra la caja abierta. Pagos y movimientos no se
        // editan ni se borran (un error se corrige con una devolución).
        Route::post('pedidos/{pedido}/pagos', [PagoController::class, 'cobrar'])->name('pedidos.pagos');
        Route::post('pedidos/{pedido}/devoluciones', [PagoController::class, 'devolver'])->name('pedidos.devoluciones');
        Route::post('cajas/abrir', [CajaController::class, 'abrir'])->name('cajas.abrir');
        Route::post('cajas/movimientos', [CajaController::class, 'movimientos'])->name('cajas.movimientos');
        Route::post('cajas/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('cajas.cerrar');
    });

    // "actual" va antes de {caja}: si no, la captura como id.
    Route::get('cajas/actual', [CajaController::class, 'actual'])->name('cajas.actual');
    Route::get('cajas', [CajaController::class, 'index'])->name('cajas.index');
    Route::get('cajas/{caja}', [CajaController::class, 'show'])->name('cajas.show');

    Route::post('pedidos/{pedido}/confirmar', [PedidoController::class, 'confirmar'])->name('pedidos.confirmar');
    Route::post('pedidos/{pedido}/entregar', [PedidoController::class, 'entregar'])->name('pedidos.entregar');
    Route::post('pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->name('pedidos.cancelar');

    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('inventario/variantes', [InventarioController::class, 'variantes'])->name('inventario.variantes');

    Route::patch('usuarios/{usuario}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('usuarios.toggle-active');
    Route::get('usuarios/{usuario}/sesiones', [UserController::class, 'sesiones'])
        ->name('usuarios.sesiones');
    Route::delete('usuarios/{usuario}/sesiones/{token}', [UserController::class, 'revocarSesion'])
        ->name('usuarios.sesiones.revocar');
});
