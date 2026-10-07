<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Oferta;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Models\Variante;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Las cifras del negocio que leen el dashboard y los reportes. Sólo lee.
 *
 * - Venta = pedido confirmado o entregado, contado por `confirmado_at`.
 * - Ganancia = total vendido − costo CONGELADO de cada ítem (el de ese día).
 * - Los rangos llegan como Periodo (días del negocio) y se consultan en UTC.
 */
class Estadisticas
{
    public const ESTADOS_VENTA = [Pedido::CONFIRMADO, Pedido::ENTREGADO];

    /** Menos de esto es stock bajo (fijo por ahora; por variante más adelante). */
    public const STOCK_BAJO = 4;

    /** Días sin vender para considerar un producto "sin movimiento". */
    public const DIAS_SIN_MOVIMIENTO = 30;

    // ── Ventas ──

    /**
     * @return array{total: float, cantidad: int, ticket_promedio: float}
     */
    public function resumenVentas(Periodo $p): array
    {
        $fila = $this->vendidos($p)->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as cantidad')->first();
        $total = (float) $fila->total;
        $cantidad = (int) $fila->cantidad;

        return [
            'total' => round($total, 2),
            'cantidad' => $cantidad,
            'ticket_promedio' => $cantidad ? round($total / $cantidad, 2) : 0,
        ];
    }

    /**
     * Una barra por día. Se agrupa en PHP: convertir la zona en SQL cambia
     * entre MySQL y SQLite, y un año de una tienda son pocas filas.
     *
     * @return array<int, array{fecha: string, total: float, cantidad: int}>
     */
    public function seriePorDia(Periodo $p): array
    {
        $zona = $p->desde->getTimezone();
        $dias = [];
        for ($d = $p->desde; $d->lt($p->hasta); $d = $d->addDay()) {
            $dias[$d->toDateString()] = ['fecha' => $d->toDateString(), 'total' => 0.0, 'cantidad' => 0];
        }

        $this->vendidos($p)->get(['total', 'confirmado_at'])->each(function (Pedido $pedido) use (&$dias, $zona) {
            $dia = $pedido->confirmado_at->copy()->timezone($zona)->toDateString();
            if (isset($dias[$dia])) {
                $dias[$dia]['total'] += (float) $pedido->total;
                $dias[$dia]['cantidad']++;
            }
        });

        return array_values(array_map(fn ($d) => [...$d, 'total' => round($d['total'], 2)], $dias));
    }

    /**
     * @return array{monto: float, margen: int, items_sin_costo: int}
     */
    public function ganancia(Periodo $p): array
    {
        $vendido = (float) $this->vendidos($p)->sum('total');
        $costo = (float) $this->items($p)->whereNotNull('pedido_items.costo_unitario')
            ->sum(DB::raw('pedido_items.cantidad * pedido_items.costo_unitario'));
        // Sin costo (stock que entró por un ajuste, sin compra) no se puede
        // valuar: se informa en vez de contarlo como ganancia pura.
        $sinCosto = $this->items($p)->whereNull('pedido_items.costo_unitario')->count();
        $ganancia = $vendido - $costo;

        return [
            'monto' => round($ganancia, 2),
            'margen' => $vendido > 0 ? (int) round($ganancia / $vendido * 100) : 0,
            'items_sin_costo' => $sinCosto,
        ];
    }

    /**
     * Lo cobrado por método de pago (con las devoluciones restando). Por fecha
     * del PAGO: un adelanto de ayer cuenta ayer.
     *
     * @return array<int, array{metodo: string, label: string, total: float, cantidad: int}>
     */
    public function porMetodo(Periodo $p): array
    {
        $filas = Pago::query()
            ->where('created_at', '>=', $p->desdeUtc())
            ->where('created_at', '<', $p->hastaUtc())
            ->groupBy('metodo')
            ->get(['metodo', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as cantidad')])
            ->keyBy('metodo');

        return collect(Pago::METODOS)->map(fn ($label, $metodo) => [
            'metodo' => $metodo,
            'label' => $label,
            'total' => round((float) ($filas[$metodo]->total ?? 0), 2),
            'cantidad' => (int) ($filas[$metodo]->cantidad ?? 0),
        ])->values()->all();
    }

    /**
     * @return array<int, array{id: int, nombre: string, unidades: int, total: float}>
     */
    public function topProductos(Periodo $p, int $limite = 5): array
    {
        return $this->items($p)
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('unidades')
            ->limit($limite)
            ->get(['productos.id', 'productos.nombre', DB::raw('SUM(pedido_items.cantidad) as unidades'), DB::raw('SUM(pedido_items.subtotal) as total')])
            ->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre, 'unidades' => (int) $f->unidades, 'total' => round((float) $f->total, 2)])
            ->all();
    }

    /**
     * Unidades por talla, en el orden de exhibición de las tallas: es la
     * curva de talles que hay que pedirle al proveedor.
     *
     * @return array<int, array{talla: string, unidades: int, total: float}>
     */
    public function porTalla(Periodo $p): array
    {
        return $this->items($p)
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('tallas', 'tallas.id', '=', 'variantes.talla_id')
            ->groupBy('tallas.id', 'tallas.nombre', 'tallas.orden')
            ->orderBy('tallas.orden')
            ->get(['tallas.nombre', DB::raw('SUM(pedido_items.cantidad) as unidades'), DB::raw('SUM(pedido_items.subtotal) as total')])
            ->map(fn ($f) => ['talla' => $f->nombre, 'unidades' => (int) $f->unidades, 'total' => round((float) $f->total, 2)])
            ->all();
    }

    /**
     * @return array<int, array{canal: string, label: string, total: float, cantidad: int}>
     */
    public function porCanal(Periodo $p): array
    {
        $filas = $this->vendidos($p)
            ->groupBy('canal')
            ->get(['canal', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as cantidad')])
            ->keyBy('canal');

        return collect(Pedido::CANALES)->map(fn ($label, $canal) => [
            'canal' => $canal,
            'label' => $label,
            'total' => round((float) ($filas[$canal]->total ?? 0), 2),
            'cantidad' => (int) ($filas[$canal]->cantidad ?? 0),
        ])->values()->all();
    }

    /**
     * Por categoría RAÍZ (Niños, Niñas, Bebés): las subcategorías suman a
     * su raíz. Las categorías se resuelven en PHP (son pocas).
     *
     * @return array<int, array{categoria: string, unidades: int, total: float}>
     */
    public function porCategoria(Periodo $p): array
    {
        $categorias = Categoria::query()->get(['id', 'parent_id', 'nombre'])->keyBy('id');
        $raiz = function (int $id) use ($categorias) {
            $actual = $categorias[$id] ?? null;
            while ($actual && $actual->parent_id && isset($categorias[$actual->parent_id])) {
                $actual = $categorias[$actual->parent_id];
            }

            return $actual;
        };

        $porRaiz = [];
        $this->items($p)
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->groupBy('productos.categoria_id')
            ->get(['productos.categoria_id', DB::raw('SUM(pedido_items.cantidad) as unidades'), DB::raw('SUM(pedido_items.subtotal) as total')])
            ->each(function ($f) use (&$porRaiz, $raiz) {
                $categoria = $raiz((int) $f->categoria_id);
                $clave = $categoria?->id ?? 0;
                $porRaiz[$clave] ??= ['categoria' => $categoria?->nombre ?? 'Sin categoría', 'unidades' => 0, 'total' => 0.0];
                $porRaiz[$clave]['unidades'] += (int) $f->unidades;
                $porRaiz[$clave]['total'] += (float) $f->total;
            });

        return collect($porRaiz)
            ->map(fn ($c) => [...$c, 'total' => round($c['total'], 2)])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Quién registró la venta (en el POS, el cajero).
     *
     * @return array<int, array{cajero: string, total: float, cantidad: int}>
     */
    public function porCajero(Periodo $p): array
    {
        return $this->vendidos($p)
            ->leftJoin('users', 'users.id', '=', 'pedidos.user_id')
            ->groupBy('pedidos.user_id', 'users.name')
            ->orderByDesc('total')
            ->get([DB::raw("COALESCE(users.name, '—') as cajero"), DB::raw('SUM(pedidos.total) as total'), DB::raw('COUNT(*) as cantidad')])
            ->map(fn ($f) => ['cajero' => $f->cajero, 'total' => round((float) $f->total, 2), 'cantidad' => (int) $f->cantidad])
            ->all();
    }

    /**
     * Sin "Cliente varios" (venta de mostrador sin cliente): no es alguien a
     * quien fidelizar.
     *
     * @return array<int, array{id: int, nombre: string, total: float, cantidad: int}>
     */
    public function mejoresClientes(Periodo $p, int $limite = 8): array
    {
        return $this->vendidos($p)
            ->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')
            ->groupBy('clientes.id', 'clientes.nombre')
            ->orderByDesc('total')
            ->limit($limite)
            ->get(['clientes.id', 'clientes.nombre', DB::raw('SUM(pedidos.total) as total'), DB::raw('COUNT(*) as cantidad')])
            ->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre, 'total' => round((float) $f->total, 2), 'cantidad' => (int) $f->cantidad])
            ->all();
    }

    /**
     * Las últimas ventas, sin importar el período: "qué se vendió recién".
     *
     * @return array<int, array<string, mixed>>
     */
    public function ultimasVentas(int $limite = 8): array
    {
        return Pedido::query()
            ->whereIn('estado', self::ESTADOS_VENTA)
            ->with(['cliente:id,nombre', 'usuario:id,name', 'pagos:id,pedido_id,metodo,monto'])
            ->withSum('items as unidades', 'cantidad')
            ->latest('confirmado_at')
            ->latest('id')
            ->limit($limite)
            ->get()
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'fecha' => $p->confirmado_at?->toIso8601String(),
                'cliente' => $p->cliente?->nombre,
                'canal' => Pedido::CANALES[$p->canal] ?? $p->canal,
                'unidades' => (int) $p->unidades,
                'total' => round((float) $p->total, 2),
                // Las devoluciones (monto negativo) no son "cómo pagó".
                'metodos' => $p->pagos->where('monto', '>', 0)->pluck('metodo')->unique()
                    ->map(fn ($m) => Pago::METODOS[$m] ?? $m)->values(),
                'cajero' => $p->usuario?->name,
            ])
            ->all();
    }

    // ── Inventario ──

    /**
     * @return array<string, mixed>
     */
    public function stockBajo(int $limite = 8): array
    {
        $query = Variante::query()
            ->where('stock', '<', self::STOCK_BAJO)
            ->whereHas('producto', fn (Builder $q) => $q->where('activo', true));

        return [
            'umbral' => self::STOCK_BAJO,
            'total' => (clone $query)->count(),
            'agotadas' => (clone $query)->where('stock', '<=', 0)->count(),
            'variantes' => $query
                ->with(['producto:id,nombre', 'talla:id,nombre', 'color:id,nombre,hexadecimal'])
                ->orderBy('stock')
                ->orderBy('producto_id')
                ->limit($limite)
                ->get()
                ->map(fn (Variante $v) => [
                    'id' => $v->id,
                    'producto_id' => $v->producto_id,
                    'producto' => $v->producto->nombre,
                    'talla' => $v->talla->nombre,
                    'color' => $v->color->only(['nombre', 'hexadecimal']),
                    'stock' => $v->stock,
                ]),
        ];
    }

    /**
     * Productos activos CON stock que no vendieron nada en los últimos N
     * días: candidatos a oferta. Ordenados por la plata que tienen parada.
     *
     * @return array<string, mixed>
     */
    public function sinMovimiento(int $limite = 8): array
    {
        $desde = CarbonImmutable::now()->subDays(self::DIAS_SIN_MOVIMIENTO);
        $vendidos = PedidoItem::query()
            ->join('pedidos', 'pedidos.id', '=', 'pedido_items.pedido_id')
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->whereIn('pedidos.estado', self::ESTADOS_VENTA)
            ->where('pedidos.confirmado_at', '>=', $desde)
            ->select('variantes.producto_id');

        $query = Producto::query()
            ->where('activo', true)
            ->whereNotIn('id', $vendidos)
            // Creado hace menos que eso no es "sin movimiento": es nuevo.
            ->where('created_at', '<', $desde)
            ->withSum('variantes as stock_total', 'stock')
            ->withSum('variantes as valor_costo', DB::raw('stock * COALESCE(costo_promedio, 0)'))
            // whereHas y no HAVING: HAVING sin GROUP BY no es portable.
            ->whereHas('variantes', fn (Builder $v) => $v->where('stock', '>', 0));

        $productos = $query->get(['id', 'nombre', 'precio']);

        return [
            'dias' => self::DIAS_SIN_MOVIMIENTO,
            'total' => $productos->count(),
            'productos' => $productos
                ->sortByDesc(fn ($p) => (float) $p->valor_costo)
                ->take($limite)
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'stock' => (int) $p->stock_total,
                    'valor_costo' => round((float) $p->valor_costo, 2),
                ])
                ->values(),
        ];
    }

    /**
     * Cuánta plata hay en mercadería: al costo (lo que se pagó) y al precio
     * de lista (lo que se cobraría vendiéndolo todo).
     *
     * @return array<string, mixed>
     */
    public function valorInventario(): array
    {
        $fila = Variante::query()
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->where('variantes.stock', '>', 0)
            ->first([
                DB::raw('COALESCE(SUM(variantes.stock), 0) as unidades'),
                DB::raw('COALESCE(SUM(variantes.stock * COALESCE(variantes.costo_promedio, 0)), 0) as costo'),
                DB::raw('COALESCE(SUM(variantes.stock * COALESCE(variantes.precio, productos.precio)), 0) as venta'),
                DB::raw('SUM(CASE WHEN variantes.costo_promedio IS NULL THEN variantes.stock ELSE 0 END) as sin_costo'),
            ]);

        $costo = (float) $fila->costo;
        $venta = (float) $fila->venta;

        return [
            'unidades' => (int) $fila->unidades,
            'costo' => round($costo, 2),
            'venta' => round($venta, 2),
            'ganancia_potencial' => round($venta - $costo, 2),
            'unidades_sin_costo' => (int) $fila->sin_costo,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function ofertasActivas(): array
    {
        return Oferta::query()
            ->vigentes()
            ->withCount(['productos', 'variantes', 'categorias'])
            ->orderBy('termina_at')
            ->get()
            ->map(fn (Oferta $o) => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'etiqueta' => $o->etiqueta(),
                // "3 productos · 1 categoría": a qué se aplica, resumido.
                'aplica_a' => collect([
                    [$o->productos_count, 'producto', 'productos'],
                    [$o->variantes_count, 'variante', 'variantes'],
                    [$o->categorias_count, 'categoría', 'categorías'],
                ])->filter(fn ($c) => $c[0] > 0)->map(fn ($c) => "{$c[0]} ".($c[0] === 1 ? $c[1] : $c[2]))->implode(' · ') ?: '—',
                'termina_at' => $o->termina_at->toIso8601String(),
            ])
            ->all();
    }

    // ── Base ──

    public function vendidos(Periodo $p): Builder
    {
        return Pedido::query()
            ->whereIn('pedidos.estado', self::ESTADOS_VENTA)
            ->where('pedidos.confirmado_at', '>=', $p->desdeUtc())
            ->where('pedidos.confirmado_at', '<', $p->hastaUtc());
    }

    private function items(Periodo $p): Builder
    {
        return PedidoItem::query()
            ->join('pedidos', 'pedidos.id', '=', 'pedido_items.pedido_id')
            ->whereIn('pedidos.estado', self::ESTADOS_VENTA)
            ->where('pedidos.confirmado_at', '>=', $p->desdeUtc())
            ->where('pedidos.confirmado_at', '<', $p->hastaUtc());
    }
}
