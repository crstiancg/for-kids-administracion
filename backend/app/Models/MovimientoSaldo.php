<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Libro del saldo a favor de un cliente. INMUTABLE: el saldo es la suma;
 * un error se corrige con otro movimiento.
 */
class MovimientoSaldo extends Model
{
    protected $table = 'movimientos_saldo';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El saldo no se edita: registrá otro movimiento.'));
        static::deleting(fn () => throw new LogicException('El saldo no se borra: registrá otro movimiento.'));
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
