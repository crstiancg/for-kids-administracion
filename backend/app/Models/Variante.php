<?php

namespace App\Models;

use App\Support\Ean13;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Un producto en una talla y un color: lo que efectivamente tiene SKU y stock.
 * `stock` y `costo_promedio` no son fillable a propósito: sólo los mueve
 * App\Services\Inventario, dejando su movimiento en el libro.
 * `codigo_barras` tampoco: lo asigna el sistema al crear y no cambia nunca
 * (ya está impreso en las etiquetas).
 */
#[Fillable(['talla_id', 'color_id', 'sku', 'precio', 'medidas'])]
class Variante extends Model
{
    protected static function booted(): void
    {
        // Sale del id, que recién existe después del INSERT.
        static::created(function (Variante $variante) {
            $variante->forceFill(['codigo_barras' => Ean13::paraVariante($variante->id)])->saveQuietly();
        });
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'costo_promedio' => 'decimal:4',
            // {"Largo": 52, "Pecho": 40} en cm.
            'medidas' => 'array',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function talla(): BelongsTo
    {
        return $this->belongsTo(Talla::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->orderBy('orden');
    }

    /**
     * La primera foto del color (orden 0), para el punto de venta.
     */
    public function portada(): MorphOne
    {
        return $this->morphOne(Archivo::class, 'archivable')->ofMany('orden', 'min');
    }
}
