<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarVentaRequest;
use App\Http\Resources\CatalogoProductoResource;
use App\Http\Resources\PedidoResource;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Services\Cajas;
use App\Services\Pedidos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Punto de venta: una venta de mostrador ES un pedido que nace y termina en
 * el acto. No hay lógica propia de stock ni de dinero: encadena los mismos
 * servicios que el flujo de pedidos, en UNA transacción. Si falla el stock o
 * un cobro, no queda nada a medias (ni pedido, ni descuento de stock, ni
 * pagos).
 */
class VentaController extends Controller
{
    public function __construct(private Pedidos $pedidos, private Cajas $cajas) {}

    /**
     * Catálogo del punto de venta: productos activos con portada, stock,
     * vendidos (últimos 90 días) y sus variantes. Filtra por categoría (con
     * sus subcategorías), talla, color, "con stock" y nombre/SKU.
     */
    public function catalogo(Request $request): JsonResponse
    {
        $vendidos = PedidoItem::query()
            ->selectRaw('COALESCE(SUM(pedido_items.cantidad), 0)')
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('pedidos', 'pedidos.id', '=', 'pedido_items.pedido_id')
            ->whereColumn('variantes.producto_id', 'productos.id')
            ->whereIn('pedidos.estado', [Pedido::CONFIRMADO, Pedido::ENTREGADO])
            ->where('pedidos.confirmado_at', '>=', now()->subDays(90));

        $query = Producto::query()
            ->select('productos.*')
            ->addSelect(['vendidos' => $vendidos])
            ->withSum('variantes as stock_total', 'stock')
            ->where('activo', true)
            ->with([
                'categoria:id,nombre',
                'portada',
                'variantes' => fn ($q) => $q
                    ->join('tallas', 'tallas.id', '=', 'variantes.talla_id')
                    ->orderBy('tallas.orden')
                    ->orderBy('variantes.id')
                    ->select('variantes.*'),
                'variantes.talla:id,nombre,orden',
                'variantes.color:id,nombre,hexadecimal',
                'variantes.portada',
            ]);

        if ($request->filled('categoria_id')) {
            $categoria = Categoria::find($request->integer('categoria_id'));
            $query->whereIn('categoria_id', $categoria ? [$categoria->id, ...$categoria->descendientesIds()] : []);
        }

        // Talla y color filtran por variantes que existan (y, con "con stock",
        // que tengan unidades): "talla 8" muestra lo que se puede vender en 8.
        $conStock = $request->boolean('con_stock');
        $variante = fn (Builder $v) => $v
            ->when($request->filled('talla_id'), fn ($q) => $q->where('talla_id', $request->integer('talla_id')))
            ->when($request->filled('color_id'), fn ($q) => $q->where('color_id', $request->integer('color_id')))
            ->when($conStock, fn ($q) => $q->where('stock', '>', 0));
        if ($request->filled('talla_id') || $request->filled('color_id') || $conStock) {
            $query->whereHas('variantes', $variante);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('productos.nombre', 'like', $term)
                ->orWhereHas('variantes', fn (Builder $v) => $v
                    ->where('sku', 'like', $term)
                    ->orWhere('codigo_barras', 'like', $term)));
        }

        match ($request->input('order_by', 'vendidos')) {
            'nombre' => $query->orderBy('productos.nombre'),
            'precio' => $query->orderBy('productos.precio'),
            '-precio' => $query->orderByDesc('productos.precio'),
            'stock' => $query->orderByDesc('stock_total'),
            default => $query->orderByDesc('vendidos')->orderBy('productos.nombre'),
        };

        return response()->json(
            $query->paginate($this->getPageSize($request))
                ->through(fn ($producto) => (new CatalogoProductoResource($producto))->resolve($request))
        );
    }

    public function store(RegistrarVentaRequest $request): JsonResponse
    {
        $venta = $request->validated('venta');
        $usuario = $request->user();

        $pedido = DB::transaction(function () use ($venta, $usuario) {
            $pedido = $this->pedidos->guardar(new Pedido, [
                'cliente_id' => $venta['cliente_id'] ?? null,
                'canal' => 'mostrador',
                'descuento' => $venta['descuento'] ?? 0,
                'observacion' => null,
                'items' => $venta['items'],
            ], $usuario);

            // Los errores de los servicios vienen con claves de pedidos
            // (pedido.items.N, pago.monto): se muestran en el carrito.
            $this->traducirErrores(fn () => $this->pedidos->confirmar($pedido, $usuario), 'pedido.items.', 'venta.items.');

            foreach (array_values($venta['pagos']) as $j => $pago) {
                $this->traducirErrores(fn () => $this->cajas->cobrar($pedido, $pago, $usuario), 'pago.', "venta.pagos.{$j}.");
            }

            $this->pedidos->entregar($pedido);

            return $pedido;
        });

        return response()->json($this->conDetalle($pedido->refresh()), 201);
    }

    private function traducirErrores(callable $accion, string $de, string $a): void
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($mensajes, $clave) => [str_replace($de, $a, $clave) => $mensajes])
                ->all())->status($e->status);
        }
    }

    /**
     * El pedido con todo lo necesario para imprimir el ticket.
     *
     * @return array<string, mixed>
     */
    private function conDetalle(Pedido $pedido): array
    {
        $pedido->load([
            'cliente',
            'usuario:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.variante.producto:id,nombre,precio,categoria_id',
            'items.variante.talla:id,nombre',
            'items.variante.color:id,nombre,hexadecimal',
            'pagos' => fn ($q) => $q->orderBy('id'),
            'pagos.usuario:id,name',
        ]);

        return (new PedidoResource($pedido))->resolve(request());
    }
}
