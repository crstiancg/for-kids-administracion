<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:4',
        ];
    }

    public function cambio(): BelongsTo
    {
        return $this->belongsTo(Cambio::class);
    }

    public function pedidoItem(): BelongsTo
    {
        return $this->belongsTo(PedidoItem::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}
