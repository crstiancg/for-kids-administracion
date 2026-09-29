<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * pendiente → confirmado → entregado
 *        ↘           ↘
 *         cancelado   cancelado (devuelve el stock)
 *
 * Los cambios de estado pasan sólo por App\Services\Pedidos: confirmar y
 * cancelar mueven stock a través de App\Services\Inventario.
 */
#[Fillable(['cliente_id', 'canal', 'descuento', 'observacion', 'user_id'])]
class Pedido extends Model
{
    public const PENDIENTE = 'pendiente';

    public const CONFIRMADO = 'confirmado';

    public const ENTREGADO = 'entregado';

    public const CANCELADO = 'cancelado';

    public const CANALES = [
        'mostrador' => 'Mostrador',
        'whatsapp' => 'WhatsApp',
        'redes' => 'Redes sociales',
        'web' => 'Web',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmado_at' => 'datetime',
            'entregado_at' => 'datetime',
            'cancelado_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function editable(): bool
    {
        return $this->estado === self::PENDIENTE;
    }
}
