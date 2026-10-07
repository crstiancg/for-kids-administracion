<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\User;
use App\Models\Variante;
use App\Services\Cajas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * El tablero de inicio. Es ruta libre (sólo sesión): cada bloque se arma
 * SÓLO si el usuario ya tiene el permiso de ese dato, así el dashboard no
 * abre ningún acceso nuevo. Un bloque sin permiso no viene (no viene null).
 *
 * - Venta = pedido confirmado o entregado, contado por `confirmado_at`.
 * - Ganancia = total vendido − costo CONGELADO de cada ítem.
 * - "Hoy" es el día en la zona del negocio, no en UTC.
 */
class DashboardController extends Controller
{
    /** Menos de esto es stock bajo (fijo por ahora; por variante más adelante). */
    public const STOCK_BAJO = 4;

    private const ESTADOS_VENTA = [Pedido::CONFIRMADO, Pedido::ENTREGADO];

    public function __construct(private Cajas $cajas) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $zona = config('app.zona_negocio');
        $hoy = CarbonImmutable::now($zona)->startOfDay();

        $bloques = [];

        if ($usuario->can('pedidos.index')) {
            $bloques['ventas'] = $this->ventas($hoy);
            $bloques['pendientes'] = Pedido::where('estado', Pedido::PENDIENTE)->count();
        }
        if ($usuario->can('inventario.index')) {
            $bloques['ganancia'] = $this->ganancia($hoy);
            $bloques['stock_bajo'] = $this->stockBajo();
        }
        if ($usuario->can('cajas.actual')) {
            $bloques['mi_caja'] = $this->miCaja($usuario);
        }
        if ($usuario->can('cajas.index')) {
            $bloques['cajas_abiertas'] = $this->cajasAbiertas();
        }

        return response()->json($bloques);
    }

    /**
     * @return array<string, mixed>
     */
    private function ventas(CarbonImmutable $hoy): array
    {
        $desde = $hoy->subDays(29);

        // Por día en la zona del negocio. Se agrupa en PHP: la conversión de
        // zona en SQL cambia entre MySQL y SQLite, y 30 días de una tienda
        // son pocas filas.
        $porDia = array_fill_keys(
            collect(range(0, 29))->map(fn ($i) => $desde->addDays($i)->toDateString())->all(),
            ['total' => 0.0, 'cantidad' => 0],
        );
        $this->vendidos($desde, $hoy->addDay())
            ->get(['total', 'confirmado_at'])
            ->each(function (Pedido $p) use (&$porDia, $hoy) {
                $dia = $p->confirmado_at->copy()->timezone($hoy->getTimezone())->toDateString();
                if (isset($porDia[$dia])) {
                    $porDia[$dia]['total'] += (float) $p->total;
                    $porDia[$dia]['cantidad']++;
                }
            });

        $deHoy = $porDia[$hoy->toDateString()];
        $deAyer = $porDia[$hoy->subDay()->toDateString()];

        return [
            'hoy' => round($deHoy['total'], 2),
            'cantidad_hoy' => $deHoy['cantidad'],
            'ticket_promedio' => $deHoy['cantidad'] ? round($deHoy['total'] / $deHoy['cantidad'], 2) : 0,
            'ayer' => round($deAyer['total'], 2),
            'serie' => collect($porDia)->map(fn ($d, $fecha) => [
                'fecha' => $fecha,
                'total' => round($d['total'], 2),
                'cantidad' => $d['cantidad'],
            ])->values(),
            'top_semana' => $this->topSemana($hoy),
        ];
    }

    /**
     * Los 5 productos con más unidades vendidas en los últimos 7 días.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topSemana(CarbonImmutable $hoy): array
    {
        return PedidoItem::query()
            ->join('pedidos', 'pedidos.id', '=', 'pedido_items.pedido_id')
            ->join('variantes', 'variantes.id', '=', 'pedido_items.variante_id')
            ->join('productos', 'productos.id', '=', 'variantes.producto_id')
            ->whereIn('pedidos.estado', self::ESTADOS_VENTA)
            ->where('pedidos.confirmado_at', '>=', $hoy->subDays(6)->utc())
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('unidades')
            ->limit(5)
            ->get([
                'productos.id',
                'productos.nombre',
                DB::raw('SUM(pedido_items.cantidad) as unidades'),
                DB::raw('SUM(pedido_items.subtotal) as total'),
            ])
            ->map(fn ($fila) => [
                'id' => $fila->id,
                'nombre' => $fila->nombre,
                'unidades' => (int) $fila->unidades,
                'total' => round((float) $fila->total, 2),
            ])
            ->all();
    }

    /**
     * Ganancia de hoy y del mes. Los ítems sin costo (vendidos de stock que
     * entró por un ajuste, sin compra) no se pueden valuar: se informan
     * aparte en vez de contarlos como ganancia pura.
     *
     * @return array<string, mixed>
     */
    private function ganancia(CarbonImmutable $hoy): array
    {
        $calcular = function (CarbonImmutable $desde, CarbonImmutable $hasta) {
            $vendido = (float) $this->vendidos($desde, $hasta)->sum('total');
            $costo = PedidoItem::query()
                ->whereHas('pedido', fn (Builder $q) => $this->filtrarVendidos($q, $desde, $hasta))
                ->whereNotNull('costo_unitario')
                ->selectRaw('COALESCE(SUM(cantidad * costo_unitario), 0) as costo')
                ->value('costo');
            $sinCosto = PedidoItem::query()
                ->whereHas('pedido', fn (Builder $q) => $this->filtrarVendidos($q, $desde, $hasta))
                ->whereNull('costo_unitario')
                ->count();

            $ganancia = $vendido - (float) $costo;

            return [
                'monto' => round($ganancia, 2),
                'margen' => $vendido > 0 ? (int) round($ganancia / $vendido * 100) : 0,
                'items_sin_costo' => $sinCosto,
            ];
        };

        return [
            'hoy' => $calcular($hoy, $hoy->addDay()),
            'mes' => $calcular($hoy->startOfMonth(), $hoy->addDay()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockBajo(): array
    {
        $query = Variante::query()
            ->where('stock', '<', self::STOCK_BAJO)
            ->whereHas('producto', fn (Builder $p) => $p->where('activo', true));

        return [
            'umbral' => self::STOCK_BAJO,
            'total' => (clone $query)->count(),
            'agotadas' => (clone $query)->where('stock', '<=', 0)->count(),
            'variantes' => $query
                ->with(['producto:id,nombre', 'talla:id,nombre', 'color:id,nombre,hexadecimal'])
                ->orderBy('stock')
                ->orderBy('producto_id')
                ->limit(8)
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
     * @return array<string, mixed>|null  null = no tiene caja abierta
     */
    private function miCaja(User $usuario): ?array
    {
        $caja = $this->cajas->actual($usuario);
        if (! $caja) {
            return null;
        }
        $resumen = $this->cajas->resumen($caja);

        return [
            'id' => $caja->id,
            'vencida' => $caja->vencida(),
            'abierta_at' => $caja->abierta_at->toIso8601String(),
            'total_cobrado' => $resumen['total_cobrado'],
            'efectivo_esperado' => $resumen['efectivo_esperado'],
        ];
    }

    /**
     * Las cajas abiertas ahora (una por cajero) con lo cobrado en cada una.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cajasAbiertas(): array
    {
        return Caja::query()
            ->where('estado', Caja::ABIERTA)
            ->with('abiertaPor:id,name')
            ->withSum('pagos as total_cobrado', 'monto')
            ->orderBy('abierta_at')
            ->get()
            ->map(fn (Caja $c) => [
                'id' => $c->id,
                'cajero' => $c->abiertaPor?->name ?? '—',
                'vencida' => $c->vencida(),
                'abierta_at' => $c->abierta_at->toIso8601String(),
                'total_cobrado' => round((float) $c->total_cobrado, 2),
            ])
            ->all();
    }

    private function vendidos(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        return $this->filtrarVendidos(Pedido::query(), $desde, $hasta);
    }

    private function filtrarVendidos(Builder $query, CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        // Los timestamps se guardan en UTC: el día del negocio se traduce.
        return $query
            ->whereIn('estado', self::ESTADOS_VENTA)
            ->where('confirmado_at', '>=', $desde->utc())
            ->where('confirmado_at', '<', $hasta->utc());
    }
}
