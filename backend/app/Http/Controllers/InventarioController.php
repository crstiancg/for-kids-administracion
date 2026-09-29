<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAjusteRequest;
use App\Http\Requests\RegistrarEntradaRequest;
use App\Http\Requests\RegistrarSalidaRequest;
use App\Http\Resources\MovimientoResource;
use App\Http\Resources\VarianteStockResource;
use App\Models\MovimientoInventario;
use App\Models\Variante;
use App\Services\Inventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;

/**
 * Libro de inventario: se consulta y se le agregan movimientos. No hay
 * update ni destroy: un movimiento mal cargado se corrige con otro.
 */
class InventarioController extends Controller
{
    private const RELACIONES = ['variante.producto:id,nombre', 'variante.talla:id,nombre', 'variante.color:id,nombre,hexadecimal'];

    public function __construct(private Inventario $inventario) {}

    /**
     * Historial, del más nuevo al más viejo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MovimientoInventario::query()->with([...self::RELACIONES, 'usuario:id,name']);

        if ($request->filled('producto_id')) {
            $query->whereHas('variante', fn (Builder $q) => $q->where('producto_id', $request->integer('producto_id')));
        }
        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->date('hasta'));
        }

        // Por SKU, producto o referencia (n° de factura).
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('movimiento_inventarios.referencia', 'like', $term)
                ->orWhereHas('variante', fn (Builder $v) => $v
                    ->where('sku', 'like', $term)
                    ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', $term))));
        }

        if (! $request->filled('order_by')) {
            $request->merge(['order_by' => '-id']);
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['tipo', 'variante_id', 'grupo'],
            [],
            ['id', 'tipo', 'cantidad'],
            MovimientoResource::class,
        );
    }

    /**
     * Buscador de variantes para las líneas: por SKU o nombre del producto.
     */
    public function variantes(Request $request): JsonResponse
    {
        $query = Variante::query()
            ->with(['producto:id,nombre,precio', 'talla:id,nombre,orden', 'color:id,nombre,hexadecimal'])
            ->whereHas('producto', fn (Builder $p) => $p->where('activo', true));

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('variantes.sku', 'like', $term)
                ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', $term)));
        }

        return $this->generateViewSetList(
            $request,
            $query->orderBy('producto_id')->orderBy('talla_id')->orderBy('color_id'),
            ['producto_id'],
            [],
            [],
            VarianteStockResource::class,
        );
    }

    public function entradas(RegistrarEntradaRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        return $this->respuesta($this->inventario->entrada(
            $movimiento['lineas'], $movimiento['referencia'] ?? null, $movimiento['observacion'] ?? null, $request->user(),
        ));
    }

    public function salidas(RegistrarSalidaRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        return $this->respuesta($this->inventario->salida(
            $movimiento['lineas'], $movimiento['motivo'], $movimiento['referencia'] ?? null, $movimiento['observacion'] ?? null, $request->user(),
        ));
    }

    public function ajustes(RegistrarAjusteRequest $request): JsonResponse
    {
        $movimiento = $request->validated('movimiento');

        return $this->respuesta($this->inventario->ajuste(
            $movimiento['lineas'], $movimiento['referencia'] ?? null, $movimiento['observacion'] ?? null, $request->user(),
        ));
    }

    /**
     * Los movimientos creados. Vacío es válido (un conteo que coincidió en
     * todo no genera movimientos).
     *
     * @param  Collection<int, MovimientoInventario>  $movimientos
     */
    private function respuesta(Collection $movimientos): JsonResponse
    {
        $movimientos->load([...self::RELACIONES, 'usuario:id,name']);

        return response()->json([
            'data' => $movimientos->map(fn ($m) => (new MovimientoResource($m))->resolve(request())),
        ], 201);
    }
}
