<?php

namespace App\Http\Requests;

/**
 * Compra o reposición: cada línea con cantidad y costo unitario.
 */
class RegistrarEntradaRequest extends MovimientoInventarioRequest
{
    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.cantidad" => ['required', 'integer', 'min:1', 'max:100000'],
            "movimiento.lineas.{$i}.costo_unitario" => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ];
    }
}
