<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarCambioRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Cambio;
use App\Models\CambioItem;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Services\Cambios;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Cambios de prenda sobre una venta entregada (ver App\Services\Cambios).
 */
class CambioController extends Controller
{
    public function __construct(private Cambios $cambios) {}

    /**
     * Lo que el form necesita: qué puede volver de cada línea (descontando
     * cambios anteriores) y a qué valor, el saldo del cliente y el plazo.
     */
    public function preparar(Pedido $pedido): JsonResponse
    {
        $pedido->load(['cliente', 'items.variante.producto:id,nombre', 'items.variante.talla:id,nombre', 'items.variante.color:id,nombre,hexadecimal']);

        $yaDevuelto = CambioItem::query()
            ->whereIn('pedido_item_id', $pedido->items->pluck('id'))
            ->groupBy('pedido_item_id')
            ->selectRaw('pedido_item_id, SUM(cantidad) as total')
            ->pluck('total', 'pedido_item_id');

        $factor = (float) $pedido->subtotal > 0 ? (float) $pedido->total / (float) $pedido->subtotal : 1.0;

        $zona = config('app.zona_negocio');
        $dias = $pedido->entregado_at
            ? (int) CarbonImmutable::parse($pedido->entregado_at)->timezone($zona)->startOfDay()->diffInDays(CarbonImmutable::now($zona)->startOfDay())
            : null;

        return response()->json([
            'pedido' => ['id' => $pedido->id, 'codigo' => $pedido->codigo, 'estado' => $pedido->estado],
            'cliente' => $pedido->cliente ? [
                'id' => $pedido->cliente->id,
                'nombre' => $pedido->cliente->nombre,
                'saldo' => $pedido->cliente->saldo(),
            ] : null,
            'plazo_dias' => Cambio::PLAZO_DIAS,
            'dias_desde_entrega' => $dias,
            'cambiable' => $pedido->estado === Pedido::ENTREGADO && $dias !== null && $dias <= Cambio::PLAZO_DIAS,
            'items' => $pedido->items->map(fn ($i) => [
                'id' => $i->id,
                'producto' => $i->variante->producto->nombre,
                'talla' => $i->variante->talla->nombre,
                'color' => $i->variante->color->only(['nombre', 'hexadecimal']),
                'vendidos' => $i->cantidad,
                'disponibles' => $i->cantidad - (int) ($yaDevuelto[$i->id] ?? 0),
                // Lo que realmente se pagó por unidad (descuento repartido).
                'valor_unitario' => round((float) $i->precio_unitario * $factor, 2),
            ])->values(),
        ]);
    }

    public function store(RegistrarCambioRequest $request, Pedido $pedido): JsonResponse
    {
        $datos = $request->validated('cambio');

        $cambio = $this->cambios->registrar(
            $pedido,
            $datos['devueltos'],
            $datos['nuevos'] ?? [],
            $datos['pagos'] ?? [],
            $datos['cliente_id'] ?? null,
            $datos['observacion'] ?? null,
            $request->user(),
        );

        $nuevo = $cambio->pedidoNuevo?->load(['cliente', 'usuario:id,name', 'items.variante.producto', 'items.variante.talla', 'items.variante.color', 'pagos']);

        return response()->json([
            'cambio' => ['id' => $cambio->id, 'valor_devuelto' => $cambio->valor_devuelto],
            // El ticket de lo que se llevó (si se llevó algo).
            'pedido_nuevo' => $nuevo ? (new PedidoResource($nuevo))->resolve($request) : null,
            'saldo_cliente' => Cliente::find($cambio->cliente_id)->saldo(),
        ], 201);
    }
}
