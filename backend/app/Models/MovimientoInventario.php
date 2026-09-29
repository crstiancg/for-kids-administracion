<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Una línea del libro de inventario. INMUTABLE: se crea y nunca se edita ni se
 * borra. Si algo se cargó mal, se corrige con otro movimiento (una salida que
 * compense una entrada de más): así el historial siempre explica el stock.
 *
 * Se crea sólo a través de App\Services\Inventario, que mueve el stock de la
 * variante en la misma transacción.
 */
#[Fillable(['grupo', 'variante_id', 'pedido_id', 'tipo', 'cantidad', 'stock_resultante', 'costo_unitario', 'motivo', 'referencia', 'observacion', 'user_id'])]
class MovimientoInventario extends Model
{
    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    public const AJUSTE = 'ajuste';

    /** Motivos de una salida manual (las ventas descontarán desde Pedidos). */
    public const MOTIVOS_SALIDA = [
        'merma' => 'Merma',
        'danado' => 'Dañado',
        'regalo' => 'Regalo / promoción',
        'devolucion_proveedor' => 'Devolución al proveedor',
        'uso_interno' => 'Uso interno',
        'otro' => 'Otro',
    ];

    public const MOTIVO_CONTEO = 'conteo';

    /** Los registra App\Services\Pedidos, no se eligen a mano. */
    public const MOTIVO_VENTA = 'venta';

    public const MOTIVO_DEVOLUCION_VENTA = 'devolucion_venta';

    /** Etiqueta de cualquier motivo, para mostrar. */
    public static function etiquetaMotivo(?string $motivo): ?string
    {
        return match ($motivo) {
            null => null,
            self::MOTIVO_CONTEO => 'Conteo físico',
            self::MOTIVO_VENTA => 'Venta',
            self::MOTIVO_DEVOLUCION_VENTA => 'Pedido cancelado',
            default => self::MOTIVOS_SALIDA[$motivo] ?? $motivo,
        };
    }

    // Sólo created_at: un movimiento no se actualiza.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'stock_resultante' => 'integer',
            'costo_unitario' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de inventario no se editan: registrá otro que lo corrija.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de inventario no se borran: registrá otro que lo corrija.'));
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
