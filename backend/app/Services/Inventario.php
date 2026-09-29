<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El ÚNICO lugar que mueve `variantes.stock`. Cada cambio deja su movimiento
 * en el libro (movimiento_inventarios) dentro de la misma transacción: el
 * stock y su historial nunca se desincronizan.
 *
 * Las filas de las variantes se bloquean (FOR UPDATE) mientras se registra:
 * dos personas descontando la misma variante a la vez esperan su turno en vez
 * de pisarse el stock.
 */
class Inventario
{
    /**
     * Compra o reposición (o la devolución de una venta cancelada, con
     * `$motivo`). Recalcula el costo promedio ponderado.
     *
     * `$extra` son columnas adicionales del movimiento (p. ej. `pedido_id`).
     *
     * @param  array<int, array{variante_id: int, cantidad: int, costo_unitario: numeric-string|float|null}>  $lineas
     * @param  array<string, mixed>  $extra
     * @return Collection<int, MovimientoInventario>
     */
    public function entrada(array $lineas, ?string $referencia, ?string $observacion, ?User $usuario, ?string $motivo = null, array $extra = []): Collection
    {
        return $this->registrar($lineas, function (Variante $variante, array $linea) use ($motivo, $extra) {
            $cantidad = (int) $linea['cantidad'];
            // null = costo desconocido (p. ej. devolver una venta de stock que
            // entró por un ajuste): no se toca el promedio. Un 0 lo arrastraría
            // hacia abajo sin que nadie haya comprado nada gratis.
            $costo = isset($linea['costo_unitario']) ? (float) $linea['costo_unitario'] : null;

            // Promedio ponderado. Con stock en 0 (o sin costo previo) el
            // costo anterior no pesa: vale el de esta compra.
            if ($costo !== null) {
                $stock = max(0, $variante->stock);
                $anterior = $variante->costo_promedio !== null ? (float) $variante->costo_promedio : null;
                $variante->costo_promedio = $anterior === null || $stock === 0
                    ? $costo
                    : round((($stock * $anterior) + ($cantidad * $costo)) / ($stock + $cantidad), 4);
            }

            return [
                ...$extra,
                'tipo' => MovimientoInventario::ENTRADA,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'motivo' => $motivo,
            ];
        }, $referencia, $observacion, $usuario);
    }

    /**
     * Merma, daño, regalo, venta… Nunca deja el stock en negativo.
     *
     * @param  array<int, array{variante_id: int, cantidad: int}>  $lineas
     * @param  array<string, mixed>  $extra  columnas adicionales del movimiento (p. ej. `pedido_id`)
     * @return Collection<int, MovimientoInventario>
     */
    public function salida(array $lineas, string $motivo, ?string $referencia, ?string $observacion, ?User $usuario, array $extra = []): Collection
    {
        return $this->registrar($lineas, fn (Variante $variante, array $linea) => [
            ...$extra,
            'tipo' => MovimientoInventario::SALIDA,
            'cantidad' => -(int) $linea['cantidad'],
            'motivo' => $motivo,
        ], $referencia, $observacion, $usuario);
    }

    /**
     * Conteo físico: se informa el stock REAL y se registra la diferencia.
     * Las variantes que ya coinciden no generan movimiento.
     *
     * @param  array<int, array{variante_id: int, stock_real: int}>  $lineas
     * @return Collection<int, MovimientoInventario>
     */
    public function ajuste(array $lineas, ?string $referencia, ?string $observacion, ?User $usuario): Collection
    {
        return $this->registrar($lineas, function (Variante $variante, array $linea) {
            $diferencia = (int) $linea['stock_real'] - $variante->stock;

            return $diferencia === 0 ? null : [
                'tipo' => MovimientoInventario::AJUSTE,
                'cantidad' => $diferencia,
                'motivo' => MovimientoInventario::MOTIVO_CONTEO,
            ];
        }, $referencia, $observacion, $usuario);
    }

    /**
     * `$calcular` devuelve los datos del movimiento (con `cantidad` con signo)
     * o null si no hay nada que registrar para esa línea.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  callable(Variante, array<string, mixed>): (array<string, mixed>|null)  $calcular
     * @return Collection<int, MovimientoInventario>
     */
    private function registrar(array $lineas, callable $calcular, ?string $referencia, ?string $observacion, ?User $usuario): Collection
    {
        return DB::transaction(function () use ($lineas, $calcular, $referencia, $observacion, $usuario) {
            $grupo = (string) Str::uuid();

            // Bloqueadas en orden de id: dos documentos con las mismas
            // variantes en distinto orden no se trancan entre sí (deadlock).
            $variantes = Variante::query()
                ->whereKey(collect($lineas)->pluck('variante_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $movimientos = new Collection;
            $sinStock = [];

            foreach (array_values($lineas) as $i => $linea) {
                $variante = $variantes[(int) $linea['variante_id']];
                $datos = $calcular($variante, $linea);
                if ($datos === null) {
                    continue;
                }

                $stock = $variante->stock + $datos['cantidad'];
                if ($stock < 0) {
                    $sinStock["movimiento.lineas.{$i}.cantidad"] = "Stock insuficiente: hay {$variante->stock}.";

                    continue;
                }

                // stock y costo_promedio no son fillable a propósito.
                $variante->forceFill(['stock' => $stock])->save();

                $movimientos->push(MovimientoInventario::create([
                    ...$datos,
                    'grupo' => $grupo,
                    'variante_id' => $variante->id,
                    'stock_resultante' => $stock,
                    'referencia' => $referencia,
                    'observacion' => $observacion,
                    'user_id' => $usuario?->id,
                ]));
            }

            // Todo o nada: con una línea sin stock no se registra ninguna.
            if ($sinStock !== []) {
                throw ValidationException::withMessages($sinStock);
            }

            return $movimientos;
        });
    }
}
