<?php

namespace App\Http\Resources;

use App\Models\Variante;
use App\Services\Precios;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
    private function conPrecio(): bool
    {
        return $this->relationLoaded('producto') && array_key_exists('precio', $this->producto->getAttributes());
    }

    /**
     * @return array{precio: string, precio_lista: string, oferta: ?string}
     */
    private function precioVigente(): array
    {
        $vigente = app(Precios::class)->vigente(
            (float) ($this->precio ?? $this->producto->precio),
            $this->producto->id,
            $this->producto->categoria_id,
            $this->id,
        );

        return [
            'precio' => $vigente['precio'],
            'precio_lista' => $vigente['precio_lista'],
            'oferta' => $vigente['oferta']?->etiqueta(),
        ];
    }

    private function miniatura(): ?string
    {
        $archivo = $this->portada
            ?? ($this->relationLoaded('producto') && $this->producto->relationLoaded('portada') ? $this->producto->portada : null);

        return $archivo ? Storage::disk('public')->url($archivo->miniatura ?? $archivo->ruta) : null;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'costo_promedio' => $this->costo_promedio,
            // Precio de venta HOY (con la mejor oferta vigente) y el de lista.
            // Sólo cuando se cargó el precio del producto.
            ...($this->conPrecio() ? $this->precioVigente() : []),
            'producto' => $this->whenLoaded('producto', fn () => $this->producto->only(['id', 'nombre'])),
            'talla' => $this->whenLoaded('talla', fn () => $this->talla->nombre),
            'color' => $this->whenLoaded('color', fn () => $this->color->only(['nombre', 'hexadecimal'])),
            // Foto del color o, si no tiene, la del producto (punto de venta).
            'miniatura_url' => $this->when($this->relationLoaded('portada'), fn () => $this->miniatura()),
        ];
    }
}
