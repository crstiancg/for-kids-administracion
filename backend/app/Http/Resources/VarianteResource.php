<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Variante
 */
class VarianteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'talla_id' => $this->talla_id,
            'color_id' => $this->color_id,
            'talla' => $this->whenLoaded('talla', fn () => $this->talla->only(['id', 'nombre', 'orden'])),
            'color' => $this->whenLoaded('color', fn () => $this->color->only(['id', 'nombre', 'hexadecimal'])),
            'sku' => $this->sku,
            // null = usa el precio base del producto.
            'precio' => $this->precio,
            'stock' => $this->stock,
            // Con historial de inventario no se puede quitar del producto.
            'con_movimientos' => $this->whenHas('movimientos_exists', fn ($existe) => (bool) $existe),
            // {"Largo": 52} en cm; objeto vacío y no [] cuando no hay.
            'medidas' => (object) ($this->medidas ?? []),
            'archivos' => ArchivoResource::collection($this->whenLoaded('archivos')),
        ];
    }
}
