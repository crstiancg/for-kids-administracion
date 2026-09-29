<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea del pedido con precio (y, al confirmar, costo) congelados: lo
 * que se cobró y lo que costó en ESE momento.
 */
#[Fillable(['variante_id', 'cantidad', 'precio_unitario', 'subtotal'])]
class PedidoItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}
