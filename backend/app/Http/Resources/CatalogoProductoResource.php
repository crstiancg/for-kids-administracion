<?php

namespace App\Http\Resources;

use App\Models\Producto;
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

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio' => $this->precio,
            'categoria' => $this->categoria?->only(['id', 'nombre']),
            'miniatura_url' => $miniatura($this->portada),
            'stock_total' => (int) $this->stock_total,
            // Unidades vendidas en los últimos 90 días (confirmadas o entregadas).
            'vendidos' => (int) $this->vendidos,
            'variantes' => $this->variantes->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'stock' => $v->stock,
                // Precio de venta: el de la variante o el base del producto.
                'precio' => $v->precio ?? $this->precio,
                'talla' => $v->talla?->only(['id', 'nombre', 'orden']),
                'color' => $v->color?->only(['id', 'nombre', 'hexadecimal']),
                // La foto del color; si no tiene, la del producto.
                'miniatura_url' => $miniatura($v->portada) ?? $miniatura($this->portada),
            ])->values(),
        ];
    }
}
