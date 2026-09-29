<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Services\Pedidos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los pedidos no se borran: se cancelan (son historial de ventas). Sólo un
 * pedido pendiente se edita; los cambios de estado van por App\Services\Pedidos.
 */
class PedidoController extends Controller
{
    public function __construct(private Pedidos $pedidos) {}

    public function index(Request $request): JsonResponse
    {
        $query = Pedido::query()->with('cliente')->withCount('items');

        // Por código, nombre o documento del cliente.
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('pedidos.codigo', 'like', $term)
                ->orWhereHas('cliente', fn (Builder $c) => $c
                    ->where('nombre', 'like', $term)
                    ->orWhere('numero_documento', 'like', $term)));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['estado', 'canal', 'cliente_id'],
            [],
            ['id', 'total', 'estado'],
            PedidoResource::class,
        );
    }

    public function store(StorePedidoRequest $request): JsonResponse
    {
        $pedido = $this->pedidos->guardar(new Pedido, $request->validated('pedido'), $request->user());

        return response()->json($this->conDetalle($pedido), 201);
    }

    public function show(Pedido $pedido): JsonResponse
    {
        return response()->json($this->conDetalle($pedido));
    }

    public function update(StorePedidoRequest $request, Pedido $pedido): JsonResponse
    {
        if (! $pedido->editable()) {
            return response()->json([
                'message' => "Un pedido {$pedido->estado} ya no se edita.",
            ], 409);
        }

        $this->pedidos->guardar($pedido, $request->validated('pedido'), $request->user());

        return response()->json($this->conDetalle($pedido));
    }

    public function confirmar(Request $request, Pedido $pedido): JsonResponse
    {
        return response()->json($this->conDetalle($this->pedidos->confirmar($pedido, $request->user())));
    }

    public function entregar(Pedido $pedido): JsonResponse
    {
        return response()->json($this->conDetalle($this->pedidos->entregar($pedido)));
    }

    public function cancelar(Request $request, Pedido $pedido): JsonResponse
    {
        return response()->json($this->conDetalle($this->pedidos->cancelar($pedido, $request->user())));
    }

    /**
     * @return array<string, mixed>
     */
    private function conDetalle(Pedido $pedido): array
    {
        $pedido->load([
            'cliente',
            'usuario:id,name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.variante.producto:id,nombre,precio',
            'items.variante.talla:id,nombre',
            'items.variante.color:id,nombre,hexadecimal',
        ]);

        return (new PedidoResource($pedido))->resolve(request());
    }
}
