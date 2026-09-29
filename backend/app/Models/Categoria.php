<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Árbol de categorías: parent_id apunta a la misma tabla (null = raíz).
 */
#[Fillable(['nombre', 'parent_id'])]
class Categoria extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés ("categorias" sale
     * bien de casualidad, pero no hay que depender de eso).
     */
    protected $table = 'categorias';

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Ids de todas las subcategorías, a cualquier profundidad. Recorre por
     * niveles (una consulta por nivel), no fila por fila.
     *
     * @return int[]
     */
    public function descendientesIds(): array
    {
        $ids = [];
        $nivel = [$this->getKey()];

        while ($nivel !== []) {
            $nivel = self::query()->whereIn('parent_id', $nivel)->pluck('id')->all();
            $ids = [...$ids, ...$nivel];
        }

        return $ids;
    }
}
