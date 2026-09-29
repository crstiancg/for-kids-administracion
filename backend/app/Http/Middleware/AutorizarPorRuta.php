<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización "tipo Casbin" sobre spatie: el permiso requerido es el nombre
 * de la ruta. Rechaza por defecto: una ruta sin nombre, o con un nombre que
 * nadie tiene asignado, devuelve 403 en vez de quedar abierta por olvido.
 */
class AutorizarPorRuta
{
    public const ALIAS = 'autorizar.ruta';

    public function handle(Request $request, Closure $next): Response
    {
        $nombre = $request->route()?->getName();

        if ($nombre !== null && in_array($nombre, config('permisos.libres', []), true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($nombre === null || $user === null) {
            abort(403, 'No tenés permiso para realizar esta acción.');
        }

        // Ojo: la clave lleva puntos (roles.index), así que NO se puede leer
        // con config("permisos.implicitos.$nombre").
        $aceptados = [$nombre, ...(config('permisos.implicitos', [])[$nombre] ?? [])];

        foreach ($aceptados as $permiso) {
            // checkPermissionTo y no hasPermissionTo: si el permiso no existe
            // en la base (ruta nueva sin sincronizar) da false, no una excepción.
            if ($user->checkPermissionTo($permiso, 'api')) {
                return $next($request);
            }
        }

        abort(403, 'No tenés permiso para realizar esta acción.');
    }
}
