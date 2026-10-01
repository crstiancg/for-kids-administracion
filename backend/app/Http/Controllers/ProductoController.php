<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Models\Archivo;
use App\Models\Categoria;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Variante;
use App\Services\ArchivosService;
use App\Services\Inventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function __construct(private ArchivosService $archivos, private Inventario $inventario) {}

    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->with(['categoria:id,nombre', 'portada'])
            ->withCount('variantes')
            ->withSum('variantes as stock_total', 'stock');

        // Filtrar por "Ropa" trae también lo de "Ropa › Niños › Polos".
        if ($request->filled('categoria_id')) {
            $categoria = Categoria::find($request->integer('categoria_id'));
            $query->whereIn('categoria_id', $categoria
                ? [$categoria->id, ...$categoria->descendientesIds()]
                : []);
        }

        // Búsqueda por nombre o por SKU de cualquiera de sus variantes. Va
        // acá y no en generateViewSetList: ese sólo busca en columnas propias.
        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->where(fn (Builder $q) => $q
                ->where('productos.nombre', 'like', $term)
                ->orWhereHas('variantes', fn (Builder $v) => $v->where('sku', 'like', $term)));
        }

        return $this->generateViewSetList(
            $request,
            $query,
            ['activo'],
            [],
            ['id', 'nombre', 'precio', 'activo'],
            ProductoResource::class,
        );
    }

    public function store(StoreProductoRequest $request): JsonResponse
    {
        $producto = DB::transaction(function () use ($request) {
            $datos = $request->validated('producto');
            $producto = Producto::create($datos);
            $this->guardarRelaciones($producto, $datos, $request);

            return $producto;
        });

        return response()->json($this->conDetalle($producto), 201);
    }

    public function show(Producto $producto): JsonResponse
    {
        return response()->json($this->conDetalle($producto));
    }

    public function update(StoreProductoRequest $request, Producto $producto): JsonResponse
    {
        DB::transaction(function () use ($request, $producto) {
            $datos = $request->validated('producto');
            $producto->update($datos);
            $this->guardarRelaciones($producto, $datos, $request);
        });

        return response()->json($this->conDetalle($producto->refresh()));
    }

    public function destroy(Producto $producto): JsonResponse|Response
    {
        if ($producto->variantes()->where('stock', '!=', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene variantes con stock. Desactívelo en su lugar.',
            ], 409);
        }

        // El historial de inventario no se pierde (FK restrict).
        if ($producto->variantes()->whereHas('movimientos')->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene historial de inventario. Desactívelo en su lugar.',
            ], 409);
        }

        DB::transaction(function () use ($producto) {
            // El polimórfico no tiene FK: el cascade de variantes no alcanza
            // a sus fotos.
            foreach ($producto->variantes as $variante) {
                $this->archivos->eliminarDe($variante);
            }
            $this->archivos->eliminarDe($producto);
            $producto->delete();
        });

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $datos  producto validado
     */
    private function guardarRelaciones(Producto $producto, array $datos, StoreProductoRequest $request): void
    {
        // Antes de tocar nada: una foto puede venir de una variante que se
        // quita en este mismo guardado.
        $fuentes = $this->fuentesDeFotos($datos);

        $this->archivos->sincronizar($producto, $datos['archivos'] ?? [], "productos/{$producto->id}", $fuentes);
        $variantes = $this->sincronizarVariantes($producto, $datos['variantes'], $fuentes);
        $this->registrarStockInicial($variantes, $datos, $request);
    }

    /**
     * El stock inicial de las variantes NUEVAS entra como una entrada de
     * inventario ("Alta de producto"), en la misma transacción: el stock
     * sigue saliendo sólo del libro de movimientos (historial y costo
     * promedio incluidos). Las variantes que ya existían se mueven desde
     * Inventario.
     *
     * @param  array<int, Variante>  $variantes  en el orden del payload
     * @param  array<string, mixed>  $datos
     */
    private function registrarStockInicial(array $variantes, array $datos, StoreProductoRequest $request): void
    {
        $lineas = [];
        foreach (array_values($datos['variantes']) as $i => $fila) {
            $cantidad = (int) ($fila['stock_inicial'] ?? 0);
            if (! empty($fila['id']) || $cantidad <= 0) {
                continue;
            }
            $lineas[] = [
                'variante_id' => $variantes[$i]->id,
                'cantidad' => $cantidad,
                // El de la variante o, si no se ajustó, el general del producto.
                'costo_unitario' => $fila['costo_unitario'] ?? $datos['costo_compra'],
            ];
        }

        if ($lineas === []) {
            return;
        }

        $this->inventario->entrada(
            $lineas,
            $datos['referencia_compra'] ?? null,
            null,
            $request->user(),
            MovimientoInventario::MOTIVO_ALTA_PRODUCTO,
        );
    }

    /**
     * Las filas con id se actualizan, las sin id se crean y las que ya no
     * vienen se borran (el request ya garantizó que ninguna tenía stock).
     *
     * Devuelve las variantes guardadas en el orden del payload (para
     * registrarles el stock inicial).
     *
     * @param  array<int, array<string, mixed>>  $variantes
     * @param  array<int, Archivo>  $fuentes
     * @return array<int, Variante>
     */
    private function sincronizarVariantes(Producto $producto, array $variantes, array $fuentes): array
    {
        $conservadas = collect($variantes)->pluck('id')->filter()->all();

        foreach ($producto->variantes()->whereNotIn('id', $conservadas)->get() as $quitada) {
            $this->archivos->eliminarDe($quitada);
            $quitada->delete();
        }

        $existentes = $producto->variantes()->get()->keyBy('id');

        // Primero se liberan los SKU de las que se editan: si A pasa a usar el
        // SKU viejo de B en el mismo guardado, el unique chocaría a mitad.
        foreach ($existentes as $variante) {
            $variante->update(['sku' => "~{$variante->id}"]);
        }

        $guardadas = [];
        foreach (array_values($variantes) as $datos) {
            $campos = collect($datos)->only(['talla_id', 'color_id', 'sku', 'precio'])->all();
            // En multipart llegan como texto: se guardan como número.
            $campos['medidas'] = array_map('floatval', $datos['medidas'] ?? []) ?: null;

            $variante = empty($datos['id'])
                ? $producto->variantes()->create($campos)
                : tap($existentes[$datos['id']])->update($campos);

            $this->archivos->sincronizar($variante, $datos['archivos'] ?? [], "productos/{$producto->id}/variantes", $fuentes);
            $guardadas[] = $variante;
        }

        return $guardadas;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<int, Archivo>
     */
    private function fuentesDeFotos(array $datos): array
    {
        $ids = collect($datos['archivos'] ?? [])
            ->merge(collect($datos['variantes'])->flatMap(fn ($v) => $v['archivos'] ?? []))
            ->pluck('id')
            ->filter()
            ->unique();

        return $ids->isEmpty() ? [] : Archivo::query()->whereKey($ids)->get()->keyBy('id')->all();
    }

    /**
     * Sin el envoltorio `data` de los Resources: el resto de la API devuelve
     * el objeto directo y el front lo espera así.
     *
     * @return array<string, mixed>
     */
    private function conDetalle(Producto $producto): array
    {
        return (new ProductoResource($producto->load([
            'categoria:id,nombre',
            'archivos',
            'variantes' => fn ($q) => $q
                ->join('tallas', 'tallas.id', '=', 'variantes.talla_id')
                ->orderBy('tallas.orden')
                ->orderBy('variantes.id')
                ->select('variantes.*')
                // Después del select: antes, select('variantes.*') lo pisa.
                ->withExists('movimientos'),
            'variantes.talla:id,nombre,orden',
            'variantes.color:id,nombre,hexadecimal',
            'variantes.archivos',
        ])))->resolve(request());
    }
}
