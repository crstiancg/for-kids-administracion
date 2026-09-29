<?php

namespace App\Http\Resources;

use App\Models\MovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MovimientoInventario
 */
class MovimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grupo' => $this->grupo,
            'tipo' => $this->tipo,
            // Con signo: +10 entra, -2 sale.
            'cantidad' => $this->cantidad,
            'stock_resultante' => $this->stock_resultante,
            'costo_unitario' => $this->costo_unitario,
            'motivo' => $this->motivo,
            'motivo_label' => $this->motivo === MovimientoInventario::MOTIVO_CONTEO
                ? 'Conteo físico'
                : (MovimientoInventario::MOTIVOS_SALIDA[$this->motivo] ?? null),
            'referencia' => $this->referencia,
            'observacion' => $this->observacion,
            'fecha' => $this->created_at?->toIso8601String(),
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'name'])),
            'variante' => $this->whenLoaded('variante', fn () => new VarianteStockResource($this->variante)),
        ];
    }
}
