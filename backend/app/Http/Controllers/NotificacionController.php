<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\Oferta;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La campana del header. No guarda nada: cada aviso se calcula del estado
 * actual y desaparece solo cuando se resuelve (se cierra la caja, se
 * confirma el pedido…). Ruta libre: cada aviso se arma SÓLO con el permiso
 * de su dato. Sin avisos, la campana no muestra punto.
 */
class NotificacionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $avisos = [];

        // Cajas de un día anterior sin cerrar: el efectivo no se contó.
        if ($usuario->can('cajas.index') || $usuario->can('cajas.actual')) {
            $vencidas = Caja::query()
                ->where('estado', Caja::ABIERTA)
                // Quien no ve el historial de cajas sólo se entera de la suya.
                ->unless($usuario->can('cajas.index'), fn (Builder $q) => $q->where('abierta_por', $usuario->id))
                ->with('abiertaPor:id,name')
                ->get()
                ->filter(fn (Caja $c) => $c->vencida());

            if ($vencidas->isNotEmpty()) {
                $propia = $vencidas->firstWhere('abierta_por', $usuario->id);
                $avisos[] = [
                    'clave' => 'cajas-vencidas',
                    'nivel' => 'critico',
                    'icono' => 'point_of_sale',
                    'titulo' => $propia && $vencidas->count() === 1
                        ? 'Tu caja de un día anterior sigue abierta'
                        : $vencidas->count().' '.($vencidas->count() === 1 ? 'caja' : 'cajas').' de días anteriores sin cerrar',
                    'detalle' => $vencidas->map(fn (Caja $c) => $c->abiertaPor?->name ?? '—')->implode(', '),
                    'ruta' => $propia ? '/caja' : '/cajas',
                ];
            }
        }

        // Pedidos que esperan hace más de un día: un cliente esperando.
        if ($usuario->can('pedidos.index')) {
            $demorados = Pedido::where('estado', Pedido::PENDIENTE)->where('created_at', '<', now()->subDay())->count();
            if ($demorados) {
                $avisos[] = [
                    'clave' => 'pedidos-demorados',
                    'nivel' => 'aviso',
                    'icono' => 'schedule',
                    'titulo' => $demorados.' '.($demorados === 1 ? 'pedido pendiente' : 'pedidos pendientes').' hace más de 24 h',
                    'detalle' => 'Confirmalos o cancelalos.',
                    'ruta' => '/pedidos',
                ];
            }
        }

        if ($usuario->can('inventario.index')) {
            $agotadas = Variante::where('stock', '<=', 0)
                ->whereHas('producto', fn (Builder $p) => $p->where('activo', true))
                ->count();
            if ($agotadas) {
                $avisos[] = [
                    'clave' => 'agotadas',
                    'nivel' => 'aviso',
                    'icono' => 'inventory_2',
                    'titulo' => $agotadas.' '.($agotadas === 1 ? 'variante agotada' : 'variantes agotadas'),
                    'detalle' => 'De productos activos: no se pueden vender.',
                    'ruta' => '/',
                ];
            }
        }

        if ($usuario->can('ofertas.index')) {
            $porVencer = Oferta::query()->vigentes()->where('termina_at', '<', now()->addDay())->count();
            if ($porVencer) {
                $avisos[] = [
                    'clave' => 'ofertas-por-vencer',
                    'nivel' => 'info',
                    'icono' => 'local_offer',
                    'titulo' => $porVencer.' '.($porVencer === 1 ? 'oferta vence' : 'ofertas vencen').' en menos de 24 h',
                    'detalle' => 'Extendela si querés que siga.',
                    'ruta' => '/ofertas',
                ];
            }
        }

        return response()->json(['avisos' => $avisos]);
    }
}
