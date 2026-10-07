<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * El buscador del header: productos, pedidos y clientes en una sola
 * consulta. Ruta libre (sólo sesión): cada grupo se busca SÓLO si el
 * usuario ya tiene permiso de listarlo, así no abre ningún acceso nuevo.
 */
class BuscarController extends Controller
{
    private const POR_GRUPO = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $termino = trim((string) $request->input('q'));
        if (mb_strlen($termino) < 2) {
            return response()->json(['resultados' => []]);
        }

        /** @var User $usuario */
        $usuario = $request->user();
        $like = '%'.$termino.'%';
        $resultados = [];

        if ($usuario->can('productos.index')) {
            Producto::query()
                ->with('portada')
                ->withSum('variantes as stock_total', 'stock')
                // Por nombre, SKU o el código de barras que lee el escáner.
                ->where(fn (Builder $q) => $q
                    ->where('nombre', 'like', $like)
                    ->orWhereHas('variantes', fn (Builder $v) => $v
                        ->where('sku', 'like', $like)
                        ->orWhere('codigo_barras', $termino)))
                ->orderBy('nombre')
                ->limit(self::POR_GRUPO)
                ->get()
                ->each(function (Producto $p) use (&$resultados) {
                    $resultados[] = [
                        'tipo' => 'producto',
                        'id' => $p->id,
                        'titulo' => $p->nombre,
                        'detalle' => 'Stock '.(int) $p->stock_total.($p->activo ? '' : ' · inactivo'),
                        // Igual que ArchivoResource: la miniatura o, si no hay, el original.
                        'imagen' => $p->portada ? Storage::disk('public')->url($p->portada->miniatura ?? $p->portada->ruta) : null,
                        'ruta' => "/productos/{$p->id}",
                    ];
                });
        }

        if ($usuario->can('pedidos.index')) {
            // "123", "P-123" o "P-000123": todos apuntan al pedido 123.
            $numero = (int) preg_replace('/\D/', '', $termino);
            Pedido::query()
                ->with('cliente:id,nombre')
                ->where(fn (Builder $q) => $q
                    ->when($numero > 0, fn (Builder $q) => $q->orWhere('id', $numero))
                    ->orWhere('codigo', 'like', $like)
                    ->orWhereHas('cliente', fn (Builder $c) => $c->where('nombre', 'like', $like)))
                ->latest('id')
                ->limit(self::POR_GRUPO)
                ->get()
                ->each(function (Pedido $p) use (&$resultados) {
                    $resultados[] = [
                        'tipo' => 'pedido',
                        'id' => $p->id,
                        'titulo' => $p->codigo,
                        'detalle' => ($p->cliente?->nombre ?? 'Cliente varios').' · '.ucfirst($p->estado).' · S/ '.number_format((float) $p->total, 2),
                        'imagen' => null,
                        'ruta' => "/pedidos?ver={$p->id}",
                    ];
                });
        }

        if ($usuario->can('clientes.index')) {
            Cliente::query()
                ->where(fn (Builder $q) => $q
                    ->where('nombre', 'like', $like)
                    ->orWhere('numero_documento', 'like', $like)
                    ->orWhere('telefono', 'like', $like))
                ->orderBy('nombre')
                ->limit(self::POR_GRUPO)
                ->get()
                ->each(function (Cliente $c) use (&$resultados) {
                    $resultados[] = [
                        'tipo' => 'cliente',
                        'id' => $c->id,
                        'titulo' => $c->nombre,
                        'detalle' => collect([
                            $c->numero_documento ? "{$c->tipo_documento} {$c->numero_documento}" : null,
                            $c->telefono,
                        ])->filter()->implode(' · ') ?: 'Sin documento',
                        'imagen' => null,
                        'ruta' => '/clientes?buscar='.rawurlencode($c->nombre),
                    ];
                });
        }

        return response()->json(['resultados' => $resultados]);
    }
}
