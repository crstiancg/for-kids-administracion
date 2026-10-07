<?php

namespace App\Services;

use App\Models\Cambio;
use App\Models\CambioItem;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\MovimientoSaldo;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use App\Models\Variante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de prenda sobre una venta ENTREGADA, dentro del plazo. Reglas:
 *
 * - Lo que vuelve vale lo que REALMENTE se pagó (el descuento del pedido se
 *   reparte entre sus ítems) y queda como SALDO A FAVOR del cliente: no se
 *   devuelve efectivo.
 * - Lo que vuelve entra al inventario con su costo de esa venta.
 * - Lo que se lleva es un pedido nuevo (stock, costo y caja como cualquier
 *   venta) que se paga PRIMERO con el saldo; si cuesta más, la diferencia
 *   se cobra en caja. Si cuesta menos, el resto sigue a favor.
 *
 * Todo en una transacción: o se hace el cambio entero o nada.
 */
class Cambios
{
    public function __construct(
        private Pedidos $pedidos,
        private Cajas $cajas,
        private Inventario $inventario,
        private Precios $precios,
    ) {}

    /**
     * @param  array<int, array{pedido_item_id: int, cantidad: int}>  $devueltos
     * @param  array<int, array{variante_id: int, cantidad: int}>  $nuevos
     * @param  array<int, array{metodo: string, monto: numeric, recibido?: numeric|null, referencia?: string|null}>  $pagos  sólo la diferencia
     */
    public function registrar(
        Pedido $pedido,
        array $devueltos,
        array $nuevos,
        array $pagos,
        ?int $clienteId,
        ?string $observacion,
        User $usuario,
    ): Cambio {
        return DB::transaction(function () use ($pedido, $devueltos, $nuevos, $pagos, $clienteId, $observacion, $usuario) {
            $pedido = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $this->exigirCambiable($pedido);

            // El saldo necesita dueño: el del pedido o uno que se elige ahora
            // (una venta de "Cliente varios").
            $cliente = Cliente::find($pedido->cliente_id ?? $clienteId);
            if (! $cliente) {
                throw ValidationException::withMessages(['cambio.cliente_id' => 'Elegí el cliente: lo devuelto queda como su saldo a favor.']);
            }

            [$items, $valor] = $this->validarDevueltos($pedido, $devueltos);

            $cambio = Cambio::query()->forceCreate([
                'pedido_id' => $pedido->id,
                'cliente_id' => $cliente->id,
                'valor_devuelto' => $valor,
                'observacion' => $observacion,
                'user_id' => $usuario->id,
            ]);
            foreach ($items as $item) {
                CambioItem::query()->forceCreate(['cambio_id' => $cambio->id, ...$item]);
            }

            // Vuelve al stock con el costo de ESA venta (no el promedio de
            // hoy): así el costo promedio no se distorsiona.
            $this->inventario->entrada(
                array_map(fn ($i) => ['variante_id' => $i['variante_id'], 'cantidad' => $i['cantidad'], 'costo_unitario' => $i['costo_unitario']], $items),
                $pedido->codigo,
                $observacion,
                $usuario,
                MovimientoInventario::MOTIVO_CAMBIO,
                ['pedido_id' => $pedido->id],
            );

            MovimientoSaldo::query()->forceCreate([
                'cliente_id' => $cliente->id,
                'monto' => $valor,
                'concepto' => "Cambio de {$pedido->codigo}",
                'cambio_id' => $cambio->id,
                'user_id' => $usuario->id,
            ]);

            if ($nuevos !== []) {
                $nuevo = $this->entregarNuevos($pedido, $cliente, $nuevos, $pagos, $usuario);
                $cambio->forceFill(['pedido_nuevo_id' => $nuevo->id])->save();
            }

            return $cambio;
        });
    }

    /**
     * Entregado y dentro del plazo (en días del negocio, no UTC).
     */
    private function exigirCambiable(Pedido $pedido): void
    {
        if ($pedido->estado !== Pedido::ENTREGADO) {
            throw ValidationException::withMessages(['cambio' => 'Sólo se cambia lo de una venta entregada.'])->status(409);
        }

        $zona = config('app.zona_negocio');
        $entregado = CarbonImmutable::parse($pedido->entregado_at)->timezone($zona)->startOfDay();
        $dias = (int) $entregado->diffInDays(CarbonImmutable::now($zona)->startOfDay());
        if ($dias > Cambio::PLAZO_DIAS) {
            throw ValidationException::withMessages([
                'cambio' => 'Pasaron '.$dias.' días de la compra: el plazo para cambios es de '.Cambio::PLAZO_DIAS.'.',
            ])->status(409);
        }
    }

    /**
     * Cada línea: que sea de este pedido y que no vuelva más de lo vendido
     * (contando cambios anteriores). Valor = precio × (total / subtotal).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function validarDevueltos(Pedido $pedido, array $devueltos): array
    {
        if ($devueltos === []) {
            throw ValidationException::withMessages(['cambio.devueltos' => 'Marcá qué prenda vuelve.']);
        }

        $lineas = PedidoItem::query()->where('pedido_id', $pedido->id)->get()->keyBy('id');
        $yaDevuelto = CambioItem::query()
            ->whereIn('pedido_item_id', $lineas->keys())
            ->groupBy('pedido_item_id')
            ->selectRaw('pedido_item_id, SUM(cantidad) as total')
            ->pluck('total', 'pedido_item_id');

        // El descuento del pedido, repartido en proporción a cada línea.
        $factor = (float) $pedido->subtotal > 0 ? (float) $pedido->total / (float) $pedido->subtotal : 1.0;

        $items = [];
        $valor = 0.0;
        $errores = [];
        foreach (array_values($devueltos) as $i => $d) {
            $linea = $lineas[(int) $d['pedido_item_id']] ?? null;
            if (! $linea) {
                $errores["cambio.devueltos.{$i}.pedido_item_id"] = 'Esa prenda no es de esta venta.';

                continue;
            }
            $disponible = $linea->cantidad - (int) ($yaDevuelto[$linea->id] ?? 0);
            $cantidad = (int) $d['cantidad'];
            if ($cantidad > $disponible) {
                $errores["cambio.devueltos.{$i}.cantidad"] = $disponible > 0
                    ? "Sólo pueden volver {$disponible}."
                    : 'Esta prenda ya se cambió.';

                continue;
            }

            $unitario = round((float) $linea->precio_unitario * $factor, 2);
            $valor += $unitario * $cantidad;
            $items[] = [
                'pedido_item_id' => $linea->id,
                'variante_id' => $linea->variante_id,
                'cantidad' => $cantidad,
                'valor_unitario' => $unitario,
                'costo_unitario' => $linea->costo_unitario,
            ];
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return [$items, round($valor, 2)];
    }

    /**
     * Lo que se lleva: un pedido nuevo al precio de HOY (con ofertas), que
     * se paga primero con el saldo y el resto con $pagos.
     */
    private function entregarNuevos(Pedido $original, Cliente $cliente, array $nuevos, array $pagos, User $usuario): Pedido
    {
        $variantes = Variante::query()->with('producto:id,precio,categoria_id')->whereKey(array_column($nuevos, 'variante_id'))->get()->keyBy('id');

        $pedido = $this->pedidos->guardar(new Pedido, [
            'cliente_id' => $cliente->id,
            'canal' => 'mostrador',
            'descuento' => 0,
            'observacion' => "Cambio de {$original->codigo}",
            'items' => array_map(function ($n) use ($variantes) {
                $v = $variantes[(int) $n['variante_id']];

                return [
                    'variante_id' => $v->id,
                    'cantidad' => (int) $n['cantidad'],
                    'precio_unitario' => $this->precios->vigente((float) ($v->precio ?? $v->producto->precio), $v->producto_id, $v->producto->categoria_id, $v->id)['precio'],
                ];
            }, array_values($nuevos)),
        ], $usuario);

        $this->traducir(fn () => $this->pedidos->confirmar($pedido, $usuario), 'pedido.items.', 'cambio.nuevos.');
        $pedido->refresh();

        $total = (float) $pedido->total;
        $conSaldo = round(min($cliente->saldo(), $total), 2);
        if ($conSaldo > 0) {
            $this->cajas->cobrar($pedido, ['metodo' => Pago::SALDO, 'monto' => $conSaldo], $usuario);
        }

        // La diferencia, si la nueva cuesta más: tiene que cubrirse justa.
        $diferencia = round($total - $conSaldo, 2);
        $pagado = round(array_sum(array_map(fn ($p) => (float) $p['monto'], $pagos)), 2);
        if (abs($pagado - $diferencia) > 0.004) {
            throw ValidationException::withMessages([
                'cambio.pagos' => $diferencia > 0
                    ? 'Falta cobrar la diferencia: S/ '.number_format($diferencia, 2).'.'
                    : 'No hay diferencia que cobrar.',
            ]);
        }
        foreach (array_values($pagos) as $j => $pago) {
            $this->traducir(fn () => $this->cajas->cobrar($pedido, $pago, $usuario), 'pago.', "cambio.pagos.{$j}.");
        }

        return $this->pedidos->entregar($pedido);
    }

    private function traducir(callable $accion, string $de, string $a): void
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($mensajes, $clave) => [str_replace($de, $a, $clave) => $mensajes])
                ->all())->status($e->status);
        }
    }
}
