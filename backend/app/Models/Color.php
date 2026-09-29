<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'hexadecimal'])]
class Color extends Model
{
    /**
     * Explícita: por convención Laravel pluraliza en inglés ("colors").
     */
    protected $table = 'colores';
}
