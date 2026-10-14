<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * El operador de búsqueda de texto SIN distinguir mayúsculas en cualquier
 * motor: en MySQL `like` ya lo es (por la collation), en Postgres no — ahí es
 * `ilike`. Sin esto, en producción (Postgres) buscar "polo" no encuentra
 * "Polo".
 */
final class Busqueda
{
    public static function like(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
