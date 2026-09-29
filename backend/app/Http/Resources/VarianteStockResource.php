<?php

namespace App\Http\Resources;

use App\Models\Variante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una variante vista desde inventario: qué es (producto, talla, color, SKU) y
 * cuánto hay. Sirve para el buscador de las líneas y para el historial.
 *
 * @mixin Variante
 */
class VarianteStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'costo_promedio' => $this->costo_promedio,
            'producto' => $this->whenLoaded('producto', fn () => $this->producto->only(['id', 'nombre'])),
            'talla' => $this->whenLoaded('talla', fn () => $this->talla->nombre),
            'color' => $this->whenLoaded('color', fn () => $this->color->only(['nombre', 'hexadecimal'])),
        ];
    }
}
