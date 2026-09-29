<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'orden'])]
class Talla extends Model
{
    public function variantes(): HasMany
    {
        return $this->hasMany(Variante::class);
    }
}
