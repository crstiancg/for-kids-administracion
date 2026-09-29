<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sirve para el listado (con portada, conteo y stock total) y para el
 * detalle (con galería y variantes): cada bloque aparece sólo si se cargó.
 *
 * @mixin \App\Models\Producto
 */
class ProductoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'categoria_id' => $this->categoria_id,
            'categoria' => $this->whenLoaded('categoria', fn () => $this->categoria->only(['id', 'nombre'])),
            'descripcion' => $this->descripcion,
            'precio' => $this->precio,
            'activo' => $this->activo,
            'variantes_count' => $this->whenCounted('variantes'),
            // SUM llega como texto desde MySQL.
            'stock_total' => $this->whenHas('stock_total', fn ($total) => (int) $total),
            // null (no "new ArchivoResource(null)") cuando no tiene fotos.
            'portada' => $this->whenLoaded('portada', fn () => $this->portada ? new ArchivoResource($this->portada) : null),
            'archivos' => ArchivoResource::collection($this->whenLoaded('archivos')),
            'variantes' => VarianteResource::collection($this->whenLoaded('variantes')),
        ];
    }
}
