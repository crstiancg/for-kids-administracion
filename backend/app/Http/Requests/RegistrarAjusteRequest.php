<?php

namespace App\Http\Requests;

/**
 * Conteo físico: por cada variante, el stock REAL que se contó.
 */
class RegistrarAjusteRequest extends MovimientoInventarioRequest
{
    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.stock_real" => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
