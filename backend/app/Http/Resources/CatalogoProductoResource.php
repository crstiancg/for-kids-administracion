<?php

namespace App\Http\Resources;

use App\Models\Producto;
use App\Services\Precios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Un producto tal como lo muestra el punto de venta: su tarjeta (portada,
 * precio, stock, vendidos) y sus variantes para elegir talla × color.
 *
 * @mixin Producto
 */
class CatalogoProductoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disco = Storage::disk('public');
        $miniatura = fn ($archivo) => $archivo ? $disco->url($archivo->miniatura ?? $archivo->ruta) : null;

        $precios = app(Precios::class);

        // Precio de hoy de cada variante (su oferta puede ser del producto,
        // de la variante puntual o de la categoría).
        $variantes = $this->variantes->map(fn ($v) => [
            'variante' => $v,
            ...$precios->vigente((float) ($v->precio ?? $this->precio), $this->id, $this->categoria_id, $v->id),
        ]);

        // Badge de la tarjeta: la oferta más fuerte entre sus variantes ("-30%
        // sólo en rojo" también se ve en el catálogo).
        $delProducto = $variantes
            ->filter(fn ($x) => $x['oferta'])
            ->sortBy(fn ($x) => (float) $x['precio'] / max((float) $x['precio_lista'], 0.01))
            ->first()['oferta'] ?? null;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio' => $this->precio,
            'categoria' => $this->categoria?->only(['id', 'nombre']),
            'miniatura_url' => $miniatura($this->portada),
            'oferta' => $delProducto ? [
                'id' => $delProducto->id,
                'nombre' => $delProducto->nombre,
                'etiqueta' => $delProducto->etiqueta(),
                'termina_at' => $delProducto->termina_at->toIso8601String(),
            ] : null,
            'stock_total' => (int) $this->stock_total,
            // Unidades vendidas en los últimos 90 días (confirmadas o entregadas).
            'vendidos' => (int) $this->vendidos,
            'variantes' => $variantes->map(fn ($x) => [
                'id' => $x['variante']->id,
                'sku' => $x['variante']->sku,
                'stock' => $x['variante']->stock,
                // Precio de venta HOY (con oferta, si hay) y el de lista (el
                // de la variante o el base del producto), para tacharlo.
                'precio' => $x['precio'],
                'precio_lista' => $x['precio_lista'],
                'oferta' => $x['oferta']?->etiqueta(),
                'talla' => $x['variante']->talla?->only(['id', 'nombre', 'orden']),
                'color' => $x['variante']->color?->only(['id', 'nombre', 'hexadecimal']),
                // La foto del color; si no tiene, la del producto.
                'miniatura_url' => $miniatura($x['variante']->portada) ?? $miniatura($this->portada),
            ])->values(),
        ];
    }
}
