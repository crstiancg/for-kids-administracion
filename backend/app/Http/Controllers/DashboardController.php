<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Cajas;
use App\Services\Estadisticas;
use App\Support\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El tablero de inicio. Es ruta libre (sólo sesión): cada bloque se arma
 * SÓLO si el usuario ya tiene el permiso de ese dato, así el dashboard no
 * abre ningún acceso nuevo. Un bloque sin permiso no viene (ni como null).
 *
 * ?periodo=hoy|semana|mes|rango: las cifras, métodos y tallas siguen al
 * período; stock, pendientes y cajas son siempre "ahora".
 */
class DashboardController extends Controller
{
    public function __construct(private Estadisticas $estadisticas, private Cajas $cajas) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $periodo = Periodo::desdeRequest($request);
        $e = $this->estadisticas;

        $bloques = ['periodo' => $periodo->toArray()];

        if ($usuario->can('pedidos.index')) {
            $bloques['ventas'] = [
                ...$e->resumenVentas($periodo),
                'anterior' => $e->resumenVentas($periodo->anterior())['total'],
                // "Hoy" solo es una barra: el gráfico muestra el último mes.
                'serie' => $e->seriePorDia($periodo->tipo === 'hoy' ? Periodo::ultimos(30) : $periodo),
                'top' => $e->topProductos($periodo),
                'por_metodo' => $e->porMetodo($periodo),
                'por_talla' => $e->porTalla($periodo),
                'ultimas' => $e->ultimasVentas(),
            ];
            $bloques['pendientes'] = Pedido::where('estado', Pedido::PENDIENTE)->count();
        }
        if ($usuario->can('inventario.index')) {
            $bloques['ganancia'] = [
                'periodo' => $e->ganancia($periodo),
                'mes' => $e->ganancia(Periodo::mesActual()),
            ];
            $bloques['stock_bajo'] = $e->stockBajo();
            $bloques['sin_movimiento'] = $e->sinMovimiento();
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
            // Sin saldo a favor: no es plata nueva en el cajón.
            ->withSum(['pagos as total_cobrado' => fn ($q) => $q->where('metodo', '!=', Pago::SALDO)], 'monto')
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
}
