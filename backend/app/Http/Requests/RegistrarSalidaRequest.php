<?php

namespace App\Http\Requests;

use App\Models\MovimientoInventario;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Salida manual (merma, daño, regalo…). Las ventas descuentan desde Pedidos.
 */
class RegistrarSalidaRequest extends MovimientoInventarioRequest
{
    protected function reglasDelDocumento(): array
    {
        return [
            'movimiento.motivo' => ['required', Rule::in(array_keys(MovimientoInventario::MOTIVOS_SALIDA))],
        ];
    }

    protected function reglasDeLinea(int|string $i): array
    {
        return [
            "movimiento.lineas.{$i}.cantidad" => ['required', 'integer', 'min:1', 'max:100000', $this->hayStock($i)],
        ];
    }

    private function hayStock(int|string $i): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($i) {
            $stock = $this->stockDe($i);

            if ($stock !== null && (int) $value > $stock) {
                $fail("Stock insuficiente: hay {$stock}.");
            }
        };
    }
}
