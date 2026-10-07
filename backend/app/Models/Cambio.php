<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un cambio de prenda sobre una venta entregada. Lo registra sólo
 * App\Services\Cambios (sin fillable a propósito); no se edita.
 */
class Cambio extends Model
{
    public const UPDATED_AT = null;

    /** Días desde la entrega en que se acepta un cambio. */
    public const PLAZO_DIAS = 30;

    protected function casts(): array
    {
        return [
            'valor_devuelto' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function pedidoNuevo(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_nuevo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CambioItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
